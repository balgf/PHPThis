<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';

use PHPThis\Verification\SyntaxProfile;

$root = dirname(__DIR__);
$catalogue = file_get_contents($root . '/docs/strict-profile.md');

if (!is_string($catalogue)) {
    throw new RuntimeException('Unable to read the Strict Profile catalogue.');
}

foreach (['PHT001', 'PHT002', 'PHT003', 'PHT004', 'PHT005', 'PHT006'] as $profileId) {
    requireProfile(str_contains($catalogue, "`{$profileId}`"), "Strict Profile catalogue omitted {$profileId}.");
}

$syntaxFixture = <<<'PHP'
<?php

class OpenClass {}
abstract class AbstractClass {}

for ($index = 0; $index < 1; $index++) {
    $database->selectAllRows('SELECT id FROM users');
}

foreach ($items as $item) {
    $database->selectOneRow('SELECT id FROM users');
}

while ($database->selectOneRow('SELECT id FROM users') !== null) {}

do {
    $database->executeStatement('UPDATE users SET active = 1');
} while (false);

foreach ($items as $item) {
    $callback = static function () use ($database): void {
        $database->selectAllRows('SELECT id FROM users');
    };
}

final class ObscuredMagic
{
    public function /* comment */ __isset(string $name): bool { return $name !== ''; }
    public function &__get(string $name): mixed { return $name; }
}
PHP;

requireParseable($syntaxFixture);
$syntaxFailures = SyntaxProfile::failures($syntaxFixture, 'fixture.php');
$expectedSyntaxFailures = [
    'PHT002 fixture.php:3 named class OpenClass must be final.',
    'PHT002 fixture.php:4 named class AbstractClass must be final.',
    'PHT003 fixture.php:7 calls a database method inside a loop.',
    'PHT003 fixture.php:11 calls a database method inside a loop.',
    'PHT003 fixture.php:14 calls a database method inside a loop.',
    'PHT003 fixture.php:17 calls a database method inside a loop.',
    'PHT003 fixture.php:22 calls a database method inside a loop.',
    'fixture.php:28 defines forbidden magic method __isset.',
    'fixture.php:29 defines forbidden magic method __get.',
];

requireProfile($syntaxFailures === $expectedSyntaxFailures, 'Syntax-profile fixture diagnostics changed.');

$alternativeLoopSyntaxFixture = <<<'PHP'
<?php

for ($index = 0; $index < 1; $index++):
    $noop = $index;
    $database->selectAllRows('SELECT id FROM users');
endfor;

foreach ($items as $item):
    $noop = $item;
    $database->selectOneRow('SELECT id FROM users');
endforeach;

while ($ready):
    $ready = false;
    $database->executeStatement('UPDATE users SET active = 1');
endwhile;

foreach ($items as $item):
    $callback = static function () use ($database): void {
        $database->selectAllRows('SELECT id FROM users');
    };
endforeach;

foreach ($groups as $group):
    for ($index = 0; $index < 1; $index++):
        $noop = $group;
        $database->selectOneRow('SELECT id FROM users');
    endfor;
    $database->executeStatement('UPDATE users SET active = 1');
endforeach;

foreach ($groups as $group):
    foreach ($group as $item):
        $database->selectAllRows('SELECT id FROM users');
    endforeach;
    $database->executeStatement('UPDATE users SET active = 1');
endforeach;

$database->selectOneRow('SELECT id FROM users');
PHP;

requireParseable($alternativeLoopSyntaxFixture);
$alternativeLoopSyntaxFailures = SyntaxProfile::failures($alternativeLoopSyntaxFixture, 'alternative-loop.php');
$expectedAlternativeLoopSyntaxFailures = [
    'PHT003 alternative-loop.php:5 calls a database method inside a loop.',
    'PHT003 alternative-loop.php:10 calls a database method inside a loop.',
    'PHT003 alternative-loop.php:15 calls a database method inside a loop.',
    'PHT003 alternative-loop.php:20 calls a database method inside a loop.',
    'PHT003 alternative-loop.php:27 calls a database method inside a loop.',
    'PHT003 alternative-loop.php:29 calls a database method inside a loop.',
    'PHT003 alternative-loop.php:34 calls a database method inside a loop.',
    'PHT003 alternative-loop.php:36 calls a database method inside a loop.',
];

