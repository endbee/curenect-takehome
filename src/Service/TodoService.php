<?php

namespace App\Service;

use App\Entity\Todo;
use App\Entity\User;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class TodoService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TodoRepository $repo,
        private readonly AuthorizationCheckerInterface $auth
    ) {}

    public function listFor(User $owner): array
    {
        return $this->repo->findByOwner($owner);
    }

    public function create(Todo $todo, User $owner): Todo
    {
        $todo->setOwner($owner);
        $todo->setDone(false);

        $this->em->persist($todo);
        $this->em->flush();

        return $todo;
    }

    public function toggle(Todo $todo): Todo
    {
        $this->denyUnlessGranted('EDIT', $todo);
        $todo->setDone(!$todo->isDone());
        $this->em->flush();

        return $todo;
    }

    public function delete(Todo $todo): void
    {
        $this->denyUnlessGranted('DELETE', $todo);
        $this->em->remove($todo);
        $this->em->flush();
    }

    private function denyUnlessGranted(string $attribute, Todo $todo): void
    {
        if (!$this->auth->isGranted($attribute, $todo)) {
            throw new AccessDeniedException();
        }
    }
}

