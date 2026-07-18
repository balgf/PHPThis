<?php

declare(strict_types=1);

namespace PHPThis\Verification\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodCallableNode;
use PHPStan\Rules\Rule;

/** @implements Rule<MethodCallableNode> */
final class ConnectionMethodCallableRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCallableNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!ConnectionSqlRuleSupport::isConnectionMethod($node->getVar(), $node->getName(), $scope)) {
            return [];
        }

        return [ConnectionSqlRuleSupport::directCallError()];
    }
}
