<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

enum PacketRole: string { case Primary = 'primary'; case Supporting = 'supporting'; }

final readonly class PacketId
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[0-9a-f]{32}\z/', $value) !== 1) { throw new \InvalidArgumentException('Invalid packet identity.'); }
    }
    public function key(PacketRole $role): string { return 'private-documents/v1/packets/' . $this->value . '/' . $role->value; }
}

final readonly class PacketObject
{
    private function __construct(
        public PacketRole $role, public int $bytes, public string $sha256, public string $checksum,
        public string $nonce, public string $state, public ?ObjectVersion $version, public int $attempts,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row, PacketRole $role): self
    {
        $bytes = $row['bytes'] ?? null; $digest = $row['sha256'] ?? null; $nonce = $row['nonce'] ?? null;
        $state = $row['state'] ?? null; $version = $row['version'] ?? null; $attempts = $row['attempts'] ?? null;
        if (!is_int($bytes) || $bytes < 1 || $bytes > 65536 || !is_string($digest)
            || preg_match('/\A[0-9a-f]{64}\z/', $digest) !== 1 || !is_string($nonce) || preg_match('/\A[0-9a-f]{32}\z/', $nonce) !== 1
            || !is_string($state) || !in_array($state, ['ready', 'write_started', 'stored', 'verified', 'collision', 'deletion_pending', 'deleted'], true)
            || !is_int($attempts) || $attempts < 0 || $attempts > 2) { throw new DocumentUnavailable('Packet object unavailable.'); }
        $parsed = $version === null ? null : ObjectVersion::fromInput($version);
        if ((in_array($state, ['ready', 'write_started', 'collision'], true) && ($parsed !== null || $attempts !== 0))
            || (in_array($state, ['stored', 'verified', 'deletion_pending', 'deleted'], true) && $parsed === null)
            || (in_array($state, ['stored', 'verified'], true) && $attempts !== 0)
            || ($state === 'deleted' && $attempts === 0)) { throw new DocumentUnavailable('Packet object state inconsistent.'); }
        $raw = hex2bin($digest);
        if ($raw === false) { throw new DocumentUnavailable('Packet digest unavailable.'); }
        return new self($role, $bytes, $digest, base64_encode($raw), $nonce, $state, $parsed, $attempts);
    }

    /** @return array{profile: string, operation: string, role: string, ownership: string} */
    public function metadata(PacketId $id): array
    {
        return ['profile' => 'private-document-packet-v1', 'operation' => $id->value, 'role' => $this->role->value, 'ownership' => $this->nonce];
    }
    public function resolved(): bool { return in_array($this->state, ['ready', 'collision', 'deleted'], true); }
}

final readonly class PacketRecord
{
    private function __construct(
        public PacketId $id, public DocumentOwner $owner, public string $requestId, public string $fingerprint,
        public ?DocumentMetadata $metadata, public string $state, public int $created, public int $expires, public string $target,
        public PacketObject $primary, public PacketObject $supporting,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        $id = $row['packet_id'] ?? null; $owner = $row['owner_id'] ?? null; $retry = $row['request_id'] ?? null;
        $fingerprint = $row['request_fingerprint'] ?? null; $state = $row['state'] ?? null;
        $created = $row['created_at'] ?? null; $expires = $row['expires_at'] ?? null; $target = $row['target'] ?? null;
        if (!is_string($id) || !is_int($owner) || $owner < 1 || !is_string($retry) || preg_match('/\A[0-9a-f]{32}\z/', $retry) !== 1
            || !is_string($fingerprint) || preg_match('/\A[0-9a-f]{64}\z/', $fingerprint) !== 1
            || !is_string($state) || !in_array($state, ['pending', 'reconciliation_required', 'accepted', 'aborting', 'deletion_pending', 'failed', 'deleted'], true)
            || !is_int($created) || $created < 1 || $created > PHP_INT_MAX - 86400 || !is_int($expires) || $expires !== $created + 86400
            || !is_string($target) || preg_match('/\A[0-9a-f]{64}\z/', $target) !== 1) { throw new DocumentUnavailable('Packet record unavailable.'); }
        $primary = PacketObject::fromDatabaseRow(['bytes' => $row['primary_bytes'] ?? null, 'sha256' => $row['primary_sha256'] ?? null,
            'nonce' => $row['primary_nonce'] ?? null, 'state' => $row['primary_state'] ?? null,
            'version' => $row['primary_version'] ?? null, 'attempts' => $row['primary_delete_attempts'] ?? null], PacketRole::Primary);
        $supporting = PacketObject::fromDatabaseRow(['bytes' => $row['supporting_bytes'] ?? null, 'sha256' => $row['supporting_sha256'] ?? null,
            'nonce' => $row['supporting_nonce'] ?? null, 'state' => $row['supporting_state'] ?? null,
            'version' => $row['supporting_version'] ?? null, 'attempts' => $row['supporting_delete_attempts'] ?? null], PacketRole::Supporting);
        $label = $row['label'] ?? null; $note = $row['note'] ?? null;
        if (in_array($state, ['failed', 'deleted'], true)) {
            if ($label !== null || $note !== null || !$primary->resolved() || !$supporting->resolved()) {
                throw new DocumentUnavailable('Packet terminal state inconsistent.');
            }
            $metadata = null;
        } else {
            try { $metadata = DocumentMetadata::fromFields($note === null ? ['label' => $label] : ['label' => $label, 'note' => $note]); }
            catch (\Throwable) { throw new DocumentUnavailable('Packet descriptions unavailable.'); }
        }
        if ($state === 'accepted' && ($primary->state !== 'verified' || $supporting->state !== 'verified')) {
            throw new DocumentUnavailable('Packet acceptance inconsistent.');
        }
        return new self(new PacketId($id), new DocumentOwner($owner), $retry, $fingerprint, $metadata, $state, $created, $expires, $target, $primary, $supporting);
    }
}

final readonly class PacketReceipt
{
    private function __construct(public PacketId $id, public int $status, public string $outcome) {}
    public static function created(PacketId $id): self { return new self($id, 201, 'accepted'); }
    public static function repeated(PacketRecord $record, string $fingerprint, int $now): self
    {
        if (!hash_equals($record->fingerprint, $fingerprint)) { throw new PacketConflict(); }
        if ($record->expires <= $now || in_array($record->state, ['failed', 'deleted', 'deletion_pending'], true)) { throw new PacketGone(); }
        return new self($record->id, $record->state === 'accepted' ? 200 : 202, $record->state === 'accepted' ? 'accepted' : 'pending');
    }
}
