<?php

declare(strict_types=1);

namespace App\Message;

final class DueTodoReminderMessage
{
    public function __construct(
        public readonly int $todoId,
        public readonly ?string $dueAtIso = null
    ) {}
}
