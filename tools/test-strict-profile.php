<?php

declare(strict_types=1);

require_once __DIR__ . '/strict-profile.php';

$root = dirname(__DIR__);
$catalogue = file_get_contents($root . '/docs/strict-profile.md');

if (!is_string($catalogue)) {
    throw new RuntimeException('Unable to read the Strict Profile catalogue.');
}

foreach (['PHT001', 'PHT002', 'PHT003'] as $profileId) {
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
PHP;

$syntaxFailures = PHPThisStrictProfile::syntaxFailures($syntaxFixture, 'fixture.php');
$expectedSyntaxFailures = [
    'PHT002 fixture.php:3 named class OpenClass must be final.',
    'PHT002 fixture.php:4 named class AbstractClass must be final.',
    'PHT003 fixture.php:7 calls a database method inside a loop.',
    'PHT003 fixture.php:11 calls a database method inside a loop.',
    'PHT003 fixture.php:14 calls a database method inside a loop.',
    'PHT003 fixture.php:17 calls a database method inside a loop.',
    'PHT003 fixture.php:22 calls a database method inside a loop.',
];

requireProfile($syntaxFailures === $expectedSyntaxFailures, 'PHT002/PHT003 fixture diagnostics changed.');

$validSyntaxFixture = <<<'PHP'
<?php

final class ClosedClass {}
final readonly class ImmutableValue {}
$anonymous = new class {};
$attributedAnonymous = new #[Example] class {};
interface Contract {}
trait Behavior {}
enum Status { case Ready; }
$comment = 'class Fake { $database->selectAllRows(); }';
// class Commented { $database->selectOneRow(); }
PHP;

requireProfile(
    PHPThisStrictProfile::syntaxFailures($validSyntaxFixture, 'valid.php') === [],
    'PHT002/PHT003 rejected valid syntax or text inside a string.',
);

$fixtureDirectory = $root . '/tmp/strict-profile-tests';

if (!is_dir($fixtureDirectory) && !mkdir($fixtureDirectory, 0777, true) && !is_dir($fixtureDirectory)) {
    throw new RuntimeException('Unable to create strict-profile fixture directory.');
}

$invalidPath = $fixtureDirectory . '/pht001-invalid.php';
$validPath = $fixtureDirectory . '/pht001-valid.php';
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

writeFixture($invalidPath, $invalidSource);
writeFixture($validPath, $validSource);

$invalidResult = runProfileAnalysis($root, $invalidPath);
requireProfile($invalidResult['exit_code'] === 1, 'PHT001 invalid fixture unexpectedly passed.');

$jsonOffset = strpos($invalidResult['stdout'], '{"totals":');

if (!is_int($jsonOffset)) {
    throw new RuntimeException('PHT001 output omitted its JSON result.');
}

$decoded = json_decode(substr($invalidResult['stdout'], $jsonOffset), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($decoded)) {
    throw new RuntimeException('PHT001 did not return a JSON object.');
}

$files = $decoded['files'] ?? null;

if (!is_array($files)) {
    throw new RuntimeException('PHT001 JSON omitted files.');
}

$fileResult = $files[$invalidPath] ?? null;

if (!is_array($fileResult)) {
    throw new RuntimeException('PHT001 JSON omitted the invalid fixture.');
}

$messages = $fileResult['messages'] ?? null;

if (!is_array($messages)) {
    throw new RuntimeException('PHT001 JSON omitted fixture messages.');
}

$profileLines = [];

foreach ($messages as $message) {
    if (!is_array($message) || ($message['identifier'] ?? null) !== 'phpthis.pht001') {
        continue;
    }

    requireProfile(($message['ignorable'] ?? null) === false, 'PHT001 must not be ignorable.');
    $line = $message['line'] ?? null;
    requireProfile(is_int($line), 'PHT001 diagnostic omitted its source line.');
    $profileLines[] = $line;
}

requireProfile(
    $profileLines === [...range(7, 16), 25],
    'PHT001 did not reject every mixed cast, conversion function, and template-mixed conversion.',
);

$validResult = runProfileAnalysis($root, $validPath);
requireProfile(
    $validResult['exit_code'] === 0,
    "PHT001 rejected validated or known-type conversions.\n{$validResult['stderr']}\n{$validResult['stdout']}",
);

fwrite(STDOUT, "PASS strict profile: PHT001, PHT002, and PHT003\n");

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
