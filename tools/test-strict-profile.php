<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';
require_once dirname(__DIR__) . '/verification/EnvironmentAccessProfile.php';
require_once __DIR__ . '/process-support.php';

use PHPThis\Verification\EnvironmentAccessProfile;
use PHPThis\Verification\SyntaxProfile;

$root = dirname(__DIR__);
$catalogue = file_get_contents($root . '/docs/strict-profile.md');

if (!is_string($catalogue)) {
    throw new RuntimeException('Unable to read the Strict Profile catalogue.');
}

foreach (['PHT001', 'PHT002', 'PHT003', 'PHT004', 'PHT005', 'PHT006', 'PHT007', 'PHT008'] as $profileId) {
    requireProfile(str_contains($catalogue, "`{$profileId}`"), "Strict Profile catalogue omitted {$profileId}.");
}

$validEnvironmentFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture;

$dsn = \getenv('APP_RUNTIME_DATABASE_DSN');
$username = \getenv("APP_RUNTIME_DATABASE_USERNAME");
$expectedFunctionName = 'getenv';
$instance = new getenv();
$inputInstance = new INPUT_ENV();
$namespacedFunctionName = "get\env";
$namespacedFunctionName();

#[getenv()]
final class EnvironmentNamedAttribute
{
}

#[INPUT_ENV()]
final class EnvironmentInputAttribute
{
}

$callbackNamedConstructor = new array_map('getenv');
$dynamicClassInstance = new ('getenv')();

#[array_map('getenv')]
final class CallbackNamedAttribute
{
}

\array_filter(...[['getenv']]);
\array_reduce(...[[], static fn (mixed $carry, mixed $item): mixed => $carry, 'getenv']);
\array_reduce(
    array: [],
    initial: 'getenv',
    callback: static fn (mixed $carry, mixed $item): mixed => $carry,
);
\App\array_map('getenv', []);
\App\constant('INPUT_ENV');
Labels::fromCallable('getenv');

function namesOnly(object $object): void
{
    $reader = 'getenv';
    $object->$reader();
    Labels::$reader();
    new $reader();
}

function reassignBeforeInvocation(bool $replace): void
{
    $reader = 'getenv';

    if ($replace) {
        $reader = 'strlen';
    }

    $reader('APP_KEY');
}
PHP;

requireParseable($validEnvironmentFixture);
$validEnvironmentResult = EnvironmentAccessProfile::inspect(
    $validEnvironmentFixture,
    'src/ApplicationEnvironment.php',
);
requireProfile(
    $validEnvironmentResult === [
        'reads' => [7, 8],
        'keys' => ['APP_RUNTIME_DATABASE_DSN', 'APP_RUNTIME_DATABASE_USERNAME'],
        'failures' => [],
    ],
    'PHT007 rejected canonical literal reads in one application configuration boundary.',
);

$groupedAttributeFixture = <<<'PHP'
<?php

declare(strict_types=1);

#[Marker, INPUT_ENV]
#[Marker, getenv()]
#[Marker, array_map('getenv')]
final class GroupedEnvironmentNames
{
}
PHP;

requireParseable($groupedAttributeFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $groupedAttributeFixture,
        'src/GroupedEnvironmentNames.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 treated names, calls, or literal arguments inside a grouped PHP attribute declaration as executable environment access.',
);

$attributeValueBypassFixture = <<<'PHP'
<?php

declare(strict_types=1);

#[\Attribute(\Attribute::TARGET_CLASS)]
final class SourceAttribute
{
    public function __construct(public int $source) {}
}

#[SourceAttribute(INPUT_ENV)]
final class AttributedInput
{
}

function inputSourceFromAttribute(): int
{
    $attribute = (new \ReflectionClass(AttributedInput::class))
        ->getAttributes(SourceAttribute::class)[0];

    return $attribute->newInstance()->source;
}

filter_input(inputSourceFromAttribute(), 'APP_KEY');
PHP;

requireParseable($attributeValueBypassFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $attributeValueBypassFixture,
        'src/AttributeValueBypass.php',
    )['failures'] === [
        'PHT007 src/AttributeValueBypass.php:11 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 allowed an INPUT_ENV attribute argument to carry the global source through reflection into filter_input.',
);

$acceptedKeyBoundaryFixture = sprintf(
    <<<'PHP'
<?php

declare(strict_types=1);

\getenv('A');
\getenv('%s');
PHP,
    str_repeat('A', 128),
);

requireParseable($acceptedKeyBoundaryFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $acceptedKeyBoundaryFixture,
        'src/AcceptedEnvironmentKeyBoundaries.php',
    ) === [
        'reads' => [5, 6],
        'keys' => ['A', str_repeat('A', 128)],
        'failures' => [],
    ],
    'PHT007 rejected an accepted one-byte or 128-byte uppercase literal environment key.',
);

