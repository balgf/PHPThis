<?php

declare(strict_types=1);

namespace PHPThis\Verification\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** @implements Rule<MethodCall> */
final class ConstantSqlStringRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            !ConnectionSqlRuleSupport::isConnectionMethod($node->var, $node->name, $scope)
            || $node->isFirstClassCallable()
        ) {
            return [];
        }

        $sql = ConnectionSqlRuleSupport::sqlExpression($node);

        if (
            $sql instanceof Expr
            && ConnectionSqlRuleSupport::isFiniteNonBlankConstantString($sql, $scope)
        ) {
            return [];
        }

        return [ConnectionSqlRuleSupport::constantSqlError()];
    }
}
