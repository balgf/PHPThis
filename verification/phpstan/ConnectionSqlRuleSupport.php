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
}
