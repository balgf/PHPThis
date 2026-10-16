<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

final class DocumentUnavailable extends \RuntimeException {}
final class DocumentCollision extends \RuntimeException {}
final class UnknownDocumentCommand extends \InvalidArgumentException {}

final readonly class DocumentId
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[0-9a-f]{32}\z/', $value) !== 1) { throw new \InvalidArgumentException('Invalid document identity.'); }
    }
    public function key(): string { return 'private-documents/v1/' . $this->value . '/content'; }
}

final readonly class ObjectVersion
{
    private function __construct(public string $value) {}
    public static function fromInput(mixed $value): self
    {
        if (!is_string($value) || $value === 'null' || preg_match('/\A[\x21-\x7e]{1,1024}\z/', $value) !== 1) {
            throw new DocumentUnavailable('Object version unavailable.');
        }
        return new self($value);
    }
}

final readonly class DocumentRecord
{
    private function __construct(
        public DocumentId $id, public DocumentOwner $owner, public ?DocumentMetadata $metadata, public int $bytes, public string $sha256, public string $checksum,
        public string $state, public ?ObjectVersion $version, public int $attempts,
        public int $created, public int $expires, public string $target,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        $owner = $row['owner_id'] ?? null; $label = $row['label'] ?? null; $note = $row['note'] ?? null;
        $id = $row['document_id'] ?? null; $bytes = $row['size_bytes'] ?? null;
        $digest = $row['sha256'] ?? null; $state = $row['state'] ?? null;
        $version = $row['version_id'] ?? null; $attempts = $row['delete_attempts'] ?? null;
        $created = $row['created_at'] ?? null; $expires = $row['expires_at'] ?? null;
        $target = $row['target'] ?? null;
        if (!is_int($owner) || $owner < 1 || !is_string($id) || !is_int($bytes) || $bytes < 1 || $bytes > 65_536
            || !is_string($digest) || preg_match('/\A[0-9a-f]{64}\z/', $digest) !== 1
            || !is_string($state) || !in_array($state, ['pending', 'stored', 'accepted', 'failed', 'reconciliation_required', 'deletion_pending', 'deleted'], true)
            || !is_int($attempts) || $attempts < 0 || $attempts > 2
            || !is_int($created) || $created < 1 || $created > PHP_INT_MAX - 86_400
            || !is_int($expires) || $expires !== $created + 86_400
            || !is_string($target) || preg_match('/\A[0-9a-f]{64}\z/', $target) !== 1) {
            throw new DocumentUnavailable('Document ownership unavailable.');
        }
        $raw = hex2bin($digest);
        if ($raw === false) { throw new DocumentUnavailable('Document digest unavailable.'); }
        $parsedVersion = $version === null ? null : ObjectVersion::fromInput($version);
        if (in_array($state, ['stored', 'accepted', 'deletion_pending', 'deleted'], true) && $parsedVersion === null) {
            throw new DocumentUnavailable('Document version missing.');
        }
        if (in_array($state, ['failed', 'deleted'], true)) {
            if ($label !== null || $note !== null) { throw new DocumentUnavailable('Terminal metadata was not cleared.'); }
            $metadata = null;
        } else {
            try { $metadata = DocumentMetadata::fromFields($note === null ? ['label' => $label] : ['label' => $label, 'note' => $note]); }
            catch (\Throwable) { throw new DocumentUnavailable('Document metadata unavailable.'); }
        }
        return new self(new DocumentId($id), new DocumentOwner($owner), $metadata, $bytes, $digest, base64_encode($raw), $state, $parsedVersion, $attempts, $created, $expires, $target);
    }
}
