<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Todo;
use App\Entity\User;
use App\Repository\TodoRepository;
use App\Service\TodoService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class TodoServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private TodoRepository $repo;
    private AuthorizationCheckerInterface $auth;
    private TodoService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(TodoRepository::class);
        $this->auth = $this->createMock(AuthorizationCheckerInterface::class);

        $this->service = new TodoService($this->em, $this->repo, $this->auth);
    }

    public function testListForReturnsTodosFromRepository(): void
    {
        $owner = new User();
        $todos = [new Todo(), new Todo()];

        $this->repo
            ->expects($this->once())
            ->method('findByOwner')
            ->with($owner)
            ->willReturn($todos);

        $result = $this->service->listFor($owner);

        $this->assertSame($todos, $result);
    }

    public function testCreateSetsOwnerAndDoneFalseAndPersists(): void
    {
        $owner = new User();
        $todo = new Todo();

        $this->em->expects($this->once())->method('persist')->with($todo);
        $this->em->expects($this->once())->method('flush');

        $created = $this->service->create($todo, $owner);

        $this->assertSame($todo, $created);
        $this->assertSame($owner, $todo->getOwner(), 'Owner should be set');
        $this->assertFalse($todo->isDone(), 'New todo should be marked not done');
    }

    public function testToggleWhenGrantedFlipsDoneAndFlushes(): void
    {
        $todo = new Todo();
        $todo->setDone(false);

        $this->auth
            ->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', $todo)
            ->willReturn(true);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $toggled = $this->service->toggle($todo);

        $this->assertTrue($toggled->isDone(), 'Done flag should be toggled to true');
    }

    public function testToggleWhenDeniedThrowsAndDoesNotFlush(): void
    {
        $todo = new Todo();
        $todo->setDone(false);

        $this->auth
            ->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', $todo)
            ->willReturn(false);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(AccessDeniedException::class);
        $this->service->toggle($todo);
    }

    public function testDeleteWhenGrantedRemovesAndFlushes(): void
    {
        $todo = new Todo();

        $this->auth
            ->expects($this->once())
            ->method('isGranted')
            ->with('DELETE', $todo)
            ->willReturn(true);

        $this->em->expects($this->once())->method('remove')->with($todo);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($todo);

        $this->addToAssertionCount(1); // no exception = success
    }

    public function testDeleteWhenDeniedThrowsAndDoesNotRemoveOrFlush(): void
    {
        $todo = new Todo();

        $this->auth
            ->expects($this->once())
            ->method('isGranted')
            ->with('DELETE', $todo)
            ->willReturn(false);

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(AccessDeniedException::class);
        $this->service->delete($todo);
    }
}
