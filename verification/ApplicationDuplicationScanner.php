<?php

declare(strict_types=1);

namespace PHPThis\Verification;

use PhpToken;

final class ApplicationDuplicationScanner
{
    private const MINIMUM_TOKENS = 48;
    private const MAXIMUM_MANIFEST_FILES = 4_096;
    private const MAXIMUM_SOURCE_BYTES_PER_FILE = 32_768;
    private const MAXIMUM_TOTAL_SOURCE_BYTES = 2_097_152;
    private const MAXIMUM_TOKENS_PER_FILE = 16_384;
    private const MAXIMUM_TOTAL_TOKENS = 24_000;
    private const MAXIMUM_WINDOWS = 24_000;
    private const MAXIMUM_HASH_VARIANTS = 4;
    private const MAXIMUM_EXTENSION_ATTEMPTS = 2_048;
    private const MAXIMUM_TOKEN_COMPARISONS = 1_000_000;
    private const MAXIMUM_GROUPS = 256;
    private const MAXIMUM_LOCATIONS_PER_GROUP = 256;
    private const MAXIMUM_PROPAGATION_CHECKS = 32_768;
    private const MAXIMUM_DEBUG_GROUPS = 10;
    private const MAXIMUM_DEBUG_LOCATIONS = 8;
    private const MAXIMUM_DISPLAY_PATH_BYTES = 160;
    private const LOCATION_STRIDE = self::MAXIMUM_TOKENS_PER_FILE + 1;

    private const VALUE_BEARING_TOKEN_IDS = [
        T_LNUMBER,
        T_DNUMBER,
        T_STRING,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_NAME_QUALIFIED,
        T_VARIABLE,
        T_INLINE_HTML,
        T_ENCAPSED_AND_WHITESPACE,
        T_CONSTANT_ENCAPSED_STRING,
        T_STRING_VARNAME,
        T_NUM_STRING,
        T_START_HEREDOC,
        T_END_HEREDOC,
        T_BAD_CHARACTER,
    ];

    /** @var array<string, string> */
    private array $capturedSources = [];

    private int $capturedSourceBytes = 0;
    private int $manifestFiles = 0;
    private bool $captureIncomplete = false;

    /**
     * @var ?array{
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
    private ?array $report = null;

    public function collect(string $relativePath, string $source): void
    {
        if ($this->report !== null) {
            $this->captureIncomplete = true;

            return;
        }

        $this->manifestFiles++;

        if ($this->manifestFiles > self::MAXIMUM_MANIFEST_FILES) {
            $this->captureIncomplete = true;

            return;
        }

        $bytes = strlen($source);

        if (
            $bytes > self::MAXIMUM_SOURCE_BYTES_PER_FILE
            || $this->capturedSourceBytes + $bytes > self::MAXIMUM_TOTAL_SOURCE_BYTES
        ) {
            $this->captureIncomplete = true;

            return;
        }

        $this->capturedSources[$relativePath] = $source;
        $this->capturedSourceBytes += $bytes;
    }

    /**
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
    public function inspect(): array
    {
        if ($this->report !== null) {
            return $this->report;
        }

        ksort($this->capturedSources, SORT_STRING);
        $incomplete = $this->captureIncomplete;
        $scannedTokens = 0;

        /**
         * @var list<array{
         *     path: string,
         *     tokens: list<string>,
         *     start_lines: list<int>,
         *     end_lines: list<int>
         * }> $files
         */
        $files = [];

        foreach ($this->capturedSources as $relativePath => $source) {
            $normalized = $this->normalize($source);
            unset($this->capturedSources[$relativePath]);

            if ($normalized['truncated']) {
                $incomplete = true;
                continue;
            }

            $tokenCount = count($normalized['tokens']);

            if ($scannedTokens + $tokenCount > self::MAXIMUM_TOTAL_TOKENS) {
                $incomplete = true;
                break;
            }

            $files[] = [
                'path' => $relativePath,
                'tokens' => $normalized['tokens'],
                'start_lines' => $normalized['start_lines'],
                'end_lines' => $normalized['end_lines'],
            ];
            $scannedTokens += $tokenCount;
        }

        $this->capturedSources = [];