requireProfile(
    $alternativeLoopSyntaxFailures === $expectedAlternativeLoopSyntaxFailures,
    'PHT003 alternative-loop fixture diagnostics changed.',
);

$singleStatementLoopSyntaxFixture = <<<'PHP'
<?php

for ($index = 0; $index < 1; $index++) $database->selectAllRows('SELECT id FROM users');
foreach ($items as $item) $database->selectOneRow('SELECT id FROM users');
while ($ready) $database->executeStatement('UPDATE users SET active = 1');
do $database->selectAllRows('SELECT id FROM users'); while (false);

foreach ($items as $item)
    $selected = $item ? $item : null;

$database->selectOneRow('SELECT id FROM users');
PHP;

requireParseable($singleStatementLoopSyntaxFixture);
$singleStatementLoopSyntaxFailures = SyntaxProfile::failures(
    $singleStatementLoopSyntaxFixture,
    'single-statement-loop.php',
);
$expectedSingleStatementLoopSyntaxFailures = [
    'PHT003 single-statement-loop.php:3 calls a database method inside a loop.',
    'PHT003 single-statement-loop.php:4 calls a database method inside a loop.',
    'PHT003 single-statement-loop.php:5 calls a database method inside a loop.',
    'PHT003 single-statement-loop.php:6 calls a database method inside a loop.',
];

requireProfile(
    $singleStatementLoopSyntaxFailures === $expectedSingleStatementLoopSyntaxFailures,
    'PHT003 single-statement-loop fixture diagnostics changed.',
);

$loopBoundaryFixture = <<<'PHP'
<?php

for ($index = 0; $database->SELECTONEROW('SELECT id FROM users') !== null; $index++) {
    $database->selectAllRows('SELECT id FROM users');
}
foreach ($database->selectallrows('SELECT id FROM users') as $row)
    $database->EXECUTESTATEMENT('UPDATE users SET active = 1');
do
    $database?-> /* comment */ SelectAllRows('SELECT id FROM users');
while ($database->selectOneRow('SELECT id FROM users') !== null);

$database->EXECUTESTATEMENT('UPDATE users SET active = 1');
PHP;

requireParseable($loopBoundaryFixture);
$loopBoundaryFailures = SyntaxProfile::failures($loopBoundaryFixture, 'loop-boundaries.php');
$expectedLoopBoundaryFailures = [
    'PHT003 loop-boundaries.php:3 calls a database method inside a loop.',
    'PHT003 loop-boundaries.php:4 calls a database method inside a loop.',
    'PHT003 loop-boundaries.php:6 calls a database method inside a loop.',
    'PHT003 loop-boundaries.php:7 calls a database method inside a loop.',
    'PHT003 loop-boundaries.php:9 calls a database method inside a loop.',
    'PHT003 loop-boundaries.php:10 calls a database method inside a loop.',
];

requireProfile(
    $loopBoundaryFailures === $expectedLoopBoundaryFailures,
    'PHT003 loop-boundary fixture diagnostics changed.',
);

$compoundLoopBodyFixture = <<<'PHP'
<?php

foreach ($items as $item)
    if ($enabled) {
        $noop = $item;
    } else {
        $database->selectOneRow('SELECT id FROM users');
    }

foreach ($items as $item)
    try {
        $noop = $item;
    } catch (RuntimeException) {
        $database->executeStatement('UPDATE users SET active = 1');
    } finally {
        $database->selectAllRows('SELECT id FROM users');
    }