$invalidKeyBoundaryFixture = sprintf(
    <<<'PHP'
<?php

declare(strict_types=1);

\getenv('');
\getenv('app_key');
\getenv('APP-KEY');
\getenv('%s');
PHP,
    str_repeat('A', 129),
);

requireParseable($invalidKeyBoundaryFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $invalidKeyBoundaryFixture,
        'src/InvalidEnvironmentKeyBoundaries.php',
    )['failures'] === [
        'PHT007 src/InvalidEnvironmentKeyBoundaries.php:5 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
        'PHT007 src/InvalidEnvironmentKeyBoundaries.php:6 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
        'PHT007 src/InvalidEnvironmentKeyBoundaries.php:7 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
        'PHT007 src/InvalidEnvironmentKeyBoundaries.php:8 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
    ],
    'PHT007 accepted an empty, lowercase, invalid, or 129-byte literal environment key.',
);

$unpackedCallableLimitationFixture = <<<'PHP'
<?php

declare(strict_types=1);

array_map(...['getenv', []]);
PHP;

requireParseable($unpackedCallableLimitationFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $unpackedCallableLimitationFixture,
        'src/UnpackedCallableLimitation.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 argument-unpack limitation changed without a profile decision.',
);

$aliasedCallbackLimitationFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture;

use Closure as NativeClosure;
use function array_map as map;

map('getenv', []);
NativeClosure::fromCallable('getenv');
PHP;

requireParseable($aliasedCallbackLimitationFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $aliasedCallbackLimitationFixture,
        'src/AliasedCallbackLimitation.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 native-callback alias limitation changed without a profile decision.',
);

$implicitReassignmentLimitationFixture = <<<'PHP'
<?php

declare(strict_types=1);

function reassignThroughForeach(): void
{
    $reader = 'getenv';

    foreach (['strlen'] as $reader) {
    }

    $reader('APP_KEY');
}

function reassignThroughDestructuring(): void
{
    $reader = 'getenv';
    [$reader] = ['strlen'];
    $reader('APP_KEY');
}
PHP;

requireParseable($implicitReassignmentLimitationFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $implicitReassignmentLimitationFixture,
        'src/ImplicitReassignmentLimitation.php',
    )['failures'] === [
        'PHT007 src/ImplicitReassignmentLimitation.php:7 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/ImplicitReassignmentLimitation.php:17 references environment function getenv indirectly; use direct \getenv calls only.',
    ],
    'PHT007 implicit-reassignment limitation changed without a profile decision.',
);

$separateCallableScopeFixture = <<<'PHP'
<?php

declare(strict_types=1);

function describeEnvironmentReader(): void
{
    $reader = 'getenv';
}

function invokeProvidedReader(callable $reader): void
{
    $reader();
}
PHP;

requireParseable($separateCallableScopeFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $separateCallableScopeFixture,
        'src/SeparateCallableScopes.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 confused identical variable names in separate PHP function scopes.',
);

$largeHarmlessLiteralFixture = "<?php\n\ndeclare(strict_types=1);\n\nfunction describeEnvironmentReaders(): void\n{\n";

for ($fixtureIndex = 0; $fixtureIndex < 4_000; $fixtureIndex++) {
    $largeHarmlessLiteralFixture .= sprintf(
        "    \$reader%d = 'getenv';\n",
        $fixtureIndex,
    );
}

$largeHarmlessLiteralFixture .= "}\n";

requireParseable($largeHarmlessLiteralFixture);
$largeInspectionStarted = hrtime(true);
$largeHarmlessLiteralResult = EnvironmentAccessProfile::inspect(
    $largeHarmlessLiteralFixture,
    'src/LargeHarmlessLiteralFixture.php',
);
$largeInspectionNanoseconds = hrtime(true) - $largeInspectionStarted;
requireProfile(
    $largeHarmlessLiteralResult === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 confused repeated harmless literal assignments with indirect environment calls.',
);
requireProfile(
    $largeInspectionNanoseconds < 2_000_000_000,
    sprintf(
        'PHT007 same-function harmless literal assignment scan took %.3f seconds; variable-use indexing regressed.',
        $largeInspectionNanoseconds / 1_000_000_000,
    ),
);

$validInputEnvDeclarationFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture;

use Vendor\FilterSource as INPUT_ENV;
use const Vendor\SAFE_SOURCE as INPUT_ENV;

$safeImportedSource = INPUT_ENV;

function INPUT_ENV(): int
{
    return 1;
}

$safeFunctionCall = INPUT_ENV();

enum FilterSource
{
    case INPUT_ENV;
}

function acceptInputSource(INPUT_ENV $value): void
{
}

function acceptUnionSource(INPUT_ENV|FilterSource $value): void
{
}

function acceptIntersectionSource(INPUT_ENV&FilterSource $value): void
{
}