        /** @var array<string, int> $windowRepresentatives */
        $windowRepresentatives = [];
        /** @var array<string, list<int>> $repeatedWindows */
        $repeatedWindows = [];
        $scannedWindows = 0;
        $tokenComparisons = 0;
        $stop = false;

        foreach ($files as $fileIndex => $file) {
            $windowLimit = count($file['tokens']) - self::MINIMUM_TOKENS;

            for ($start = 0; $start <= $windowLimit; $start++) {
                if ($scannedWindows >= self::MAXIMUM_WINDOWS) {
                    $incomplete = true;
                    $stop = true;
                    break;
                }

                $scannedWindows++;
                $fingerprint = $this->fingerprint($file['tokens'], $start, self::MINIMUM_TOKENS);
                $encodedLocation = $this->encodeLocation($fileIndex, $start);
                $indexed = false;

                for ($variant = 0; $variant < self::MAXIMUM_HASH_VARIANTS; $variant++) {
                    $variantKey = $variant === 0 ? $fingerprint : $fingerprint . pack('N', $variant);
                    $representative = $windowRepresentatives[$variantKey] ?? null;

                    if ($representative === null) {
                        $windowRepresentatives[$variantKey] = $encodedLocation;
                        $indexed = true;
                        break;
                    }

                    [$representativeFile, $representativeStart] = $this->decodeLocation($representative);
                    $matches = $this->rangesEqual(
                        $files[$representativeFile]['tokens'],
                        $representativeStart,
                        $file['tokens'],
                        $start,
                        self::MINIMUM_TOKENS,
                        $tokenComparisons,
                    );

                    if ($matches === null) {
                        $incomplete = true;
                        $stop = true;
                        break 2;
                    }

                    if (!$matches) {
                        continue;
                    }

                    if (!isset($repeatedWindows[$variantKey])) {
                        $repeatedWindows[$variantKey] = [$representative];
                    }

                    $repeatedWindows[$variantKey][] = $encodedLocation;
                    $indexed = true;
                    break;
                }

                if (!$indexed) {
                    $incomplete = true;
                    $stop = true;
                    break;
                }
            }

            if ($stop) {
                break;
            }
        }

        unset($windowRepresentatives);

        /** @var array<string, array{first_end: int, second_end: int}> $coveredAlignments */
        $coveredAlignments = [];
        /**
         * @var list<array{
         *     tokens: int,
         *     representative_file: int,
         *     representative_start: int,
         *     locations: array<int, array{file: int, start: int}>
         * }> $workingGroups
         */
        $workingGroups = [];
        /** @var array<string, list<int>> $groupIndex */
        $groupIndex = [];
        $extensionAttempts = 0;