foreach ($items as $item)
    $selected = (static function () use ($item): bool {
        return $item !== null;
    })()
        ? $database->SELECTONEROW('SELECT id FROM users')
        : null;

foreach ($items as $item)
    if ($item === null)
        $noop = null;
    elseif ($enabled)
        $database->selectAllRows('SELECT id FROM users');
    else
        $database->executeStatement('UPDATE users SET active = 1');

$database->selectOneRow('SELECT id FROM users');
PHP;

requireParseable($compoundLoopBodyFixture);
$compoundLoopBodyFailures = SyntaxProfile::failures($compoundLoopBodyFixture, 'compound-loop.php');
$expectedCompoundLoopBodyFailures = [
    'PHT003 compound-loop.php:7 calls a database method inside a loop.',
    'PHT003 compound-loop.php:14 calls a database method inside a loop.',
    'PHT003 compound-loop.php:16 calls a database method inside a loop.',
    'PHT003 compound-loop.php:23 calls a database method inside a loop.',
    'PHT003 compound-loop.php:30 calls a database method inside a loop.',
    'PHT003 compound-loop.php:32 calls a database method inside a loop.',
];

requireProfile(
    $compoundLoopBodyFailures === $expectedCompoundLoopBodyFailures,
    'PHT003 compound-loop fixture diagnostics changed.',
);

$loopDelimiterFixture = <<<'PHP'
<?php

foreach ($items as $item) {
    $label = "{$item}";
    $database->selectOneRow('SELECT id FROM users');
}

foreach ($items as $item)
    $value = new #[Example] class {};

$database->selectAllRows('SELECT id FROM users');

foreach ($items as $item) checkpoint:

$database->executeStatement('UPDATE users SET active = 1');

$outsideCallback = static function () use ($database): void {
    $database->selectOneRow('SELECT id FROM users');
};
PHP;

requireParseable($loopDelimiterFixture);
$loopDelimiterFailures = SyntaxProfile::failures($loopDelimiterFixture, 'loop-delimiters.php');
$expectedLoopDelimiterFailures = [
    'PHT003 loop-delimiters.php:5 calls a database method inside a loop.',
];

requireProfile(
    $loopDelimiterFailures === $expectedLoopDelimiterFailures,
    'PHT003 loop-delimiter fixture diagnostics changed.',
);

$validSyntaxFixture = <<<'PHP'
<?php

final class ClosedClass {}
final readonly class ImmutableValue {}
$constructed = new class { public function /* comment */ __construct() {} };
$anonymous = new class {};
$attributedAnonymous = new #[Example] class {};
interface Contract {}
trait Behavior {}
enum Status { case Ready; }
$comment = 'class Fake { $database->selectAllRows(); }';
// class Commented { $database->selectOneRow(); }
PHP;

requireParseable($validSyntaxFixture);
requireProfile(
    SyntaxProfile::failures($validSyntaxFixture, 'valid.php') === [],
    'Shared syntax guard rejected valid syntax or text inside a string.',
);

$fixtureDirectory = $root . '/tmp/strict-profile-tests';

if (!is_dir($fixtureDirectory) && !mkdir($fixtureDirectory, 0777, true) && !is_dir($fixtureDirectory)) {
    throw new RuntimeException('Unable to create strict-profile fixture directory.');
}

$invalidPath = $fixtureDirectory . '/pht001-invalid.php';
$validPath = $fixtureDirectory . '/pht001-valid.php';
$invalidPdoPath = $fixtureDirectory . '/pht005-invalid.php';
$validPdoPath = $fixtureDirectory . '/pht005-valid.php';
$invalidSqlPath = $fixtureDirectory . '/pht006-invalid.php';
$validSqlPath = $fixtureDirectory . '/pht006-valid.php';
$invalidSource = <<<'PHP'
<?php

declare(strict_types=1);

