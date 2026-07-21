<?php

declare(strict_types=1);

use PHPThis\Verification\ApplicationDuplicationScanner;

require_once dirname(__DIR__) . '/verification/ApplicationDuplicationScanner.php';

if (($argv[1] ?? null) === '--memory-probe') {
    duplicationTestMemoryProbe();
    exit(0);
}

$tests = [
    'fixed duplication bounds remain explicit' => static function (): void {
        $reflection = new ReflectionClass(ApplicationDuplicationScanner::class);
        $expected = [
            'MINIMUM_TOKENS' => 48,
            'MAXIMUM_MANIFEST_FILES' => 4_096,
            'MAXIMUM_SOURCE_BYTES_PER_FILE' => 32_768,
            'MAXIMUM_TOTAL_SOURCE_BYTES' => 2_097_152,
            'MAXIMUM_TOKENS_PER_FILE' => 16_384,
            'MAXIMUM_TOTAL_TOKENS' => 24_000,
            'MAXIMUM_WINDOWS' => 24_000,
            'MAXIMUM_HASH_VARIANTS' => 4,
            'MAXIMUM_EXTENSION_ATTEMPTS' => 2_048,
            'MAXIMUM_TOKEN_COMPARISONS' => 1_000_000,
            'MAXIMUM_GROUPS' => 256,
            'MAXIMUM_LOCATIONS_PER_GROUP' => 256,
            'MAXIMUM_PROPAGATION_CHECKS' => 32_768,
            'MAXIMUM_DEBUG_GROUPS' => 10,
            'MAXIMUM_DEBUG_LOCATIONS' => 8,
            'MAXIMUM_DISPLAY_PATH_BYTES' => 160,
        ];

        foreach ($expected as $name => $value) {
            duplicationTestRequire(
                $reflection->getConstant($name) === $value,
                "Duplication bound {$name} changed without updating its contract test.",
            );
        }
    },
    'a block below the threshold is not reported' => static function (): void {
        $block = duplicationTestBlock(100, 11) . 'return $shared;';
        $sources = [
            'src/BelowOne.php' => duplicationTestBareSource($block, 'belowOne'),
            'src/BelowTwo.php' => duplicationTestBareSource($block, 'belowTwo'),
        ];
        $report = duplicationTestInspect($sources);

        duplicationTestRequire($report['groups'] === [], 'A 47-token block reached the advisory threshold.');
        duplicationTestRequire(!$report['incomplete'], 'A small below-threshold scan was incomplete.');
        duplicationTestRequire(
            duplicationTestRender($sources, false)
                === "PASS application duplication advisory: no possible groups (minimum 48 normalized tokens)\n",
            'The no-match summary changed.',
        );
    },
    'comments and whitespace do not hide an exact-threshold clone' => static function (): void {
        $plain = duplicationTestBlock(200, 12);
        $decorated = duplicationTestBlock(200, 12, true);
        $sources = [
            'unconventional/Plain.php' => duplicationTestBareSource($plain, 'plainEnd'),
            '.hidden/Decorated.php' => duplicationTestBareSource($decorated, 'decoratedEnd'),
        ];
        $report = duplicationTestInspect($sources);

        duplicationTestRequire(count($report['groups']) === 1, 'The exact-threshold clone was not one group.');
        duplicationTestRequire($report['groups'][0]['tokens'] === 48, 'The clone did not stay at the 48-token threshold.');
        duplicationTestRequire(count($report['groups'][0]['locations']) === 2, 'The clone did not retain both locations.');

        $normal = duplicationTestRender($sources, false);
        $debug = duplicationTestRender($sources, true);
        duplicationTestRequire(
            $normal === "ADVISORY possible application duplication: 1 group (minimum 48 normalized tokens); run `phpthis check --debug` for details; application validity is unaffected\n",
            'The concise normal advisory changed or disclosed additional detail.',
        );
        duplicationTestRequire(str_contains($debug, '".hidden/Decorated.php"'), 'Debug output omitted the hidden source path.');
        duplicationTestRequire(str_contains($debug, '"unconventional/Plain.php"'), 'Debug output omitted the unconventional source path.');
        duplicationTestRequire(!str_contains($debug, 'DUPLICATION_PRIVATE_CANARY'), 'Debug output disclosed source content.');
    },
    'identifier and literal changes do not become exact clones' => static function (): void {
        $first = duplicationTestBlock(250, 20);
        $differentLiterals = duplicationTestBlock(750, 20);
        $differentIdentifier = str_replace('$shared', '$separate', $first);
        $report = duplicationTestInspect([
            'src/First.php' => duplicationTestBareSource($first, 'firstEnd'),
            'src/DifferentLiterals.php' => duplicationTestBareSource($differentLiterals, 'literalEnd'),
            'src/DifferentIdentifier.php' => duplicationTestBareSource($differentIdentifier, 'identifierEnd'),
        ]);

        duplicationTestRequire($report['groups'] === [], 'Changed identifiers or literals were treated as exact clones.');
        duplicationTestRequire(!$report['incomplete'], 'A small exact-value comparison was incomplete.');
    },
    'overlapping windows collapse into one maximal group' => static function (): void {
        $block = duplicationTestBlock(300, 30);
        $report = duplicationTestInspect([
            'src/LongOne.php' => duplicationTestBareSource($block, 'longOneEnd'),
            'src/LongTwo.php' => duplicationTestBareSource($block, 'longTwoEnd'),
        ]);

        duplicationTestRequire(count($report['groups']) === 1, 'A long clone produced overlapping groups.');
        duplicationTestRequire($report['groups'][0]['tokens'] === 120, 'The long clone was not extended to its maximal span.');
    },
    'one clone across three files remains one group' => static function (): void {
        $block = duplicationTestBlock(400, 14);
        $report = duplicationTestInspect([
            'z/Third.php' => duplicationTestSource('ThirdClone', 'third', $block, 'third'),
            'a/First.php' => duplicationTestSource('FirstClone', 'first', $block, 'first'),
            'm/Second.php' => duplicationTestSource('SecondClone', 'second', $block, 'second'),
        ]);

        duplicationTestRequire(count($report['groups']) === 1, 'Three copies became pairwise duplicate groups.');
        duplicationTestRequire(count($report['groups'][0]['locations']) === 3, 'The group omitted a clone location.');
        duplicationTestRequire(
            array_column($report['groups'][0]['locations'], 'path') === ['a/First.php', 'm/Second.php', 'z/Third.php'],
            'Clone locations were not sorted by application-relative path.',
        );
    },
    'a nested third copy remains visible beside its longer parent clone' => static function (): void {
        $longBlock = duplicationTestBlock(450, 18);
        $nestedBlock = duplicationTestBlock(452, 14);
        $report = duplicationTestInspect([
            'a/LongOne.php' => duplicationTestBareSource($longBlock, 'longOneEnd'),
            'b/LongTwo.php' => duplicationTestBareSource($longBlock, 'longTwoEnd'),
            'c/Nested.php' => duplicationTestBareSource($nestedBlock, 'nestedEnd'),
        ]);

        duplicationTestRequire(count($report['groups']) === 2, 'A nested third copy was lost or fragmented.');
        duplicationTestRequire(
            array_column($report['groups'], 'tokens') === [72, 56],
            'Nested and parent clone sizes were not deterministic.',
        );
        duplicationTestRequire(
            count($report['groups'][0]['locations']) === 2
                && count($report['groups'][1]['locations']) === 3,
            'The parent and nested clone locations were not consolidated correctly.',
        );
    },
    'separated copies in one file are reported without self overlap' => static function (): void {
        $block = duplicationTestBlock(500, 12);
        $source = "<?php\n{$block}separatorForClone:;\n{$block}sameFileEnd:;\n";
        $report = duplicationTestInspect(['src/SameFileClone.php' => $source]);
        $matchingGroups = array_values(array_filter(
            $report['groups'],
            static fn (array $group): bool => $group['tokens'] === 48 && count($group['locations']) === 2,
        ));

        duplicationTestRequire(count($report['groups']) === 1, 'Separated same-file copies produced extra groups.');
        duplicationTestRequire(count($matchingGroups) === 1, 'Separated same-file copies were not one non-overlapping group.');
    },
    'adjacent periodic copies collapse without overlapping noise' => static function (): void {
        $block = duplicationTestBlock(550, 12);
        $report = duplicationTestInspect([
            'src/Periodic.php' => "<?php\n{$block}{$block}{$block}{$block}periodicEnd:;\n",
        ]);

        duplicationTestRequire(count($report['groups']) === 1, 'Adjacent periodic copies produced shifted overlapping groups.');
        duplicationTestRequire($report['groups'][0]['tokens'] === 96, 'The periodic group was not extended to its maximal non-overlapping span.');
        duplicationTestRequire(count($report['groups'][0]['locations']) === 2, 'The periodic group omitted a maximal separated occurrence.');
    },
    'unequal periodic files retain only non-overlapping locations' => static function (): void {
        $copy = str_repeat("\$shared++;\n", 30);
        $report = duplicationTestInspect([
            'a/ShortPeriodic.php' => duplicationTestBareSource($copy, 'shortEnd'),
            'b/LongPeriodic.php' => duplicationTestBareSource($copy . $copy, 'longEnd'),
        ]);

        duplicationTestRequire(count($report['groups']) === 1, 'Unequal periodic files produced fragmented groups.');
        duplicationTestRequire($report['groups'][0]['tokens'] === 90, 'The unequal periodic group did not retain one maximal copy.');
        duplicationTestRequire(
            count($report['groups'][0]['locations']) === 3,
            'The unequal periodic group omitted a location or retained overlapping locations.',
        );
        duplicationTestRequire(
            array_column($report['groups'][0]['locations'], 'path')
                === ['a/ShortPeriodic.php', 'b/LongPeriodic.php', 'b/LongPeriodic.php'],
            'Unequal periodic locations were not deterministic.',
        );
    },
    'manifest order does not change deterministic output' => static function (): void {
        $firstBlock = duplicationTestBlock(600, 12);
        $secondBlock = duplicationTestBlock(800, 15);
        $ordered = [
            'a/FirstA.php' => duplicationTestSource('FirstA', 'a', $firstBlock, 'a'),
            'b/FirstB.php' => duplicationTestSource('FirstB', 'b', $firstBlock, 'b'),
            'c/SecondA.php' => duplicationTestSource('SecondA', 'c', $secondBlock, 'c'),
            'd/SecondB.php' => duplicationTestSource('SecondB', 'd', $secondBlock, 'd'),
        ];
        $reversed = array_reverse($ordered, true);

        $report = duplicationTestInspect($ordered);
        duplicationTestRequire(count($report['groups']) === 2, 'The deterministic fixture did not create exactly two groups.');
        duplicationTestRequire(
            array_column($report['groups'], 'tokens') === [63, 51],
            'The deterministic fixture did not preserve its expected group order.',
        );

        duplicationTestRequire(
            duplicationTestRender($ordered, true) === duplicationTestRender($reversed, true),
            'Advisory bytes changed with manifest insertion order.',
        );
        duplicationTestRequire(
            duplicationTestRender($ordered, true) === duplicationTestRender($ordered, true),
            'Repeated advisory runs were not byte-identical.',
        );
    },
    'debug group and location details are capped' => static function (): void {
        $manyLocations = [];
        $locationBlock = duplicationTestBlock(1_000, 12);

        for ($index = 0; $index < 9; $index++) {
            $manyLocations[sprintf('locations/%02d.php', $index)] = duplicationTestSource(
                'Location' . $index,
                'beforeLocation' . $index,
                $locationBlock,
                'afterLocation' . $index,
            );
        }

        $locationOutput = duplicationTestRender($manyLocations, true);
        duplicationTestRequire(
            substr_count($locationOutput, 'ADVISORY duplication location 1.') === 8,
            'Debug output did not enforce the eight-location cap.',
        );
        duplicationTestRequire(
            str_contains($locationOutput, 'locations truncated: 8 of 9 shown'),
            'Debug output omitted location truncation.',
        );

        $manyGroups = [];

        for ($group = 0; $group < 11; $group++) {
            $block = duplicationTestBlock(2_000 + ($group * 100), 12);

            for ($copy = 0; $copy < 2; $copy++) {
                $suffix = $group . '_' . $copy;
                $manyGroups["groups/{$suffix}.php"] = duplicationTestSource(
                    'Group' . $group . 'Copy' . $copy,
                    'beforeGroup' . $group . 'Copy' . $copy,
                    $block,
                    'afterGroup' . $group . 'Copy' . $copy,
                );
            }
        }

        $groupOutput = duplicationTestRender($manyGroups, true);
        duplicationTestRequire(
            substr_count($groupOutput, 'ADVISORY duplication group ') === 10,
            'Debug output did not enforce the ten-group cap.',
        );
        duplicationTestRequire(
            str_contains($groupOutput, 'ADVISORY duplication details truncated: 10 of 11 groups shown'),
            'Debug output omitted group truncation.',
        );
    },
    'resource saturation remains an advisory' => static function (): void {
        $source = "<?php\n\ndeclare(strict_types=1);\n" . str_repeat(';', 50_001);
        $report = duplicationTestInspect(['large/Repeated.php' => $source]);
        $output = duplicationTestRender(['large/Repeated.php' => $source], false);

        duplicationTestRequire($report['incomplete'], 'The per-file source cap was not enforced.');
        duplicationTestRequire($report['groups'] === [], 'A cap-skipped file produced a group.');
        duplicationTestRequire(str_starts_with($output, 'ADVISORY application duplication scan incomplete:'), 'A resource cap became a pass or failure.');
        duplicationTestRequire(str_contains($output, 'application validity is unaffected'), 'The incomplete scan omitted its validity boundary.');

        $block = duplicationTestBlock(2_500, 12);
        $partialSources = [
            'a/First.php' => duplicationTestBareSource($block, 'firstEnd'),
            'b/Second.php' => duplicationTestBareSource($block, 'secondEnd'),
            'z/Large.php' => $source,
        ];
        $partialOutput = duplicationTestRender($partialSources, false);
        duplicationTestRequire(
            str_contains($partialOutput, 'at least 1 group found within an incomplete bounded scan'),
            'A partial finding did not state its singular count and incomplete scan state.',
        );
    },
    'fixed limits remain safe under a 64 MiB process budget' => static function (): void {
        $command = [
            PHP_BINARY,
            '-d',
            'memory_limit=64M',
            __FILE__,
            '--memory-probe',
        ];
        $pipes = [];
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('The low-memory scanner subprocess did not start.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        duplicationTestRequire($stderr === '', 'The low-memory scanner subprocess wrote to stderr.');
        duplicationTestRequire(
            $exitCode === 0,
            'The bounded scanner exceeded its 64 MiB process budget: ' . trim($stdout . "\n" . $stderr),
        );
    },
    'multiline tokens report their actual ending line' => static function (): void {
        $block = duplicationTestBlock(3_500, 11) . "\$shared += \"alpha\nbeta\";\n";
        $report = duplicationTestInspect([
            'a/MultilineOne.php' => duplicationTestBareSource($block, 'firstEnd'),
            'b/MultilineTwo.php' => duplicationTestBareSource($block, 'secondEnd'),
        ]);

        duplicationTestRequire(count($report['groups']) === 1, 'The multiline clone was not consolidated.');

        foreach ($report['groups'][0]['locations'] as $location) {
            duplicationTestRequire($location['start_line'] === 2, 'The multiline clone start line changed.');
            duplicationTestRequire($location['end_line'] === 14, 'The multiline token end line was not preserved.');
        }
    },
    'debug paths are escaped and bounded without source leakage' => static function (): void {
        $block = duplicationTestBlock(3_000, 12);
        $long = str_repeat('p', 220);
        $sources = [
            "odd/line\nbreak\e[31m/right\u{202E}left/{$long}.php" => duplicationTestSource('OddPathOne', 'one', $block, 'one'),
            'safe/Second.php' => duplicationTestSource('OddPathTwo', 'two', $block, 'two'),
        ];
        $output = duplicationTestRender($sources, true);

        duplicationTestRequire(!str_contains($output, "line\nbreak"), 'A debug path injected a newline.');
        duplicationTestRequire(!str_contains($output, "\e"), 'A debug path emitted a raw escape byte.');
        duplicationTestRequire(!str_contains($output, "\u{202E}"), 'A debug path emitted a raw bidi control character.');
        duplicationTestRequire(str_contains($output, 'line\\nbreak\\u001b[31m'), 'A debug path was not JSON escaped.');
        duplicationTestRequire(str_contains($output, 'right\\u202eleft'), 'A debug path did not escape its bidi control character.');
        duplicationTestRequire(str_contains($output, '..."'), 'A long debug path was not truncated.');
        duplicationTestRequire(!str_contains($output, 'DUPLICATION_PRIVATE_CANARY'), 'Advisory output disclosed a source canary.');
    },
];

$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
    } catch (Throwable $throwable) {
        fwrite(STDERR, "FAIL application duplication test {$name}: {$throwable->getMessage()}\n");
    }
}

