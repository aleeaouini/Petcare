<?php
namespace App\Controller;
use App\Form\AnimalType;

use App\Entity\User;
use App\Entity\Animal; // N'oublie pas d'importer l'entité Animal
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;  // Assure-toi que l'annotation est bien présente
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class AdminUserController extends AbstractController
{
    #[Route('/assign-veterinaire/{userId}', name: 'assign_veterinaire')]
    public function assignVeterinaireRole($userId, EntityManagerInterface $entityManager)
    {
        $user = $entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Vérifier si un vétérinaire existe déjà
        $existingVeterinaire = $entityManager->getRepository(User::class)
            ->findOneBy(['roles' => '["ROLE_USER", "ROLE_VETERINAIRE"]']);

        if ($existingVeterinaire) {
            // Si un vétérinaire existe déjà, lui retirer le rôle
            $existingVeterinaire->setRoles(['ROLE_USER']);
            $entityManager->persist($existingVeterinaire);
            $entityManager->flush();
        }

        
        // Attribuer le rôle "VETERINAIRE" au nouvel utilisateur
        $user->setRoles(['ROLE_USER', 'ROLE_VETERINAIRE']);
        $entityManager->persist($user);
        $entityManager->flush();

        // Rediriger vers la page d'administration (ou toute autre page)
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        // Vérifie si l'utilisateur connecté a le rôle "ROLE_VETERINAIRE"
        if (!$this->getUser()) {
    return $this->redirectToRoute('app_login');
}

if (!$this->isGranted('ROLE_VETERINAIRE')) {
    return $this->redirectToRoute('home');
}

        // Récupérer tous les animaux depuis la base de données
        $animals = $entityManager->getRepository(Animal::class)->findAll();

        // Si aucun animal n'est trouvé
        if (!$animals) {
            // Si tu souhaites traiter ce cas particulier, tu peux ajouter un message ou une gestion d'erreur ici
        }

        // Passer les animaux à la vue
        return $this->render('admin/dashboard.html.twig', [
            'animals' => $animals,
        ]);
    }

    #[Route('/animal/edit/{id}', name: 'edit_animal')]
    public function editAnimal($id, Request $request, EntityManagerInterface $entityManager)
    {
        $animal = $entityManager->getRepository(Animal::class)->find($id);
        
        if (!$animal) {
            throw $this->createNotFoundException('Animal non trouvé');
        }

        // Création du formulaire d'édition
        $form = $this->createFormBuilder($animal)
            ->add('name', TextType::class)
            ->add('description', TextareaType::class)
            ->add('save', SubmitType::class, ['label' => 'Enregistrer les modifications'])
            ->getForm();

        // Traiter la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarder les modifications dans la base de données
            $entityManager->persist($animal);
            $entityManager->flush();

            // Rediriger après la soumission
            return $this->redirectToRoute('admin_dashboard');
        }

        // Passer le formulaire et l'animal à la vue
        return $this->render('admin/edit_animal.html.twig', [
            'form' => $form->createView(),
            'animal' => $animal,
        ]);
    }

    #[Route('/animal/delete/{id}', name: 'delete_animal')]
    public function deleteAnimal($id, EntityManagerInterface $entityManager)
    {
        $animal = $entityManager->getRepository(Animal::class)->find($id);
        
        if (!$animal) {
            throw $this->createNotFoundException('Animal non trouvé');
        }

        // Supprimer l'animal
        $entityManager->remove($animal);
        $entityManager->flush();

        // Redirige vers le tableau de bord ou une autre page
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/animal/new', name: 'new_animal')]
    public function newAnimal(Request $request, EntityManagerInterface $entityManager)
    {
        $animal = new Animal();
    
        $form = $this->createForm(AnimalType::class, $animal);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($animal);
            $entityManager->flush();
    
            return $this->redirectToRoute('admin_dashboard');
        }
    
        return $this->render('admin/new_animal.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    



    
}