        if (!$stop) {
            foreach ($repeatedWindows as $occurrences) {
                [$firstFileIndex, $firstStart] = $this->decodeLocation($occurrences[0]);

                foreach (array_slice($occurrences, 1) as $encodedOccurrence) {
                    [$secondFileIndex, $secondStart] = $this->decodeLocation($encodedOccurrence);

                    if (
                        $firstFileIndex === $secondFileIndex
                        && abs($secondStart - $firstStart) < self::MINIMUM_TOKENS
                    ) {
                        continue;
                    }

                    $alignmentKey = sprintf(
                        '%d:%d:%d',
                        $firstFileIndex,
                        $secondFileIndex,
                        $secondStart - $firstStart,
                    );
                    $covered = $coveredAlignments[$alignmentKey] ?? null;

                    if (
                        $covered !== null
                        && $firstStart + self::MINIMUM_TOKENS - 1 <= $covered['first_end']
                        && $secondStart + self::MINIMUM_TOKENS - 1 <= $covered['second_end']
                    ) {
                        continue;
                    }

                    if ($extensionAttempts >= self::MAXIMUM_EXTENSION_ATTEMPTS) {
                        $incomplete = true;
                        $stop = true;
                        break 2;
                    }

                    $extensionAttempts++;
                    $firstTokens = $files[$firstFileIndex]['tokens'];
                    $secondTokens = $files[$secondFileIndex]['tokens'];
                    $left = 0;

                    while (
                        $firstStart - $left > 0
                        && $secondStart - $left > 0
                    ) {
                        $comparison = $this->tokensEqual(
                            $firstTokens[$firstStart - $left - 1],
                            $secondTokens[$secondStart - $left - 1],
                            $tokenComparisons,
                        );

                        if ($comparison === null) {
                            $incomplete = true;
                            $stop = true;
                            break 3;
                        }

                        if (!$comparison) {
                            break;
                        }

                        $left++;
                    }

                    $length = self::MINIMUM_TOKENS + $left;

                    while (
                        $firstStart - $left + $length < count($firstTokens)
                        && $secondStart - $left + $length < count($secondTokens)
                    ) {
                        $comparison = $this->tokensEqual(
                            $firstTokens[$firstStart - $left + $length],
                            $secondTokens[$secondStart - $left + $length],
                            $tokenComparisons,
                        );

                        if ($comparison === null) {
                            $incomplete = true;
                            $stop = true;
                            break 3;
                        }

                        if (!$comparison) {
                            break;
                        }

                        $length++;
                    }

                    $maximalFirstStart = $firstStart - $left;
                    $maximalSecondStart = $secondStart - $left;
                    $coveredAlignments[$alignmentKey] = [
                        'first_end' => $maximalFirstStart + $length - 1,
                        'second_end' => $maximalSecondStart + $length - 1,
                    ];

                    if ($firstFileIndex === $secondFileIndex) {
                        $length = min($length, abs($maximalSecondStart - $maximalFirstStart));
                    }

                    $contentFingerprint = $this->fingerprint(
                        $firstTokens,
                        $maximalFirstStart,
                        $length,
                    );
                    $candidateGroupIndexes = $groupIndex[$contentFingerprint] ?? [];
                    $groupPosition = null;

                    foreach ($candidateGroupIndexes as $candidateGroupIndex) {
                        $candidateGroup = $workingGroups[$candidateGroupIndex];

                        if ($candidateGroup['tokens'] !== $length) {
                            continue;
                        }

                        $sameContent = $this->rangesEqual(
                            $files[$candidateGroup['representative_file']]['tokens'],
                            $candidateGroup['representative_start'],
                            $firstTokens,
                            $maximalFirstStart,
                            $length,
                            $tokenComparisons,
                        );

                        if ($sameContent === null) {
                            $incomplete = true;
                            $stop = true;
                            break 3;
                        }

                        if ($sameContent) {
                            $groupPosition = $candidateGroupIndex;
                            break;
                        }
                    }

                    if ($groupPosition === null) {
                        if (count($workingGroups) >= self::MAXIMUM_GROUPS) {
                            $incomplete = true;
                            $stop = true;
                            break 2;
                        }

                        $groupPosition = count($workingGroups);
                        $workingGroups[] = [
                            'tokens' => $length,
                            'representative_file' => $firstFileIndex,
                            'representative_start' => $maximalFirstStart,
                            'locations' => [],
                        ];
                        $candidateGroupIndexes[] = $groupPosition;
                        $groupIndex[$contentFingerprint] = $candidateGroupIndexes;
                    }

                    $workingGroup = $workingGroups[$groupPosition];

                    foreach (
                        [
                            ['file' => $firstFileIndex, 'start' => $maximalFirstStart],
                            ['file' => $secondFileIndex, 'start' => $maximalSecondStart],
                        ] as $location
                    ) {
                        $locationKey = $this->encodeLocation($location['file'], $location['start']);

                        if (isset($workingGroup['locations'][$locationKey])) {
                            continue;
                        }

                        if (
                            $this->overlapsExistingLocation(
                                $workingGroup['locations'],
                                $location['file'],
                                $location['start'],
                                $workingGroup['tokens'],
                            )
                        ) {
                            continue;
                        }

                        if (count($workingGroup['locations']) >= self::MAXIMUM_LOCATIONS_PER_GROUP) {
                            $incomplete = true;
                            continue;
                        }

                        $workingGroup['locations'][$locationKey] = $location;
                    }

                    $workingGroups[$groupPosition] = $workingGroup;
                }
            }
        }

        unset($repeatedWindows, $coveredAlignments, $groupIndex);

        $propagationChecks = 0;
        $groupOrder = array_keys($workingGroups);
        usort(
            $groupOrder,
            static fn (int $left, int $right): int => $workingGroups[$right]['tokens']
                <=> $workingGroups[$left]['tokens'],
        );

