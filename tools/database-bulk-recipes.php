<?php

declare(strict_types=1);

use BulkWriteRecipe\LedgerBatch;
use BulkWriteRecipe\LedgerEntry;
use BulkWriteRecipe\MySqlLedgerImport;
use BulkWriteRecipe\PostgresLedgerImport;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Database\QueryTrace;
use PHPThis\Verification\EnvironmentAccessProfile;
use PHPThis\Verification\SyntaxProfile;

function proveBulkRecipeInputAndProfile(): void
{
    $root = dirname(__DIR__);
    foreach (['LedgerEntry.php', 'LedgerBatch.php', 'MySqlLedgerImport.php', 'PostgresLedgerImport.php', 'input-tests.php'] as $file) {
        $path = 'tests/fixtures/bulk-write/' . $file;
        $source = file_get_contents($root . '/' . $path);
        if (!is_string($source) || SyntaxProfile::failures($source, $path) !== [] || EnvironmentAccessProfile::inspect($source, $path)['failures'] !== []) {
            throw new RuntimeException('Bulk recipe source failed the structural Strict Profile.');
        }
    }
    $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/tests/fixtures/bulk-write/input-tests.php'], $root, null, 10_000, 16_384, 16_384);
    if ($result['exit_code'] !== 0 || $result['stderr'] !== '' || $result['stdout'] !== "PASS bulk-write exact input and projection boundaries\n") {
        throw new RuntimeException('Bulk recipe input tests failed: ' . $result['stderr']);
    }
    fwrite(STDOUT, $result['stdout']);
}

/**
 * @param 'mysql'|'pgsql' $driver
 * @param array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} $configuration
 */