function returnInputSource(): FilterSource|INPUT_ENV
{
    throw new \LogicException();
}

try {
} catch (INPUT_ENV|FilterSource $error) {
}

const input_env = 1;
$lowercaseApplicationConstant = input_env;
PHP;

requireParseable($validInputEnvDeclarationFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $validInputEnvDeclarationFixture,
        'src/InputEnvDeclarations.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 rejected a declaration, type, class alias, safe constant alias, or lowercase application constant named INPUT_ENV.',
);

$validLocalInputEnvConstantFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureLocal;

const INPUT_ENV = 17;
$source = INPUT_ENV;
PHP;

requireParseable($validLocalInputEnvConstantFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $validLocalInputEnvConstantFixture,
        'src/LocalInputEnvConstant.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 treated a declared namespaced INPUT_ENV constant as PHP\'s global input source.',
);

$lateLocalInputEnvConstantFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureLateLocal;

$source = INPUT_ENV;
const INPUT_ENV = 17;
PHP;

requireParseable($lateLocalInputEnvConstantFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $lateLocalInputEnvConstantFixture,
        'src/LateLocalInputEnvConstant.php',
    )['failures'] === [
        'PHT007 src/LateLocalInputEnvConstant.php:7 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 allowed a later namespaced declaration to hide an earlier global INPUT_ENV fallback.',
);

$multiConstantInputEnvDeclaratorFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureMultiConstant;

const SOURCE = INPUT_ENV, INPUT_ENV = 17;
$localSource = INPUT_ENV;
PHP;

requireParseable($multiConstantInputEnvDeclaratorFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $multiConstantInputEnvDeclaratorFixture,
        'src/MultiConstantInputEnvDeclarator.php',
    )['failures'] === [
        'PHT007 src/MultiConstantInputEnvDeclarator.php:7 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 confused an initializer before a later INPUT_ENV declarator with safe local-constant use.',
);

$selfInitializingInputEnvConstantFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureSelfInitializing;

const INPUT_ENV = INPUT_ENV;
PHP;

requireParseable($selfInitializingInputEnvConstantFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $selfInitializingInputEnvConstantFixture,
        'src/SelfInitializingInputEnvConstant.php',
    )['failures'] === [
        'PHT007 src/SelfInitializingInputEnvConstant.php:7 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 treated INPUT_ENV as locally bound inside its own constant initializer.',
);

$subsequentConstantInputEnvUseFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureSubsequentConstant;

const INPUT_ENV = 17, SOURCE = INPUT_ENV;
PHP;

requireParseable($subsequentConstantInputEnvUseFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $subsequentConstantInputEnvUseFixture,
        'src/SubsequentConstantInputEnvUse.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 failed to activate a local INPUT_ENV binding after its declarator ended.',
);

$validUnaliasedInputEnvImportFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureImported;

use const Vendor\INPUT_ENV;

$source = INPUT_ENV;
PHP;

requireParseable($validUnaliasedInputEnvImportFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $validUnaliasedInputEnvImportFixture,
        'src/ImportedInputEnvConstant.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 treated an imported namespaced INPUT_ENV constant as PHP\'s global input source.',
);

$lateInputEnvImportFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureLateImport;

$source = INPUT_ENV;
use const Vendor\SAFE_SOURCE as INPUT_ENV;
PHP;

requireParseable($lateInputEnvImportFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $lateInputEnvImportFixture,
        'src/LateInputEnvImport.php',
    )['failures'] === [
        'PHT007 src/LateInputEnvImport.php:7 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 allowed a later safe import to hide an earlier global INPUT_ENV fallback.',
);

$validInputEnvFunctionFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureFunction;

function INPUT_ENV(): int
{
    return 1;
}

$source = INPUT_ENV();
PHP;

requireParseable($validInputEnvFunctionFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $validInputEnvFunctionFixture,
        'src/InputEnvFunction.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 treated a function invocation named INPUT_ENV as a global constant reference.',
);

$qualifiedInputEnvWithSafeAliasFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace FixtureQualified;

use const Vendor\SAFE_SOURCE as INPUT_ENV;

$safe = INPUT_ENV;
$global = \INPUT_ENV;
PHP;

requireParseable($qualifiedInputEnvWithSafeAliasFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $qualifiedInputEnvWithSafeAliasFixture,
        'src/QualifiedInputEnvWithSafeAlias.php',
    )['failures'] === [
        'PHT007 src/QualifiedInputEnvWithSafeAlias.php:10 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 allowed a safe bare alias to hide an explicitly global INPUT_ENV reference.',
);

$validInputEnvNamespaceFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace INPUT_ENV;

final class Marker
{
}
PHP;

requireParseable($validInputEnvNamespaceFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $validInputEnvNamespaceFixture,
        'src/InputEnvNamespace.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 rejected INPUT_ENV when it was a namespace declaration.',
);

$validGroupedConstImportFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture;

use const Vendor\{INPUT_ENV as SOURCE};
PHP;

requireParseable($validGroupedConstImportFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $validGroupedConstImportFixture,
        'src/GroupedConstImport.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 treated a namespaced grouped constant import as a global INPUT_ENV import.',
);

$invalidEnvironmentFixture = <<<'PHP'
<?php

declare(strict_types=1);

$key = 'APP_KEY';
getenv('APP_KEY');
\GeTeNv('APP_KEY');
Fixture\getenv('APP_KEY');
\getenv();
\getenv($key);
\getenv('APP_KEY', true);
\getenv(name: 'APP_KEY');
\getenv(...['APP_KEY']);
$fromEnvironment = $_ENV['APP_KEY'];
$fromServer = $_SERVER['APP_KEY'];
$filtered = filter_input(INPUT_ENV, 'APP_KEY');
\putenv('APP_KEY=value');
\apache_getenv('APP_KEY');
\apache_setenv('APP_KEY', 'value');
$reader = "get\x65nv";
$reader('APP_KEY');
$unicodeReader = "get\u{65}nv";
$unicodeReader('APP_KEY');
$filteredIndirect = filter_input(constant("INPUT_\x45NV"), 'APP_KEY');
$harmless = 'getenv';
PHP;

requireParseable($invalidEnvironmentFixture);
$invalidEnvironmentResult = EnvironmentAccessProfile::inspect(
    $invalidEnvironmentFixture,
    'src/InvalidEnvironment.php',
);
$expectedInvalidEnvironmentFailures = [
    "PHT007 src/InvalidEnvironment.php:6 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
    "PHT007 src/InvalidEnvironment.php:7 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
    "PHT007 src/InvalidEnvironment.php:8 calls getenv without the canonical fully qualified spelling; use \\getenv('EXACT_LITERAL_KEY').",
    'PHT007 src/InvalidEnvironment.php:9 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
    'PHT007 src/InvalidEnvironment.php:10 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
    'PHT007 src/InvalidEnvironment.php:11 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
    'PHT007 src/InvalidEnvironment.php:12 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
    'PHT007 src/InvalidEnvironment.php:13 must call \getenv with exactly one non-empty uppercase literal key of at most 128 bytes.',
    'PHT007 src/InvalidEnvironment.php:14 reads $_ENV; read exact keys with \getenv in the single application configuration boundary.',
    'PHT007 src/InvalidEnvironment.php:15 indexes $_SERVER; pass the HTTP transport array unchanged or read configuration with \getenv in the single configuration boundary.',
    'PHT007 src/InvalidEnvironment.php:16 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    'PHT007 src/InvalidEnvironment.php:17 calls environment function putenv; process environment is read-only through direct \getenv calls.',
    'PHT007 src/InvalidEnvironment.php:18 calls environment function apache_getenv; process environment is read-only through direct \getenv calls.',
    'PHT007 src/InvalidEnvironment.php:19 calls environment function apache_setenv; process environment is read-only through direct \getenv calls.',
    'PHT007 src/InvalidEnvironment.php:20 references environment function getenv indirectly; use direct \getenv calls only.',
    'PHT007 src/InvalidEnvironment.php:22 references environment function getenv indirectly; use direct \getenv calls only.',
    'PHT007 src/InvalidEnvironment.php:24 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
];
requireProfile(
    $invalidEnvironmentResult['reads'] === [6, 7, 8, 9, 10, 11, 12, 13],
    'PHT007 did not record every direct getenv spelling.',
);
requireProfile(
    $invalidEnvironmentResult['failures'] === $expectedInvalidEnvironmentFailures,
    'PHT007 invalid-access fixture diagnostics changed.',
);

$invalidLiteralCallableFixture = <<<'PHP'
<?php

declare(strict_types=1);

('getenv')('APP_KEY');
array_map('getenv', ['APP_KEY']);
\array_map('getenv', ['APP_KEY']);
array_map(callback: 'getenv', arrays: ['APP_KEY']);
array_reduce([], 'getenv');
register_shutdown_function('putenv', 'APP_KEY=value');
call_user_func(('apache_getenv'), 'APP_KEY');
\Closure::fromCallable('getenv');
Closure::fromCallable('getenv');

function invokeEnvironmentReader(): void
{
    if (true) {
        $reader = ('getenv');
    }

    $reader('APP_KEY');
}

function invokeParenthesizedEnvironmentReader(): void
{
    $reader = 'getenv';
    ($reader)('APP_KEY');
}
PHP;

requireParseable($invalidLiteralCallableFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $invalidLiteralCallableFixture,
        'src/InvalidLiteralCallables.php',
    )['failures'] === [
        'PHT007 src/InvalidLiteralCallables.php:5 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:6 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:7 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:8 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:9 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:10 references environment function putenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:11 references environment function apache_getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:12 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:13 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:18 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/InvalidLiteralCallables.php:26 references environment function getenv indirectly; use direct \getenv calls only.',
    ],
    'PHT007 accepted a directly recognizable literal environment callable.',
);

$invalidImportedClosureFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture;

use Closure;

Closure::fromCallable('getenv');
PHP;

requireParseable($invalidImportedClosureFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $invalidImportedClosureFixture,
        'src/InvalidImportedClosure.php',
    )['failures'] === [
        'PHT007 src/InvalidImportedClosure.php:9 references environment function getenv indirectly; use direct \getenv calls only.',
    ],
    'PHT007 accepted a literal environment callable through an imported native Closure name.',
);

$capturedLiteralCallableFixture = <<<'PHP'
<?php

declare(strict_types=1);

function readAfterInterpolation(string $name): string|false
{
    $reader = 'getenv';
    $message = "reader={$name}";
    return $reader('APP_KEY');
}

function readThroughClosure(): void
{
    $reader = 'getenv';
    $closure = function () use ($reader): void {
        $reader('APP_KEY');
    };
}

function readThroughArrow(): void
{
    $reader = 'getenv';
    $closure = fn (): string|false => $reader('APP_KEY');
}

function readThroughInterpolatedArrow(): void
{
    $reader = 'getenv';
    $closure = fn (string $name): string|false => ["reader={$name}", $reader('APP_KEY')][1];
}
PHP;

requireParseable($capturedLiteralCallableFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $capturedLiteralCallableFixture,
        'src/CapturedLiteralCallables.php',
    )['failures'] === [
        'PHT007 src/CapturedLiteralCallables.php:7 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/CapturedLiteralCallables.php:14 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/CapturedLiteralCallables.php:22 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/CapturedLiteralCallables.php:28 references environment function getenv indirectly; use direct \getenv calls only.',
    ],
    'PHT007 lost a literal callable across interpolation or explicit closure capture.',
);

$shadowedArrowParameterFixture = <<<'PHP'
<?php

declare(strict_types=1);

function keyedDefaultShadowsReader(): void
{
    $reader = 'getenv';
    $callback = fn (
        array $defaults = ['reader' => 'strlen'],
        mixed $reader = null,
    ): mixed => $reader();
}

function byReferenceArrowShadowsReader(): void
{
    $reader = 'getenv';
    $callback = fn & (mixed $reader = null): mixed => $reader();
}
PHP;

requireParseable($shadowedArrowParameterFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $shadowedArrowParameterFixture,
        'src/ShadowedArrowParameters.php',
    ) === ['reads' => [], 'keys' => [], 'failures' => []],
    'PHT007 mistook a keyed-array parameter default or by-reference arrow declaration for captured environment-reader use.',
);

$capturedComplexArrowFixture = <<<'PHP'
<?php

declare(strict_types=1);

function keyedDefaultCapturesReader(): void
{
    $reader = 'getenv';
    $callback = fn (array $defaults = ['reader' => 'strlen']): mixed => $reader();
}

function byReferenceArrowCapturesReader(): void
{
    $reader = 'getenv';
    $callback = fn & (): mixed => $reader();
}
PHP;

requireParseable($capturedComplexArrowFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $capturedComplexArrowFixture,
        'src/CapturedComplexArrows.php',
    )['failures'] === [
        'PHT007 src/CapturedComplexArrows.php:7 references environment function getenv indirectly; use direct \getenv calls only.',
        'PHT007 src/CapturedComplexArrows.php:13 references environment function getenv indirectly; use direct \getenv calls only.',
    ],
    'PHT007 lost an environment-reader capture while parsing a keyed default or by-reference arrow.',
);

$invalidConstantLookupFixture = <<<'PHP'
<?php

declare(strict_types=1);

$named = filter_input(constant(name: 'INPUT_ENV'), 'APP_KEY');
$parenthesized = filter_input(constant(('INPUT_ENV')), 'APP_KEY');
PHP;

requireParseable($invalidConstantLookupFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $invalidConstantLookupFixture,
        'src/InvalidConstantLookup.php',
    )['failures'] === [
        'PHT007 src/InvalidConstantLookup.php:5 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
        'PHT007 src/InvalidConstantLookup.php:6 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
    ],
    'PHT007 accepted a named or parenthesized literal INPUT_ENV lookup.',
);

$invalidInputEnvAliasFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture;

use const INPUT_ENV as SOURCE;

$aliased = filter_input(SOURCE, 'APP_KEY');
$fullyQualified = filter_input(constant('\\INPUT_ENV'), 'APP_KEY');
PHP;