if ($passed !== count($tests)) {
    exit(1);
}

fwrite(STDOUT, sprintf("PASS application duplication advisory: %d direct tests\n", $passed));

function duplicationTestRequire(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, string> $sources */
function duplicationTestScanner(array $sources): ApplicationDuplicationScanner
{
    $scanner = new ApplicationDuplicationScanner();

    foreach ($sources as $relativePath => $source) {
        $scanner->collect($relativePath, $source);
    }

    return $scanner;
}

/**
 * @param array<string, string> $sources
 * @return array{
 *     groups: list<array{
 *         tokens: int,
 *         locations: list<array{path: string, start_line: int, end_line: int}>
 *     }>,
 *     incomplete: bool,
 *     manifest_files: int,
 *     scanned_files: int,
 *     scanned_tokens: int,
 *     scanned_windows: int
 * }
 */
function duplicationTestInspect(array $sources): array
{
    return duplicationTestScanner($sources)->inspect();
}

/** @param array<string, string> $sources */
function duplicationTestRender(array $sources, bool $debug): string
{
    return duplicationTestScanner($sources)->render($debug);
}

function duplicationTestBlock(int $firstValue, int $statements, bool $decorated = false): string
{
    $lines = [];

    for ($offset = 0; $offset < $statements; $offset++) {
        $value = $firstValue + $offset;
        $comment = $offset % 2 === 0
            ? '/* DUPLICATION_PRIVATE_CANARY */'
            : '/** DUPLICATION_PRIVATE_CANARY */';
        $lines[] = $decorated
            ? "\$shared {$comment} +=\n    {$value};"
            : "\$shared += {$value};";
    }

    return implode("\n", $lines) . "\n";
}

function duplicationTestSource(
    string $class,
    string $before,
    string $block,
    string $after,
): string {
    return <<<PHP
<?php

declare(strict_types=1);

namespace DuplicationFixture;

final class {$class}
{
    public function run(): int
    {
        \$before{$before} = 0;
        {$block}        \$after{$after} = 1;

        return \$after{$after};
    }
}
PHP;
}

function duplicationTestBareSource(string $block, string $endLabel): string
{
    return "<?php\n{$block}{$endLabel}:;\n";
}

function duplicationTestMemoryProbe(): void
{
    $uniqueSources = [];

    for ($file = 0; $file < 4; $file++) {
        $uniqueSources["memory/Unique{$file}.php"] = duplicationTestBareSource(
            duplicationTestBlock(100_000 + ($file * 2_000), 1_499),
            'uniqueEnd' . $file,
        );
    }

    $uniqueReport = duplicationTestInspect($uniqueSources);
    duplicationTestRequire(!$uniqueReport['incomplete'], 'The near-window-limit memory probe was incomplete.');
    duplicationTestRequire($uniqueReport['groups'] === [], 'The mostly unique memory probe produced a group.');
    duplicationTestRequire(
        $uniqueReport['scanned_tokens'] === 23_996,
        'The near-window-limit memory probe did not retain its expected normalized-token count.',
    );
    duplicationTestRequire(
        $uniqueReport['scanned_windows'] === 23_808,
        'The near-window-limit memory probe did not exercise its expected window count.',
    );
    unset($uniqueReport, $uniqueSources);

    $acceptedDense = "<?php\n" . str_repeat(';', 16_384);
    $acceptedDenseReport = duplicationTestInspect(['memory/AcceptedDense.php' => $acceptedDense]);
    duplicationTestRequire(
        $acceptedDenseReport['scanned_tokens'] === 16_384,
        'The accepted dense-token memory probe did not exercise its full per-file token allowance.',
    );
    unset($acceptedDense, $acceptedDenseReport);

    $dense = "<?php\n" . str_repeat(';', 32_762);
    $denseReport = duplicationTestInspect(['memory/Dense.php' => $dense]);
    duplicationTestRequire($denseReport['incomplete'], 'The dense-token memory probe did not reach a fixed bound.');
    duplicationTestRequire($denseReport['groups'] === [], 'The dense-token memory probe produced a group.');
}
