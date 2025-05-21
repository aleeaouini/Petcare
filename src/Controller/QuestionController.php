<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\Animal;
use App\Form\QuestionType;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QuestionController extends AbstractController
{

    #[Route('/mes-questions', name: 'user_questions')]
    public function userQuestions(Request $request, QuestionRepository $questionRepository): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            $this->addFlash('warning', 'Vous devez être connecté pour voir vos questions.');
            return $this->redirectToRoute('app_login');
        }
        
        $email = $user->getEmail();

        $questions = $questionRepository->findBy(
            ['userEmail' => $email],
            ['createdAt' => 'DESC']
        );

        return $this->render('question/conversation.html.twig', [
            'questions' => $questions,
            'email' => $email,
            'userRole' => $this->getUser() ? $this->getUser()->getRoles()[0] : null,
        ]);
    }

    #[Route('/admin/questions', name: 'admin_questions')]
    public function viewAll(QuestionRepository $questionRepository): Response
    {
        $questions = $questionRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/questions.html.twig', [
            'questions' => $questions,
        ]);
    }

    #[Route('/admin/question/{id}/answer', name: 'admin_answer_question', methods: ['POST'])]
    public function answerQuestion(Request $request, Question $question, EntityManagerInterface $em): Response
    {
        $answer = $request->request->get('answer');

        if ($answer) {
            $question->setAnswer($answer);
            $question->setIsAnswered(true);
            $em->flush();

            $this->addFlash('success', 'Réponse envoyée avec succès.');
        } else {
            $this->addFlash('error', 'Le contenu de la réponse est vide.');
        }

        return $this->redirectToRoute('admin_questions');
    }

     #[Route('/admin/questions/users', name: 'admin_question_users')]
    public function listUsers(QuestionRepository $repo): Response
    {
        if (!$this->isGranted('ROLE_VETERINAIRE')) {
        // Rediriger vers login si non autorisé
        return $this->redirectToRoute('login');
        }
        // Récupérer la liste des emails uniques des utilisateurs ayant posé des questions
        $users = $repo->createQueryBuilder('q')
            ->select('DISTINCT q.userEmail')
            ->where('q.userEmail IS NOT NULL')
            ->getQuery()
            ->getResult();

        // Récupérer toutes les questions (pour compter les non répondues)
        $questions = $repo->findAll();

        return $this->render('admin/question_users.html.twig', [
            'users' => $users,
            'questions' => $questions,
        ]);
    }


    #[Route('/admin/questions/user/{email}', name: 'admin_question_conversation')]
    public function viewConversation(QuestionRepository $repo, string $email): Response
    {
        $questions = $repo->findBy(['userEmail' => $email], ['createdAt' => 'ASC']);

        return $this->render('admin/questions.html.twig', [
            'questions' => $questions,
            'email' => $email,
        ]);
    }

    #[Route('/admin/questions/repondre/{id}', name: 'admin_question_repondre', methods: ['POST'])]
    public function repondre(Question $question, Request $request, EntityManagerInterface $em): Response
    {
        // Le nom du champ textarea dans le form Twig est "answerContent"
        $reponse = $request->request->get('answerContent');

        if ($reponse) {
            $question->setAnswer($reponse);
            $question->setIsAnswered(true);
            $em->flush();

            $this->addFlash('success', 'Réponse ajoutée avec succès.');
        } else {
            $this->addFlash('error', 'La réponse ne peut pas être vide.');
        }

        return $this->redirectToRoute('admin_question_conversation', ['email' => $question->getUserEmail()]);
    }

    #[Route('/questions/conversation/{email}', name: 'question_conversation', methods: ['GET', 'POST'])]
    public function userConversation(string $email, QuestionRepository $questionRepository, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier si l'utilisateur est connecté et correspond à l'email
        $user = $this->getUser();
        if (!$user || $user->getEmail() !== $email) {
            // Si c'est un admin, on le laisse passer
            if (!$this->isGranted('ROLE_VETERINAIRE')) {
                $this->addFlash('error', 'Vous n\'êtes pas autorisé à accéder à cette conversation.');
                return $this->redirectToRoute('home');
            }
        }

        if ($request->isMethod('POST')) {
            $content = $request->request->get('content');

            if ($content) {
                $question = new Question();
                $question->setUserEmail($email);
                // Si l'animal n'est pas spécifié, on utilise un animal par défaut ou null
                // Dans une vraie application, vous devriez gérer cela différemment
                $question->setAnimal($questionRepository->findOneBy(['userEmail' => $email])->getAnimal());
                $question->setContent($content);
                $question->setCreatedAt(new \DateTimeImmutable());
                $question->setIsAnswered(false);

                $em->persist($question);
                $em->flush();

                $this->addFlash('success', 'Message envoyé avec succès.');

                return $this->redirectToRoute('question_conversation', ['email' => $email]);
            } else {
                $this->addFlash('error', 'Le message ne peut pas être vide.');
            }
        }

        $questions = $questionRepository->findBy(['userEmail' => $email], ['createdAt' => 'ASC']);

        return $this->render('question/conversation.html.twig', [
            'email' => $email,
            'questions' => $questions,
        ]);
    }

    #[Route('/animal/{id}/poser-question-direct', name: 'poser_question_directe')]
    public function poserQuestionDirecte(Animal $animal, EntityManagerInterface $em): Response
    {
        // Vérifier si l'utilisateur est connecté
        $user = $this->getUser();
        $userEmail = $user ? $user->getEmail() : 'anonyme_' . rand(1000, 9999);

        $question = new Question();
        $question->setAnimal($animal);
        $question->setContent("Bonjour, j'ai une question concernant l'animal : " . $animal->getName());
        $question->setCreatedAt(new \DateTimeImmutable());
        $question->setUserEmail($userEmail);
        $question->setIsAnswered(false);

        $em->persist($question);
        $em->flush();

        // Rediriger vers la page de conversation
        return $this->redirectToRoute('question_conversation', [
            'email' => $userEmail
        ]);
    }
}