        foreach ($groupOrder as $sourceGroupIndex) {
            $sourceGroup = $workingGroups[$sourceGroupIndex];

            foreach ($groupOrder as $targetGroupIndex) {
                $targetGroup = $workingGroups[$targetGroupIndex];

                if ($sourceGroup['tokens'] <= $targetGroup['tokens']) {
                    continue;
                }

                $offsets = [];

                foreach ($targetGroup['locations'] as $targetLocation) {
                    foreach ($sourceGroup['locations'] as $sourceLocation) {
                        $propagationChecks++;

                        if ($propagationChecks > self::MAXIMUM_PROPAGATION_CHECKS) {
                            $incomplete = true;
                            $stop = true;
                            break 4;
                        }

                        if (
                            $targetLocation['file'] === $sourceLocation['file']
                            && $targetLocation['start'] >= $sourceLocation['start']
                            && $targetLocation['start'] + $targetGroup['tokens']
                                <= $sourceLocation['start'] + $sourceGroup['tokens']
                        ) {
                            $offsets[$targetLocation['start'] - $sourceLocation['start']] = true;
                        }
                    }
                }

                foreach (array_keys($offsets) as $offset) {
                    foreach ($sourceGroup['locations'] as $sourceLocation) {
                        $candidate = [
                            'file' => $sourceLocation['file'],
                            'start' => $sourceLocation['start'] + $offset,
                        ];
                        $candidateKey = $this->encodeLocation($candidate['file'], $candidate['start']);

                        if (isset($targetGroup['locations'][$candidateKey])) {
                            continue;
                        }

                        if (
                            $this->overlapsExistingLocation(
                                $targetGroup['locations'],
                                $candidate['file'],
                                $candidate['start'],
                                $targetGroup['tokens'],
                            )
                        ) {
                            continue;
                        }

                        if (count($targetGroup['locations']) >= self::MAXIMUM_LOCATIONS_PER_GROUP) {
                            $incomplete = true;
                            continue;
                        }

                        $targetGroup['locations'][$candidateKey] = $candidate;
                    }
                }

                $workingGroups[$targetGroupIndex] = $targetGroup;
            }
        }

        /** @var list<int> $keptGroupIndexes */
        $keptGroupIndexes = [];

        foreach ($groupOrder as $candidateGroupIndex) {
            $candidateGroup = $workingGroups[$candidateGroupIndex];
            $contained = false;

            foreach ($keptGroupIndexes as $keptGroupIndex) {
                $keptGroup = $workingGroups[$keptGroupIndex];

                if ($keptGroup['tokens'] <= $candidateGroup['tokens']) {
                    continue;
                }

                $allLocationsContained = true;

                foreach ($candidateGroup['locations'] as $candidateLocation) {
                    $locationContained = false;

                    foreach ($keptGroup['locations'] as $keptLocation) {
                        $propagationChecks++;

                        if ($propagationChecks > self::MAXIMUM_PROPAGATION_CHECKS) {
                            $incomplete = true;
                            $stop = true;
                            break 4;
                        }

                        if (
                            $candidateLocation['file'] === $keptLocation['file']
                            && $candidateLocation['start'] >= $keptLocation['start']
                            && $candidateLocation['start'] + $candidateGroup['tokens']
                                <= $keptLocation['start'] + $keptGroup['tokens']
                        ) {
                            $locationContained = true;
                            break;
                        }
                    }

                    if (!$locationContained) {
                        $allLocationsContained = false;
                        break;
                    }
                }

                if ($allLocationsContained) {
                    $contained = true;
                    break;
                }
            }

            if (!$contained) {
                $keptGroupIndexes[] = $candidateGroupIndex;
            }
        }

        if ($stop && $keptGroupIndexes === []) {
            $keptGroupIndexes = $groupOrder;
        }

        /**
         * @var list<array{
         *     tokens: int,
         *     locations: list<array{path: string, start_line: int, end_line: int}>
         * }> $groups
         */
        $groups = [];

