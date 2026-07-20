<?php

declare(strict_types=1);

namespace PHPThis\Http;

final class ResponseEmitter
{
    private const int FILE_CHUNK_BYTES = 8_192;

    public function emit(Response $response): void
    {
        if (headers_sent()) {
            throw new ResponseEmissionFailed(true);
        }
        if ($response->fileBody !== null) {
            $this->emitFile($response, $response->fileBody);
            return;
        }
        $this->emitHead($response);
        echo $response->body;
    }
    private function emitFile(Response $response, LocalFileBody $body): void
    {
        $handle = @fopen($body->path, 'rb');
        if (!is_resource($handle)) {
            throw new ResponseEmissionFailed(false);
        }
        try {
            $metadata = @fstat($handle);
            if (
                !is_array($metadata)
                || ($metadata['mode'] & 0170000) !== 0100000
                || $metadata['size'] !== $body->bytes
            ) {
                throw new ResponseEmissionFailed(false);
            }
            $this->emitHead($response);
            $remainingBytes = $body->bytes;
            while ($remainingBytes > 0) {
                $chunk = @fread($handle, min(self::FILE_CHUNK_BYTES, $remainingBytes));
                if (!is_string($chunk) || $chunk === '') {
                    throw new ResponseEmissionFailed(true);
                }
                echo $chunk;
                $remainingBytes -= strlen($chunk);
            }
        } finally {
            @fclose($handle);
        }
    }

    private function emitHead(Response $response): void
    {
        http_response_code($response->status);
        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        foreach ($response->cookies as $cookie) {
            header('Set-Cookie: ' . $cookie->headerValue(), false);
        }
    }
}