function invalidCoercions(mixed $value): void
{
    (int) $value;
    (float) $value;
    (string) $value;
    (bool) $value;
    intval($value);
    floatval($value);
    doubleval($value);
    strval($value);
    boolval($value);
    settype($value, 'int');
}

/**
 * @template T
 * @param T $value
 */
function invalidTemplateCoercion(mixed $value): int
{
    return (int) $value;
}
PHP;
$validSource = <<<'PHP'
<?php

declare(strict_types=1);

function knownCoercion(int|float $value): int
{
    return (int) $value;
}

function narrowedCoercion(mixed $value): int
{
    if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
        throw new UnexpectedValueException();
    }

    return (int) $value;
}

function knownFunctionCoercion(string $value): int
{
    return intval($value);
}
PHP;
$invalidPdoSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace ProfileFixture;

use PDO;
use PDO as Driver;

final class PdoSubclass extends \PDO
{
}

final class DirectPdoFactories
{
    public function imported(): PDO
    {
        return new ImportedPdoTarget('sqlite::memory:');
    }

    public function aliased(): Driver
    {
        return new Driver('sqlite::memory:');
    }

    public function fullyQualified(): \PDO
    {
        return new \PDO('sqlite::memory:');
    }

    public function dynamicClassString(): \PDO
    {
        $class = \PDO::class;

        return new $class('sqlite::memory:');
    }

    public function namedSubclass(): PdoSubclass
    {
        return new PdoSubclass('sqlite::memory:');
    }

    public function anonymousSubclass(): \PDO
    {
        return new class('sqlite::memory:') extends \PDO {};
    }
}
PHP;
$invalidPdoSource = str_replace('ImportedPdoTarget', 'PDO', $invalidPdoSource);
$validPdoSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace ProfileFixtureValid;

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

final class LocalPdoTarget
{
}

final class AcceptedConnectionFactories
{
    public function localClass(): LocalPdoTarget
    {
        return new LocalPdoTarget();
    }

    public function frameworkConnection(): Connection
    {
        return Connection::connect(
            'sqlite::memory:',
            new QueryBudget(1),
            new QueryTrace(1),
        );
    }
}
PHP;
$validPdoSource = str_replace('LocalPdoTarget', 'PDO', $validPdoSource);
$invalidSqlSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace ProfileSqlInvalid;

use PHPThis\Database\Connection;

final class SimilarApi
{
    public function selectAllRows(string $sql): void
    {
    }

    public function selectOneRow(string $sql): void
    {
    }
}

final class UnsafeSql
{
    public function run(
        Connection $connection,
        ?Connection $nullableConnection,
        string $sql,
        string $column,
        string $method,
        bool $empty,
    ): void {
        $connection->selectAllRows($sql);
        $connection->selectOneRow("SELECT {$column} FROM users");
        $connection->executeStatement('DELETE FROM users ORDER BY ' . $column);
        $connection->selectAllRows('   ');

        $maybeEmpty = $empty ? '' : 'SELECT id FROM users';
        $connection->selectOneRow($maybeEmpty);

        /** @var 'SELECT id FROM users' $claimedSql */
        $claimedSql = $sql;
        $connection->selectAllRows($claimedSql);
        $connection->executeStatement(parameters: [], sql: $sql);
        $nullableConnection?->selectAllRows($sql);

        /** @var SimilarApi $maskedConnection */
        $maskedConnection = $connection;
        $maskedConnection->selectAllRows($sql);
        $connection->executeStatement($this->sanitize($sql));
        $connection->SELECTALLROWS($sql);

        $arguments = [$sql];
        $connection->selectAllRows(...$arguments);
        $firstClass = $connection->selectOneRow(...);
        $callableArray = [$connection, 'executeStatement'];
        $dynamicCallableArray = [$connection, $method];
        $reversedCallableArray = [1 => 'selectAllRows', 0 => $connection];
        $numericStringCallableArray = ['0' => $connection, '1' => 'selectOneRow'];
        $computedKeyCallableArray = [(0 + 0) => $connection, (1 + 0) => 'executeStatement'];
        $unpackedCallableArray = [...[$connection], ...['selectAllRows']];
    }

