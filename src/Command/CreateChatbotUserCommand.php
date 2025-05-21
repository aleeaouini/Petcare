<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-chatbot-user',
    description: 'Creates a Chatbot user in the database'
)]
class CreateChatbotUserCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a Chatbot user in the database')
            ->setHelp('This command creates a User entity with email "bot@yourapp.com" for chatbot messages.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'bot@yourapp.com']);
        if ($existingUser) {
            $io->success('Chatbot user already exists.');
            return Command::SUCCESS;
        }

        $chatbotUser = new User();
        $chatbotUser->setEmail('bot@yourapp.com');
        $chatbotUser->setRoles(['ROLE_BOT']);
        $chatbotUser->setPassword(
            $this->passwordHasher->hashPassword($chatbotUser, 'chatbot_dummy_password_2025')
        );
        $chatbotUser->setIsVerified(true);

        $this->entityManager->persist($chatbotUser);
        $this->entityManager->flush();

        $io->success('Chatbot user created successfully with email: bot@yourapp.com');

        return Command::SUCCESS;
    }
}