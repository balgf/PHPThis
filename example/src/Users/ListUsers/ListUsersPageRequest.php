<?php

declare(strict_types=1);

namespace Example\Users\ListUsers;

use PHPThis\Http\InvalidRequest;

final readonly class ListUsersPageRequest
{
    private function __construct(public ?int $afterUserId)
    {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        if ($query === []) {
            return new self(null);
        }

        if (count($query) !== 1 || !array_key_exists('after_user_id', $query)) {
            throw new InvalidRequest('List-users query accepts only after_user_id.');
        }

        $value = $query['after_user_id'];

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new InvalidRequest('List-users after_user_id must be a canonical positive integer.');
        }

        $afterUserId = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($afterUserId) || $afterUserId < 1) {
            throw new InvalidRequest('List-users after_user_id is outside the supported integer range.');
        }

        return new self($afterUserId);
    }
}
