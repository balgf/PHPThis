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
        'Nested workspace fixture requires root, database path, query budget, and authorization arguments.',
    );
}

if (preg_match('/^[1-9][0-9]*$/D', $budgetValue) !== 1) {
    throw new RuntimeException('Nested workspace fixture query budget must be a positive integer.');
}

$budgetLimit = filter_var($budgetValue, FILTER_VALIDATE_INT);

if (!is_int($budgetLimit) || $budgetLimit < 1) {
    throw new RuntimeException('Nested workspace fixture query budget is outside the supported integer range.');
}

if (!in_array($authorizationValue, ['allow', 'deny'], true)) {
    throw new RuntimeException('Nested workspace fixture authorization must be allow or deny.');
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
            selected_workspaces.id AS workspace_id,
            selected_workspaces.name AS workspace_name,
            selected_workspaces.created_by_user_id,
            creators.id AS creator_id,
            creators.display_name AS creator_display_name,
            creators.avatar_url AS creator_avatar_url
        FROM (
            SELECT
                workspaces.id,
                workspaces.name,
                workspaces.created_by_user_id
            FROM workspaces
            WHERE workspaces.tenant_id = :workspace_tenant_id
              AND workspaces.is_visible = :workspace_is_visible
              AND workspaces.authorized_principal_id = :workspace_authorized_principal_id
            ORDER BY workspaces.id
            LIMIT :parent_limit
        ) AS selected_workspaces
        LEFT JOIN users AS creators
         ON creators.id = selected_workspaces.created_by_user_id
         AND creators.tenant_id = :creator_tenant_id
         AND creators.is_visible = :creator_is_visible
         AND creators.authorized_principal_id = :creator_authorized_principal_id
        ORDER BY selected_workspaces.id
        SQL,
    [
        'workspace_tenant_id' => 42,
        'workspace_is_visible' => 1,
        'workspace_authorized_principal_id' => 7,
        'parent_limit' => 50,
        'creator_tenant_id' => 42,
        'creator_is_visible' => 1,
        'creator_authorized_principal_id' => 7,
    ],
);
$afterLoadStatements = $trace->snapshot()['statements'];
$workspaces = [];

foreach ($rows as $row) {
    $workspaces[] = acceptedNestedWorkspaceItem($row);
}

$afterMappingStatements = $trace->snapshot()['statements'];
$body = json_encode(['data' => $workspaces], JSON_THROW_ON_ERROR) . "\n";
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
 *     name: non-empty-string,
 *     created_by_user_id: non-empty-string,
 *     creator: array{id: non-empty-string, display_name: non-empty-string, avatar_url: non-empty-string|null}|null
 * }
 */
function acceptedNestedWorkspaceItem(array $row): array
{
    if (
        count($row) !== 6
        || !array_key_exists('workspace_id', $row)
        || !array_key_exists('workspace_name', $row)
        || !array_key_exists('created_by_user_id', $row)
        || !array_key_exists('creator_id', $row)
        || !array_key_exists('creator_display_name', $row)
        || !array_key_exists('creator_avatar_url', $row)
    ) {
        throw new UnexpectedValueException('Nested workspace row has an incompatible shape.');
    }

    $workspaceId = acceptedNestedWorkspaceUuid($row['workspace_id'], 'workspace_id');
    $workspaceName = acceptedNestedWorkspaceText($row['workspace_name'], 'workspace_name', 160);
    $createdByUserId = acceptedNestedWorkspaceUuid($row['created_by_user_id'], 'created_by_user_id');
    $creatorIdValue = $row['creator_id'];
    $creatorDisplayNameValue = $row['creator_display_name'];
    $creatorAvatarUrlValue = $row['creator_avatar_url'];
    $creator = null;

    if ($creatorIdValue === null) {
        if ($creatorDisplayNameValue !== null || $creatorAvatarUrlValue !== null) {
            throw new UnexpectedValueException('Absent nested creator fields must all be null.');
        }
    } else {
        $creatorId = acceptedNestedWorkspaceUuid($creatorIdValue, 'creator_id');

        if ($creatorId !== $createdByUserId) {
            throw new UnexpectedValueException('Nested creator id must match created_by_user_id.');
        }

        $creatorDisplayName = acceptedNestedWorkspaceText(
            $creatorDisplayNameValue,
            'creator_display_name',
            160,
        );
        $creatorAvatarUrl = $creatorAvatarUrlValue === null
            ? null
            : acceptedNestedWorkspaceAvatarUrl($creatorAvatarUrlValue);
        $creator = [
            'id' => $creatorId,
            'display_name' => $creatorDisplayName,
            'avatar_url' => $creatorAvatarUrl,
        ];
    }

    return [
        'id' => $workspaceId,
        'name' => $workspaceName,
        'created_by_user_id' => $createdByUserId,
        'creator' => $creator,
    ];
}

/** @return non-empty-string */
function acceptedNestedWorkspaceUuid(mixed $value, string $field): string
{
    if (
        !is_string($value)
        || preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $value,
        ) !== 1
    ) {
        throw new UnexpectedValueException("Nested workspace {$field} is not a canonical lowercase UUID.");
    }

    return $value;
}

/** @return non-empty-string */
function acceptedNestedWorkspaceText(mixed $value, string $field, int $maximumBytes): string
{
    if (
        !is_string($value)
        || $value === ''
        || strlen($value) > $maximumBytes
        || preg_match('//u', $value) !== 1
    ) {
        throw new UnexpectedValueException("Nested workspace {$field} is outside its UTF-8 byte bound.");
    }

    return $value;
}

/** @return non-empty-string */
function acceptedNestedWorkspaceAvatarUrl(mixed $value): string
{
    if (
        !is_string($value)
        || $value === ''
        || strlen($value) > 2_048
        || preg_match('/^https:\/\/[!-~]+$/D', $value) !== 1
    ) {
        throw new UnexpectedValueException('Nested workspace creator_avatar_url is not a bounded HTTPS URL.');
    }

    return $value;
}
