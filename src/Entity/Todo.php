<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TodoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TodoRepository::class)]
class Todo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(nullable: false)]
    private int $id;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Please enter a task.')]
    #[Assert\Length(max: 255, maxMessage: 'Task cannot be longer than {{ limit }} characters.')]
    private string $task = '';

    #[ORM\Column(nullable: false)]
    private bool $done = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    public function getId(): ?int { return $this->id; }

    public function getTask(): string { return $this->task; }
    public function setTask(string $task): self { $this->task = $task; return $this; }

    public function isDone(): bool { return $this->done; }
    public function setDone(bool $done): self { $this->done = $done; return $this; }

    public function getOwner(): User { return $this->owner; }
    public function setOwner(User $owner): self { $this->owner = $owner; return $this; }

    public function getDueAt(): ?\DateTimeImmutable { return $this->dueAt; }
    public function setDueAt(?\DateTimeImmutable $dueAt): self { $this->dueAt = $dueAt; return $this; }
}

