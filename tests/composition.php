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
    $parentDirectory = __DIR__ . '/../tmp/application-tests';

    if (
        !is_dir($parentDirectory)
        && !mkdir($parentDirectory, 0777, true)
        && !is_dir($parentDirectory)
    ) {
        throw new RuntimeException('Unable to create the setup migration test directory.');
    }

    $resolvedParentDirectory = realpath($parentDirectory);

    if (!is_string($resolvedParentDirectory)) {
        throw new RuntimeException('Unable to resolve the setup migration test directory.');
    }

    $directory = $resolvedParentDirectory . '/setup-example-' . bin2hex(random_bytes(8));

    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Unable to create the isolated setup migration test directory.');
    }

    $resolvedDirectory = realpath($directory);

    if (!is_string($resolvedDirectory)) {
        throw new RuntimeException('Unable to resolve the isolated setup migration test directory.');
    }

    $databasePath = $resolvedDirectory . '/setup-example-fresh.sqlite';
    $documentFileDirectory = $databasePath . '.files';

    $setupPath = __DIR__ . '/../tools/setup-example.php';
    $defaultDatabasePath = dirname(__DIR__) . '/tmp/example.sqlite';
    $defaultExistedBefore = is_file($defaultDatabasePath);
    $defaultHashBefore = $defaultExistedBefore
        ? hash_file('sha256', $defaultDatabasePath)
        : null;

    if ($defaultExistedBefore && !is_string($defaultHashBefore)) {
        throw new RuntimeException('Unable to fingerprint the default example database before setup tests.');
    }

    $relativeSubmittedPath = 'tmp/application-tests/'
        . basename($resolvedDirectory)
        . '/setup-example-relative-rejected.sqlite';
    $relativeTargetPath = dirname(__DIR__) . '/' . $relativeSubmittedPath;
    $controlSubmittedPath = $resolvedDirectory . "/setup-example-\n-rejected.sqlite";
    $extraArgumentTargetPath = $resolvedDirectory . '/setup-example-extra-argv-rejected.sqlite';
    $relativeDocumentFileTarget = $relativeTargetPath . '.files';
    $controlDocumentFileTarget = $controlSubmittedPath . '.files';
    $extraArgumentDocumentFileTarget = $extraArgumentTargetPath . '.files';
    $directoryDocumentFileTarget = $resolvedDirectory . DIRECTORY_SEPARATOR . '.files';
    $windowsDriveSubmittedPath = 'C:\\phpthis-setup-'
        . basename($resolvedDirectory)
        . '-rejected.sqlite';
    $windowsDriveTargetPath = dirname(__DIR__) . '/' . $windowsDriveSubmittedPath;
    $windowsDriveDocumentFileTarget = $windowsDriveTargetPath . '.files';

    try {
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
        $windowsDrivePath = runIsolatedPhpTest(
            $setupPath,
            [$windowsDriveSubmittedPath],
        );

        if (
            $windowsDrivePath['exit_code'] === 0
            || is_file($windowsDriveTargetPath)
            || file_exists($windowsDriveDocumentFileTarget)
            || is_link($windowsDriveDocumentFileTarget)
        ) {
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
        || file_exists($relativeDocumentFileTarget)
        || is_link($relativeDocumentFileTarget)
        || file_exists($controlDocumentFileTarget)
        || is_link($controlDocumentFileTarget)
        || file_exists($extraArgumentDocumentFileTarget)
        || is_link($extraArgumentDocumentFileTarget)
        || file_exists($directoryDocumentFileTarget)
        || is_link($directoryDocumentFileTarget)
        || $first['exit_code'] !== 0
        || $second['exit_code'] !== 0
        || $first['stdout'] !== $expectedOutput
        || $second['stdout'] !== $expectedOutput
        || $first['stderr'] !== ''
        || $second['stderr'] !== ''
    ) {
        throw new RuntimeException('Expected unsafe paths to fail and explicit setup to run twice.');
    }

    $documentFileDirectoryMetadata = lstat($documentFileDirectory);

    if (
        !is_array($documentFileDirectoryMetadata)
        || ($documentFileDirectoryMetadata['mode'] & 0170000) !== 0040000
        || ($documentFileDirectoryMetadata['mode'] & 0777) !== 0700
    ) {
        throw new RuntimeException('Expected setup to provision one private document file directory.');
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
    } finally {
        foreach ([$windowsDriveTargetPath, $windowsDriveDocumentFileTarget] as $unexpectedPath) {
            if (is_link($unexpectedPath) || is_file($unexpectedPath)) {
                if (!unlink($unexpectedPath)) {
                    throw new RuntimeException('Unable to remove an unexpected setup test artifact.');
                }
            } elseif (is_dir($unexpectedPath)) {
                removeCompositionTestDirectory($unexpectedPath);
            }
        }

        removeCompositionTestDirectory($resolvedDirectory);
    }
};

}

function removeCompositionTestDirectory(string $directory): void
{
    if (is_link($directory)) {
        if (!unlink($directory)) {
            throw new RuntimeException('Unable to remove a composition test symlink.');
        }

        return;
    }

    if (!is_dir($directory)) {
        return;
    }

    $entries = scandir($directory);

    if (!is_array($entries)) {
        throw new RuntimeException('Unable to inspect a composition test directory.');
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;

        if (is_link($path)) {
            if (!unlink($path)) {
                throw new RuntimeException('Unable to remove a composition test symlink.');
            }
        } elseif (is_dir($path)) {
            removeCompositionTestDirectory($path);
        } elseif (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove a composition test artifact.');
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException('Unable to remove a composition test directory.');
    }
}