        foreach ($keptGroupIndexes as $keptGroupIndex) {
            $workingGroup = $workingGroups[$keptGroupIndex];
            $locations = array_values($workingGroup['locations']);
            usort(
                $locations,
                static function (array $left, array $right) use ($files): int {
                    $pathOrder = strcmp($files[$left['file']]['path'], $files[$right['file']]['path']);

                    return $pathOrder !== 0 ? $pathOrder : $left['start'] <=> $right['start'];
                },
            );
            $reportedLocations = [];

            foreach ($locations as $location) {
                $normalizedFile = $files[$location['file']];
                $endToken = $location['start'] + $workingGroup['tokens'] - 1;
                $reportedLocations[] = [
                    'path' => $normalizedFile['path'],
                    'start_line' => $normalizedFile['start_lines'][$location['start']],
                    'end_line' => $normalizedFile['end_lines'][$endToken],
                ];
            }

            $groups[] = [
                'tokens' => $workingGroup['tokens'],
                'locations' => $reportedLocations,
            ];
        }

        usort($groups, static function (array $left, array $right): int {
            $tokenOrder = $right['tokens'] <=> $left['tokens'];

            if ($tokenOrder !== 0) {
                return $tokenOrder;
            }

            $leftFirst = $left['locations'][0];
            $rightFirst = $right['locations'][0];
            $firstPathOrder = strcmp($leftFirst['path'], $rightFirst['path']);

            if ($firstPathOrder !== 0) {
                return $firstPathOrder;
            }

            $firstLineOrder = $leftFirst['start_line'] <=> $rightFirst['start_line'];

            if ($firstLineOrder !== 0) {
                return $firstLineOrder;
            }

            $leftSecond = $left['locations'][1];
            $rightSecond = $right['locations'][1];
            $secondPathOrder = strcmp($leftSecond['path'], $rightSecond['path']);

            if ($secondPathOrder !== 0) {
                return $secondPathOrder;
            }

            $secondLineOrder = $leftSecond['start_line'] <=> $rightSecond['start_line'];

            return $secondLineOrder !== 0
                ? $secondLineOrder
                : $leftSecond['end_line'] <=> $rightSecond['end_line'];
        });

        $this->report = [
            'groups' => $groups,
            'incomplete' => $incomplete,
            'manifest_files' => $this->manifestFiles,
            'scanned_files' => count($files),
            'scanned_tokens' => $scannedTokens,
            'scanned_windows' => $scannedWindows,
        ];