requireParseable($invalidInputEnvAliasFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $invalidInputEnvAliasFixture,
        'src/InvalidInputEnvAliases.php',
    )['failures'] === [
        'PHT007 src/InvalidInputEnvAliases.php:7 imports INPUT_ENV; process environment access must use direct \getenv calls in the single application configuration boundary.',
        'PHT007 src/InvalidInputEnvAliases.php:10 resolves INPUT_ENV indirectly; process environment is read-only through direct \getenv calls.',
    ],
    'PHT007 accepted an imported INPUT_ENV alias or a fully qualified literal INPUT_ENV lookup.',
);

$invalidInputEnvExpressionFixture = <<<'PHP'
<?php

declare(strict_types=1);

$source = 0;
switch ($source) {
    case INPUT_ENV:
        break;
}
$conditional = true ? INPUT_ENV : 0;
consume(source: INPUT_ENV);
PHP;

requireParseable($invalidInputEnvExpressionFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $invalidInputEnvExpressionFixture,
        'src/InvalidInputEnvExpressions.php',
    )['failures'] === [
        'PHT007 src/InvalidInputEnvExpressions.php:7 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
        'PHT007 src/InvalidInputEnvExpressions.php:10 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
        'PHT007 src/InvalidInputEnvExpressions.php:11 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 confused an INPUT_ENV value expression with a declaration or type.',
);

$switchCaseSeparatorFixture = <<<'PHP'
<?php

declare(strict_types=1);

$source = 0;
switch ($source) {
    case INPUT_ENV:
        break;
    case INPUT_ENV;
        break;
}
PHP;

requireParseable($switchCaseSeparatorFixture);
requireProfile(
    EnvironmentAccessProfile::inspect(
        $switchCaseSeparatorFixture,
        'src/SwitchCaseSeparators.php',
    )['failures'] === [
        'PHT007 src/SwitchCaseSeparators.php:7 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
        'PHT007 src/SwitchCaseSeparators.php:9 uses INPUT_ENV; read exact keys with \getenv in the single application configuration boundary.',
    ],
    'PHT007 treated a switch case using a colon or semicolon as an enum case declaration.',
);

$environmentImportFixture = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture;

use function getenv as readEnvironment;
use function putenv;
PHP;

requireParseable($environmentImportFixture);
requireProfile(
    EnvironmentAccessProfile::inspect($environmentImportFixture, 'src/EnvironmentImports.php')['failures'] === [
        'PHT007 src/EnvironmentImports.php:7 imports environment function getenv; use direct \getenv calls only.',
        'PHT007 src/EnvironmentImports.php:8 imports environment function putenv; use direct \getenv calls only.',
    ],
    'PHT007 did not reject imported environment functions.',
);

$canonicalServerHandoffFixture = <<<'PHP'
<?php

declare(strict_types=1);

$response = $coordinator->handle($_SERVER, $_GET, $_POST, $_FILES);
PHP;

requireParseable($canonicalServerHandoffFixture);
requireProfile(
    EnvironmentAccessProfile::inspect($canonicalServerHandoffFixture, 'public/index.php') === [
        'reads' => [],
        'keys' => [],
        'failures' => [],
    ],
    'PHT007 rejected the canonical front-controller transport handoff.',
);

$invalidServerHandoffFixture = <<<'PHP'
<?php

declare(strict_types=1);

$server = $_SERVER;
Config::fromServer($_SERVER);
$configurationReader->handle($_SERVER, $_GET, $_POST, $_FILES);
PHP;

requireParseable($invalidServerHandoffFixture);
requireProfile(
    EnvironmentAccessProfile::inspect($invalidServerHandoffFixture, 'public/index.php')['failures'] === [
        'PHT007 public/index.php:5 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
        'PHT007 public/index.php:6 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
        'PHT007 public/index.php:7 reads bare $_SERVER outside the canonical front-controller transport handoff; pass exactly $_SERVER, $_GET, $_POST, and $_FILES to the terminal coordinator or use \getenv in the configuration boundary.',
    ],
    'PHT007 accepted a non-canonical bare $_SERVER handoff.',
);

requireProfile(
    EnvironmentAccessProfile::boundaryFailures([
        'src/ZEnvironment.php' => [12, 18],
        'src/AEnvironment.php' => [7],
        'src/NoEnvironment.php' => [],
    ]) === [
        'PHT007 src/AEnvironment.php:7 reads process environment in more than one application-owned PHP file; centralize every \getenv call in one configuration boundary.',
        'PHT007 src/ZEnvironment.php:12 reads process environment in more than one application-owned PHP file; centralize every \getenv call in one configuration boundary.',
    ],
    'PHT007 cross-file diagnostics changed.',
);

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

$validEvalMethodFixture = <<<'PHP'
<?php

final class InstanceEvalMethod
{
    public function & /* declaration comment */ EvAl(string $value): string
    {
        return $value;
    }
}

final class StaticEvalMethod
{
    public static function EVAL(string $value): string
    {
        return $value;
    }
}

