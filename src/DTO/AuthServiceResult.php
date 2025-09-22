<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;

final class AuthServiceResult
{
    private function __construct(
        private bool $success,
        private ?User $user,
        /** @var list<string> */
        private array $errors = [],
    ) {}

    public static function success(User $user): self
    {
        return new self(true, $user, []);
    }

    /** @param list<string> $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }

    public function isSuccess(): bool { return $this->success; }
    public function getUser(): ?User { return $this->user; }

    /** @return list<string> */
    public function getErrors(): array { return $this->errors; }
}
