<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\AuthServiceResult;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthServiceTest extends TestCase
{
    private AuthenticationUtils $authUtils;
    private UserRepository $users;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    private AuthService $service;

    protected function setUp(): void
    {
        $this->authUtils = $this->createMock(AuthenticationUtils::class);
        $this->users     = $this->createMock(UserRepository::class);
        $this->em        = $this->createMock(EntityManagerInterface::class);
        $this->hasher    = $this->createMock(UserPasswordHasherInterface::class);

        $this->service = new AuthService(
            $this->authUtils,
            $this->users,
            $this->em,
            $this->hasher
        );
    }

    public function testGetLoginViewDataReturnsLastUsernameAndError(): void
    {
        $this->authUtils->expects($this->once())
            ->method('getLastUsername')
            ->willReturn('alice@example.com');

        $lastError = new AuthenticationException('Bad credentials');
        $this->authUtils->expects($this->once())
            ->method('getLastAuthenticationError')
            ->willReturn($lastError);

        $viewData = $this->service->getLoginViewData();

        $this->assertSame('alice@example.com', $viewData['last_username']);
        $this->assertSame($lastError, $viewData['error']);
    }

    public function testGetLoginViewDataReturnsNoErrorsOnSuccess(): void
    {
        $this->authUtils->expects($this->once())
            ->method('getLastUsername')
            ->willReturn('alice@example.com');

        $viewData = $this->service->getLoginViewData();

        $this->assertSame('alice@example.com', $viewData['last_username']);
        $this->assertSame(null, $viewData['error']);
    }

    public function testRegisterFailsWhenEmailOrPasswordMissing(): void
    {
        $result1 = $this->service->register(null, 'secret');
        $result2 = $this->service->register('user@example.com', null);
        $result3 = $this->service->register('', '');

        foreach ([$result1, $result2, $result3] as $result) {
            $this->assertInstanceOf(AuthServiceResult::class, $result);
            $this->assertFalse($result->isSuccess());
            $this->assertContains('Email and password are required.', $result->getErrors());
        }

        $this->users->expects($this->never())->method('findOneBy');
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');
        $this->hasher->expects($this->never())->method('hashPassword');
    }

    public function testRegisterFailsWithInvalidEmail(): void
    {
        $this->users->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'not-an-email'])
            ->willReturn(null);

        $result = $this->service->register('not-an-email', 'verysecure');

        $this->assertFalse($result->isSuccess());
        $this->assertContains('Please enter a valid email address.', $result->getErrors());

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');
    }

    public function testRegisterFailsWhenEmailAlreadyRegistered(): void
    {
        $existing = (new User())->setEmail('taken@example.com');

        $this->users->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'taken@example.com'])
            ->willReturn($existing);

        $result = $this->service->register('taken@example.com', 'verysecure');

        $this->assertFalse($result->isSuccess());
        $this->assertContains('Email already registered.', $result->getErrors());

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');
    }

    public function testRegisterFailsWhenPasswordTooShort(): void
    {
        $this->users->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'bob@example.com'])
            ->willReturn(null);

        $result = $this->service->register('bob@example.com', 'short');

        $this->assertFalse($result->isSuccess());
        $this->assertContains('Password must be at least 8 characters.', $result->getErrors());

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');
    }

    public function testRegisterAggregatesMultipleValidationErrors(): void
    {
        $this->users->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'bad'])
            ->willReturn((new User())->setEmail('bad'));

        $result = $this->service->register('bad', '123');

        $this->assertFalse($result->isSuccess());
        $errors = $result->getErrors();
        $this->assertContains('Please enter a valid email address.', $errors);
        $this->assertContains('Email already registered.', $errors);
        $this->assertContains('Password must be at least 8 characters.', $errors);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');
    }

    public function testRegisterSuccessNormalizesEmailHashesPasswordAndPersists(): void
    {
        $rawEmail = '  Alice@Example.COM ';
        $normalized = 'alice@example.com';
        $plain = 'supersecret';

        $this->users->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $normalized])
            ->willReturn(null);

        $this->hasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->callback(function (User $u) use ($normalized) {
                // email should already be normalized on the user when hashing
                return $u->getEmail() === $normalized;
            }), $plain)
            ->willReturn('$argon2id$hash');

        $capturedUser = null;

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (User $u) use (&$capturedUser, $normalized) {
                $capturedUser = $u;
                return $u->getEmail() === $normalized && \is_string($u->getPassword());
            }));

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->register($rawEmail, $plain);

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(User::class, $result->getUser());
        $this->assertSame($capturedUser, $result->getUser());
        $this->assertSame($normalized, $result->getUser()->getEmail());
        $this->assertSame('$argon2id$hash', $result->getUser()->getPassword());
    }
}