function proveDatabaseBulkRecipes(string $driver, array $configuration, string $version): void
{
    $expected = $driver === 'mysql' ? '8.4.11' : '17.11';
    if ($version !== $expected) {
        throw new RuntimeException('Bulk recipes require the maintained exact engine version before fixture DDL.');
    }
    $setup = Connection::connect($configuration['dsn'], new QueryBudget(32), new QueryTrace(32), $configuration['username'], $configuration['password']);
    $created = false;
    try {
        $settings = $driver === 'mysql'
            ? $setup->selectOneRow('SELECT @@SESSION.sql_mode AS sql_mode, @@SESSION.transaction_isolation AS isolation_level, @@SESSION.character_set_connection AS character_set')
            : $setup->selectOneRow("SELECT current_setting('transaction_isolation') AS isolation_level, current_setting('server_encoding') AS character_set");
        if ($driver === 'mysql') {
            $mode = $settings['sql_mode'] ?? null;
            requireBulkRecipe(is_string($mode) && (in_array('STRICT_TRANS_TABLES', explode(',', $mode), true) || in_array('STRICT_ALL_TABLES', explode(',', $mode), true)), 'MySQL strict mode is required.');
            requireBulkRecipe(($settings['character_set'] ?? null) === 'utf8mb4', 'MySQL recipe requires utf8mb4.');
            $setup->executeStatement(<<<'SQL'
                CREATE TABLE phpthis_recipe_entries (
                    entry_id BIGINT NOT NULL PRIMARY KEY,
                    quantity BIGINT NOT NULL,
                    amount DECIMAL(30,4) NOT NULL,
                    memo VARCHAR(128) NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
                SQL);
        } else {
            requireBulkRecipe(($settings['character_set'] ?? null) === 'UTF8', 'PostgreSQL recipe requires UTF8.');
            $setup->executeStatement(<<<'SQL'
                CREATE TABLE phpthis_recipe_entries (
                    entry_id bigint NOT NULL PRIMARY KEY,
                    quantity bigint NOT NULL,
                    amount numeric(30,4) NOT NULL,
                    memo varchar(128) NULL
                )
                SQL);
        }
        $created = true;
        $seed = json_encode(range(1, 64), JSON_THROW_ON_ERROR);
        $seedCount = $driver === 'mysql'
            ? $setup->executeStatement(<<<'SQL'
                INSERT INTO phpthis_recipe_entries (entry_id, quantity, amount, memo)
                SELECT left_rows.n * :width + right_rows.n, :quantity, :amount, NULL
                FROM JSON_TABLE(:left_seed, '$[*]' COLUMNS (n INT PATH '$')) AS left_rows
                CROSS JOIN JSON_TABLE(:right_seed, '$[*]' COLUMNS (n INT PATH '$')) AS right_rows
                SQL, ['width' => 64, 'quantity' => '0', 'amount' => '0.0000', 'left_seed' => $seed, 'right_seed' => $seed])
            : $setup->executeStatement(<<<'SQL'
                INSERT INTO phpthis_recipe_entries (entry_id, quantity, amount, memo)
                SELECT CAST(left_rows.n AS bigint) * :width + CAST(right_rows.n AS bigint), :quantity, :amount, NULL
                FROM jsonb_array_elements_text(CAST(:left_seed AS jsonb)) AS left_rows(n)
                CROSS JOIN jsonb_array_elements_text(CAST(:right_seed AS jsonb)) AS right_rows(n)
                SQL, ['width' => 64, 'quantity' => '0', 'amount' => '0.0000', 'left_seed' => $seed, 'right_seed' => $seed]);
        requireBulkRecipe($seedCount === 4_096, 'Representative background fixture is incomplete.');
        if ($driver === 'mysql') {
            $setup->selectAllRows('ANALYZE TABLE phpthis_recipe_entries');
        } else {
            $setup->executeStatement('ANALYZE phpthis_recipe_entries');
        }
        $empty = runBulkRecipe($driver, $configuration, '{"entries":[]}', 0);
        requireBulkRecipe($empty['rows'] === [] && $empty['statements'] === 0, 'Empty import performed database statements.');
        $one = [['entry_id' => '9223372036854775807', 'quantity' => '-9223372036854775808', 'amount' => '-99999999999999999999999999.9999', 'memo' => "Exact 雪 😀; 'quoted'\n"]];
        $oneResult = runBulkRecipe($driver, $configuration, json_encode(['entries' => $one], JSON_THROW_ON_ERROR), 2);
        requireBulkRecipe($oneResult['rows'] === $one && $oneResult['statements'] === 2, 'Single-row exact values or count changed.');
        $maximum = bulkRecipeRows(64, 100_000);
        $maximumJson = json_encode(['entries' => array_reverse($maximum)], JSON_THROW_ON_ERROR);
        $manyResult = runBulkRecipe($driver, $configuration, $maximumJson, 2);
        requireBulkRecipe($manyResult['rows'] === $maximum && $manyResult['statements'] === 2, 'Maximum batch lost exact values or changed statement count.');
        $conflict = json_encode(['entries' => [bulkRecipeRows(1, 200_000)[0], $one[0]]], JSON_THROW_ON_ERROR);
        $failed = runBulkRecipe($driver, $configuration, $conflict, 2, PDOException::class);
        requireBulkRecipe($failed['statements'] === 1 && $failed['failures'] === 1, 'Conflict must fail the insert once.');
        $budgetFailure = runBulkRecipe($driver, $configuration, json_encode(['entries' => bulkRecipeRows(1, 300_000)], JSON_THROW_ON_ERROR), 1, QueryBudgetExceeded::class);
        requireBulkRecipe($budgetFailure['statements'] === 1 && $budgetFailure['failures'] === 0, 'Post-insert budget failure was not exercised.');
        $survivors = $setup->selectOneRow('SELECT COUNT(entry_id) AS row_count FROM phpthis_recipe_entries WHERE entry_id IN (:conflict_id, :rolled_back_id)', ['conflict_id' => '200001', 'rolled_back_id' => '300001']);
        requireBulkRecipe(($survivors['row_count'] ?? null) === 0 || ($survivors['row_count'] ?? null) === '0', 'A failed transaction left a partial insert.');
        // The first of two deliberately separate transactions stays committed if the second conflicts.
        runBulkRecipe($driver, $configuration, json_encode(['entries' => bulkRecipeRows(1, 400_000)], JSON_THROW_ON_ERROR), 2);
        runBulkRecipe($driver, $configuration, json_encode(['entries' => [bulkRecipeRows(1, 500_000)[0], $one[0]]], JSON_THROW_ON_ERROR), 2, PDOException::class);
        $separate = $setup->selectAllRows($driver === 'mysql' ? 'SELECT CAST(entry_id AS CHAR) AS entry_id FROM phpthis_recipe_entries WHERE entry_id IN (:committed_id, :failed_id) ORDER BY entry_id LIMIT :maximum_rows' : 'SELECT CAST(entry_id AS text) AS entry_id FROM phpthis_recipe_entries WHERE entry_id IN (:committed_id, :failed_id) ORDER BY entry_id LIMIT :maximum_rows', ['committed_id' => '400001', 'failed_id' => '500001', 'maximum_rows' => 2]);
        requireBulkRecipe($separate === [['entry_id' => '400001']], 'Separate transaction failure boundary changed.');
        proveBulkEngineCoercions($driver, $configuration);
        proveBulkExistingTransaction($driver, $configuration);
        $plans = [
            'one' => proveBulkRecipePlans($driver, $configuration, LedgerBatch::fromJson(json_encode(['entries' => $one], JSON_THROW_ON_ERROR))),
            'maximum' => proveBulkRecipePlans($driver, $configuration, LedgerBatch::fromJson($maximumJson)),
        ];
        fwrite(STDOUT, 'PASS bulk-write ' . $driver . ' ' . $version . ': ' . json_encode(['rows' => [0, 1, 64], 'statements' => [0, 2, 2], 'background_rows' => 4_096, 'settings' => $settings, 'plans' => $plans, 'exact_values' => true, 'rollback' => true], JSON_THROW_ON_ERROR) . "\n");
    } finally {
        if ($setup->inTransaction()) {
            $setup->rollBack();
        }
        if ($created) {
            $cleanup = Connection::connect($configuration['dsn'], new QueryBudget(1), new QueryTrace(1), $configuration['username'], $configuration['password']);
            $cleanup->executeStatement('DROP TABLE phpthis_recipe_entries');
        }
    }
}

/** @return list<array{entry_id: string, quantity: string, amount: string, memo: ?string}> */
function bulkRecipeRows(int $count, int $base): array
{
    $rows = [];
    for ($index = 1; $index <= $count; ++$index) {
        $rows[] = ['entry_id' => (string) ($base + $index), 'quantity' => $index % 2 === 0 ? '9223372036854775807' : '0', 'amount' => $index % 2 === 0 ? '99999999999999999999999999.9999' : '0.0000', 'memo' => $index % 2 === 0 ? null : "Málaga 雪 😀 '); DELETE FROM users; --"];
    }
    return $rows;
}

/**
 * @param 'mysql'|'pgsql' $driver
 * @param array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} $configuration
 * @param class-string<Throwable>|null $failureClass
 * @return array{rows: list<array{entry_id: string, quantity: string, amount: string, memo: ?string}>, statements: int, failures: int}
 */
function runBulkRecipe(string $driver, array $configuration, string $json, int $limit, ?string $failureClass = null): array
{
    $batch = LedgerBatch::fromJson($json);
    $budget = new QueryBudget(max(1, $limit));
    $trace = new QueryTrace(2);
    $connection = Connection::connect($configuration['dsn'], $budget, $trace, $configuration['username'], $configuration['password']);
    $operation = $driver === 'mysql' ? new MySqlLedgerImport($connection) : new PostgresLedgerImport($connection);
    $entries = [];
    try {
        $entries = $operation->import($batch);
        requireBulkRecipe($failureClass === null, 'Expected import failure did not occur.');
    } catch (Throwable $failure) {
        if ($failureClass === null || !$failure instanceof $failureClass) {
            throw $failure;
        }
    }
    requireBulkRecipe(!$connection->inTransaction(), 'Import left a transaction open.');
    $rows = [];
    foreach ($entries as $entry) {
        $rows[] = $entry->representation();
    }
    $snapshot = $trace->snapshot();
    return ['rows' => $rows, 'statements' => $snapshot['statements'], 'failures' => $snapshot['failures']];
}

function requireBulkRecipe(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param 'mysql'|'pgsql' $driver
 * @param array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} $configuration
 */
function proveBulkEngineCoercions(string $driver, array $configuration): void
{
    $valid = ['entry_id' => '700001', 'quantity' => '9007199254740993', 'amount' => '1.2345', 'memo' => null];
    $missingQuantity = $valid;
    unset($missingQuantity['quantity']);
    $missingMemo = $valid;
    unset($missingMemo['memo']);
    $cases = [
        ['invalid-json', '{', null],
        ['missing-quantity', json_encode([$missingQuantity], JSON_THROW_ON_ERROR), null],
        ['missing-memo', json_encode([$missingMemo], JSON_THROW_ON_ERROR), $driver === 'pgsql' ? $valid : null],
        ['unknown-field', json_encode([$valid + ['ignored' => true]], JSON_THROW_ON_ERROR), $valid],
        ['scale-rounding', json_encode([array_replace($valid, ['amount' => '1.23456'])], JSON_THROW_ON_ERROR), array_replace($valid, ['amount' => '1.2346'])],
        ['numeric-json', '[{"entry_id":700001,"quantity":9007199254740993,"amount":1.2345,"memo":null}]', $valid],
        ['integer-overflow', json_encode([array_replace($valid, ['quantity' => '9223372036854775808'])], JSON_THROW_ON_ERROR), $driver === 'mysql' ? array_replace($valid, ['quantity' => '-9223372036854775808']) : null],
        ['decimal-overflow', json_encode([array_replace($valid, ['amount' => '100000000000000000000000000.0000'])], JSON_THROW_ON_ERROR), null],
        ['integer-junk', json_encode([array_replace($valid, ['quantity' => '12junk'])], JSON_THROW_ON_ERROR), null],
        ['null-quantity', json_encode([array_replace($valid, ['quantity' => null])], JSON_THROW_ON_ERROR), null],
    ];
    foreach ($cases as [$name, $json, $expected]) {
        proveBulkEngineCoercionCase($driver, $configuration, $name, $json, $expected);
    }
}

/**
 * @param 'mysql'|'pgsql' $driver
 * @param array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} $configuration
 * @param array{entry_id: string, quantity: string, amount: string, memo: ?string}|null $expected
 */
function proveBulkEngineCoercionCase(string $driver, array $configuration, string $name, string $json, ?array $expected): void
{
    $connection = Connection::connect($configuration['dsn'], new QueryBudget(2), new QueryTrace(2), $configuration['username'], $configuration['password']);
    $connection->beginTransaction();
    try {
        try {
            $affected = $driver === 'mysql'
                ? $connection->executeStatement(MySqlLedgerImport::INSERT_SQL, ['entries_json' => $json])
                : $connection->executeStatement(PostgresLedgerImport::INSERT_SQL, ['entries_json' => $json]);
        } catch (PDOException $failure) {
            requireBulkRecipe($expected === null, 'Engine rejected a recorded coercion control: ' . $driver . '/' . $name);
            return;
        }
        requireBulkRecipe($expected !== null && $affected === 1, 'Engine accepted a required failure: ' . $driver . '/' . $name);
        $row = $driver === 'mysql'
            ? $connection->selectOneRow(MySqlLedgerImport::SELECT_SQL, ['requested_entries' => '[{"entry_id":"700001"}]', 'row_limit' => 1])
            : $connection->selectOneRow(PostgresLedgerImport::SELECT_SQL, ['requested_entries' => '[{"entry_id":"700001"}]', 'row_limit' => 1]);
        requireBulkRecipe($row === $expected, 'Engine coercion result changed: ' . $driver . '/' . $name);
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}

/**
 * @param 'mysql'|'pgsql' $driver
 * @param array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} $configuration
 * @return array{read_rows: int, index_lookups: int, rows_per_lookup: int, insert_source: string}
 */
function proveBulkRecipePlans(string $driver, array $configuration, LedgerBatch $batch): array
{
    $connection = Connection::connect($configuration['dsn'], new QueryBudget(2), new QueryTrace(2), $configuration['username'], $configuration['password']);
    $parameters = ['requested_entries' => $batch->encodedRows, 'row_limit' => $batch->rowCount];
    if ($driver === 'mysql') {
        $read = $connection->selectOneRow('EXPLAIN ANALYZE ' . MySqlLedgerImport::SELECT_SQL, $parameters);
        $insert = $connection->selectOneRow('EXPLAIN FORMAT=JSON ' . MySqlLedgerImport::INSERT_SQL, ['entries_json' => $batch->encodedRows]);
        $readPlan = $read['EXPLAIN'] ?? null;
        $insertPlan = $insert['EXPLAIN'] ?? null;
    } else {
        $read = $connection->selectOneRow('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) ' . PostgresLedgerImport::SELECT_SQL, $parameters);
        $insert = $connection->selectOneRow('EXPLAIN (FORMAT JSON) ' . PostgresLedgerImport::INSERT_SQL, ['entries_json' => $batch->encodedRows]);
        $readPlan = $read['QUERY PLAN'] ?? null;
        $insertPlan = $insert['QUERY PLAN'] ?? null;
    }
    if (!is_string($readPlan) || !is_string($insertPlan) || strlen($readPlan) > 65_536 || strlen($insertPlan) > 65_536) {
        throw new RuntimeException('Engine plan is unavailable or unexpectedly large.');
    }
    if ($driver === 'mysql') {
        $matches = [];
        if (preg_match('/Single-row index lookup on persisted using PRIMARY[^\n]*actual time=[^)]* rows=([0-9]+) loops=([0-9]+)\)/', $readPlan, $matches) !== 1) {
            throw new RuntimeException('MySQL readback lost its primary-key lookup.');
        }
        requireBulkRecipe($matches[1] === '1' && $matches[2] === (string) $batch->rowCount, 'MySQL examined more than one stored row per input.');
        requireBulkRecipe(!str_contains($readPlan, 'Table scan on persisted') && str_contains($readPlan, 'rows=' . $batch->rowCount . ' loops=1'), 'MySQL readback plan changed its bounded row count.');
        $insertJson = json_decode($insertPlan, true, 16, JSON_THROW_ON_ERROR);
        requireBulkRecipe(is_array($insertJson) && isset($insertJson['query_block']) && is_array($insertJson['query_block']), 'MySQL insert plan is malformed.');
        $block = is_array($insertJson) ? ($insertJson['query_block'] ?? null) : null;
        $from = is_array($block) ? ($block['insert_from'] ?? null) : null;
        $table = is_array($from) ? ($from['table'] ?? null) : null;
        requireBulkRecipe(is_array($table) && ($table['table_function'] ?? null) === 'json_table', 'MySQL insert no longer uses its bounded JSON source.');
        $source = 'json_table';
    } else {
        $nodes = bulkRecipePlanNodes($readPlan);
        $indexed = 0;
        foreach ($nodes as $node) {
            if (($node['Relation Name'] ?? null) === 'phpthis_recipe_entries') {
                requireBulkRecipe(($node['Node Type'] ?? null) === 'Index Scan' && ($node['Index Name'] ?? null) === 'phpthis_recipe_entries_pkey', 'PostgreSQL readback lost its primary-key lookup.');
                requireBulkRecipe(($node['Actual Rows'] ?? null) === 1 && ($node['Actual Loops'] ?? null) === $batch->rowCount, 'PostgreSQL examined more than one stored row per input.');
                ++$indexed;
            }
            requireBulkRecipe(($node['Temp Written Blocks'] ?? 0) === 0, 'PostgreSQL bounded fixture unexpectedly spilled to disk.');
        }
        requireBulkRecipe($indexed === 1 && ($nodes[0]['Actual Rows'] ?? null) === $batch->rowCount, 'PostgreSQL plan lost its bounded result.');
        $insertNodes = bulkRecipePlanNodes($insertPlan);
        requireBulkRecipe(count($insertNodes) === 2 && ($insertNodes[1]['Function Name'] ?? null) === 'jsonb_to_recordset', 'PostgreSQL insert no longer uses its bounded JSON source.');
        $source = 'jsonb_to_recordset';
    }
    return ['read_rows' => $batch->rowCount, 'index_lookups' => $batch->rowCount, 'rows_per_lookup' => 1, 'insert_source' => $source];
}

/** @return non-empty-list<array<array-key, mixed>> */
function bulkRecipePlanNodes(string $json): array
{
    $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    $first = is_array($decoded) ? ($decoded[0] ?? null) : null;
    $plan = is_array($first) ? ($first['Plan'] ?? null) : null;
    if (!is_array($plan)) {
        throw new RuntimeException('PostgreSQL plan is malformed.');
    }
    $pending = [$plan];
    $nodes = [];
    while ($pending !== []) {
        $node = array_shift($pending);
        if (count($nodes) >= 32) {
            throw new RuntimeException('PostgreSQL plan nodes are excessive.');
        }
        $nodes[] = $node;
        $children = $node['Plans'] ?? [];
        if (!is_array($children)) {
            throw new RuntimeException('PostgreSQL plan children are malformed.');
        }
        foreach ($children as $child) {
            if (!is_array($child)) {
                throw new RuntimeException('PostgreSQL plan child is malformed.');
            }
            $pending[] = $child;
        }
    }
    return $nodes;
}

/**
 * @param 'mysql'|'pgsql' $driver
 * @param array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} $configuration
 */
function proveBulkExistingTransaction(string $driver, array $configuration): void
{
    $budget = new QueryBudget(1);
    $connection = Connection::connect($configuration['dsn'], $budget, new QueryTrace(1), $configuration['username'], $configuration['password']);
    $operation = $driver === 'mysql' ? new MySqlLedgerImport($connection) : new PostgresLedgerImport($connection);
    $batch = LedgerBatch::fromJson(json_encode(['entries' => bulkRecipeRows(1, 800_000)], JSON_THROW_ON_ERROR));
    $connection->beginTransaction();
    try {
        try {
            $operation->import($batch);
            throw new RuntimeException('Import accepted a caller-owned transaction.');
        } catch (LogicException) {
            requireBulkRecipe($connection->inTransaction() && $budget->used() === 0, 'Import modified its caller transaction.');
        }
    } finally {
        $connection->rollBack();
    }
}
