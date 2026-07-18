<?php

declare(strict_types=1);

namespace PHPThis\Verification\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Cast\Bool_;
use PhpParser\Node\Expr\Cast\Double;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\Cast\String_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;

/** @implements Rule<Expr> */
final class MixedScalarCoercionRule implements Rule
{
    private const FUNCTIONS = [
        'boolval',
        'doubleval',
        'floatval',
        'intval',
        'settype',
        'strval',
    ];

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $value = match (true) {
            $node instanceof Bool_,
            $node instanceof Double,
            $node instanceof Int_,
            $node instanceof String_ => $node->expr,
            $node instanceof FuncCall => $this->functionValue($node, $scope),
            default => null,
        };

        if (!$value instanceof Expr || !$scope->getType($value) instanceof MixedType) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                '[PHT001] Scalar coercion from mixed is forbidden; validate and narrow the value first.',
            )
                ->identifier('phpthis.pht001')
                ->nonIgnorable()
                ->build(),
        ];
    }

    private function functionValue(FuncCall $call, Scope $scope): ?Expr
    {
        if (
            !$call->name instanceof Name
            || $call->getArgs() === []
            || !$this->reflectionProvider->hasFunction($call->name, $scope)
        ) {
            return null;
        }

        $name = strtolower($this->reflectionProvider->getFunction($call->name, $scope)->getName());

        if (!in_array($name, self::FUNCTIONS, true)) {
            return null;
        }

        return $call->getArgs()[0]->value;
    }
}
