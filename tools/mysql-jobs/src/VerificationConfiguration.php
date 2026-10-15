<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class VerificationConfiguration
{
    public const string USERNAME = 'root';

    private function __construct(public string $dsn, #[SensitiveParameter] public string $password)
    {
    }

    public static function fromInputs(mixed $dsn, #[SensitiveParameter] mixed $password): self
    {
        if (!is_string($dsn) || !is_string($password)) {
            throw new InvalidArgumentException('Required verification configuration is invalid.');
        }
        if (strlen($dsn) > 128 || preg_match('/\Amysql:host=127\.0\.0\.1;port=([1-9][0-9]{0,4});dbname=phpthis_jobs;charset=utf8mb4\z/D', $dsn, $parts) !== 1
            || filter_var($parts[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false
            || preg_match('/\A[0-9a-f]{64}\z/D', $password) !== 1) {
            throw new InvalidArgumentException('Required verification configuration is invalid.');
        }
        return new self($dsn, $password);
    }
}
