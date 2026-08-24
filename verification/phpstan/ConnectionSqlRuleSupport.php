<?php

declare(strict_types=1);

namespace PHPThis\Verification\PHPStan;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

final class ConnectionSqlRuleSupport
{
    private const CONNECTION_CLASS = 'PHPThis\\Database\\Connection';

    private const DATABASE_METHODS = [
        'executestatement',
        'selectallrows',
        'selectonerow',
    ];

    public static function isConnectionMethod(Expr $receiver, Identifier|Expr $name, Scope $scope): bool
    {
        return $name instanceof Identifier
            && in_array(strtolower($name->toString()), self::DATABASE_METHODS, true)
            && self::isConnection($receiver, $scope);
    }

    public static function isConnection(Expr $expression, Scope $scope): bool
    {
        foreach ([$scope->getNativeType($expression), $scope->getType($expression)] as $type) {
            if (self::isConnectionType($type)) {
                return true;
            }
        }

        return false;
    }

    public static function isConnectionType(Type $type): bool
    {
        return in_array(self::CONNECTION_CLASS, $type->getObjectClassNames(), true);
    }

    public static function isDatabaseMethodName(string $method): bool
    {
        return in_array(strtolower($method), self::DATABASE_METHODS, true);
    }

    public static function sqlExpression(MethodCall $call): ?Expr
    {
        foreach ($call->getArgs() as $argument) {
            if (
                $argument->name instanceof Identifier
                && strtolower($argument->name->toString()) === 'sql'
            ) {
                return $argument->unpack ? null : $argument->value;
            }
        }

        foreach ($call->getArgs() as $argument) {
            if ($argument->name === null) {
                return $argument->unpack ? null : $argument->value;
            }
        }

        return null;
    }

    public static function isFiniteNonBlankConstantString(Expr $sql, Scope $scope): bool
    {
        $type = $scope->getNativeType($sql);
        $constantStrings = $type->getConstantStrings();

        if (
            !$type->isString()->yes()
            || !$type->isConstantScalarValue()->yes()
            || $constantStrings === []
        ) {
            return false;
        }

        foreach ($constantStrings as $constantString) {
            if (trim($constantString->getValue()) === '') {
                return false;
            }
        }

        return true;
    }

