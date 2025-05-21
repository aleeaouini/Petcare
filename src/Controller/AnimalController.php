<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Form\AnimalType;
use App\Repository\AnimalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class AnimalController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(AnimalRepository $animalRepository): Response
    {
        $animals = $animalRepository->findAll();

        return $this->render('admin/dashboard.html.twig', [
            'animals' => $animals,
        ]);
    }

    #[Route('/animal/new', name: 'new_animal', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, LoggerInterface $logger): Response
    {
        $animal = new Animal();
        $form = $this->createForm(AnimalType::class, $animal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photo')->getData();

            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $animal->setPhoto($newFilename);
                } catch (FileException $e) {
                    $logger->error('Erreur lors de l\'upload : ' . $e->getMessage());
                    $this->addFlash('error', 'Erreur lors de l\'upload de la photo.');
                }
            }

            $entityManager->persist($animal);
            $entityManager->flush();

            $this->addFlash('success', 'Nouvel animal ajouté.');
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('admin/new_animal.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/animal/{id}/edit', name: 'edit_animal', methods: ['GET', 'POST'])]
    public function edit(Animal $animal, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(AnimalType::class, $animal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photo')->getData();

            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $animal->setPhoto($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de la photo.');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Animal modifié avec succès.');
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('admin/edit_animal.html.twig', [
            'form' => $form->createView(),
            'animal' => $animal,
        ]);
    }

    #[Route('/animal/{id}/delete', name: 'delete_animal', methods: ['POST'])]
    public function delete(Animal $animal, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($animal);
        $entityManager->flush();

        $this->addFlash('success', 'Animal supprimé avec succès.');
        return $this->redirectToRoute('dashboard');
    }
}
