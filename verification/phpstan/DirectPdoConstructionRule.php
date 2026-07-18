<?php

declare(strict_types=1);

namespace PHPThis\Verification\PHPStan;

use PDO;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/** @implements Rule<New_> */
final class DirectPdoConstructionRule implements Rule
{
    private const CONNECTION_CLASS = 'PHPThis\\Database\\Connection';

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            !$this->constructsPdo($node, $scope)
            || $this->isFrameworkConnection($scope)
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                '[PHT005] Direct PDO construction is forbidden; use PHPThis\\Database\\Connection::connect.',
            )
                ->identifier('phpthis.pht005')
                ->nonIgnorable()
                ->build(),
        ];
    }

    private function constructsPdo(New_ $node, Scope $scope): bool
    {
        if ($node->class instanceof Name) {
            return $this->isPdoType($scope->resolveName($node->class));
        }

        if ($node->class instanceof Class_) {
            return $node->class->extends instanceof Name
                && $this->isPdoType($scope->resolveName($node->class->extends));
        }

        $constructedType = $scope->getType($node->class)->getClassStringObjectType();

        return (new ObjectType(PDO::class))->isSuperTypeOf($constructedType)->yes();
    }

    private function isPdoType(string $className): bool
    {
        return (new ObjectType(PDO::class))->isSuperTypeOf(new ObjectType($className))->yes();
    }

    private function isFrameworkConnection(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || $classReflection->getName() !== self::CONNECTION_CLASS) {
            return false;
        }

        $declaringFile = $classReflection->getFileName();
        $frameworkFile = realpath(dirname(__DIR__, 2) . '/src/Database/Connection.php');

        return is_string($declaringFile)
            && is_string($frameworkFile)
            && realpath($declaringFile) === $frameworkFile;
    }
}
