<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'app_chat')]
    public function index(): JsonResponse
    {
        return $this->json(['message' => 'Bienvenue sur le chatbot']);
    }

    #[Route('/chat/send', name: 'chat_send', methods: ['POST'])]
    public function sendMessage(
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non connecté'], 403);
        }

        // ✅ Génère un ID de conversation unique s’il n’existe pas
        if (!$session->has('conversation_id')) {
            $conversationId = uniqid('conv_', true);
            $session->set('conversation_id', $conversationId);
        } else {
            $conversationId = $session->get('conversation_id');
        }

        // Enregistrement du message de l'utilisateur
        $message = new Message();
        $message->setUser($user);
        $message->setContent($data['message'] ?? '');
        $message->setRole($data['role'] ?? 'user');
        $message->setCreatedAt(new \DateTime());
        $message->setConversationId($conversationId);
        $em->persist($message);

        // Simule une réponse du chatbot (à remplacer plus tard par appel à une API)
        $chatbotReply = 'Désolé, aucune réponse.';

        // Enregistrement du message de l'assistant
        $botMessage = new Message();
        $botMessage->setUser($user);
        $botMessage->setContent($chatbotReply);
        $botMessage->setRole('assistant');
        $botMessage->setCreatedAt(new \DateTime());
        $botMessage->setConversationId($conversationId);
        $em->persist($botMessage);

        $em->flush();

        return new JsonResponse(['reply' => $chatbotReply]);
    }

    #[Route('/chat/messages', name: 'chat_messages', methods: ['GET'])]
    public function getMessages(
        MessageRepository $messageRepository,
        SessionInterface $session,
        Request $request
    ): JsonResponse {
        $conversationId = $session->get('conversation_id');

        if (!$conversationId) {
            return new JsonResponse(['messages' => []]);
        }

        $messages = $messageRepository->findBy(
            ['conversationId' => $conversationId],
            ['createdAt' => 'ASC']
        );

        $data = [];
        foreach ($messages as $message) {
            $data[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse(['messages' => $data]);
    }
}
