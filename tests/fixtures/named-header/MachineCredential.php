<?php

declare(strict_types=1);

namespace NamedHeaderRecipe;

use PHPThis\Http\Request;
use SensitiveParameter;

final readonly class MachineCredential
{
    private function __construct(#[SensitiveParameter] public string $value)
    {
    }

    public static function fromRequest(#[SensitiveParameter] Request $request): self
    {
        // This read-only profile has no query/body/cookie credential or input channel.
        if (
            $request->method !== 'GET' || $request->query !== [] || $request->body !== ''
            || $request->multipart !== null || $request->uploads !== []
            || array_key_exists('authorization', $request->headers)
            || array_key_exists('proxy-authorization', $request->headers)
            || array_key_exists('cookie', $request->headers)
            || array_key_exists('x-api-key', $request->headers)
        ) {
            throw new MachineCredentialRejected('Machine request is rejected.');
        }
        $value = $request->headers['example-machine-key'] ?? null;
        if ($value === null || strlen($value) !== 68 || preg_match('/\Amk1_[0-9a-f]{64}\z/D', $value) !== 1) {
            throw new MachineCredentialRejected('Machine request is rejected.');
        }
        return new self($value);
    }
}
