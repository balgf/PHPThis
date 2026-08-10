<?php

declare(strict_types=1);

const AGENT_EVALUATION_MAX_JSON_BYTES = 1_048_576;
const AGENT_EVALUATION_MAX_ARTIFACT_BYTES = 16_777_216;
const AGENT_EVALUATION_MAX_JSON_DEPTH = 64;

/** @return array<string, mixed> */
function agentEvaluationJsonFile(string $path): array
{
    return agentEvaluationValueObject(agentEvaluationJsonValueFile($path), "JSON file {$path}");
}

function agentEvaluationJsonValueFile(string $path): mixed
{
    agentEvaluationRequireBoundedFile($path, AGENT_EVALUATION_MAX_JSON_BYTES, "JSON file {$path}");
    $source = file_get_contents($path);

    if (!is_string($source)) {
        throw new RuntimeException("Unable to read JSON file: {$path}.");
    }

    $decoded = json_decode($source, false, AGENT_EVALUATION_MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
    $offset = 0;
    agentEvaluationScanJsonValue($source, $offset);
    agentEvaluationSkipJsonWhitespace($source, $offset);

    if ($offset !== strlen($source)) {
        throw new RuntimeException("JSON file {$path} could not be scanned completely.");
    }

    return $decoded;
}

function agentEvaluationScanJsonValue(string $source, int &$offset): void
{
    agentEvaluationSkipJsonWhitespace($source, $offset);
    $character = $source[$offset] ?? '';

    if ($character === '{') {
        agentEvaluationScanJsonObject($source, $offset);
        return;
    }

    if ($character === '[') {
        agentEvaluationScanJsonArray($source, $offset);
        return;
    }

    if ($character === '"') {
        agentEvaluationScanJsonString($source, $offset);
        return;
    }

    $start = $offset;
    $length = strlen($source);

    while ($offset < $length && !str_contains(" \t\r\n,]}", $source[$offset])) {
        $offset++;
    }

    if ($offset === $start) {
        throw new RuntimeException('JSON value scanner encountered an invalid token.');
    }
}

function agentEvaluationScanJsonObject(string $source, int &$offset): void
{
    $offset++;
    agentEvaluationSkipJsonWhitespace($source, $offset);

    if (($source[$offset] ?? '') === '}') {
        $offset++;
        return;
    }

    $seen = [];

    while (true) {
        agentEvaluationSkipJsonWhitespace($source, $offset);
        $name = agentEvaluationScanJsonString($source, $offset);
        $identity = strlen($name) . ':' . $name;

        if (isset($seen[$identity])) {
            throw new RuntimeException('JSON input contains a duplicate object name.');
        }

        $seen[$identity] = true;
        agentEvaluationSkipJsonWhitespace($source, $offset);

        if (($source[$offset] ?? '') !== ':') {
            throw new RuntimeException('JSON object scanner expected a name separator.');
        }

        $offset++;
        agentEvaluationScanJsonValue($source, $offset);
        agentEvaluationSkipJsonWhitespace($source, $offset);
        $separator = $source[$offset] ?? '';

        if ($separator === '}') {
            $offset++;
            return;
        }

        if ($separator !== ',') {
            throw new RuntimeException('JSON object scanner expected an item separator.');
        }

        $offset++;
    }
}

function agentEvaluationScanJsonArray(string $source, int &$offset): void
{
    $offset++;
    agentEvaluationSkipJsonWhitespace($source, $offset);

    if (($source[$offset] ?? '') === ']') {
        $offset++;
        return;
    }

    while (true) {
        agentEvaluationScanJsonValue($source, $offset);
        agentEvaluationSkipJsonWhitespace($source, $offset);
        $separator = $source[$offset] ?? '';

        if ($separator === ']') {
            $offset++;
            return;
        }

        if ($separator !== ',') {
            throw new RuntimeException('JSON array scanner expected an item separator.');
        }

        $offset++;
    }
}

function agentEvaluationScanJsonString(string $source, int &$offset): string
{
    if (($source[$offset] ?? '') !== '"') {
        throw new RuntimeException('JSON object scanner expected a string name.');
    }

    $start = $offset;
    $length = strlen($source);
    $offset++;

    while ($offset < $length) {
        $character = $source[$offset];

        if ($character === '"') {
            $offset++;
            $value = json_decode(
                substr($source, $start, $offset - $start),
                false,
                2,
                JSON_THROW_ON_ERROR,
            );

            if (!is_string($value)) {
                throw new RuntimeException('JSON string scanner did not decode one string.');
            }

            return $value;
        }

        if ($character === '\\') {
            $escape = $source[$offset + 1] ?? '';
            $offset += $escape === 'u' ? 6 : 2;
            continue;
        }

        $offset++;
    }

    throw new RuntimeException('JSON string scanner reached an unterminated string.');
}

function agentEvaluationSkipJsonWhitespace(string $source, int &$offset): void
{
    $length = strlen($source);

    while ($offset < $length && str_contains(" \t\r\n", $source[$offset])) {
        $offset++;
    }
}

function agentEvaluationContainedArtifactPath(string $root, string $relativePath, string $owner): string
{
    $candidate = $root . '/' . $relativePath;

    if (is_link($candidate)) {
        throw new RuntimeException("{$owner} must not be a symlink.");
    }

    $path = realpath($candidate);
    $prefix = $root === DIRECTORY_SEPARATOR ? $root : $root . DIRECTORY_SEPARATOR;

    if (!is_string($path) || !str_starts_with($path, $prefix)) {
        throw new RuntimeException("{$owner} must remain inside the run artifact root.");
    }

    return $path;
}

function agentEvaluationRequireBoundedFile(string $path, int $maximumBytes, string $owner): void
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException("{$owner} must resolve to one regular non-symlink file.");
    }

    $bytes = filesize($path);

    if (!is_int($bytes) || $bytes > $maximumBytes) {
        throw new RuntimeException("{$owner} exceeds its bounded file size.");
    }
}

