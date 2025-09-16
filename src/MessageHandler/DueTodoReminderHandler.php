<?php

namespace App\MessageHandler;

use App\Message\DueTodoReminderMessage;
use App\Repository\TodoRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final class DueTodoReminderHandler
{
    public function __construct(
        private readonly TodoRepository $todoRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(DueTodoReminderMessage $msg): void
    {
        $todo = $this->todoRepository->find($msg->todoId);
        if (!$todo || $todo->isDone() || !$todo->getDueAt()) {
            return;
        }

        $scheduledTs = null;
        if ($msg->dueAtIso !== null) {
            try { $scheduledTs = (new \DateTimeImmutable($msg->dueAtIso))->getTimestamp(); }
            catch (\Throwable $e) { $this->logger->warning('Invalid dueAtIso', ['value'=>$msg->dueAtIso]); }
        }
        if ($scheduledTs !== null && $todo->getDueAt()->getTimestamp() !== $scheduledTs) {
            return;
        }

        $to = $todo->getOwner()?->getEmail();
        if (!$to) {
            $this->logger->warning('Skipping reminder: owner has no email', ['todo'=>$todo->getId()]);
            return;
        }

        $link = $this->urls->generate('index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('reminders@your-app.test')
            ->to($to)
            ->subject(sprintf('⏰ Todo due: %s', $todo->getTask()))
            ->htmlTemplate('emails/todo_due_reminder.html.twig')
            ->context([
                'todo' => $todo,
                'link' => $link,
            ]);

        $this->mailer->send($email);
    }
}
