<?php
namespace App\Controller;

use App\Entity\Animal;
use App\Entity\CartItem;
use App\Repository\AnimalRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    #[Route('/cart/add/{id}', name: 'add_to_cart', methods: ['POST'])]
    public function addToCart(int $id, AnimalRepository $animalRepo, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('login');
        }

        $user = $this->getUser();
        $animal = $animalRepo->find($id);

        if (!$animal) {
            throw $this->createNotFoundException('Animal non trouvé.');
        }

        // Vérifie si l'animal est déjà dans le panier
        $existingItem = $em->getRepository(CartItem::class)->findOneBy([
            'user' => $user,
            'animal' => $animal,
            'confirmed' => false,
        ]);

        if (!$existingItem) {
            $item = new CartItem();
            $item->setUser($user);
            $item->setAnimal($animal);
            $item->setConfirmed(false);  // Statut "confirmed" à false
            $em->persist($item);
            $em->flush();
        }

        return $this->redirectToRoute('view_cart');
    }

    #[Route('/cart', name: 'view_cart')]
    public function viewCart(CartItemRepository $cartItemRepo): Response
    {
        if ($this->isGranted('ROLE_VETERINAIRE')) {
        return $this->redirectToRoute('app_login');
    }
        $user = $this->getUser();
        $items = $cartItemRepo->findBy([
            'user' => $user,
            'confirmed' => false,
        ]);

        return $this->render('cart/view.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/cart/delete/{id}', name: 'delete_cart_item')]
    public function deleteItem(int $id, CartItemRepository $repo, EntityManagerInterface $em): Response
    {
        $item = $repo->find($id);

        if (!$item || $item->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Cet élément n\'existe pas ou ne vous appartient pas.');
            return $this->redirectToRoute('view_cart');
        }

        $em->remove($item);
        $em->flush();

        $this->addFlash('success', 'L\'élément a été supprimé avec succès.');

        return $this->redirectToRoute('view_cart');
    }

    #[Route('/cart/confirm', name: 'confirm_cart', methods: ['POST'])]
    public function confirmCart(CartItemRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $items = $repo->findBy([
            'user' => $user,
            'confirmed' => false,
        ]);

        foreach ($items as $item) {
            $item->setConfirmed(true);
        }

        $em->flush();

        $this->addFlash('success', 'Panier confirmé avec succès.');

        return $this->redirectToRoute('view_cart');
    }
}
