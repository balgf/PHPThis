<?php

declare(strict_types=1);

namespace Example\Jobs;

use InvalidArgumentException;
use JsonException;
use LogicException;
use stdClass;

final readonly class UserWelcomeJobEnvelope
{
    public const int VERSION = 1;
    public const string TYPE = 'user.welcome';
    private const int MAX_JSON_BYTES = 2_048;

    private function __construct(
        public string $jobId,
        public int $version,
        public string $type,
        public string $idempotencyKey,
        public string $email,
    ) {
    }

    public static function forEmail(string $email): self
    {
        if (!self::emailIsValid($email)) {
            throw new InvalidArgumentException('A validated email is required for a user-welcome job.');
        }

        return new self(
            bin2hex(random_bytes(16)),
            self::VERSION,
            self::TYPE,
            self::idempotencyKeyForEmail($email),
            $email,
        );
    }

    public static function fromStored(string $jobId, string $json): self
    {
        if (
            preg_match('/\A[0-9a-f]{32}\z/D', $jobId) !== 1
            || strlen($json) < 2
            || strlen($json) > self::MAX_JSON_BYTES
        ) {
            throw self::invalid();
        }

        try {
            $decoded = json_decode($json, false, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw self::invalid();
        }

        if (!$decoded instanceof stdClass) {
            throw self::invalid();
        }

        $values = get_object_vars($decoded);

        if (
            count($values) !== 4
            || !array_key_exists('version', $values)
            || !array_key_exists('type', $values)
            || !array_key_exists('idempotency_key', $values)
            || !array_key_exists('payload', $values)
        ) {
            throw self::invalid();
        }

        $version = $values['version'];
        $type = $values['type'];
        $idempotencyKey = $values['idempotency_key'];
        $payload = $values['payload'];

        if (
            !is_int($version)
            || $version !== self::VERSION
            || !is_string($type)
            || $type !== self::TYPE
            || !is_string($idempotencyKey)
            || preg_match('/\A[0-9a-f]{64}\z/D', $idempotencyKey) !== 1
            || !$payload instanceof stdClass
        ) {
            throw self::invalid();
        }

        $payloadValues = get_object_vars($payload);

        if (
            count($payloadValues) !== 1
            || !array_key_exists('email', $payloadValues)
        ) {
            throw self::invalid();
        }

        $email = $payloadValues['email'];

        if (
            !is_string($email)
            || !self::emailIsValid($email)
            || !hash_equals(self::idempotencyKeyForEmail($email), $idempotencyKey)
        ) {
            throw self::invalid();
        }

        return new self($jobId, $version, $type, $idempotencyKey, $email);
    }

    public function toJson(): string
    {
        $json = json_encode(
            [
                'version' => $this->version,
                'type' => $this->type,
                'idempotency_key' => $this->idempotencyKey,
                'payload' => ['email' => $this->email],
            ],
            JSON_THROW_ON_ERROR,
        );

        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new LogicException('User-welcome job envelope exceeds its fixed byte limit.');
        }

        return $json;
    }

    private static function emailIsValid(string $email): bool
    {
        $bytes = strlen($email);

        return $bytes >= 3
            && $bytes <= 254
            && filter_var($email, FILTER_VALIDATE_EMAIL, 0) === $email;
    }

    private static function idempotencyKeyForEmail(string $email): string
    {
        return hash('sha256', "user-welcome:v1\0" . $email);
    }

    private static function invalid(): InvalidUserWelcomeJobEnvelope
    {
        return new InvalidUserWelcomeJobEnvelope('Stored user-welcome job envelope is invalid.');
    }
}
