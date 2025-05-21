<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    public function findByUserOrChatbot(User $user, User $chatbot): array
    {
        return $this->createQueryBuilder('cm')
            ->where('cm.sender = :user OR cm.sender = :chatbot')
            ->setParameter('user', $user)
            ->setParameter('chatbot', $chatbot)
            ->orderBy('cm.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}