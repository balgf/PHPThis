<?php

declare(strict_types=1);

namespace PHPThis\Verification\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\Constant\ConstantIntegerType;

/** @implements Rule<Array_> */
final class ConnectionCallableArrayRule implements Rule
{
    public function getNodeType(): string
    {
        return Array_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $nativeArrayType = $scope->getNativeType($node);
        $enhancedArrayType = $scope->getType($node);

        if ($nativeArrayType->isCallable()->no() && $enhancedArrayType->isCallable()->no()) {
            return [];
        }

        $receiverOffset = new ConstantIntegerType(0);
        $methodOffset = new ConstantIntegerType(1);
        $nativeReceiverType = $nativeArrayType->getOffsetValueType($receiverOffset);
        $enhancedReceiverType = $enhancedArrayType->getOffsetValueType($receiverOffset);

        if (
            !ConnectionSqlRuleSupport::isConnectionType($nativeReceiverType)
            && !ConnectionSqlRuleSupport::isConnectionType($enhancedReceiverType)
        ) {
            return [];
        }

        $methodType = $nativeArrayType->getOffsetValueType($methodOffset);
        $constantMethods = $methodType->getConstantStrings();

        if ($methodType->isString()->no()) {
            return [];
        }

        if (!$methodType->isString()->yes() || !$methodType->isConstantScalarValue()->yes()) {
            return [ConnectionSqlRuleSupport::directCallError()];
        }

        foreach ($constantMethods as $constantMethod) {
            if (ConnectionSqlRuleSupport::isDatabaseMethodName($constantMethod->getValue())) {
                return [ConnectionSqlRuleSupport::directCallError()];
            }
        }

        return [];
    }
}
