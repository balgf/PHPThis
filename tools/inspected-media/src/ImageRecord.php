<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class ImageRecord
{
    private function __construct(
        public ImageId $id, public int $owner, public int $bytes, public ?string $digest,
        public string $state, public string $definition, public int $attempts,
        public int $created, public int $expires, public ?string $token,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (array_keys($row) !== ['image_id', 'owner_id', 'size_bytes', 'digest', 'state', 'definition', 'attempts', 'created_at', 'expires_at', 'inspection_token']
            || !is_string($row['image_id']) || !is_int($row['owner_id']) || !is_int($row['size_bytes'])
            || !(is_string($row['digest']) || $row['digest'] === null) || !is_string($row['state'])
            || !is_string($row['definition']) || !is_int($row['attempts']) || !is_int($row['created_at'])
            || !is_int($row['expires_at']) || !(is_string($row['inspection_token']) || $row['inspection_token'] === null)) {
            throw new CatalogUnavailable('Invalid retained metadata.');
        }
        if ($row['owner_id'] < 1 || $row['size_bytes'] < 1 || $row['size_bytes'] > 1_048_576
            || ($row['digest'] !== null && preg_match('/\A[0-9a-f]{64}\z/D', $row['digest']) !== 1)
            || !in_array($row['state'], ['reserved', 'pending', 'inspecting', 'accepted', 'rejected', 'scanner_error'], true)
            || preg_match('/\A[0-9a-f]{64}\z/D', $row['definition']) !== 1
            || $row['attempts'] < 0 || $row['attempts'] > 2
            || $row['created_at'] < 1 || $row['expires_at'] !== $row['created_at'] + 86_400
            || ($row['inspection_token'] !== null && preg_match('/\A[0-9a-f]{32}\z/D', $row['inspection_token']) !== 1)) {
            throw new CatalogUnavailable('Invalid retained metadata.');
        }
        return new self(new ImageId($row['image_id']), $row['owner_id'], $row['size_bytes'], $row['digest'],
            $row['state'], $row['definition'], $row['attempts'], $row['created_at'], $row['expires_at'], $row['inspection_token']);
    }
}
