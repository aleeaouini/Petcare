<?php

namespace App\Controller;

use App\Repository\AnimalRepository; // <-- IMPORTANT !
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'home')]
public function index(AnimalRepository $animalRepository): Response
{
    $animals = $animalRepository->findAll();

    return $this->render('home/index.html.twig', [
        'animals' => $animals,
    ]);
}

    #[Route('/animal/{id}', name: 'animal_show')]
public function show(int $id, AnimalRepository $animalRepository): Response
{
    $animal = $animalRepository->find($id);

    if (!$animal) {
        throw $this->createNotFoundException('Animal non trouvé');
    }

    return $this->render('home/show.html.twig', [
        'animal' => $animal,
    ]);
}
}
