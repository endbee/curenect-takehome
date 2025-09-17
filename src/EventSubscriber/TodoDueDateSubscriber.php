<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Todo;
use App\Message\DueTodoReminderMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
final class TodoDueDateSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly int $leadSeconds = 120,
        private readonly LoggerInterface $logger,
    ) {}

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postUpdate];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->maybeSchedule($args, force:true);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Todo) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();
        $changes = $uow->getEntityChangeSet($entity);
        $relevant = \array_intersect_key($changes, ['dueAt' => true, 'done' => true]);
        if ($relevant === []) {
            return;
        }

        $this->scheduleFor($entity);
    }

    private function maybeSchedule(LifecycleEventArgs $args, bool $force = false): void
    {
        $todo = $args->getObject();
        $this->logger->info("Start scheduling reminder");
        if ($todo instanceof Todo) {
            $this->logger->info('Scheduling reminders', [
                'todo' => $todo->getId(),
                'due'  => $todo->getDueAt()?->format(DATE_ATOM),
                'lead' => $this->leadSeconds,
                'early_delay_ms' => max(0, ($todo->getDueAt()->getTimestamp() - $this->leadSeconds - time())*1000),
                'due_delay_ms'   => max(0, ($todo->getDueAt()->getTimestamp() - time())*1000),
            ]);
            $this->scheduleFor($todo);
        }
    }

    private function scheduleFor(Todo $todo): void
    {
        $dueAt = $todo->getDueAt();
        if ($todo->isDone() || !$dueAt) {
            return;
        }

        $now = new \DateTimeImmutable('now');
        $earlyAt = $dueAt->sub(new \DateInterval('PT' . max(0, $this->leadSeconds) . 'S'));
        $this->dispatchAt($todo, $earlyAt, $dueAt);

        $this->dispatchAt($todo, $dueAt, $dueAt);
    }

    private function dispatchAt(Todo $todo, \DateTimeImmutable $when, \DateTimeImmutable $dueAt): void
    {
        $now = new \DateTimeImmutable('now');
        $delayMs = max(0, ($when->getTimestamp() - $now->getTimestamp()) * 1000);

        $this->bus->dispatch(
            new DueTodoReminderMessage($todo->getId(), $dueAt->format(\DateTimeInterface::ATOM)),
            [new DelayStamp($delayMs)]
        );
    }
}
