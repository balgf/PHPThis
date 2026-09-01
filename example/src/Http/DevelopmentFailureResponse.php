<?php

declare(strict_types=1);

namespace Example\Http;

use Example\Observability\FailureClass;
use PHPThis\Http\Response;
use Throwable;

final class DevelopmentFailureResponse
{
    private const int MAXIMUM_BODY_BYTES = 65_536;
    private const int MAXIMUM_CHAINED_FAILURES = 4;
    private const int MAXIMUM_FRAMES = 32;
    private const int MAXIMUM_STRING_BYTES = 4_096;
    private const string PREFIX = "PHPThis development failure\n";
    private const string TRUNCATION_MARKER = "[diagnostic truncated]\n";

    /** @var array<class-string<Throwable>, true> */
    private const array SAFE_MESSAGE_CLASSES = [
        DevelopmentDiagnosticFailure::class => true,
    ];

    public function respond(Throwable $failure): Response
    {
        $body = self::PREFIX;
        $current = $failure;
        $exceptionIndex = 0;
        $totalFrames = 0;

        while ($current !== null) {
            if ($exceptionIndex >= self::MAXIMUM_CHAINED_FAILURES) {
                $body .= self::TRUNCATION_MARKER;

                break;
            }

            $exceptionPrefix = 'exception[' . $exceptionIndex . ']';

            if (!$this->appendString(
                $body,
                $exceptionPrefix . '.class',
                FailureClass::fromThrowable($current),
            )) {
                $body .= self::TRUNCATION_MARKER;

                break;
            }

            if (!array_key_exists($current::class, self::SAFE_MESSAGE_CLASSES)) {
                if (!$this->appendLine($body, $exceptionPrefix . ".message=<omitted>\n")) {
                    $body .= self::TRUNCATION_MARKER;

                    break;
                }
            } elseif (!$this->appendString(
                $body,
                $exceptionPrefix . '.message',
                $current->getMessage(),
            )) {
                $body .= self::TRUNCATION_MARKER;

                break;
            }

            if (
                !$this->appendString($body, $exceptionPrefix . '.file', $current->getFile())
                || !$this->appendLine($body, $exceptionPrefix . '.line=' . $current->getLine() . "\n")
            ) {
                $body .= self::TRUNCATION_MARKER;

                break;
            }

            $truncated = false;
            $frameIndex = 0;

            foreach ($current->getTrace() as $frame) {
                if ($totalFrames >= self::MAXIMUM_FRAMES) {
                    $truncated = true;

                    break;
                }

                $framePrefix = $exceptionPrefix . '.frame[' . $frameIndex . ']';

                if (!$this->appendLine($body, $framePrefix . "\n")) {
                    $truncated = true;

                    break;
                }

                $totalFrames++;

                $file = $this->frameString($frame, 'file');

                if ($file !== null && !$this->appendString($body, $framePrefix . '.file', $file)) {
                    $truncated = true;

                    break;
                }

                $line = $this->frameInteger($frame, 'line');

                if (
                    $line !== null
                    && !$this->appendLine($body, $framePrefix . '.line=' . $line . "\n")
                ) {
                    $truncated = true;

                    break;
                }

                foreach (['class', 'type', 'function'] as $member) {
                    $value = $this->frameString($frame, $member);

                    if ($value === null) {
                        continue;
                    }

                    if (!$this->appendString($body, $framePrefix . '.' . $member, $value)) {
                        $truncated = true;

                        break 2;
                    }
                }

                $frameIndex++;
            }

            if ($truncated) {
                $body .= self::TRUNCATION_MARKER;

                break;
            }

            $current = $current->getPrevious();
            $exceptionIndex++;
        }

        return new Response(
            500,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $body,
        );
    }

    private function appendString(string &$body, string $name, string $value): bool
    {
        $truncated = strlen($value) > self::MAXIMUM_STRING_BYTES;
        $encoded = json_encode(
            substr($value, 0, self::MAXIMUM_STRING_BYTES),
            JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES,
        );

        if (!$this->appendLine($body, $name . '=' . $encoded . "\n")) {
            return false;
        }

        return !$truncated;
    }

    /** @param array<array-key, mixed> $frame */
    private function frameString(array $frame, string $member): ?string
    {
        $value = $frame[$member] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<array-key, mixed> $frame */
    private function frameInteger(array $frame, string $member): ?int
    {
        $value = $frame[$member] ?? null;

        return is_int($value) ? $value : null;
    }

    private function appendLine(string &$body, string $line): bool
    {
        if (
            strlen($body) + strlen($line) + strlen(self::TRUNCATION_MARKER)
                > self::MAXIMUM_BODY_BYTES
        ) {
            return false;
        }

        $body .= $line;

        return true;
    }
}
