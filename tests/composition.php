<?php

declare(strict_types=1);

use Example\Accounts\DenyAllAccountAuthentication;
use Example\Accounts\DenyAllAccountAuthorization;
use Example\Accounts\DenyAllAccountTenantResolution;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\Routes;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\Request;
use PHPThis\Routing\Router;

/**
 * @return Generator<string, Closure(): void, mixed, void>
 */
function compositionBehaviorTests(): Generator
{
    yield 'example composes explicit route modules' => static function (): void {
    $application = new Application(new Router(Routes::create(
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        Connection::connect('sqlite::memory:', new QueryBudget(4), new QueryTrace(4)),
        new SelectAuthorizedDocument(
            Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        ),
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
        new DenyAllAccountAuthentication(),
        new DenyAllAccountTenantResolution(),
        new DenyAllAccountAuthorization(),
        new DenyAllAccountAuthorization(),
        new DenyAllAccountAuthorization(),
        new LocalDocumentFiles(__DIR__ . '/../tmp/application-tests/document-files'),
    )));
    $response = $application->handle(new Request('GET', '/health'));

    if (
        $response->status !== 200
        || $response->headers !== [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]
        || $response->body !== "{\"status\":\"ok\"}\n"
    ) {
        throw new RuntimeException('Expected the composed example health route.');
    }
};

    yield 'example setup creates and reseeds a fresh database idempotently' => static function (): void {
    $directory = __DIR__ . '/../tmp/application-tests';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the setup migration test directory.');
    }

    $resolvedDirectory = realpath($directory);

    if (!is_string($resolvedDirectory)) {
        throw new RuntimeException('Unable to resolve the setup migration test directory.');
    }

    $databasePath = $resolvedDirectory . '/setup-example-fresh.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset the setup migration test database.');
    }

    $setupPath = __DIR__ . '/../tools/setup-example.php';
    $defaultDatabasePath = dirname(__DIR__) . '/tmp/example.sqlite';
    $defaultExistedBefore = is_file($defaultDatabasePath);
    $defaultHashBefore = $defaultExistedBefore
        ? hash_file('sha256', $defaultDatabasePath)
        : null;

    if ($defaultExistedBefore && !is_string($defaultHashBefore)) {
        throw new RuntimeException('Unable to fingerprint the default example database before setup tests.');
    }

    $relativeSubmittedPath = 'tmp/application-tests/setup-example-relative-rejected.sqlite';
    $relativeTargetPath = dirname(__DIR__) . '/' . $relativeSubmittedPath;
    $controlSubmittedPath = $resolvedDirectory . "/setup-example-\n-rejected.sqlite";
    $extraArgumentTargetPath = $resolvedDirectory . '/setup-example-extra-argv-rejected.sqlite';

    if (is_file($relativeTargetPath) && !unlink($relativeTargetPath)) {
        throw new RuntimeException('Unable to reset the rejected relative-path target.');
    }

    if (is_file($controlSubmittedPath) && !unlink($controlSubmittedPath)) {
        throw new RuntimeException('Unable to reset the rejected control-path target.');
    }

    if (is_file($extraArgumentTargetPath) && !unlink($extraArgumentTargetPath)) {
        throw new RuntimeException('Unable to reset the rejected extra-argument target.');
    }

    $emptyPath = runIsolatedPhpTest($setupPath, ['']);
    $relativePath = runIsolatedPhpTest($setupPath, [$relativeSubmittedPath]);
    $directoryPath = runIsolatedPhpTest(
        $setupPath,
        [$resolvedDirectory . DIRECTORY_SEPARATOR],
    );
    $controlPath = runIsolatedPhpTest($setupPath, [$controlSubmittedPath]);
    $oversizedPath = runIsolatedPhpTest($setupPath, ['/' . str_repeat('a', 4_096)]);
    $extraArgument = runIsolatedPhpTest(
        $setupPath,
        [$extraArgumentTargetPath, 'unexpected'],
    );

    if (DIRECTORY_SEPARATOR === '/') {
        $windowsDriveTargetPath = dirname(__DIR__) . '/C:\\phpthis-setup-rejected.sqlite';

        if (is_file($windowsDriveTargetPath) && !unlink($windowsDriveTargetPath)) {
            throw new RuntimeException('Unable to reset the rejected Windows-drive target.');
        }

        $windowsDrivePath = runIsolatedPhpTest(
            $setupPath,
            ['C:\\phpthis-setup-rejected.sqlite'],
        );

        if ($windowsDrivePath['exit_code'] === 0 || is_file($windowsDriveTargetPath)) {
            throw new RuntimeException('A Windows drive-letter path must remain relative on POSIX.');
        }
    }

    $first = runIsolatedPhpTest($setupPath, [$databasePath]);
    $second = runIsolatedPhpTest($setupPath, [$databasePath]);
    $expectedOutput = "Example database ready at {$databasePath}\n";
    $defaultExistsAfter = is_file($defaultDatabasePath);
    $defaultHashAfter = $defaultExistsAfter
        ? hash_file('sha256', $defaultDatabasePath)
        : null;

    if (
        $emptyPath['exit_code'] === 0
        || $relativePath['exit_code'] === 0
        || $directoryPath['exit_code'] === 0
        || $controlPath['exit_code'] === 0
        || $oversizedPath['exit_code'] === 0
        || $extraArgument['exit_code'] === 0
        || is_file($relativeTargetPath)
        || is_file($controlSubmittedPath)
        || is_file($extraArgumentTargetPath)
        || $first['exit_code'] !== 0
        || $second['exit_code'] !== 0
        || $first['stdout'] !== $expectedOutput
        || $second['stdout'] !== $expectedOutput
        || $first['stderr'] !== ''
        || $second['stderr'] !== ''
    ) {
        throw new RuntimeException('Expected unsafe paths to fail and explicit setup to run twice.');
    }

    if (
        $defaultExistedBefore !== $defaultExistsAfter
        || $defaultHashBefore !== $defaultHashAfter
    ) {
        throw new RuntimeException('Explicit-path setup tests must not create or modify tmp/example.sqlite.');
    }

    $verification = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(5),
        new QueryTrace(5),
    );
    $columns = $verification->selectAllRows('PRAGMA table_info(documents)');
    $indexColumns = $verification->selectAllRows(
        'PRAGMA index_xinfo(documents_account_rank_key_idx)',
    );
    $seededDocument = $verification->selectOneRow(
        <<<'SQL'
            SELECT
                documents.title,
                documents.category,
                documents.sort_rank
            FROM documents
            WHERE documents.account_id = :account_id
              AND documents.document_key = :document_key
            SQL,
        ['account_id' => 42, 'document_key' => 'Doc_9-z'],
    );
    $counts = $verification->selectOneRow(
        <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM documents) AS document_count,
                (
                    SELECT COUNT(*)
                    FROM documents
                    WHERE documents.account_id = :seed_account_id
                      AND documents.document_key = :seed_document_key
                ) AS seed_document_count,
                (SELECT COUNT(*) FROM account_memberships) AS membership_count,
                (SELECT COUNT(*) FROM account_users) AS account_user_count,
                (SELECT COUNT(*) FROM users) AS user_count,
                (SELECT COUNT(*) FROM user_events) AS event_count
            SQL,
        [
            'seed_account_id' => 42,
            'seed_document_key' => 'Doc_9-z',
        ],
    );
    $indexDefinition = $verification->selectOneRow(
        <<<'SQL'
            SELECT sqlite_master.sql
            FROM sqlite_master
            WHERE sqlite_master.type = :object_type
              AND sqlite_master.name = :object_name
            SQL,
        [
            'object_type' => 'index',
            'object_name' => 'documents_account_rank_key_idx',
        ],
    );
    $columnNames = [];

    foreach ($columns as $column) {
        $name = $column['name'] ?? null;

        if (!is_string($name)) {
            throw new RuntimeException('Document schema returned an invalid column name.');
        }

        $columnNames[] = $name;
    }

    $indexedNames = [];
    $documentKeyCollation = null;

    foreach ($indexColumns as $indexColumn) {
        $name = $indexColumn['name'] ?? null;

        if (!is_string($name)) {
            continue;
        }

        $indexedNames[] = $name;

        if ($name === 'document_key') {
            $documentKeyCollation = $indexColumn['coll'] ?? null;
        }
    }

    if (
        $columnNames !== ['account_id', 'document_key', 'title', 'category', 'sort_rank']
        || $indexedNames !== ['account_id', 'sort_rank', 'document_key']
        || $documentKeyCollation !== 'BINARY'
        || $seededDocument !== [
            'title' => 'Example document',
            'category' => 'general',
            'sort_rank' => 10,
        ]
        || $counts !== [
            'document_count' => 1,
            'seed_document_count' => 1,
            'membership_count' => 1,
            'account_user_count' => 0,
            'user_count' => 2,
            'event_count' => 1,
        ]
        || !is_array($indexDefinition)
        || !is_string($indexDefinition['sql'] ?? null)
        || !str_contains($indexDefinition['sql'], 'document_key COLLATE BINARY')
    ) {
        throw new RuntimeException(
            'Expected fresh schema columns, indexes, and idempotent seed counts.',
        );
    }
};

}
