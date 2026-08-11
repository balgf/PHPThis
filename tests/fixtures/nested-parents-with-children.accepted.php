<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

$root = $argv[1] ?? null;
$databasePath = $argv[2] ?? null;
$budgetValue = $argv[3] ?? null;
$authorizationValue = $argv[4] ?? 'allow';

if (
    !is_string($root)
    || !is_string($databasePath)
    || !is_string($budgetValue)
) {
    throw new RuntimeException(
        'Nested parent fixture requires root, database path, query budget, and authorization arguments.',
    );
}

if (preg_match('/^[1-9][0-9]*$/D', $budgetValue) !== 1) {
    throw new RuntimeException('Nested parent fixture query budget must be a positive integer.');
}

$budgetLimit = filter_var($budgetValue, FILTER_VALIDATE_INT);

if (!is_int($budgetLimit) || $budgetLimit < 1) {
    throw new RuntimeException('Nested parent fixture query budget is outside the supported integer range.');
}

if (!in_array($authorizationValue, ['allow', 'deny'], true)) {
    throw new RuntimeException('Nested parent fixture authorization must be allow or deny.');
}

require $root . '/autoload.php';

$trace = new QueryTrace(1);

if ($authorizationValue === 'deny') {
    fwrite(STDOUT, json_encode([
        'body' => '',
        'budget_exceeded' => false,
        'phase_statements' => [
            'after_load' => 0,
            'after_mapping' => 0,
            'after_encoding' => 0,
        ],
        'trace' => $trace->snapshot(),
    ], JSON_THROW_ON_ERROR));

    return;
}

$connection = Connection::connect(
    'sqlite:' . $databasePath,
    new QueryBudget($budgetLimit),
    $trace,
);
$rows = $connection->selectAllRows(
    <<<'SQL'
        SELECT
            selected_parents.id AS parent_id,
            selected_parents.label AS parent_label,
            selected_parents.child_id,
            children.id AS nested_child_id,
            children.label AS child_label,
            children.public_url AS child_public_url
        FROM (
            SELECT
                parents.id,
                parents.label,
                parents.child_id
            FROM parents
            WHERE parents.tenant_id = :parent_tenant_id
              AND parents.is_visible = :parent_is_visible
              AND parents.authorized_principal_id = :parent_authorized_principal_id
            ORDER BY parents.id
            LIMIT :parent_limit
        ) AS selected_parents
        LEFT JOIN children
         ON children.id = selected_parents.child_id
         AND children.tenant_id = :child_tenant_id
         AND children.is_visible = :child_is_visible
         AND children.authorized_principal_id = :child_authorized_principal_id
        ORDER BY selected_parents.id
        SQL,
    [
        'parent_tenant_id' => 42,
        'parent_is_visible' => 1,
        'parent_authorized_principal_id' => 7,
        'parent_limit' => 50,
        'child_tenant_id' => 42,
        'child_is_visible' => 1,
        'child_authorized_principal_id' => 7,
    ],
);
$afterLoadStatements = $trace->snapshot()['statements'];
$parents = [];

foreach ($rows as $row) {
    $parents[] = acceptedNestedParentItem($row);
}

$afterMappingStatements = $trace->snapshot()['statements'];
$body = json_encode(['data' => $parents], JSON_THROW_ON_ERROR) . "\n";
$afterEncodingStatements = $trace->snapshot()['statements'];

fwrite(STDOUT, json_encode([
    'body' => $body,
    'budget_exceeded' => false,
    'phase_statements' => [
        'after_load' => $afterLoadStatements,
        'after_mapping' => $afterMappingStatements,
        'after_encoding' => $afterEncodingStatements,
    ],
    'trace' => $trace->snapshot(),
], JSON_THROW_ON_ERROR));

/**
 * @param array<string, mixed> $row
 * @return array{
 *     id: non-empty-string,
 *     label: non-empty-string,
 *     child_id: non-empty-string,
 *     child: array{id: non-empty-string, label: non-empty-string, public_url: non-empty-string|null}|null
 * }
 */
function acceptedNestedParentItem(array $row): array
{
    if (
        count($row) !== 6
        || !array_key_exists('parent_id', $row)
        || !array_key_exists('parent_label', $row)
        || !array_key_exists('nested_child_id', $row)
        || !array_key_exists('child_id', $row)
        || !array_key_exists('child_label', $row)
        || !array_key_exists('child_public_url', $row)
    ) {
        throw new UnexpectedValueException('Nested parent row has an incompatible shape.');
    }

    $parentId = acceptedNestedParentUuid($row['parent_id'], 'parent_id');
    $parentLabel = acceptedNestedParentText($row['parent_label'], 'parent_label', 160);
    $relatedChildId = acceptedNestedParentUuid($row['child_id'], 'child_id');
    $childIdValue = $row['nested_child_id'];
    $childLabelValue = $row['child_label'];
    $childPublicUrlValue = $row['child_public_url'];
    $child = null;

    if ($childIdValue === null) {
        if ($childLabelValue !== null || $childPublicUrlValue !== null) {
            throw new UnexpectedValueException('Absent nested child fields must all be null.');
        }
    } else {
        $childId = acceptedNestedParentUuid($childIdValue, 'child_id');

        if ($childId !== $relatedChildId) {
            throw new UnexpectedValueException('Nested child id must match child_id.');
        }

        $childLabel = acceptedNestedParentText(
            $childLabelValue,
            'child_label',
            160,
        );
        $childPublicUrl = $childPublicUrlValue === null
            ? null
            : acceptedNestedParentPublicUrl($childPublicUrlValue);
        $child = [
            'id' => $childId,
            'label' => $childLabel,
            'public_url' => $childPublicUrl,
        ];
    }

    return [
        'id' => $parentId,
        'label' => $parentLabel,
        'child_id' => $relatedChildId,
        'child' => $child,
    ];
}

/** @return non-empty-string */
function acceptedNestedParentUuid(mixed $value, string $field): string
{
    if (
        !is_string($value)
        || preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $value,
        ) !== 1
    ) {
        throw new UnexpectedValueException("Nested parent {$field} is not a canonical lowercase UUID.");
    }

    return $value;
}

/** @return non-empty-string */
function acceptedNestedParentText(mixed $value, string $field, int $maximumBytes): string
{
    if (
        !is_string($value)
        || $value === ''
        || strlen($value) > $maximumBytes
        || preg_match('//u', $value) !== 1
    ) {
        throw new UnexpectedValueException("Nested parent {$field} is outside its UTF-8 byte bound.");
    }

    return $value;
}

/** @return non-empty-string */
function acceptedNestedParentPublicUrl(mixed $value): string
{
    if (
        !is_string($value)
        || $value === ''
        || strlen($value) > 2_048
        || preg_match('/^https:\/\/[!-~]+$/D', $value) !== 1
    ) {
        throw new UnexpectedValueException('Nested parent child_public_url is not a bounded HTTPS URL.');
    }

    return $value;
}
