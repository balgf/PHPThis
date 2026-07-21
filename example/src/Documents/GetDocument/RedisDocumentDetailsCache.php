<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use Example\Documents\AccountId;
use Example\Documents\AuthenticatedPrincipal;
use Example\Documents\DocumentKey;
use Example\Documents\ResolvedTenant;
use InvalidArgumentException;
use JsonException;
use Redis;
use RedisException;
use Throwable;

final class RedisDocumentDetailsCache implements RetrieveAuthorizedDocument
{
    private const float CONNECT_TIMEOUT_SECONDS = 0.25;

    private const float READ_TIMEOUT_SECONDS = 0.25;

    private const int MAXIMUM_KEY_BYTES = 192;

    private const int MAXIMUM_PAYLOAD_BYTES = 1_024;

    private const int MAXIMUM_TITLE_BYTES = 512;

    private const int MAXIMUM_TTL_MILLISECONDS = 86_400_000;

    private ?Redis $redis = null;

    private bool $connectionAttempted = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $database,
        private readonly RetrieveAuthorizedDocument $authoritative,
        private readonly string $environment,
        private readonly int $ttlMilliseconds,
        private readonly DocumentDetailsCacheTrace $trace,
    ) {
        if (
            $host === ''
            || strlen($host) > 255
            || preg_match('/[\x00-\x20\x7f]/D', $host) === 1
            || $port < 1
            || $port > 65_535
        ) {
            throw new InvalidArgumentException(
                'Redis document-details cache connection target is invalid.',
            );
        }

        if ($database < 0 || $database > 15) {
            throw new InvalidArgumentException(
                'Redis document-details cache database must be between 0 and 15.',
            );
        }

        if (preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/D', $environment) !== 1) {
            throw new InvalidArgumentException(
                'Redis document-details cache environment must be a bounded code-owned token.',
            );
        }

        if ($ttlMilliseconds < 1 || $ttlMilliseconds > self::MAXIMUM_TTL_MILLISECONDS) {
            throw new InvalidArgumentException(
                'Redis document-details cache TTL must be between 1 and 86400000 milliseconds.',
            );
        }
    }

    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?DocumentDetails {
        if ($tenant->accountId->value !== $accountId->value) {
            throw new InvalidArgumentException(
                'Redis document-details cache requires matching requested and resolved tenants.',
            );
        }

        $key = $this->key($accountId, $documentKey);
        $readOutcome = DocumentDetailsCacheReadOutcome::Miss;
        $cachedPayload = false;
        $redis = $this->client();

        if ($redis instanceof Redis) {
            try {
                $candidate = $redis->clearLastError() ? $redis->get($key) : null;

                if (is_string($candidate)) {
                    $cachedPayload = $candidate;
                } elseif ($candidate !== false || $redis->getLastError() !== null) {
                    $readOutcome = DocumentDetailsCacheReadOutcome::BackendUnavailable;
                }
            } catch (RedisException) {
                $readOutcome = DocumentDetailsCacheReadOutcome::BackendUnavailable;
            }
        } else {
            $readOutcome = DocumentDetailsCacheReadOutcome::BackendUnavailable;
        }

        if (is_string($cachedPayload)) {
            $cached = $this->parse($cachedPayload, $accountId, $documentKey);

            if ($cached !== null) {
                $this->trace->complete(
                    DocumentDetailsCacheReadOutcome::Hit,
                    DocumentDetailsCacheWriteOutcome::NotAttempted,
                );

                return $cached;
            }

            $readOutcome = DocumentDetailsCacheReadOutcome::Corrupt;
        }

        try {
            $document = $this->authoritative->retrieve(
                $principal,
                $tenant,
                $accountId,
                $documentKey,
            );
        } catch (Throwable $failure) {
            $this->trace->complete(
                $readOutcome,
                DocumentDetailsCacheWriteOutcome::NotAttempted,
            );

            throw $failure;
        }

        if ($document === null) {
            $this->trace->complete(
                $readOutcome,
                DocumentDetailsCacheWriteOutcome::NotAttempted,
            );

            return null;
        }

        $payload = $this->payload($document, $accountId, $documentKey);

        if ($payload === null) {
            $this->trace->complete(
                $readOutcome,
                DocumentDetailsCacheWriteOutcome::PayloadRejected,
            );

            return $document;
        }

        if (
            $readOutcome === DocumentDetailsCacheReadOutcome::BackendUnavailable
            || !$redis instanceof Redis
        ) {
            $this->trace->complete(
                $readOutcome,
                DocumentDetailsCacheWriteOutcome::NotAttempted,
            );

            return $document;
        }

        $writeOutcome = DocumentDetailsCacheWriteOutcome::BackendUnavailable;

        try {
            $stored = $redis->clearLastError()
                && $redis->set(
                    $key,
                    $payload,
                    ['px' => $this->ttlMilliseconds],
                );

            if ($stored === true) {
                $writeOutcome = DocumentDetailsCacheWriteOutcome::Stored;
            }
        } catch (RedisException) {
            // Cache failure is recorded below; the authoritative result remains usable.
        }

        $this->trace->complete($readOutcome, $writeOutcome);

        return $document;
    }

    public function invalidate(
        AccountId $accountId,
        DocumentKey $documentKey,
    ): RedisDocumentDetailsInvalidationOutcome {
        $redis = $this->client();
        $outcome = RedisDocumentDetailsInvalidationOutcome::BackendUnavailable;

        if ($redis instanceof Redis) {
            try {
                $deleted = $redis->clearLastError()
                    ? $redis->del($this->key($accountId, $documentKey))
                    : false;
                $outcome = match ($deleted) {
                    1 => RedisDocumentDetailsInvalidationOutcome::Deleted,
                    0 => RedisDocumentDetailsInvalidationOutcome::Absent,
                    default => RedisDocumentDetailsInvalidationOutcome::BackendUnavailable,
                };
            } catch (RedisException) {
                // The authoritative write has already committed; the outcome remains visible.
            }
        }

        $this->trace->recordInvalidation($outcome);

        return $outcome;
    }

    private function key(AccountId $accountId, DocumentKey $documentKey): string
    {
        $key = sprintf(
            'phpthis_example:%s:tenant:%d:document_details:v1:%s',
            $this->environment,
            $accountId->value,
            $documentKey->value,
        );

        if (strlen($key) > self::MAXIMUM_KEY_BYTES) {
            throw new InvalidArgumentException('Redis document-details cache key exceeds its byte limit.');
        }

        return $key;
    }

    private function parse(
        string $payload,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?DocumentDetails {
        if (strlen($payload) > self::MAXIMUM_PAYLOAD_BYTES) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (
            !is_array($decoded)
            || count($decoded) !== 4
            || !array_key_exists('schema_version', $decoded)
            || !array_key_exists('tenant_account_id', $decoded)
            || !array_key_exists('document_key', $decoded)
            || !array_key_exists('title', $decoded)
            || $decoded['schema_version'] !== 1
            || $decoded['tenant_account_id'] !== $accountId->value
            || $decoded['document_key'] !== $documentKey->value
            || !is_string($decoded['title'])
            || !$this->validTitle($decoded['title'])
        ) {
            return null;
        }

        try {
            $canonicalPayload = json_encode(
                [
                    'schema_version' => $decoded['schema_version'],
                    'tenant_account_id' => $decoded['tenant_account_id'],
                    'document_key' => $decoded['document_key'],
                    'title' => $decoded['title'],
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            return null;
        }

        if ($canonicalPayload !== $payload) {
            return null;
        }

        return DocumentDetails::fromDatabaseRow(['title' => $decoded['title']]);
    }

    private function payload(
        DocumentDetails $document,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?string {
        if (!$this->validTitle($document->title)) {
            return null;
        }

        try {
            $payload = json_encode(
                [
                    'schema_version' => 1,
                    'tenant_account_id' => $accountId->value,
                    'document_key' => $documentKey->value,
                    'title' => $document->title,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            return null;
        }

        return strlen($payload) <= self::MAXIMUM_PAYLOAD_BYTES ? $payload : null;
    }

    private function validTitle(string $title): bool
    {
        return $title !== ''
            && strlen($title) <= self::MAXIMUM_TITLE_BYTES
            && preg_match('//u', $title) === 1;
    }

    private function client(): ?Redis
    {
        if ($this->connectionAttempted) {
            return $this->redis;
        }

        $this->connectionAttempted = true;
        $redis = new Redis();

        try {
            $connected = $redis->connect(
                $this->host,
                $this->port,
                self::CONNECT_TIMEOUT_SECONDS,
                null,
                0,
                self::READ_TIMEOUT_SECONDS,
            );

            if (
                !$connected
                || !$redis->setOption(Redis::OPT_MAX_RETRIES, 0)
                || !$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE)
                || !$redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE)
                || !$redis->select($this->database)
            ) {
                return null;
            }
        } catch (RedisException) {
            return null;
        }

        $this->redis = $redis;

        return $this->redis;
    }
}
