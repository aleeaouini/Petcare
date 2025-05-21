<?php
// src/Controller/ChatMessageController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ChatMessage;
use App\Form\ChatMessageType;
use App\Entity\User;

class ChatMessageController extends AbstractController
{
    #[Route('/message/new', name: 'message_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $chatMessage = new ChatMessage();
        $form = $this->createForm(ChatMessageType::class, $chatMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ajout de la logique pour l'expéditeur
            $chatMessage->setSender('user'); // Remplacer 'user' par la logique d'expéditeur, par exemple : $this->getUser()->getUsername()
            $chatMessage->setCreatedAt(new \DateTimeImmutable());

            // Récupération du destinataire (receiver)
            $receiverUsername = $chatMessage->getReceiver(); // Assurez-vous que la méthode getReceiver() renvoie un nom d'utilisateur (string)
            $receiver = $entityManager->getRepository(User::class)->findOneBy(['username' => $receiverUsername]);

            if ($receiver) {
                $chatMessage->setReceiver($receiver); // Associe le destinataire trouvé au message
            }

            $chatMessage->setUser($this->getUser()); // L'utilisateur authentifié est l'expéditeur

            // Persister le message dans la base de données
            $entityManager->persist($chatMessage);
            $entityManager->flush();

            return $this->redirectToRoute('message_success'); // Redirection vers la page de succès
        }

        return $this->render('message/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/message/success', name: 'message_success')]
    public function success(): Response
    {
        return $this->render('message/success.html.twig'); // Page de succès après l'envoi du message
    }
}
