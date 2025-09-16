<?php

namespace App\Service;

use App\DTO\AuthServiceResult;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthService
{
    public function __construct(
        private readonly AuthenticationUtils $authUtils,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function getLoginViewData(): array
    {
        return [
            'last_username' => $this->authUtils->getLastUsername(),
            'error'         => $this->authUtils->getLastAuthenticationError(),
        ];
    }

    public function register(?string $email, ?string $plainPassword): AuthServiceResult
    {
        $errors = [];

        $email = $email ? strtolower(trim($email)) : '';
        $plainPassword = (string) $plainPassword;

        if ($email === '' || $plainPassword === '') {
            $errors[] = 'Email and password are required.';
            return AuthServiceResult::failure($errors);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($this->users->findOneBy(['email' => $email])) {
            $errors[] = 'Email already registered.';
        }

        if (\strlen($plainPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($errors) {
            return AuthServiceResult::failure($errors);
        }

        $user = (new User())->setEmail($email);
        $hash = $this->hasher->hashPassword($user, $plainPassword);
        $user->setPassword($hash);

        $this->em->persist($user);
        $this->em->flush();

        return AuthServiceResult::success($user);
    }
}