    public static function constantSqlError(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            '[PHT006] Connection SQL must resolve to one or more non-blank compile-time constant strings; '
                . 'map dynamic choices to reviewed statements and bind data values separately.',
        )
            ->identifier('phpthis.pht006')
            ->nonIgnorable()
            ->build();
    }

    public static function hasRepeatedNamedPlaceholder(string $sql): bool
    {
        $length = strlen($sql);
        /** @var array<string, true> $seen */
        $seen = [];
        $offset = 0;
        $lexicalExclusionsAllowed = true;

        while ($offset < $length) {
            $character = $sql[$offset];

            if (
                $lexicalExclusionsAllowed
                && ($character === "'" || $character === '"')
            ) {
                $afterQuotedSegment = self::afterQuotedSegment(
                    $sql,
                    $offset,
                    $character,
                    $character === "'" && self::isPostgreSqlEscapeString($sql, $offset),
                );

                if ($afterQuotedSegment === null) {
                    $lexicalExclusionsAllowed = false;
                    $offset++;
                    continue;
                }

                $offset = $afterQuotedSegment;
                continue;
            }

            if (
                $lexicalExclusionsAllowed
                && self::isPortableDashLineComment($sql, $offset)
            ) {
                $offset = self::afterLineComment($sql, $offset);
                continue;
            }

            if (
                $lexicalExclusionsAllowed
                && $character === '/'
                && ($sql[$offset + 1] ?? null) === '*'
            ) {
                $afterBlockComment = self::afterBlockComment($sql, $offset);

                if ($afterBlockComment === null) {
                    $lexicalExclusionsAllowed = false;
                    $offset += 2;
                    continue;
                }

                $offset = $afterBlockComment;
                continue;
            }

            if (
                $lexicalExclusionsAllowed
                && (
                    $character === '`'
                    || $character === '#'
                    || $character === '['
                    || (
                        $character === '-'
                        && ($sql[$offset + 1] ?? null) === '-'
                    )
                    || (
                        $character === '$'
                        && self::dollarQuoteDelimiter($sql, $offset) !== null
                    )
                )
            ) {
                $lexicalExclusionsAllowed = false;
                $offset++;
                continue;
            }

            if ($character !== ':') {
                $offset++;
                continue;
            }

            $colonEnd = $offset + 1;

            while ($colonEnd < $length && $sql[$colonEnd] === ':') {
                $colonEnd++;
            }

            if ($colonEnd > $offset + 1) {
                $offset = $colonEnd;
                continue;
            }

            $nameStart = $offset + 1;

            if ($nameStart >= $length || !self::isPlaceholderNameStart($sql[$nameStart])) {
                $offset++;
                continue;
            }

            $nameEnd = $nameStart + 1;

            while (
                $nameEnd < $length
                && self::isPlaceholderNameContinuation($sql[$nameEnd])
            ) {
                $nameEnd++;
            }

            $placeholder = substr($sql, $nameStart, $nameEnd - $nameStart);

            if (isset($seen[$placeholder])) {
                return true;
            }

            $seen[$placeholder] = true;
            $offset = $nameEnd;
        }

        return false;
    }

    public static function repeatedNamedPlaceholderError(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            '[PHT008] Connection SQL must use a distinct named placeholder for each occurrence; '
                . 'rename repeated placeholders and bind each value separately.',
        )
            ->identifier('phpthis.pht008')
            ->nonIgnorable()
            ->build();
    }

    public static function directCallError(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            '[PHT006] Connection database methods must be called directly with constant SQL; '
                . 'callable indirection is forbidden.',
        )
            ->identifier('phpthis.pht006')
            ->nonIgnorable()
            ->build();
    }

    private static function afterQuotedSegment(
        string $sql,
        int $offset,
        string $terminator,
        bool $backslashEscapes,
    ): ?int {
        $length = strlen($sql);
        $cursor = $offset + 1;

        while ($cursor < $length) {
            if ($backslashEscapes && $sql[$cursor] === '\\' && $cursor + 1 < $length) {
                $cursor += 2;
                continue;
            }

            if (
                !$backslashEscapes
                && $sql[$cursor] === '\\'
                && ($sql[$cursor + 1] ?? null) === $terminator
            ) {
                return null;
            }

            if ($sql[$cursor] !== $terminator) {
                $cursor++;
                continue;
            }

            if (($sql[$cursor + 1] ?? null) === $terminator) {
                $cursor += 2;
                continue;
            }

            return $cursor + 1;
        }

        return null;
    }

    private static function isPostgreSqlEscapeString(string $sql, int $offset): bool
    {
        if ($offset === 0 || !in_array($sql[$offset - 1], ['E', 'e'], true)) {
            return false;
        }

        $prefixOffset = $offset - 1;

        return $prefixOffset === 0
            || !self::isSqlIdentifierContinuation($sql[$prefixOffset - 1]);
    }

    private static function isPortableDashLineComment(string $sql, int $offset): bool
    {
        if ($sql[$offset] !== '-' || ($sql[$offset + 1] ?? null) !== '-') {
            return false;
        }

        $following = $sql[$offset + 2] ?? null;

        return $following === null || ord($following) <= 32;
    }

    private static function afterLineComment(string $sql, int $offset): int
    {
        $length = strlen($sql);
        $cursor = $offset + 1;

        while ($cursor < $length && $sql[$cursor] !== "\n" && $sql[$cursor] !== "\r") {
            $cursor++;
        }

        return $cursor;
    }

    private static function afterBlockComment(string $sql, int $offset): ?int
    {
        $length = strlen($sql);
        $cursor = $offset + 2;

        if (in_array($sql[$cursor] ?? null, ['!', '+'], true)) {
            return null;
        }

        while ($cursor < $length) {
            if ($sql[$cursor] === '/' && ($sql[$cursor + 1] ?? null) === '*') {
                return null;
            }

            if ($sql[$cursor] === '*' && ($sql[$cursor + 1] ?? null) === '/') {
                return $cursor + 2;
            }

            $cursor++;
        }

        return null;
    }

    private static function dollarQuoteDelimiter(string $sql, int $offset): ?string
    {
        $length = strlen($sql);
        $cursor = $offset + 1;

        if ($cursor >= $length) {
            return null;
        }

        if ($sql[$cursor] === '$') {
            return '$$';
        }

        if (!self::isDollarQuoteTagStart($sql[$cursor])) {
            return null;
        }

        $cursor++;

        while ($cursor < $length && self::isDollarQuoteTagContinuation($sql[$cursor])) {
            $cursor++;
        }

        if ($cursor >= $length || $sql[$cursor] !== '$') {
            return null;
        }

        return substr($sql, $offset, $cursor - $offset + 1);
    }

    private static function isPlaceholderNameStart(string $character): bool
    {
        $byte = ord($character);

        return ($byte >= 65 && $byte <= 90)
            || ($byte >= 97 && $byte <= 122)
            || $character === '_';
    }

    private static function isPlaceholderNameContinuation(string $character): bool
    {
        $byte = ord($character);

        return self::isPlaceholderNameStart($character) || ($byte >= 48 && $byte <= 57);
    }

    private static function isDollarQuoteTagStart(string $character): bool
    {
        return self::isPlaceholderNameStart($character) || ord($character) >= 128;
    }

    private static function isDollarQuoteTagContinuation(string $character): bool
    {
        $byte = ord($character);

        return self::isDollarQuoteTagStart($character) || ($byte >= 48 && $byte <= 57);
    }

    private static function isSqlIdentifierContinuation(string $character): bool
    {
        return self::isPlaceholderNameContinuation($character)
            || $character === '$'
            || ord($character) >= 128;
    }
}
