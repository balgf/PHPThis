<?php

declare(strict_types=1);

final class PHPThisStrictProfile
{
    /** @return list<string> */
    public static function syntaxFailures(string $contents, string $relativePath): array
    {
        $tokens = token_get_all($contents);

        return [
            ...self::nonFinalClassFailures($tokens, $relativePath),
            ...self::databaseLoopFailures($tokens, $relativePath),
        ];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return list<string>
     */
    private static function nonFinalClassFailures(array $tokens, string $relativePath): array
    {
        $failures = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            if (self::isClassConstant($tokens, $index) || self::isAnonymousClass($tokens, $index)) {
                continue;
            }

            $isFinal = false;

            for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
                $candidate = $tokens[$cursor];

                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                if (is_array($candidate) && $candidate[0] === T_FINAL) {
                    $isFinal = true;
                }

                if (!is_array($candidate) || !in_array($candidate[0], [T_FINAL, T_ABSTRACT, T_READONLY], true)) {
                    break;
                }
            }

            if ($isFinal) {
                continue;
            }

            for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
                $candidate = $tokens[$cursor];

                if (is_array($candidate) && $candidate[0] === T_STRING) {
                    $failures[] = sprintf(
                        'PHT002 %s:%d named class %s must be final.',
                        $relativePath,
                        $candidate[2],
                        $candidate[1],
                    );
                    break;
                }
            }
        }

        return $failures;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return list<string>
     */
    private static function databaseLoopFailures(array $tokens, string $relativePath): array
    {
        $failures = [];
        $loopPending = false;
        $headerDepth = null;
        $headerComplete = false;
        $braceDepth = 0;
        $loopBraceDepths = [];

        foreach ($tokens as $index => $token) {
            $tokenId = is_array($token) ? $token[0] : null;
            $tokenText = is_array($token) ? $token[1] : $token;

            if (in_array($tokenId, [T_FOR, T_FOREACH, T_WHILE, T_DO], true)) {
                $loopPending = true;
                $headerDepth = $tokenId === T_DO ? 0 : null;
                $headerComplete = $tokenId === T_DO;
                continue;
            }

            if ($loopPending && $tokenText === '(') {
                $headerDepth = ($headerDepth ?? 0) + 1;
                continue;
            }

            if ($loopPending && $tokenText === ')' && is_int($headerDepth)) {
                $headerDepth--;
                $headerComplete = $headerDepth === 0;
                continue;
            }

            if ($tokenText === '{') {
                $braceDepth++;

                if ($loopPending && $headerComplete) {
                    $loopBraceDepths[] = $braceDepth;
                    $loopPending = false;
                }

                continue;
            }

            if ($tokenText === '}') {
                if ($loopBraceDepths !== [] && end($loopBraceDepths) === $braceDepth) {
                    array_pop($loopBraceDepths);
                }

                $braceDepth--;
                continue;
            }

            $insideLoop = $loopBraceDepths !== [] || $loopPending;

            if ($loopPending && $headerComplete && $tokenText === ';') {
                $loopPending = false;
            }

            if (!$insideLoop || !in_array($tokenId, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                continue;
            }

            for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
                $candidate = $tokens[$next];

                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                if (
                    is_array($candidate)
                    && $candidate[0] === T_STRING
                    && in_array($candidate[1], ['selectAllRows', 'selectOneRow', 'executeStatement'], true)
                ) {
                    $line = $token[2];
                    $failures[] = "PHT003 {$relativePath}:{$line} calls a database method inside a loop.";
                }

                break;
            }
        }

        return $failures;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isClassConstant(array $tokens, int $index): bool
    {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $candidate = $tokens[$cursor];

            if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return (is_array($candidate) && $candidate[0] === T_DOUBLE_COLON) || $candidate === '::';
        }

        return false;
    }

    /** @param array<int, array{int, string, int}|string> $tokens */
    private static function isAnonymousClass(array $tokens, int $index): bool
    {
        $attributeDepth = 0;

        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $candidate = $tokens[$cursor];

            if ($candidate === ']') {
                $attributeDepth++;
                continue;
            }

            if ($candidate === '[' && $attributeDepth > 0) {
                $attributeDepth--;
                continue;
            }

            if (is_array($candidate) && $candidate[0] === T_ATTRIBUTE && $attributeDepth > 0) {
                $attributeDepth--;
                continue;
            }

            if ($attributeDepth > 0) {
                continue;
            }

            if (
                is_array($candidate)
                && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_READONLY], true)
            ) {
                continue;
            }

            return is_array($candidate) && $candidate[0] === T_NEW;
        }

        return false;
    }
}
