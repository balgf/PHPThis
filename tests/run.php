<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

require __DIR__ . '/request-reader-support.php';
require __DIR__ . '/process-support.php';
require __DIR__ . '/create-user-support.php';

require __DIR__ . '/request-policy.php';
require __DIR__ . '/observability.php';
require __DIR__ . '/jobs.php';
require __DIR__ . '/cli.php';
require __DIR__ . '/migrations.php';
require __DIR__ . '/document-files.php';
require __DIR__ . '/cache.php';
require __DIR__ . '/redis-coordination.php';
require __DIR__ . '/consumer-profile.php';
require __DIR__ . '/handler-decorator.php';

require __DIR__ . '/composition.php';
require __DIR__ . '/http-boundary.php';
require __DIR__ . '/routing.php';
require __DIR__ . '/input-projection.php';
require __DIR__ . '/crud.php';
require __DIR__ . '/database-boundary.php';

/**
 * @return Generator<string, array{group: non-empty-string, test: Closure(): void}, mixed, void>
 */
function frameworkBehaviorDefinitions(): Generator
{
    yield from frameworkBehaviorGroupDefinitions('request-policy', requestPolicyTests());
    yield from frameworkBehaviorGroupDefinitions('observability', observabilityTests());
    yield from frameworkBehaviorGroupDefinitions('jobs', jobTests());
    yield from frameworkBehaviorGroupDefinitions('cli', cliTests());
    yield from frameworkBehaviorGroupDefinitions('migrations', migrationTests());
    yield from frameworkBehaviorGroupDefinitions('document-files', documentFileTests());
    yield from frameworkBehaviorGroupDefinitions('cache', cacheTests());
    yield from frameworkBehaviorGroupDefinitions('redis-coordination', redisCoordinationTests());
    yield from frameworkBehaviorGroupDefinitions('consumer-profile', consumerProfileTests());
    yield from frameworkBehaviorGroupDefinitions('handler-decorator', handlerDecoratorTests());
    yield from frameworkBehaviorGroupDefinitions('composition', compositionBehaviorTests());
    yield from frameworkBehaviorGroupDefinitions('http-boundary', httpBoundaryBehaviorTests());
    yield from frameworkBehaviorGroupDefinitions('routing', routingBehaviorTests());
    yield from frameworkBehaviorGroupDefinitions('input-projection', inputProjectionBehaviorTests());
    yield from frameworkBehaviorGroupDefinitions('crud', crudBehaviorTests());
    yield from frameworkBehaviorGroupDefinitions('database-boundary', databaseBoundaryBehaviorTests());
}

/**
 * @param non-empty-string $group
 * @param iterable<string, Closure(): void> $tests
 * @return Generator<string, array{group: non-empty-string, test: Closure(): void}, mixed, void>
 */
function frameworkBehaviorGroupDefinitions(string $group, iterable $tests): Generator
{
    foreach ($tests as $name => $test) {
        yield $name => [
            'group' => $group,
            'test' => $test,
        ];
    }
}

/**
 * @return array{
 *     tests: array<string, Closure(): void>,
 *     groups: array<non-empty-string, list<string>>
 * }
 */
function frameworkBehaviorRegistry(): array
{
    /**
     * @var array{
     *     tests: array<string, Closure(): void>,
     *     groups: array<non-empty-string, list<string>>
     * }|null $cached
     */
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $registered = [];
    $groups = [];

    foreach (frameworkBehaviorDefinitions() as $name => $definition) {
        if ($name === '') {
            throw new LogicException('Framework behavior names must not be empty.');
        }

        if (array_key_exists($name, $registered)) {
            throw new LogicException('Duplicate framework behavior name: ' . $name);
        }

        $group = $definition['group'];

        $registered[$name] = $definition['test'];
        $groups[$group] ??= [];
        $groups[$group][] = $name;
    }

    $cached = [
        'tests' => $registered,
        'groups' => $groups,
    ];

    return $cached;
}

/**
 * @return array<string, Closure(): void>
 */
function frameworkBehaviorTests(): array
{
    return frameworkBehaviorRegistry()['tests'];
}

/**
 * @return array<non-empty-string, list<string>>
 */
function frameworkBehaviorGroups(): array
{
    return frameworkBehaviorRegistry()['groups'];
}

/**
 * @param non-empty-string $group
 * @return list<string>
 */
function frameworkBehaviorNamesForGroup(string $group): array
{
    return frameworkBehaviorGroups()[$group]
        ?? throw new LogicException('Unknown framework behavior group: ' . $group);
}

/**
 * @return list<string>
 */
function frameworkBehaviorInventory(): array
{
    $contents = file_get_contents(__DIR__ . '/behavior-names.txt');

    if (!is_string($contents)) {
        throw new LogicException('Unable to read the framework behavior inventory.');
    }

    if (
        $contents === ''
        || !str_ends_with($contents, "\n")
        || str_contains($contents, "\r")
        || hash('sha256', $contents) !== '85878e382942708ea8bf43a063b7c0fede99bf42bf4e39524fa10b655f632d8c'
    ) {
        throw new LogicException('Framework behavior inventory bytes do not match the reviewed baseline.');
    }

    $names = explode("\n", substr($contents, 0, -1));

    if (
        count($names) !== 181
        || count(array_unique($names)) !== 181
        || in_array('', $names, true)
    ) {
        throw new LogicException('Framework behavior inventory must contain 181 unique non-empty names.');
    }

    return $names;
}