function callEvalMethods(?InstanceEvalMethod $optional): array
{
    $instance = new InstanceEvalMethod();

    return [
        $instance -> /* instance comment */ EvAl('instance'),
        $optional ?-> /* nullsafe comment */ EvAl('nullsafe'),
        StaticEvalMethod :: /* static comment */ EVAL('static'),
    ];
}
PHP;

requireParseable($validEvalMethodFixture);
requireProfile(
    SyntaxProfile::failures($validEvalMethodFixture, 'eval-methods.php') === [],
    'Shared syntax guard rejected a legal method declaration or call named eval.',
);

$validEvalIdentifierFixture = <<<'PHP'
<?php

final class EvalConstants
{
    public const string eval = 'constant';
}

enum EvalCases: string
{
    case eval = 'case';
}

trait EvalAliasSource
{
    public function original(): string
    {
        return 'alias';
    }
}

final class EvalAliasConsumer
{
    use EvalAliasSource {
        original as eval;
    }
}

function acceptNamedEval(string $eval): string
{
    return $eval;
}

final class EvalNamedConstructor
{
    public function __construct(public string $eval) {}
}

#[Attribute(Attribute::TARGET_CLASS)]
final class EvalNamedAttribute
{
    public function __construct(public string $eval) {}
}

#[EvalNamedAttribute(eval: 'attribute')]
final class EvalAttributedClass
{
}

function useEvalIdentifiers(): array
{
    $alias = new EvalAliasConsumer();
    $constructed = new EvalNamedConstructor(eval: 'constructor');

    return [
        EvalConstants::eval,
        EvalCases::eval,
        $alias->eval(),
        acceptNamedEval(eval: 'function'),
        $constructed->eval,
    ];
}
PHP;

requireParseable($validEvalIdentifierFixture);
requireProfile(
    SyntaxProfile::failures($validEvalIdentifierFixture, 'eval-identifiers.php') === [],
    'Shared syntax guard rejected a legal class constant, enum case, trait alias, or named argument called eval.',
);

$invalidEvalConstructFixture = <<<'PHP'
<?php

function lowerCaseEval(string $source): mixed
{
    return eval($source);
}

function mixedCaseEval(string $source): mixed
{
    return EVAL /* construct comment */ ($source);
}
PHP;

