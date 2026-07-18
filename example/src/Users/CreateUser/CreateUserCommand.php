<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

use JsonException;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\RequestBodyTooLarge;
use stdClass;

final readonly class CreateUserCommand
{
    private const int MAX_JSON_BYTES = 2_048;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $email
     */
    private function __construct(
        public string $name,
        public string $email,
    ) {
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new RequestBodyTooLarge('Create-user input exceeds the 2048-byte limit.');
        }

        try {
            $decoded = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $failure) {
            throw new InvalidRequest('Create-user input must be valid JSON.', previous: $failure);
        }

        if (!$decoded instanceof stdClass) {
            throw new InvalidRequest('Create-user input must be a JSON object.');
        }

        $values = get_object_vars($decoded);

        if (
            count($values) !== 2
            || !array_key_exists('name', $values)
            || !array_key_exists('email', $values)
        ) {
            throw new InvalidRequest('Create-user input must contain exactly name and email.');
        }

        $name = $values['name'];
        $email = $values['email'];

        if (!is_string($name) || $name === '' || trim($name) !== $name) {
            throw new InvalidRequest('Create-user name must be a non-empty trimmed string.');
        }

        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) !== $email) {
            throw new InvalidRequest('Create-user email must be a valid unmodified string.');
        }

        return new self($name, $email);
    }
}