    public function runUnion(Connection|SimilarApi $receiver, string $sql): void
    {
        $receiver->selectOneRow($sql);
    }

    private function sanitize(string $sql): string
    {
        return trim($sql);
    }
}
PHP;
$validSqlSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace ProfileSqlValid;

use PHPThis\Database\Connection;

final class SimilarApi
{
    public function selectAllRows(string $sql): void
    {
    }
}

final class SafeSql
{
    private const SELECT_BY_ID = 'SELECT id FROM users WHERE id = :id';

    public function run(
        Connection $connection,
        ?Connection $nullableConnection,
        SimilarApi $similar,
        string $order,
        string $unrelatedSql,
    ): void {
        $connection->selectAllRows('SELECT id FROM users');
        $nullableConnection?->selectAllRows('SELECT id FROM users');
        $connection->selectOneRow(self::SELECT_BY_ID, ['id' => 7]);

        $insert = <<<'SQL'
            INSERT INTO users (id, name) VALUES (:id, :name)
            SQL;
        $connection->executeStatement($insert, ['id' => 7, 'name' => 'Ada']);

        $ordered = match ($order) {
            'oldest' => 'SELECT id FROM users ORDER BY id ASC',
            'newest' => 'SELECT id FROM users ORDER BY id DESC',
            default => throw new \InvalidArgumentException('Unknown order.'),
        };
        $connection->selectAllRows($ordered);
        $connection->selectOneRow(parameters: ['id' => 7], sql: 'SELECT id FROM users WHERE id = :id');
        $similar->selectAllRows($unrelatedSql);
        $otherConnectionMethod = [$connection, 'beginTransaction'];
        $reversedOtherConnectionMethod = [1 => 'beginTransaction', 0 => $connection];
        $definitelyNotCallable = [$connection, 7];
        $unrelatedCallable = [$similar, 'selectAllRows'];
        $unpackedOtherConnectionMethod = [...[$connection], ...['beginTransaction']];
    }
}
PHP;

writeFixture($invalidPath, $invalidSource);
writeFixture($validPath, $validSource);
writeFixture($invalidPdoPath, $invalidPdoSource);
writeFixture($validPdoPath, $validPdoSource);
writeFixture($invalidSqlPath, $invalidSqlSource);
writeFixture($validSqlPath, $validSqlSource);

$invalidResult = runProfileAnalysis($root, $invalidPath);
requireProfile($invalidResult['exit_code'] === 1, 'PHT001 invalid fixture unexpectedly passed.');

$profileLines = profileDiagnosticLines($invalidResult, $invalidPath, 'phpthis.pht001', 'PHT001');

requireProfile(
    $profileLines === [...range(7, 16), 25],
    'PHT001 did not reject every mixed cast, conversion function, and template-mixed conversion.',
);

$validResult = runProfileAnalysis($root, $validPath);
requireProfile(
    $validResult['exit_code'] === 0,
    "PHT001 rejected validated or known-type conversions.\n{$validResult['stderr']}\n{$validResult['stdout']}",
);

$invalidPdoResult = runProfileAnalysis($root, $invalidPdoPath);
requireProfile($invalidPdoResult['exit_code'] === 1, 'PHT005 invalid fixture unexpectedly passed.');
requireProfile(
    profileDiagnosticLines($invalidPdoResult, $invalidPdoPath, 'phpthis.pht005', 'PHT005')
        === [18, 23, 28, 35, 40, 45],
    'PHT005 did not reject direct PDO and PDO-subclass construction forms.',
);