/**
 * @param mixed $value
 * @return array<string, mixed>
 */
function agentEvaluationValueObject(mixed $value, string $owner): array
{
    if ($value instanceof stdClass) {
        $value = get_object_vars($value);
    } elseif (!is_array($value) || array_is_list($value)) {
        throw new RuntimeException("{$owner} must be a JSON object.");
    }

    foreach (array_keys($value) as $key) {
        if (!is_string($key)) {
            throw new RuntimeException("{$owner} must use string keys.");
        }
    }

    /** @var array<string, mixed> $value */
    return $value;
}

function agentEvaluationRequireJsonObjectValue(mixed $value, string $owner): void
{
    if ($value instanceof stdClass) {
        return;
    }

    if (!is_array($value) || array_is_list($value)) {
        throw new RuntimeException("{$owner} must be a JSON object.");
    }
}

/**
 * @param array<string, mixed> $object
 * @param list<string> $keys
 */
function agentEvaluationRequireExactKeys(array $object, array $keys, string $owner): void
{
    $actual = array_keys($object);
    sort($actual, SORT_STRING);
    sort($keys, SORT_STRING);

    if ($actual !== $keys) {
        throw new RuntimeException("{$owner} must contain exactly: " . implode(', ', $keys) . '.');
    }
}

/** @param array<string, mixed> $object */
function agentEvaluationRequireString(array $object, string $key, string $owner): string
{
    $value = $object[$key] ?? null;

    if (!is_string($value)) {
        throw new RuntimeException("{$owner} field {$key} must be a string.");
    }

    return $value;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequireNonEmptyString(array $object, string $key, string $owner): string
{
    $value = agentEvaluationRequireString($object, $key, $owner);

    if ($value === '') {
        throw new RuntimeException("{$owner} field {$key} must not be empty.");
    }

    return $value;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequireNullableString(array $object, string $key, string $owner): ?string
{
    $value = $object[$key] ?? null;

    if ($value !== null && !is_string($value)) {
        throw new RuntimeException("{$owner} field {$key} must be a string or null.");
    }

    return $value;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequireInteger(array $object, string $key, string $owner): int
{
    $value = $object[$key] ?? null;

    if (!is_int($value)) {
        throw new RuntimeException("{$owner} field {$key} must be an integer.");
    }

    return $value;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequirePositiveInteger(array $object, string $key, string $owner): int
{
    $value = agentEvaluationRequireInteger($object, $key, $owner);

    if ($value < 1) {
        throw new RuntimeException("{$owner} field {$key} must be positive.");
    }

    return $value;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequireNonNegativeInteger(array $object, string $key, string $owner): int
{
    $value = agentEvaluationRequireInteger($object, $key, $owner);

    if ($value < 0) {
        throw new RuntimeException("{$owner} field {$key} must not be negative.");
    }

    return $value;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequireNullableNonNegativeInteger(array $object, string $key, string $owner): ?int
{
    $value = $object[$key] ?? null;

    if ($value === null) {
        return null;
    }

    if (!is_int($value) || $value < 0) {
        throw new RuntimeException("{$owner} field {$key} must be a non-negative integer or null.");
    }

    return $value;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequireBoolean(array $object, string $key, string $owner): bool
{
    $value = $object[$key] ?? null;

    if (!is_bool($value)) {
        throw new RuntimeException("{$owner} field {$key} must be a boolean.");
    }

    return $value;
}

/**
 * @param array<string, mixed> $object
 * @return array<string, mixed>
 */
function agentEvaluationRequireObject(array $object, string $key, string $owner): array
{
    return agentEvaluationValueObject($object[$key] ?? null, "{$owner} field {$key}");
}

/**
 * @param array<string, mixed> $object
 * @return list<mixed>
 */
function agentEvaluationRequireList(array $object, string $key, string $owner): array
{
    $value = $object[$key] ?? null;

    if (!is_array($value) || !array_is_list($value)) {
        throw new RuntimeException("{$owner} field {$key} must be a list.");
    }

    return $value;
}

/**
 * @param array<string, mixed> $object
 * @return list<string>
 */
function agentEvaluationRequireStringList(array $object, string $key, string $owner): array
{
    $values = agentEvaluationRequireList($object, $key, $owner);
    $strings = [];

    foreach ($values as $value) {
        if (!is_string($value)) {
            throw new RuntimeException("{$owner} field {$key} must contain only strings.");
        }

        $strings[] = $value;
    }

    return $strings;
}

/** @param array<string, mixed> $object */
function agentEvaluationRequirePathList(array $object, string $key, string $owner): void
{
    $paths = agentEvaluationRequireStringList($object, $key, $owner);

    if ($paths === []) {
        throw new RuntimeException("{$owner} field {$key} must contain at least one path.");
    }

    if (count(array_unique($paths, SORT_STRING)) !== count($paths)) {
        throw new RuntimeException("{$owner} field {$key} must not contain duplicate paths.");
    }

    foreach ($paths as $path) {
        agentEvaluationRequireRelativePath($path, "{$owner} field {$key}");
    }
}

function agentEvaluationRequireRelativePath(string $path, string $owner): string
{
    if (
        $path === ''
        || str_starts_with($path, '/')
        || str_contains($path, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        || preg_match('~(?:\A|/)\.\.?(/|\z)~D', $path) === 1
        || preg_match('~//~', $path) === 1
    ) {
        throw new RuntimeException("{$owner} must be one normalized relative path.");
    }

    return $path;
}

function agentEvaluationRequireHash(string $hash, string $owner): string
{
    if (preg_match('/\A[a-f0-9]{64}\z/D', $hash) !== 1) {
        throw new RuntimeException("{$owner} must be one lowercase SHA-256 value.");
    }

    return $hash;
}

function agentEvaluationRequireFileHash(string $path, string $expected, string $owner): void
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException("{$owner} must resolve to one regular repository file.");
    }

    $actual = hash_file('sha256', $path);

    if (!is_string($actual) || !hash_equals($expected, $actual)) {
        throw new RuntimeException("{$owner} SHA-256 does not match its recorded hash.");
    }
}

function agentEvaluationFileHash(string $path, string $owner): string
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException("{$owner} must resolve to one regular repository file.");
    }

    $hash = hash_file('sha256', $path);

    if (!is_string($hash)) {
        throw new RuntimeException("Unable to hash {$owner}.");
    }

    return $hash;
}

/** @param mixed $value */
function agentEvaluationJson(mixed $value): string
{
    return json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ) . "\n";
}
