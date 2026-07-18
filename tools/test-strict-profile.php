<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';

use PHPThis\Verification\SyntaxProfile;

$root = dirname(__DIR__);
$catalogue = file_get_contents($root . '/docs/strict-profile.md');

if (!is_string($catalogue)) {
    throw new RuntimeException('Unable to read the Strict Profile catalogue.');
}

foreach (['PHT001', 'PHT002', 'PHT003', 'PHT004', 'PHT005'] as $profileId) {
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

writeFixture($invalidPath, $invalidSource);
writeFixture($validPath, $validSource);
writeFixture($invalidPdoPath, $invalidPdoSource);
writeFixture($validPdoPath, $validPdoSource);

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

fwrite(STDOUT, "PASS strict profile: PHT001, PHT002, PHT003, PHT004, and PHT005\n");

function requireProfile(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
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
function profileDiagnosticLines(array $result, string $path, string $identifier, string $profileId): array
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

        requireProfile(($message['ignorable'] ?? null) === false, "{$profileId} must not be ignorable.");
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
