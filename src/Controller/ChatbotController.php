<?php

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChatbotController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->apiKey = $_ENV['API_KEY'] ?? $_SERVER['API_KEY'] ?? '';
        
        $this->apiKey = trim($this->apiKey);
        
        if (empty($this->apiKey)) {
            $this->logger->error('API_KEY is not defined in .env.local');
            throw new \RuntimeException('API_KEY non définie dans .env.local');
        }
        
        $this->logger->info('API Key initialized', [
            'key_preview' => substr($this->apiKey, 0, 4) . '...' . substr($this->apiKey, -4)
        ]);
    }

    #[Route('/chatbot', name: 'chatbot')]
    public function index(): Response
    {
        return $this->render('chatbot/index.html.twig');
    }

    #[Route('/chatbot/message', name: 'chatbot_message', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            $this->logger->warning('Empty message received');
            return new JsonResponse(['error' => 'Message vide'], Response::HTTP_BAD_REQUEST);
        }

        // Get the current user
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->logger->warning('Unauthorized access to chatbot');
            return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        // Save user message
        $userChatMessage = new ChatMessage();
        $userChatMessage->setMessage($userMessage);
        $userChatMessage->setSender($user);
        $this->entityManager->persist($userChatMessage);

        try {
            // Log the request payload
            $this->logger->info('Sending request to OpenRouter', [
                'url' => 'https://openrouter.ai/api/v1/chat/completions',
                'headers' => [
                    'Authorization' => 'Bearer ' . substr($this->apiKey, 0, 4) . '...',
                    'Content-Type' => 'application/json',
                ],
                'payload' => [
                    'model' => 'google/gemini-2.0-flash-exp:free',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $userMessage
                        ]
                    ]
                ]
            ]);

            // Request to OpenRouter
            $response = $this->httpClient->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => '<YOUR_SITE_URL>',
                    'X-Title' => '<YOUR_SITE_NAME>'
                ],
                'json' => [
                    'model' => 'google/gemini-2.0-flash-exp:free',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $userMessage
                        ]
                    ]
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);

            $this->logger->info('Received response from OpenRouter', [
                'status_code' => $statusCode,
                'response_body' => $responseBody
            ]);

            if ($statusCode !== 200) {
                $this->logger->error('OpenRouter API returned non-200 status', [
                    'status_code' => $statusCode,
                    'response_body' => $responseBody
                ]);
                throw new \RuntimeException('OpenRouter API error: ' . $responseBody, $statusCode);
            }

            $apiResponse = $response->toArray();
            $chatbotReply = $apiResponse['choices'][0]['message']['content'] ?? 'Désolé, aucune réponse.';

        } catch (\Throwable $e) {
            $this->logger->error('Error during OpenRouter communication', [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'exception_trace' => $e->getTraceAsString()
            ]);

            // Fallback response
            $chatbotReply = 'Désolé, je ne peux pas répondre pour le moment. Essayez encore !';
        }

        // Get or create chatbot user
        try {
            $chatbotUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'chatbot@yourapp.com']);
            if (!$chatbotUser) {
                $this->logger->info('Creating new Chatbot user');
                $chatbotUser = new User();
                $chatbotUser->setEmail('chatbot@yourapp.com');
                $chatbotUser->setRoles(['ROLE_BOT']);
                $chatbotUser->setPassword(
                    $this->passwordHasher->hashPassword($chatbotUser, 'chatbot_dummy_password_2025')
                );
                $chatbotUser->setIsVerified(true);
                $this->entityManager->persist($chatbotUser);
            }

            // Save chatbot response
            $botChatMessage = new ChatMessage();
            $botChatMessage->setMessage($chatbotReply);
            $botChatMessage->setSender($chatbotUser);
            $this->entityManager->persist($botChatMessage);

            // Persist all changes
            $this->entityManager->flush();

            $this->logger->info('Messages saved successfully', [
                'user_email' => $user->getEmail(),
                'chatbot_reply' => $chatbotReply
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error saving messages to database', [
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'error' => 'Erreur lors de l\'enregistrement des messages.',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['response' => ['content' => $chatbotReply]]);
    }

    #[Route('/chatbot/messages', name: 'chatbot_messages', methods: ['GET'])]
    public function getMessages(ChatMessageRepository $chatMessageRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->logger->warning('Unauthorized access to messages');
            return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        $chatbotUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'chatbot@yourapp.com']);
        if (!$chatbotUser) {
            $this->logger->info('No chatbot user found, returning empty messages');
            return new JsonResponse(['messages' => []]);
        }

        // Fetch messages where user or chatbot is the sender
        $messages = $chatMessageRepository->findByUserOrChatbot($user, $chatbotUser);

        $formattedMessages = array_map(function (ChatMessage $message) {
            return [
                'content' => $message->getMessage(),
                'role' => $message->getSender()->getEmail() === 'chatbot@yourapp.com' ? 'assistant' : 'user',
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $messages);

        $this->logger->info('Messages retrieved successfully', [
            'user_email' => $user->getEmail(),
            'message_count' => count($messages)
        ]);

        return new JsonResponse(['messages' => $formattedMessages]);
    }
}