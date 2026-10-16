<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

final readonly class S3Target
{
    private function __construct(public string $bucket, public string $region, public string $account, public string $kms) {}
    public static function fromInputs(mixed $bucket, mixed $region, mixed $account, mixed $kms): self
    {
        if (!is_string($bucket) || preg_match('/\A[a-z0-9][a-z0-9-]{1,61}[a-z0-9]\z/', $bucket) !== 1
            || preg_match('/(?:\A(?:xn--|sthree-|amzn-s3-demo-)|(?:-s3alias|--ol-s3|--x-s3|--table-s3)\z)/', $bucket) === 1
            || !is_string($region) || preg_match('/\A(?:us|eu|ap|sa|ca|me|af|il|mx)-(?:[a-z]+-)?[a-z]+-[1-9]\z/', $region) !== 1
            || str_starts_with($region, 'us-gov-') || str_starts_with($region, 'us-iso')
            || !is_string($account) || preg_match('/\A[0-9]{12}\z/', $account) !== 1
            || !is_string($kms) || preg_match('/\Aarn:aws:kms:' . preg_quote($region, '/') . ':' . $account . ':key\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $kms) !== 1) {
            throw new \InvalidArgumentException('Invalid S3 target.');
        }
        return new self($bucket, $region, $account, $kms);
    }
    public function fingerprint(): string { return hash('sha256', $this->bucket . "\n" . $this->region . "\n" . $this->account . "\n" . $this->kms); }
}

final readonly class S3Settings
{
    private function __construct(
        public DocumentRoot $root, public S3Target $target, public string $caPath, public string $caHash,
        #[\SensitiveParameter] public string $accessKey, #[\SensitiveParameter] public string $secret,
        #[\SensitiveParameter] public string $token, public int $expires,
    ) {}
    public static function fromInputs(
        mixed $root, mixed $bucket, mixed $region, mixed $account, mixed $kms, mixed $caPath, mixed $caHash,
        #[\SensitiveParameter] mixed $accessKey, #[\SensitiveParameter] mixed $secret,
        #[\SensitiveParameter] mixed $token, mixed $expires,
    ): self {
        if (!is_string($caPath) || !str_starts_with($caPath, '/') || strlen($caPath) > 2048 || str_contains($caPath, "\0")
            || !is_string($caHash) || preg_match('/\A[0-9a-f]{64}\z/', $caHash) !== 1
            || !is_string($accessKey) || preg_match('/\AASIA[A-Z0-9]{16}\z/', $accessKey) !== 1
            || !is_string($secret) || preg_match('/\A[A-Za-z0-9+\/]{40}\z/', $secret) !== 1
            || !is_string($token) || preg_match('/\A[A-Za-z0-9+\/=]{16,16384}\z/', $token) !== 1
            || !is_string($expires) || preg_match('/\A[1-9][0-9]{9}\z/', $expires) !== 1) {
            throw new \InvalidArgumentException('Invalid S3 process configuration.');
        }
        return new self(DocumentRoot::fromInput($root), S3Target::fromInputs($bucket, $region, $account, $kms),
            $caPath, $caHash, $accessKey, $secret, $token, (int) $expires);
    }
    public function verify(int $now): void
    {
        // A supervisor injects one short-lived tuple per invocation; no acquisition, refresh or fallback I/O.
        if ($this->expires <= $now + 120 || $this->expires > $now + 43_200) { throw new DocumentUnavailable('Credential lifetime unavailable.'); }
        $stat = @lstat($this->caPath);
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0100000 || is_link($this->caPath)
            || $stat['size'] < 1 || $stat['size'] > 1_048_576 || !is_readable($this->caPath)) {
            throw new DocumentUnavailable('Reviewed CA bundle unavailable.');
        }
        $hash = @hash_file('sha256', $this->caPath);
        if (!is_string($hash) || !hash_equals($this->caHash, $hash)) { throw new DocumentUnavailable('Reviewed CA bundle changed.'); }
    }
}

final readonly class WriterConfiguration { public function __construct(public S3Settings $s3) {} }
final readonly class ReconcilerConfiguration { public function __construct(public S3Settings $s3) {} }
final readonly class DeletionConfiguration { public function __construct(public S3Settings $s3) {} }
