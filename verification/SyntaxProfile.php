<?php

declare(strict_types=1);

namespace PHPThis\Verification;

final class SyntaxProfile
{
    /** @return list<string> */
    public static function failures(string $contents, string $relativePath): array
    {
        $tokens = token_get_all($contents);

        return [
            ...self::nonFinalClassFailures($tokens, $relativePath),
            ...self::databaseLoopFailures($tokens, $relativePath),
            ...self::magicMethodFailures($tokens, $relativePath),
        ];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return list<string>
     */
    private static function magicMethodFailures(array $tokens, string $relativePath): array
    {
        $failures = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $magicMethod = self::magicMethodAfter($tokens, $index);

            if ($magicMethod !== null && $magicMethod['name'] !== '__construct') {
                $failures[] = sprintf(
                    '%s:%d defines forbidden magic method %s.',
                    $relativePath,
                    $magicMethod['line'],
                    $magicMethod['name'],
                );
            }
        }

        return $failures;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{name: string, line: int}|null
     */
    private static function magicMethodAfter(array $tokens, int $index): ?array
    {
        for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
            $candidate = $tokens[$cursor];

            if (
                is_array($candidate)
                && in_array(
                    $candidate[0],
                    [
                        T_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT,
                        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                    ],
                    true,
                )
            ) {
                continue;
            }

            if (!is_array($candidate) || $candidate[0] !== T_STRING) {
                return null;
            }

            $name = strtolower($candidate[1]);

            return str_starts_with($name, '__')
                ? ['name' => $name, 'line' => $candidate[2]]
                : null;
        }

        return null;
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
        $loopBoundaryChanges = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_FOR, T_FOREACH, T_WHILE, T_DO], true)) {
                continue;
            }

            $loopEnd = self::loopEndIndex($tokens, $index);

            if ($loopEnd !== null) {
                $loopBoundaryChanges[$index] = ($loopBoundaryChanges[$index] ?? 0) + 1;
                $afterLoop = $loopEnd + 1;
                $loopBoundaryChanges[$afterLoop] = ($loopBoundaryChanges[$afterLoop] ?? 0) - 1;
            }
        }

        $activeLoopDepth = 0;

        foreach ($tokens as $index => $token) {
            $activeLoopDepth += $loopBoundaryChanges[$index] ?? 0;

            if (!is_array($token) || !in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                continue;
            }

            if ($activeLoopDepth === 0) {
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
                    && in_array(
                        strtolower($candidate[1]),
                        ['selectallrows', 'selectonerow', 'executestatement'],
                        true,
                    )
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
    private static function loopEndIndex(array $tokens, int $loopIndex): ?int
    {
        $loop = $tokens[$loopIndex] ?? null;

        if (!is_array($loop)) {
            return null;
        }

        if ($loop[0] === T_DO) {
            $bodyStart = self::nextSignificantIndex($tokens, $loopIndex + 1);

            if ($bodyStart === null) {
                return null;
            }

            $bodyEnd = self::statementEndIndex($tokens, $bodyStart);
            $whileIndex = self::nextSignificantIndex($tokens, $bodyEnd + 1);

            if ($whileIndex === null || !self::tokenHasId($tokens[$whileIndex], T_WHILE)) {
                return $bodyEnd;
            }

            $headerEnd = self::controlHeaderEndIndex($tokens, $whileIndex);

            return $headerEnd === null
                ? $bodyEnd
                : self::statementTerminatorIndex($tokens, $headerEnd);
        }

        $headerEnd = self::controlHeaderEndIndex($tokens, $loopIndex);

        if ($headerEnd === null) {
            return null;
        }

        $bodyStart = self::nextSignificantIndex($tokens, $headerEnd + 1);

        if ($bodyStart === null) {
            return $headerEnd;
        }

        if (($tokens[$bodyStart] ?? null) === ':') {
            $endTokenId = match ($loop[0]) {
                T_FOR => T_ENDFOR,
                T_FOREACH => T_ENDFOREACH,
                T_WHILE => T_ENDWHILE,
                default => null,
            };

            if ($endTokenId !== null) {
                return self::alternativeStatementEndIndex(
                    $tokens,
                    $loopIndex,
                    $bodyStart,
                    $loop[0],
                    $endTokenId,
                );
            }
        }

        return self::statementEndIndex($tokens, $bodyStart);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function statementEndIndex(array $tokens, int $startIndex): int
    {
        $startIndex = self::nextSignificantIndex($tokens, $startIndex) ?? $startIndex;
        $start = $tokens[$startIndex] ?? null;

        if ($start === '{') {
            return self::matchingDelimiterIndex($tokens, $startIndex, '{', '}') ?? $startIndex;
        }

        if (is_array($start)) {
            if ($start[0] === T_STRING) {
                $possibleLabelColon = self::nextSignificantIndex($tokens, $startIndex + 1);

                if ($possibleLabelColon !== null && ($tokens[$possibleLabelColon] ?? null) === ':') {
                    return $possibleLabelColon;
                }
            }

            return match ($start[0]) {
                T_IF => self::ifStatementEndIndex($tokens, $startIndex),
                T_TRY => self::tryStatementEndIndex($tokens, $startIndex),
                T_FOR, T_FOREACH, T_WHILE, T_DO => self::loopEndIndex($tokens, $startIndex) ?? $startIndex,
                T_SWITCH => self::switchStatementEndIndex($tokens, $startIndex),
                T_DECLARE => self::declareStatementEndIndex($tokens, $startIndex),
                T_FUNCTION => self::functionStatementEndIndex($tokens, $startIndex),
                T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM => self::declarationEndIndex($tokens, $startIndex),
                default => self::simpleStatementEndIndex($tokens, $startIndex),
            };
        }

        return self::simpleStatementEndIndex($tokens, $startIndex);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function ifStatementEndIndex(array $tokens, int $ifIndex): int
    {
        $headerEnd = self::controlHeaderEndIndex($tokens, $ifIndex);

        if ($headerEnd === null) {
            return $ifIndex;
        }

        $bodyStart = self::nextSignificantIndex($tokens, $headerEnd + 1);

        if ($bodyStart === null) {
            return $headerEnd;
        }

        if (($tokens[$bodyStart] ?? null) === ':') {
            return self::alternativeStatementEndIndex($tokens, $ifIndex, $bodyStart, T_IF, T_ENDIF);
        }

        $end = self::statementEndIndex($tokens, $bodyStart);

        while (true) {
            $continuation = self::nextSignificantIndex($tokens, $end + 1);

            if ($continuation === null) {
                return $end;
            }

            if (self::tokenHasId($tokens[$continuation], T_ELSEIF)) {
                $elseifHeaderEnd = self::controlHeaderEndIndex($tokens, $continuation);

                if ($elseifHeaderEnd === null) {
                    return $end;
                }

                $elseifBodyStart = self::nextSignificantIndex($tokens, $elseifHeaderEnd + 1);

                if ($elseifBodyStart === null) {
                    return $elseifHeaderEnd;
                }

                $end = self::statementEndIndex($tokens, $elseifBodyStart);
                continue;
            }

            if (self::tokenHasId($tokens[$continuation], T_ELSE)) {
                $elseBodyStart = self::nextSignificantIndex($tokens, $continuation + 1);

                return $elseBodyStart === null
                    ? $continuation
                    : self::statementEndIndex($tokens, $elseBodyStart);
            }

            return $end;
        }
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function tryStatementEndIndex(array $tokens, int $tryIndex): int
    {
        $bodyStart = self::nextSignificantIndex($tokens, $tryIndex + 1);

        if ($bodyStart === null) {
            return $tryIndex;
        }

        $end = self::statementEndIndex($tokens, $bodyStart);

        while (true) {
            $continuation = self::nextSignificantIndex($tokens, $end + 1);

            if ($continuation === null) {
                return $end;
            }

            if (self::tokenHasId($tokens[$continuation], T_CATCH)) {
                $catchHeaderEnd = self::controlHeaderEndIndex($tokens, $continuation);

                if ($catchHeaderEnd === null) {
                    return $end;
                }

                $catchBodyStart = self::nextSignificantIndex($tokens, $catchHeaderEnd + 1);

                if ($catchBodyStart === null) {
                    return $catchHeaderEnd;
                }

                $end = self::statementEndIndex($tokens, $catchBodyStart);
                continue;
            }

            if (self::tokenHasId($tokens[$continuation], T_FINALLY)) {
                $finallyBodyStart = self::nextSignificantIndex($tokens, $continuation + 1);

                return $finallyBodyStart === null
                    ? $continuation
                    : self::statementEndIndex($tokens, $finallyBodyStart);
            }

            return $end;
        }
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function switchStatementEndIndex(array $tokens, int $switchIndex): int
    {
        $headerEnd = self::controlHeaderEndIndex($tokens, $switchIndex);

        if ($headerEnd === null) {
            return $switchIndex;
        }

        $bodyStart = self::nextSignificantIndex($tokens, $headerEnd + 1);

        if ($bodyStart === null) {
            return $headerEnd;
        }

        if (($tokens[$bodyStart] ?? null) === ':') {
            return self::alternativeStatementEndIndex(
                $tokens,
                $switchIndex,
                $bodyStart,
                T_SWITCH,
                T_ENDSWITCH,
            );
        }

        return self::statementEndIndex($tokens, $bodyStart);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function declareStatementEndIndex(array $tokens, int $declareIndex): int
    {
        $headerEnd = self::controlHeaderEndIndex($tokens, $declareIndex);

        if ($headerEnd === null) {
            return $declareIndex;
        }

        $bodyStart = self::nextSignificantIndex($tokens, $headerEnd + 1);

        if ($bodyStart === null) {
            return $headerEnd;
        }

        if (($tokens[$bodyStart] ?? null) === ':') {
            return self::alternativeStatementEndIndex(
                $tokens,
                $declareIndex,
                $bodyStart,
                T_DECLARE,
                T_ENDDECLARE,
            );
        }

        return self::statementEndIndex($tokens, $bodyStart);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function functionStatementEndIndex(array $tokens, int $functionIndex): int
    {
        for ($cursor = $functionIndex + 1, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (
                $token === '&'
                || (
                    is_array($token)
                    && in_array(
                        $token[0],
                        [T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG],
                        true,
                    )
                )
            ) {
                continue;
            }

            if (is_array($token) && $token[0] === T_STRING) {
                return self::declarationEndIndex($tokens, $functionIndex);
            }

            break;
        }

        return self::simpleStatementEndIndex($tokens, $functionIndex);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function declarationEndIndex(array $tokens, int $declarationIndex): int
    {
        for ($cursor = $declarationIndex + 1, $count = count($tokens); $cursor < $count; $cursor++) {
            if (($tokens[$cursor] ?? null) !== '{') {
                continue;
            }

            return self::matchingDelimiterIndex($tokens, $cursor, '{', '}') ?? $cursor;
        }

        return self::simpleStatementEndIndex($tokens, $declarationIndex);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function simpleStatementEndIndex(array $tokens, int $startIndex): int
    {
        $parenthesisDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        $lastIndex = $startIndex;

        for ($cursor = $startIndex, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];
            $lastIndex = $cursor;

            if ($token === '(') {
                $parenthesisDepth++;
                continue;
            }

            if ($token === ')') {
                $parenthesisDepth--;
                continue;
            }

            if ($token === '[' || self::tokenHasId($token, T_ATTRIBUTE)) {
                $bracketDepth++;
                continue;
            }

            if ($token === ']') {
                $bracketDepth--;
                continue;
            }

            if ($token === '{' || self::isInterpolationOpeningToken($token)) {
                $braceDepth++;
                continue;
            }

            if ($token === '}') {
                if ($braceDepth === 0) {
                    return $cursor;
                }

                $braceDepth--;
                continue;
            }

            if (
                ($token === ';' || self::tokenHasId($token, T_CLOSE_TAG))
                && $parenthesisDepth === 0
                && $bracketDepth === 0
                && $braceDepth === 0
            ) {
                return $cursor;
            }
        }

        return $lastIndex;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function controlHeaderEndIndex(array $tokens, int $controlIndex): ?int
    {
        $openingParenthesis = self::nextSignificantIndex($tokens, $controlIndex + 1);

        if ($openingParenthesis === null || ($tokens[$openingParenthesis] ?? null) !== '(') {
            return null;
        }

        return self::matchingDelimiterIndex($tokens, $openingParenthesis, '(', ')');
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function alternativeStatementEndIndex(
        array $tokens,
        int $statementIndex,
        int $colonIndex,
        int $openerTokenId,
        int $endTokenId,
    ): int {
        $depth = 1;

        for ($cursor = $colonIndex + 1, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (self::tokenHasId($token, $openerTokenId)) {
                $nestedHeaderEnd = self::controlHeaderEndIndex($tokens, $cursor);
                $nestedBodyStart = $nestedHeaderEnd === null
                    ? null
                    : self::nextSignificantIndex($tokens, $nestedHeaderEnd + 1);

                if ($nestedBodyStart !== null && ($tokens[$nestedBodyStart] ?? null) === ':') {
                    $depth++;
                }

                continue;
            }

            if (!self::tokenHasId($token, $endTokenId)) {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return self::statementTerminatorIndex($tokens, $cursor);
            }
        }

        return self::simpleStatementEndIndex($tokens, $statementIndex);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function statementTerminatorIndex(array $tokens, int $statementEnd): int
    {
        $terminator = self::nextSignificantIndex($tokens, $statementEnd + 1);

        return $terminator !== null && ($tokens[$terminator] ?? null) === ';'
            ? $terminator
            : $statementEnd;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function matchingDelimiterIndex(
        array $tokens,
        int $openingIndex,
        string $openingDelimiter,
        string $closingDelimiter,
    ): ?int {
        $depth = 0;

        for ($cursor = $openingIndex, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (
                $token === $openingDelimiter
                || ($openingDelimiter === '{' && self::isInterpolationOpeningToken($token))
                || ($openingDelimiter === '[' && self::tokenHasId($token, T_ATTRIBUTE))
            ) {
                $depth++;
                continue;
            }

            if ($token !== $closingDelimiter) {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function nextSignificantIndex(array $tokens, int $startIndex): ?int
    {
        for ($cursor = $startIndex, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $cursor;
        }

        return null;
    }

    /** @param array{int, string, int}|string|null $token */
    private static function tokenHasId(array|string|null $token, int $tokenId): bool
    {
        return is_array($token) && $token[0] === $tokenId;
    }

    /** @param array{int, string, int}|string|null $token */
    private static function isInterpolationOpeningToken(array|string|null $token): bool
    {
        return self::tokenHasId($token, T_CURLY_OPEN)
            || self::tokenHasId($token, T_DOLLAR_OPEN_CURLY_BRACES);
    }

    /** @param array<int, array{int, string, int}|string> $tokens */
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