requireParseable($invalidEvalConstructFixture);
requireProfile(
    SyntaxProfile::failures($invalidEvalConstructFixture, 'eval-constructs.php') === [
        'eval-constructs.php:5 uses eval.',
        'eval-constructs.php:10 uses eval.',
    ],
    'Shared syntax guard did not distinguish eval methods from the eval language construct.',
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
$invalidPlaceholderSqlPath = $fixtureDirectory . '/pht008-invalid.php';
$validPlaceholderSqlPath = $fixtureDirectory . '/pht008-valid.php';
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
$invalidPlaceholderSqlSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace ProfilePlaceholderInvalid;

use PHPThis\Database\Connection;

final class RepeatedNamedPlaceholders
{
    public function run(Connection $connection, bool $postgres): void
    {
        $connection->selectOneRow('SELECT :same AS first_value, :same AS second_value');

        $connection->selectAllRows(
            <<<'SQL'
                SELECT ':ignored' AS literal_value,
                       :same AS first_value,
                       :same AS second_value
                SQL,
        );

        $variant = $postgres
            ? 'SELECT :same AS first_value, :same AS second_value'
            : 'SELECT :first_value AS first_value, :second_value AS second_value';
        $connection->executeStatement($variant);

        $connection->selectOneRow('SELECT ARRAY[:same::integer, :same::integer]');
        $connection->selectOneRow('SELECT (1 # :same::integer), :same::integer');

        $connection->selectOneRow(
            <<<'SQL'
                SELECT 1
                # :same::integer,
                :same::integer
                SQL,
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT COALESCE($tag$, 0) + :same + :same + COALESCE($tag$, 0) AS total
                SQL,
        );

        $connection->selectOneRow('SELECT 1 /*! + :same + :same */ AS total');
        $connection->selectOneRow('SELECT 1 /*+ :same :same */ AS total');

        $connection->selectOneRow(
            <<<'SQL'
                SELECT 1 ` :same ` :same
                SQL,
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT ` ':same :same' `
                SQL,
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT $tag$ /* :same :same */ $tag$
                SQL,
        );

        $connection->selectOneRow("SELECT #':same :same'");
        $connection->selectOneRow("SELECT 1--':same :same'");
        $connection->selectOneRow("SELECT [':same :same']");

        $connection->selectOneRow(
            <<<'SQL'
                SELECT 'x\' AS literal_value, :same AS first_value, :same AS second_value
                SQL,
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT ':same, :same \' still quoted '
                SQL,
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT ":same, :same \" still quoted"
                SQL,
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT `:same, :same \` still quoted`
                SQL,
        );

        $connection->selectOneRow('SELECT 1--:same AS first_value, :same AS second_value');
        $connection->selectOneRow('SELECT /* outer /* inner */ :same, :same');
        $connection->selectOneRow('SELECT /* outer /* :same, :same */ still outer */');

        $connection->selectOneRow(
            <<<'SQL'
                SELECT a$tag$ + :same, b$tag$ + :same FROM records
                SQL,
        );

        $connection->selectOneRow('SELECT [:same], :same');
        $connection->selectOneRow("SELECT ':same, :same");
        $connection->selectOneRow('SELECT /* :same, :same');

        $connection->selectOneRow(
            <<<'SQL'
                SELECT $tag$ :same, :same
                SQL,
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT /* outer :ignored /* nested :ignored */ still :ignored */
                       :same::text AS first_value,
                       :same::text AS second_value
                SQL,
        );
    }
}
PHP;
$validPlaceholderSqlSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace ProfilePlaceholderValid;

use PHPThis\Database\Connection;

final class DistinctNamedPlaceholders
{
    public function run(Connection $connection, bool $postgres): void
    {
        $connection->selectOneRow(
            'SELECT :first_value AS first_value, :second_value AS second_value',
            ['first_value' => 7, 'second_value' => 7],
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT ':same '' remains quoted' AS single_quoted,
                       ":same "" remains quoted" AS double_quoted,
                       `mysql_identifier` AS mysql_quoted,
                       [:sqlite_name remains ambiguous] AS sqlite_quoted,
                       :same AS actual_value
                SQL,
            ['same' => 7],
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT :same AS actual_value -- :same remains a comment
                /* :same remains a comment, and so does :same */
                SQL,
            ['same' => 7],
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT E':same \':same' AS escaped_literal, :same AS actual_value
                SQL,
            ['same' => 7],
        );

        $connection->selectOneRow(
            <<<'SQL'
                SELECT :same::text AS actual_value
                SQL,
            ['same' => 7],
        );

        $connection->selectOneRow(
            'SELECT :same AS lower_value, :SAME AS upper_value',
            ['same' => 7, 'SAME' => 8],
        );

        $connection->selectOneRow(
            'SELECT :first::integer AS first_value, :second::integer AS second_value',
            ['first' => 7, 'second' => 8],
        );

        $variant = $postgres
            ? 'SELECT :value::text AS selected_value'
            : 'SELECT CAST(:value AS TEXT) AS selected_value';
        $connection->selectOneRow($variant, ['value' => 7]);
    }
}
PHP;

writeFixture($invalidPath, $invalidSource);
writeFixture($validPath, $validSource);
writeFixture($invalidPdoPath, $invalidPdoSource);
writeFixture($validPdoPath, $validPdoSource);
writeFixture($invalidSqlPath, $invalidSqlSource);
writeFixture($validSqlPath, $validSqlSource);
writeFixture($invalidPlaceholderSqlPath, $invalidPlaceholderSqlSource);
writeFixture($validPlaceholderSqlPath, $validPlaceholderSqlSource);

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

$invalidPlaceholderSqlResult = runProfileAnalysis($root, $invalidPlaceholderSqlPath);
requireProfile(
    $invalidPlaceholderSqlResult['exit_code'] === 1,
    'PHT008 repeated-placeholder fixture unexpectedly passed.',
);
requireProfile(
    profileDiagnosticLines(
        $invalidPlaceholderSqlResult,
        $invalidPlaceholderSqlPath,
        'phpthis.pht008',
        'PHT008',
    ) === [13, 15, 26, 28, 29, 31, 39, 45, 46, 48, 54, 60, 66, 67, 68, 70, 76, 82, 88, 94, 95, 96, 98, 104, 105, 106, 108, 114],
    'PHT008 did not reject each repeated exact named placeholder across finite SQL variants.',
);
$pht008Message = '[PHT008] Connection SQL must use a distinct named placeholder for each occurrence; '
    . 'rename repeated placeholders and bind each value separately.';
requireProfile(
    substr_count(
        $invalidPlaceholderSqlResult['stdout'] . $invalidPlaceholderSqlResult['stderr'],
        $pht008Message,
    ) === 28,
    'PHT008 did not emit exactly one fixed diagnostic for each invalid Connection call.',
);

$validPlaceholderSqlResult = runProfileAnalysis($root, $validPlaceholderSqlPath);
requireProfile(
    $validPlaceholderSqlResult['exit_code'] === 0,
    "PHT008 rejected distinct placeholders or a reviewed quoted, comment, cast, or dialect construct.\n"
        . $validPlaceholderSqlResult['stderr']
        . $validPlaceholderSqlResult['stdout'],
);

fwrite(STDOUT, "PASS strict profile: PHT001 through PHT008\n");

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
    return runBoundedMaintainerProcess(
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
        $root,
        null,
        120_000,
        8_388_608,
        8_388_608,
    );
}