        return $this->report;
    }

    public function write(bool $debug): void
    {
        fwrite(STDOUT, $this->render($debug));
    }

    public function render(bool $debug): string
    {
        $report = $this->inspect();
        $groupCount = count($report['groups']);
        $minimum = self::MINIMUM_TOKENS;
        $lines = [];

        if ($groupCount === 0 && !$report['incomplete']) {
            return "PASS application duplication advisory: no possible groups (minimum {$minimum} normalized tokens)\n";
        }

        if ($groupCount === 0) {
            $lines[] = 'ADVISORY application duplication scan incomplete: no possible groups found within the bounded scan; application validity is unaffected';
        } else {
            $count = $report['incomplete'] ? "at least {$groupCount}" : (string) $groupCount;
            $noun = $groupCount === 1 ? 'group' : 'groups';
            $scope = $report['incomplete'] ? ' found within an incomplete bounded scan' : '';
            $lines[] = "ADVISORY possible application duplication: {$count} {$noun}{$scope} (minimum {$minimum} normalized tokens); run `phpthis check --debug` for details; application validity is unaffected";
        }

        if (!$debug) {
            return implode("\n", $lines) . "\n";
        }

        $shownGroups = array_slice($report['groups'], 0, self::MAXIMUM_DEBUG_GROUPS);

        foreach ($shownGroups as $groupOffset => $group) {
            $groupNumber = $groupOffset + 1;
            $locationCount = count($group['locations']);
            $lines[] = "ADVISORY duplication group {$groupNumber}: {$group['tokens']} normalized tokens across {$locationCount} locations";

            foreach (array_slice($group['locations'], 0, self::MAXIMUM_DEBUG_LOCATIONS) as $locationOffset => $location) {
                $locationNumber = $locationOffset + 1;
                $path = $this->displayPath($location['path']);
                $lineRange = $location['start_line'] === $location['end_line']
                    ? (string) $location['start_line']
                    : $location['start_line'] . '-' . $location['end_line'];
                $lines[] = "ADVISORY duplication location {$groupNumber}.{$locationNumber}: {$path}:{$lineRange}";
            }

            if ($locationCount > self::MAXIMUM_DEBUG_LOCATIONS) {
                $lines[] = sprintf(
                    'ADVISORY duplication group %d locations truncated: %d of %d shown',
                    $groupNumber,
                    self::MAXIMUM_DEBUG_LOCATIONS,
                    $locationCount,
                );
            }
        }

        if ($groupCount > self::MAXIMUM_DEBUG_GROUPS) {
            $lines[] = sprintf(
                'ADVISORY duplication details truncated: %d of %d groups shown',
                self::MAXIMUM_DEBUG_GROUPS,
                $groupCount,
            );
        }

        if ($report['incomplete']) {
            $lines[] = sprintf(
                'ADVISORY application duplication scan incomplete: fixed resource limit reached after %d of %d files, %d normalized tokens, and %d windows; application validity is unaffected',
                $report['scanned_files'],
                $report['manifest_files'],
                $report['scanned_tokens'],
                $report['scanned_windows'],
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array{
     *     tokens: list<string>,
     *     start_lines: list<int>,
     *     end_lines: list<int>,
     *     truncated: bool
     * }
     */
    private function normalize(string $source): array
    {
        $tokens = [];
        $startLines = [];
        $endLines = [];

        foreach (PhpToken::tokenize($source) as $token) {
            if (in_array($token->id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) {
                continue;
            }

            if (count($tokens) >= self::MAXIMUM_TOKENS_PER_FILE) {
                return ['tokens' => [], 'start_lines' => [], 'end_lines' => [], 'truncated' => true];
            }

            $tokens[] = in_array($token->id, self::VALUE_BEARING_TOKEN_IDS, true)
                ? pack('N2', $token->id, strlen($token->text)) . $token->text
                : pack('N', $token->id);
            $startLines[] = $token->line;
            $endLines[] = $token->line + substr_count($token->text, "\n");
        }

        return [
            'tokens' => $tokens,
            'start_lines' => $startLines,
            'end_lines' => $endLines,
            'truncated' => false,
        ];
    }

    /** @param list<string> $tokens */
    private function fingerprint(array $tokens, int $start, int $length): string
    {
        $bytes = '';
        $end = $start + $length;

        for ($index = $start; $index < $end; $index++) {
            $bytes .= $tokens[$index];
        }

        return hash('sha256', $bytes, true);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function rangesEqual(
        array $left,
        int $leftStart,
        array $right,
        int $rightStart,
        int $length,
        int &$comparisons,
    ): ?bool {
        for ($offset = 0; $offset < $length; $offset++) {
            $equal = $this->tokensEqual(
                $left[$leftStart + $offset],
                $right[$rightStart + $offset],
                $comparisons,
            );

            if ($equal === null || !$equal) {
                return $equal;
            }
        }

        return true;
    }

    private function tokensEqual(string $left, string $right, int &$comparisons): ?bool
    {
        if ($comparisons >= self::MAXIMUM_TOKEN_COMPARISONS) {
            return null;
        }

        $comparisons++;

        return $left === $right;
    }

    private function encodeLocation(int $file, int $start): int
    {
        return ($file * self::LOCATION_STRIDE) + $start;
    }

    /** @return array{int, int} */
    private function decodeLocation(int $location): array
    {
        $file = intdiv($location, self::LOCATION_STRIDE);

        return [$file, $location - ($file * self::LOCATION_STRIDE)];
    }

    /** @param array<int, array{file: int, start: int}> $locations */
    private function overlapsExistingLocation(
        array $locations,
        int $file,
        int $start,
        int $tokens,
    ): bool {
        foreach ($locations as $existingLocation) {
            if (
                $file === $existingLocation['file']
                && max($start, $existingLocation['start'])
                    < min($start + $tokens, $existingLocation['start'] + $tokens)
            ) {
                return true;
            }
        }

        return false;
    }

    private function displayPath(string $path): string
    {
        $truncated = strlen($path) > self::MAXIMUM_DISPLAY_PATH_BYTES;
        $visible = $truncated ? substr($path, 0, self::MAXIMUM_DISPLAY_PATH_BYTES) : $path;
        $encoded = json_encode(
            $visible,
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (!is_string($encoded)) {
            return '"[unprintable path]"';
        }

        return $truncated ? substr($encoded, 0, -1) . '..."' : $encoded;
    }
}