$validPdoResult = runProfileAnalysis($root, $validPdoPath);
requireProfile(
    $validPdoResult['exit_code'] === 0,
    "PHT005 rejected the canonical connection factory or an unrelated namespaced PDO class.\n"
        . $validPdoResult['stderr']
        . $validPdoResult['stdout'],
);

$invalidSqlResult = runProfileAnalysis($root, $invalidSqlPath);
requireProfile($invalidSqlResult['exit_code'] === 1, 'PHT006 invalid fixture unexpectedly passed.');
requireProfile(
    profileDiagnosticLines($invalidSqlResult, $invalidSqlPath, 'phpthis.pht006', 'PHT006')
        === [30, 31, 32, 33, 36, 40, 41, 42, 47, 48, 51, 52, 53, 54, 55, 56, 57, 58, 63],
    'PHT006 did not reject dynamic, blank, annotation-narrowed, unpacked, or indirect Connection SQL.',
);
requireProfile(
    profileDiagnosticLines(
        $invalidSqlResult,
        $invalidSqlPath,
        'varTag.nativeType',
        'receiver type masking',
        false,
    ) === [45],
    'PHPStan did not reject a PHPDoc annotation that masks a native Connection receiver.',
);

$validSqlResult = runProfileAnalysis($root, $validSqlPath);
requireProfile(
    $validSqlResult['exit_code'] === 0,
    "PHT006 rejected constant SQL, finite statement selection, or an unrelated API.\n"
        . $validSqlResult['stderr']
        . $validSqlResult['stdout'],
);

fwrite(STDOUT, "PASS strict profile: PHT001 through PHT006\n");

function requireProfile(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function requireParseable(string $contents): void
{
    if (token_get_all($contents, TOKEN_PARSE) === []) {
        throw new RuntimeException('Strict-profile fixture did not contain any PHP tokens.');
    }
}

function writeFixture(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) !== strlen($contents)) {
        throw new RuntimeException("Unable to write {$path}.");
    }
}

/**
 * @param array{exit_code: int, stdout: string, stderr: string} $result
 * @return list<int>
 */
function profileDiagnosticLines(
    array $result,
    string $path,
    string $identifier,
    string $profileId,
    bool $mustBeNonIgnorable = true,
): array
{
    $jsonOffset = strpos($result['stdout'], '{"totals":');

    if (!is_int($jsonOffset)) {
        throw new RuntimeException("{$profileId} output omitted its JSON result.");
    }

    $decoded = json_decode(substr($result['stdout'], $jsonOffset), true, 512, JSON_THROW_ON_ERROR);
    $files = is_array($decoded) ? ($decoded['files'] ?? null) : null;
    $fileResult = is_array($files) ? ($files[$path] ?? null) : null;
    $messages = is_array($fileResult) ? ($fileResult['messages'] ?? null) : null;

    if (!is_array($messages)) {
        throw new RuntimeException("{$profileId} JSON omitted the invalid fixture messages.");
    }

    $lines = [];

    foreach ($messages as $message) {
        if (!is_array($message) || ($message['identifier'] ?? null) !== $identifier) {
            continue;
        }

        if ($mustBeNonIgnorable) {
            requireProfile(($message['ignorable'] ?? null) === false, "{$profileId} must not be ignorable.");
        }
        $line = $message['line'] ?? null;

        if (!is_int($line)) {
            throw new RuntimeException("{$profileId} diagnostic omitted its source line.");
        }

        $lines[] = $line;
    }

    return $lines;
}

/** @return array{exit_code: int, stdout: string, stderr: string} */
function runProfileAnalysis(string $root, string $path): array
{
    $process = proc_open(
        [
            PHP_BINARY,
            $root . '/vendor/bin/phpstan',
            'analyse',
            '--configuration=' . $root . '/phpstan.neon',
            '--no-progress',
            '--debug',
            '--error-format=json',
            $path,
        ],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PHPStan for strict-profile tests.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Unable to read PHPStan strict-profile output.');
    }

    return ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
}
