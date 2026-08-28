<?php

declare(strict_types=1);

namespace PHPThis\Verification\PHPStan;

use PDO;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\StaticMethodCallableNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/** @implements Rule<Expr> */
final class DirectPdoConstructionRule implements Rule
{
    private const CONNECTION_CLASS = 'PHPThis\\Database\\Connection';

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            !match (true) {
                $node instanceof New_ => $this->constructsPdo($node, $scope),
                $node instanceof StaticCall => $this->connectsPdo($node, $scope),
                $node::class === StaticMethodCallableNode::class => $this->connectsPdo($node, $scope),
                default => false,
            }
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

        $constructedType = $scope->getType($node->class)->getObjectTypeOrClassStringObjectType();

        return (new ObjectType(PDO::class))->isSuperTypeOf($constructedType)->yes();
    }

    private function connectsPdo(StaticCall|StaticMethodCallableNode $node, Scope $scope): bool
    {
        $name = $node instanceof StaticCall ? $node->name : $node->getName();

        if (
            !$name instanceof Identifier
            || strtolower($name->toString()) !== 'connect'
        ) {
            return false;
        }

        $class = $node instanceof StaticCall ? $node->class : $node->getClass();

        if ($class instanceof Name) {
            $className = $scope->resolveName($class);

            return $this->reflectionProvider->hasClass($className)
                && $this->resolvesNativePdoConnect($this->reflectionProvider->getClass($className));
        }

        $factoryType = $scope->getType($class)->getObjectTypeOrClassStringObjectType();

        if (!(new ObjectType(PDO::class))->isSuperTypeOf($factoryType)->yes()) {
            return false;
        }

        $pdoClassReflections = [];

        foreach ($factoryType->getObjectClassReflections() as $classReflection) {
            if ($this->isPdoType($classReflection->getName())) {
                $pdoClassReflections[] = $classReflection;
            }
        }

        if ($pdoClassReflections === []) {
            return false;
        }

        foreach ($pdoClassReflections as $classReflection) {
            if (!$this->resolvesNativePdoConnect($classReflection)) {
                return false;
            }
        }

        return true;
    }

    private function resolvesNativePdoConnect(ClassReflection $classReflection): bool
    {
        return $classReflection->hasNativeMethod('connect')
            && $classReflection->getNativeMethod('connect')->getDeclaringClass()->getName() === PDO::class;
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
