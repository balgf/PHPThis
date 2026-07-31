<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/run.php';

final class FrameworkBehaviorTest extends TestCase
{
    #[Group('request-policy')]
    #[DataProvider('requestPolicyProvider')]
    #[TestDox('$_dataName')]
    public function testRequestPolicyBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('observability')]
    #[DataProvider('observabilityProvider')]
    #[TestDox('$_dataName')]
    public function testObservabilityBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('jobs')]
    #[DataProvider('jobProvider')]
    #[TestDox('$_dataName')]
    public function testJobBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('cli')]
    #[DataProvider('cliProvider')]
    #[TestDox('$_dataName')]
    public function testCliBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('migrations')]
    #[DataProvider('migrationProvider')]
    #[TestDox('$_dataName')]
    public function testMigrationBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('document-files')]
    #[DataProvider('documentFileProvider')]
    #[TestDox('$_dataName')]
    public function testDocumentFileBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('cache')]
    #[DataProvider('cacheProvider')]
    #[TestDox('$_dataName')]
    public function testCacheBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('redis-coordination')]
    #[DataProvider('redisCoordinationProvider')]
    #[TestDox('$_dataName')]
    public function testRedisCoordinationBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('consumer-profile')]
    #[DataProvider('consumerProfileProvider')]
    #[TestDox('$_dataName')]
    public function testConsumerProfileBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('handler-decorator')]
    #[DataProvider('handlerDecoratorProvider')]
    #[TestDox('$_dataName')]
    public function testHandlerDecoratorBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('composition')]
    #[DataProvider('compositionProvider')]
    #[TestDox('$_dataName')]
    public function testCompositionBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('http-boundary')]
    #[DataProvider('httpBoundaryProvider')]
    #[TestDox('$_dataName')]
    public function testHttpBoundaryBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('routing')]
    #[DataProvider('routingProvider')]
    #[TestDox('$_dataName')]
    public function testRoutingBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('input-projection')]
    #[DataProvider('inputProjectionProvider')]
    #[TestDox('$_dataName')]
    public function testInputProjectionBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('crud')]
    #[DataProvider('crudProvider')]
    #[TestDox('$_dataName')]
    public function testCrudBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('database-boundary')]
    #[DataProvider('databaseBoundaryProvider')]
    #[TestDox('$_dataName')]
    public function testDatabaseBoundaryBehavior(string $name): void
    {
        $this->runBehavior($name);
    }

    #[Group('parity')]
    public function testReviewedInventoryAndGroupOrderMatchTheRegistry(): void
    {
        $groups = frameworkBehaviorGroups();
        $groupCounts = array_map(
            static fn (array $names): int => count($names),
            $groups,
        );
        $groupedNames = [];

        foreach ($groups as $names) {
            foreach ($names as $name) {
                $groupedNames[] = $name;
            }
        }

        self::assertSame(
            [
                'request-policy' => 20,
                'observability' => 13,
                'jobs' => 11,
                'cli' => 9,
                'migrations' => 11,
                'document-files' => 6,
                'cache' => 16,
                'redis-coordination' => 13,
                'consumer-profile' => 4,
                'handler-decorator' => 6,
                'composition' => 2,
                'http-boundary' => 11,
                'routing' => 18,
                'input-projection' => 18,
                'crud' => 11,
                'database-boundary' => 8,
            ],
            $groupCounts,
            'Expected every reviewed behavior to belong to one coherent focused group.',
        );
        self::assertSame(
            frameworkBehaviorInventory(),
            array_keys(frameworkBehaviorTests()),
            'Expected the PHPUnit registry to preserve every reviewed behavior name and its order.',
        );
        self::assertSame(
            frameworkBehaviorInventory(),
            $groupedNames,
            'Expected focused groups to flatten to the exact reviewed behavior inventory.',
        );
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function requestPolicyProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('request-policy');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function observabilityProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('observability');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function jobProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('jobs');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function cliProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('cli');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function migrationProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('migrations');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function documentFileProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('document-files');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function cacheProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('cache');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function redisCoordinationProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('redis-coordination');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function consumerProfileProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('consumer-profile');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function handlerDecoratorProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('handler-decorator');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function compositionProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('composition');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function httpBoundaryProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('http-boundary');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function routingProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('routing');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function inputProjectionProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('input-projection');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function crudProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('crud');
    }

    /** @return Generator<string, array{string}, mixed, void> */
    public static function databaseBoundaryProvider(): Generator
    {
        yield from self::groupedBehaviorProvider('database-boundary');
    }

    private function runBehavior(string $name): void
    {
        $behavior = frameworkBehaviorTests()[$name]
            ?? throw new LogicException('Unknown framework behavior: ' . $name);

        $behavior();
        $this->addToAssertionCount(1);
    }

    /**
     * @param non-empty-string $group
     * @return Generator<string, array{string}, mixed, void>
     */
    private static function groupedBehaviorProvider(string $group): Generator
    {
        foreach (frameworkBehaviorNamesForGroup($group) as $name) {
            yield $name => [$name];
        }
    }
}
