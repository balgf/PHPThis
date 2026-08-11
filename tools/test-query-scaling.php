<?php

declare(strict_types=1);

use Example\Users\ListUsers\ListUsersHandler;
use Example\Users\ListUsers\UserActivitySummary;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\Request;

require dirname(__DIR__) . '/autoload.php';
require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';

use PHPThis\Verification\SyntaxProfile;

$root = dirname(__DIR__);
$fixtureRelativePath = 'tests/fixtures/list-users.n-plus-one.php.fixture';
$fixturePath = $root . '/' . $fixtureRelativePath;
$fixtureSource = file_get_contents($fixturePath);
$nestedAcceptedRelativePath = 'tests/fixtures/workspaces-with-creators.accepted.php';
$nestedAcceptedPath = $root . '/' . $nestedAcceptedRelativePath;
$nestedRejectedRelativePath = 'tests/fixtures/workspaces-with-creators.n-plus-one.php.fixture';
$nestedRejectedPath = $root . '/' . $nestedRejectedRelativePath;
$nestedAcceptedSource = file_get_contents($nestedAcceptedPath);
$nestedRejectedSource = file_get_contents($nestedRejectedPath);

if (!is_string($fixtureSource)) {
    throw new RuntimeException('Unable to read the N+1 negative-control fixture.');
}

if (!is_string($nestedAcceptedSource) || !is_string($nestedRejectedSource)) {
    throw new RuntimeException('Unable to read the nested workspace relationship fixtures.');
}

$profileFailures = SyntaxProfile::failures($fixtureSource, $fixtureRelativePath);
$expectedProfileFailures = [
    'PHT003 tests/fixtures/list-users.n-plus-one.php.fixture:45 calls a database method inside a loop.',
];

requireScalingProof(
    $profileFailures === $expectedProfileFailures,
    'The N+1 negative control must be rejected by exactly one stable PHT003 diagnostic.',
);

$nestedAcceptedProfileFailures = SyntaxProfile::failures(
    $nestedAcceptedSource,
    $nestedAcceptedRelativePath,
);
$nestedRejectedProfileFailures = SyntaxProfile::failures(
    $nestedRejectedSource,
    $nestedRejectedRelativePath,
);
$expectedNestedRejectedProfileFailures = [
    'PHT003 tests/fixtures/workspaces-with-creators.n-plus-one.php.fixture:61 calls a database method inside a loop.',
];

requireScalingProof(
    $nestedAcceptedProfileFailures === [],
    'The accepted nested workspace fixture must pass the Strict Profile.',
);
requireScalingProof(
    $nestedRejectedProfileFailures === $expectedNestedRejectedProfileFailures,
    'The nested workspace N+1 control must be rejected by exactly one stable PHT003 diagnostic.',
);

$nestedMappingOffset = strpos($nestedAcceptedSource, 'foreach ($rows as $row) {');
$nestedEncodingOffset = strpos($nestedAcceptedSource, '$body = json_encode(');
$nestedAuthorizationOffset = strpos(
    $nestedAcceptedSource,
    "if (\$authorizationValue === 'deny') {",
);
$nestedConnectionOffset = strpos($nestedAcceptedSource, '$connection = Connection::connect(');

if (
    !is_int($nestedMappingOffset)
    || !is_int($nestedEncodingOffset)
    || !is_int($nestedAuthorizationOffset)
    || !is_int($nestedConnectionOffset)
    || $nestedAuthorizationOffset >= $nestedConnectionOffset
    || $nestedMappingOffset >= $nestedEncodingOffset
) {
    throw new RuntimeException('The accepted nested workspace mapping and encoding phases changed.');
}

$nestedMappingAndEncodingSource = substr($nestedAcceptedSource, $nestedMappingOffset);

requireScalingProof(
    substr_count($nestedAcceptedSource, '->selectAllRows(') === 1
    && substr_count($nestedAcceptedSource, '->selectOneRow(') === 0
    && substr_count($nestedAcceptedSource, '->executeStatement(') === 0
    && !str_contains($nestedAcceptedSource, 'SELECT *')
    && str_contains($nestedAcceptedSource, 'workspaces.tenant_id = :workspace_tenant_id')
    && str_contains($nestedAcceptedSource, 'workspaces.is_visible = :workspace_is_visible')
    && str_contains(
        $nestedAcceptedSource,
        'workspaces.authorized_principal_id = :workspace_authorized_principal_id',
    )
    && str_contains($nestedAcceptedSource, 'creators.tenant_id = :creator_tenant_id')
    && str_contains($nestedAcceptedSource, 'creators.is_visible = :creator_is_visible')
    && str_contains(
        $nestedAcceptedSource,
        'creators.authorized_principal_id = :creator_authorized_principal_id',
    )
    && str_contains($nestedAcceptedSource, 'LIMIT :parent_limit')
    && !str_contains($nestedAcceptedSource, 'workspaces.slug')
    && !str_contains($nestedAcceptedSource, 'creators.email')
    && !str_contains($nestedAcceptedSource, 'creators.status')
    && !str_contains($nestedAcceptedSource, 'creators.role'),
    'The accepted nested workspace fixture must retain one minimized tenant-visible JOIN query.',
);
requireScalingProof(
    !str_contains($nestedMappingAndEncodingSource, '->selectAllRows(')
    && !str_contains($nestedMappingAndEncodingSource, '->selectOneRow(')
    && !str_contains($nestedMappingAndEncodingSource, '->executeStatement('),
    'Nested workspace mapping and JSON encoding must perform zero database calls.',
);

$smallDatabase = createScalingDatabase($root, 'small', 2);
$largeDatabase = createScalingDatabase($root, 'large', 50);
$smallAccepted = runAcceptedRead($smallDatabase);
$largeAccepted = runAcceptedRead($largeDatabase);
$smallRejected = runRejectedRead($root, $fixturePath, $smallDatabase, 10);
$largeRejected = runRejectedRead($root, $fixturePath, $largeDatabase, 60);
$limitedRejected = runRejectedRead($root, $fixturePath, $largeDatabase, 3);
$continuationDatabase = createScalingDatabase($root, 'continuation', 125);
$firstPage = runAcceptedRead($continuationDatabase);
$secondPage = runAcceptedRead($continuationDatabase, '50');
$thirdPage = runAcceptedRead($continuationDatabase, '100');
$firstPageData = acceptedPageData($firstPage['body']);
$secondPageData = acceptedPageData($secondPage['body']);
$thirdPageData = acceptedPageData($thirdPage['body']);
$continuedIds = [
    ...$firstPageData['ids'],
    ...$secondPageData['ids'],
    ...$thirdPageData['ids'],
];
$continuedEventCounts = [
    ...$firstPageData['event_counts'],
    ...$secondPageData['event_counts'],
    ...$thirdPageData['event_counts'],
];

requireScalingProof($smallAccepted['body'] === $smallRejected['body'], 'Small accepted and N+1 outputs differ.');
requireScalingProof($largeAccepted['body'] === $largeRejected['body'], 'Large accepted and N+1 outputs differ.');
requireScalingProof($smallAccepted['statements'] === 1, 'Accepted small read must execute one statement.');
requireScalingProof($largeAccepted['statements'] === 1, 'Accepted large read must execute one statement.');
requireScalingProof($smallAccepted['maximum_executions'] === 1, 'Accepted small read repeated a statement.');
requireScalingProof($largeAccepted['maximum_executions'] === 1, 'Accepted large read repeated a statement.');
requireScalingProof(!$smallAccepted['truncated'], 'Accepted small trace was truncated.');
requireScalingProof(!$largeAccepted['truncated'], 'Accepted large trace was truncated.');
requireScalingProof($smallRejected['statements'] === 3, 'Small N+1 control must execute three statements.');
requireScalingProof($largeRejected['statements'] === 51, 'Large N+1 control must execute 51 statements.');
requireScalingProof($smallRejected['maximum_executions'] === 2, 'Small N+1 child query count changed.');
requireScalingProof($largeRejected['maximum_executions'] === 50, 'Large N+1 child query count changed.');
requireScalingProof(!$smallRejected['budget_exceeded'], 'Small N+1 control unexpectedly exceeded its budget.');
requireScalingProof(!$largeRejected['budget_exceeded'], 'Large N+1 control unexpectedly exceeded its budget.');
requireScalingProof($limitedRejected['budget_exceeded'], 'The bounded N+1 control did not exceed its budget.');
requireScalingProof($limitedRejected['statements'] === 3, 'Budget rejection must not enter the query trace.');
requireScalingProof($limitedRejected['maximum_executions'] === 2, 'Limited N+1 trace shape changed.');
requireScalingProof($firstPageData['ids'] === range(1, 50), 'First continuation page changed.');
requireScalingProof($secondPageData['ids'] === range(51, 100), 'Second continuation page changed.');
requireScalingProof($thirdPageData['ids'] === range(101, 125), 'Final continuation page changed.');
requireScalingProof($firstPageData['next_after_user_id'] === '50', 'First continuation cursor changed.');
requireScalingProof($secondPageData['next_after_user_id'] === '100', 'Second continuation cursor changed.');
requireScalingProof($thirdPageData['next_after_user_id'] === null, 'Final continuation cursor must be null.');
requireScalingProof($continuedIds === range(1, 125), 'Continuation introduced a gap or ordering error.');
requireScalingProof(count(array_unique($continuedIds)) === 125, 'Continuation returned a duplicate user.');
requireScalingProof(array_unique($continuedEventCounts) === [2], 'Continuation aggregate output changed.');

foreach ([$firstPage, $secondPage, $thirdPage] as $page) {
    requireScalingProof($page['statements'] === 1, 'Each accepted page must execute one statement.');
    requireScalingProof($page['maximum_executions'] === 1, 'An accepted page repeated a statement.');
    requireScalingProof(!$page['truncated'], 'An accepted page trace was truncated.');
}

$nestedEmptyDatabase = createNestedWorkspaceDatabase($root, 'nested-empty', 0);
$nestedOneDatabase = createNestedWorkspaceDatabase($root, 'nested-one', 1);
$nestedMaximumDatabase = createNestedWorkspaceDatabase($root, 'nested-maximum', 50);
$nestedEmptyAccepted = runNestedWorkspaceFixture($root, $nestedAcceptedPath, $nestedEmptyDatabase, 1);
$nestedOneAccepted = runNestedWorkspaceFixture($root, $nestedAcceptedPath, $nestedOneDatabase, 1);
$nestedMaximumAccepted = runNestedWorkspaceFixture(
    $root,
    $nestedAcceptedPath,
    $nestedMaximumDatabase,
    1,
);
$nestedDenied = runNestedWorkspaceFixture(
    $root,
    $nestedAcceptedPath,
    $nestedMaximumDatabase,
    1,
    'deny',
);
$nestedEmptyRejected = runNestedWorkspaceFixture($root, $nestedRejectedPath, $nestedEmptyDatabase, 1);
$nestedOneRejected = runNestedWorkspaceFixture($root, $nestedRejectedPath, $nestedOneDatabase, 2);
$nestedMaximumRejected = runNestedWorkspaceFixture(
    $root,
    $nestedRejectedPath,
    $nestedMaximumDatabase,
    51,
);
$nestedBudgetRejected = runNestedWorkspaceFixture(
    $root,
    $nestedRejectedPath,
    $nestedMaximumDatabase,
    3,
);
$nestedEmptyData = decodeNestedWorkspacePage($nestedEmptyAccepted['body']);
$nestedOneData = decodeNestedWorkspacePage($nestedOneAccepted['body']);
$nestedMaximumData = decodeNestedWorkspacePage($nestedMaximumAccepted['body']);
$workspaceOneId = nestedWorkspaceFixtureUuid('20000000', 1);
$creatorOneId = nestedWorkspaceFixtureUuid('10000000', 1);
$expectedOneData = [[
    'id' => $workspaceOneId,
    'name' => 'Workspace 1',
    'created_by_user_id' => $creatorOneId,
    'creator' => [
        'id' => $creatorOneId,
        'display_name' => 'Creator 1',
        'avatar_url' => 'https://example.com/avatars/1.png',
    ],
]];
$expectedMaximumIds = [];

foreach (range(1, 50) as $sequence) {
    $expectedMaximumIds[] = nestedWorkspaceFixtureUuid('20000000', $sequence);
}

$actualMaximumIds = [];

foreach ($nestedMaximumData as $workspace) {
    $actualMaximumIds[] = $workspace['id'];
}

$reorderedNestedData = decodeNestedWorkspacePage(
    '{"data":[{"creator":{"avatar_url":"https://example.com/avatars/1.png",'
    . '"display_name":"Creator 1","id":"' . $creatorOneId . '"},'
    . '"created_by_user_id":"' . $creatorOneId . '","name":"Workspace 1",'
    . '"id":"' . $workspaceOneId . '"}]}' . "\n",
);

requireScalingProof($nestedEmptyData === [], 'The empty nested workspace page changed.');
requireScalingProof(
    $nestedDenied['body'] === ''
    && $nestedDenied['statements'] === 0
    && $nestedDenied['maximum_executions'] === 0
    && $nestedDenied['phase_statements'] === [
        'after_load' => 0,
        'after_mapping' => 0,
        'after_encoding' => 0,
    ]
    && !$nestedDenied['budget_exceeded']
    && !$nestedDenied['truncated'],
    'Nested workspace authorization denial must perform zero database work.',
);
requireScalingProof(
    $nestedEmptyAccepted['body'] === "{\"data\":[]}\n",
    'The empty nested workspace body changed.',
);
requireScalingProof($nestedOneData === $expectedOneData, 'The one-parent nested workspace page changed.');
requireScalingProof(
    $reorderedNestedData === $expectedOneData,
    'Nested workspace decoding must ignore JSON object-member order.',
);
requireScalingProof(count($nestedMaximumData) === 50, 'The nested workspace page maximum changed.');
requireScalingProof(
    $actualMaximumIds === $expectedMaximumIds,
    'Tenant or visibility filtering changed the bounded parent order.',
);
requireScalingProof(
    ($nestedMaximumData[1]['created_by_user_id'] ?? null)
        === nestedWorkspaceFixtureUuid('10000000', 2)
    && $nestedMaximumData[1]['creator'] === null,
    'A hidden creator must remain an explicit null child.',
);
requireScalingProof(
    ($nestedMaximumData[3]['created_by_user_id'] ?? null)
        === nestedWorkspaceFixtureUuid('10000000', 50)
    && $nestedMaximumData[3]['creator'] === null,
    'A cross-tenant creator must remain an explicit null child.',
);
requireScalingProof(
    ($nestedMaximumData[2]['created_by_user_id'] ?? null)
        === nestedWorkspaceFixtureUuid('10000000', 3)
    && $nestedMaximumData[2]['creator'] === null,
    'A creator denied by the fixed principal predicate must remain an explicit null child.',
);
requireScalingProof(
    ($nestedMaximumData[4]['creator']['id'] ?? null)
        === ($nestedMaximumData[4]['created_by_user_id'] ?? null),
    'A present nested creator id must equal created_by_user_id.',
);
requireScalingProof(
    $nestedEmptyAccepted['body'] === $nestedEmptyRejected['body']
    && $nestedOneAccepted['body'] === $nestedOneRejected['body']
    && $nestedMaximumAccepted['body'] === $nestedMaximumRejected['body'],
    'Accepted JOIN and nested N+1 control outputs differ.',
);

foreach ([$nestedEmptyAccepted, $nestedOneAccepted, $nestedMaximumAccepted] as $accepted) {
    requireScalingProof($accepted['statements'] === 1, 'Each accepted nested page must execute one statement.');
    requireScalingProof(
        $accepted['phase_statements'] === [
            'after_load' => 1,
            'after_mapping' => 1,
            'after_encoding' => 1,
        ],
        'Accepted nested mapping or encoding added a database statement.',
    );
    requireScalingProof(
        $accepted['maximum_executions'] === 1,
        'An accepted nested page repeated a statement.',
    );
    requireScalingProof(!$accepted['budget_exceeded'], 'An accepted nested page exceeded its budget.');
    requireScalingProof(!$accepted['truncated'], 'An accepted nested page trace was truncated.');
}

requireScalingProof($nestedEmptyRejected['statements'] === 1, 'Empty N+1 control cost changed.');
requireScalingProof($nestedOneRejected['statements'] === 2, 'One-parent N+1 control cost changed.');
requireScalingProof($nestedMaximumRejected['statements'] === 51, 'Maximum N+1 control cost changed.');
requireScalingProof(
    $nestedEmptyRejected['phase_statements'] === [
        'after_load' => 1,
        'after_mapping' => 1,
        'after_encoding' => 1,
    ],
    'Empty N+1 phase counts changed.',
);
requireScalingProof(
    $nestedOneRejected['phase_statements'] === [
        'after_load' => 1,
        'after_mapping' => 2,
        'after_encoding' => 2,
    ],
    'One-parent N+1 phase counts changed.',
);
requireScalingProof(
    $nestedMaximumRejected['phase_statements'] === [
        'after_load' => 1,
        'after_mapping' => 51,
        'after_encoding' => 51,
    ],
    'Maximum N+1 phase counts changed.',
);
requireScalingProof(
    $nestedMaximumRejected['maximum_executions'] === 50,
    'Maximum N+1 creator lookup count changed.',
);
requireScalingProof(
    !$nestedEmptyRejected['budget_exceeded']
    && !$nestedOneRejected['budget_exceeded']
    && !$nestedMaximumRejected['budget_exceeded'],
    'A generous nested N+1 control unexpectedly exceeded its budget.',
);
requireScalingProof(
    !$nestedEmptyRejected['truncated']
    && !$nestedOneRejected['truncated']
    && !$nestedMaximumRejected['truncated']
    && !$nestedBudgetRejected['truncated'],
    'A nested N+1 trace unexpectedly truncated.',
);
requireScalingProof($nestedBudgetRejected['budget_exceeded'], 'Nested N+1 budget was not enforced.');
requireScalingProof(
    $nestedBudgetRejected['statements'] === 3
    && $nestedBudgetRejected['maximum_executions'] === 2,
    'Nested N+1 budget rejection must occur before statement 4 enters the trace.',
);
requireScalingProof(
    $nestedBudgetRejected['phase_statements'] === [
        'after_load' => 1,
        'after_mapping' => 3,
        'after_encoding' => 3,
    ],
    'Budgeted N+1 phase counts changed.',
);

foreach (
    [
        '{"data":[{"id":"' . $workspaceOneId . '","name":"Workspace 1",'
            . '"created_by_user_id":"' . $creatorOneId . '","creator_id":"'
            . $creatorOneId . '"}]}',
        '{"data":[{"id":"' . $workspaceOneId . '","name":"Workspace 1",'
            . '"created_by_user_id":"' . $creatorOneId . '","creator":[]}]}',
        '{"data":[{"id":"' . $workspaceOneId . '","name":"Workspace 1",'
            . '"created_by_user_id":"' . $creatorOneId . '","creator":{"id":"'
            . nestedWorkspaceFixtureUuid('10000000', 2)
            . '","display_name":"Creator 1","avatar_url":null}}]}',
        '{"data":[{"id":"' . $workspaceOneId . '","name":"Workspace 1",'
            . '"created_by_user_id":"' . $creatorOneId . '","creator":{"id":"'
            . $creatorOneId
            . '","display_name":"Creator 1","avatar_url":"http://example.com/avatar.png"}}]}',
        '{"data":[{"id":"' . $workspaceOneId . '","name":"Workspace 1",'
            . '"created_by_user_id":"' . $creatorOneId . '","creator":{"id":"'
            . $creatorOneId
            . '","display_name":"Creator 1","avatar_url":null,"email":"private@example.com"}}]}',
    ] as $incompatibleNestedBody
) {
    requireNestedWorkspaceDecoderRejection($incompatibleNestedBody . "\n");
}

$expectedOneCreator = $expectedOneData[0]['creator'];

$oversizedNestedItemsBody = json_encode(
    ['data' => array_fill(0, 51, $expectedOneData[0])],
    JSON_THROW_ON_ERROR,
) . "\n";
$oversizedNestedNameBody = json_encode(
    ['data' => [[
        ...$expectedOneData[0],
        'name' => str_repeat('w', 161),
    ]]],
    JSON_THROW_ON_ERROR,
) . "\n";
$oversizedNestedDisplayNameBody = json_encode(
    ['data' => [[
        ...$expectedOneData[0],
        'creator' => [
            ...$expectedOneCreator,
            'display_name' => str_repeat('c', 161),
        ],
    ]]],
    JSON_THROW_ON_ERROR,
) . "\n";
$oversizedNestedAvatarBody = json_encode(
    ['data' => [[
        ...$expectedOneData[0],
        'creator' => [
            ...$expectedOneCreator,
            'avatar_url' => 'https://' . str_repeat('a', 2_041),
        ],
    ]]],
    JSON_THROW_ON_ERROR,
) . "\n";

foreach (
    [
        $oversizedNestedItemsBody,
        $oversizedNestedNameBody,
        $oversizedNestedDisplayNameBody,
        $oversizedNestedAvatarBody,
    ] as $incompatibleNestedBody
) {
    requireNestedWorkspaceDecoderRejection($incompatibleNestedBody);
}

fwrite(
    STDOUT,
    "PASS query scaling: users accepted 1/page and rejected 3 -> 51; workspace.creator accepted 1/page and rejected 2 -> 51; budget stopped statement 4; mapping/JSON 0 database calls\n",
);

/** @return array{body: string, statements: int, maximum_executions: int, truncated: bool} */
function runAcceptedRead(string $databasePath, ?string $afterUserId = null): array
{
    $trace = new QueryTrace(1);
    $handler = new ListUsersHandler(
        Connection::connect('sqlite:' . $databasePath, new QueryBudget(1), $trace),
    );
    $query = $afterUserId === null ? [] : ['after_user_id' => $afterUserId];
    $response = $handler->handle(new Request('GET', '/users', $query));
    $summary = $trace->snapshot();

    return [
        'body' => $response->body,
        'statements' => $summary['statements'],
        'maximum_executions' => $summary['maximum_executions_per_fingerprint'],
        'truncated' => $summary['truncated'],
    ];
}

/** @return array{ids: list<int>, event_counts: list<int>, next_after_user_id: string|null} */
function acceptedPageData(string $body): array
{
    $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);

    if (
        !is_array($decoded)
        || count($decoded) !== 2
        || !array_key_exists('data', $decoded)
        || !array_key_exists('meta', $decoded)
    ) {
        throw new RuntimeException('Accepted page returned an invalid response shape.');
    }

    $userValues = $decoded['data'];
    $meta = $decoded['meta'];

    if (!is_array($userValues) || !array_is_list($userValues)) {
        throw new RuntimeException('Accepted page returned an invalid users collection.');
    }

    if (
        !is_array($meta)
        || count($meta) !== 1
        || !array_key_exists('next_after_user_id', $meta)
    ) {
        throw new RuntimeException('Accepted page returned invalid operation metadata.');
    }

    $nextAfterUserId = $meta['next_after_user_id'];

    if ($nextAfterUserId !== null && !is_string($nextAfterUserId)) {
        throw new RuntimeException('Accepted page returned an invalid continuation value.');
    }

    $ids = [];
    $eventCounts = [];

    foreach ($userValues as $userValue) {
        if (!is_array($userValue)) {
            throw new RuntimeException('Accepted page returned a non-object user value.');
        }

        $row = [];

        foreach ($userValue as $name => $value) {
            if (!is_string($name)) {
                throw new RuntimeException('Accepted page returned a non-string user field name.');
            }

            $row[$name] = $value;
        }

        $user = UserActivitySummary::fromDatabaseRow($row);
        $ids[] = $user->id->value;
        $eventCounts[] = $user->eventCount;
    }

    return [
        'ids' => $ids,
        'event_counts' => $eventCounts,
        'next_after_user_id' => $nextAfterUserId,
    ];
}

/** @return array{body: string, budget_exceeded: bool, statements: int, maximum_executions: int} */
function runRejectedRead(
    string $root,
    string $fixturePath,
    string $databasePath,
    int $budget,
): array {
    $process = proc_open(
        [PHP_BINARY, $fixturePath, $root, $databasePath, (string) $budget],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the N+1 negative-control fixture.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Unable to read the N+1 negative-control output.');
    }

    if ($exitCode !== 0) {
        throw new RuntimeException("N+1 negative control failed.\n{$stderr}\n{$stdout}");
    }

    $decoded = json_decode($stdout, true, 64, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException('N+1 negative control did not return a JSON object.');
    }

    $body = $decoded['body'] ?? null;
    $budgetExceeded = $decoded['budget_exceeded'] ?? null;
    $trace = $decoded['trace'] ?? null;

    if (!is_string($body) || !is_bool($budgetExceeded) || !is_array($trace)) {
        throw new RuntimeException('N+1 negative control returned an invalid result shape.');
    }

    $statements = $trace['statements'] ?? null;
    $maximumExecutions = $trace['maximum_executions_per_fingerprint'] ?? null;

    if (!is_int($statements) || !is_int($maximumExecutions)) {
        throw new RuntimeException('N+1 negative control returned an invalid trace shape.');
    }

    return [
        'body' => $body,
        'budget_exceeded' => $budgetExceeded,
        'statements' => $statements,
        'maximum_executions' => $maximumExecutions,
    ];
}

/**
 * @return array{
 *     body: string,
 *     budget_exceeded: bool,
 *     statements: int,
 *     maximum_executions: int,
 *     truncated: bool,
 *     phase_statements: array{after_load: int, after_mapping: int, after_encoding: int}
 * }
 */
function runNestedWorkspaceFixture(
    string $root,
    string $fixturePath,
    string $databasePath,
    int $budget,
    ?string $authorization = null,
): array {
    $arguments = [PHP_BINARY, $fixturePath, $root, $databasePath, (string) $budget];

    if ($authorization !== null) {
        $arguments[] = $authorization;
    }

    $process = proc_open(
        $arguments,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start a nested workspace relationship fixture.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!is_string($stdout) || !is_string($stderr)) {
        throw new RuntimeException('Unable to read nested workspace relationship output.');
    }

    if ($exitCode !== 0) {
        throw new RuntimeException("Nested workspace relationship fixture failed.\n{$stderr}\n{$stdout}");
    }

    $decoded = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException('Nested workspace relationship fixture did not return an object.');
    }

    $body = $decoded['body'] ?? null;
    $budgetExceeded = $decoded['budget_exceeded'] ?? null;
    $phaseStatements = $decoded['phase_statements'] ?? null;
    $trace = $decoded['trace'] ?? null;

    if (
        !is_string($body)
        || !is_bool($budgetExceeded)
        || !is_array($phaseStatements)
        || count($phaseStatements) !== 3
        || !array_key_exists('after_load', $phaseStatements)
        || !array_key_exists('after_mapping', $phaseStatements)
        || !array_key_exists('after_encoding', $phaseStatements)
        || !is_int($phaseStatements['after_load'])
        || !is_int($phaseStatements['after_mapping'])
        || !is_int($phaseStatements['after_encoding'])
        || !is_array($trace)
    ) {
        throw new RuntimeException('Nested workspace relationship fixture returned an invalid result shape.');
    }

    $statements = $trace['statements'] ?? null;
    $maximumExecutions = $trace['maximum_executions_per_fingerprint'] ?? null;
    $truncated = $trace['truncated'] ?? null;

    if (!is_int($statements) || !is_int($maximumExecutions) || !is_bool($truncated)) {
        throw new RuntimeException('Nested workspace relationship fixture returned an invalid trace.');
    }

    return [
        'body' => $body,
        'budget_exceeded' => $budgetExceeded,
        'statements' => $statements,
        'maximum_executions' => $maximumExecutions,
        'truncated' => $truncated,
        'phase_statements' => [
            'after_load' => $phaseStatements['after_load'],
            'after_mapping' => $phaseStatements['after_mapping'],
            'after_encoding' => $phaseStatements['after_encoding'],
        ],
    ];
}

/**
 * @return list<array{
 *     id: non-empty-string,
 *     name: non-empty-string,
 *     created_by_user_id: non-empty-string,
 *     creator: array{id: non-empty-string, display_name: non-empty-string, avatar_url: non-empty-string|null}|null
 * }>
 */
function decodeNestedWorkspacePage(string $body): array
{
    $decoded = json_decode($body, false, 16, JSON_THROW_ON_ERROR);

    if (
        !$decoded instanceof stdClass
        || count(get_object_vars($decoded)) !== 1
        || !property_exists($decoded, 'data')
        || !is_array($decoded->data)
        || !array_is_list($decoded->data)
        || count($decoded->data) > 50
    ) {
        throw new UnexpectedValueException('Nested workspace page is incompatible.');
    }

    $workspaces = [];

    foreach ($decoded->data as $workspace) {
        if (!$workspace instanceof stdClass) {
            throw new UnexpectedValueException('Nested workspace item must be an object.');
        }

        $workspaces[] = decodeNestedWorkspaceItem($workspace);
    }

    return $workspaces;
}

/**
 * @return array{
 *     id: non-empty-string,
 *     name: non-empty-string,
 *     created_by_user_id: non-empty-string,
 *     creator: array{id: non-empty-string, display_name: non-empty-string, avatar_url: non-empty-string|null}|null
 * }
 */
function decodeNestedWorkspaceItem(stdClass $workspace): array
{
    if (
        count(get_object_vars($workspace)) !== 4
        || !property_exists($workspace, 'id')
        || !property_exists($workspace, 'name')
        || !property_exists($workspace, 'created_by_user_id')
        || !property_exists($workspace, 'creator')
    ) {
        throw new UnexpectedValueException('Nested workspace item fields are incompatible.');
    }

    $workspaceId = decodedNestedWorkspaceUuid($workspace->id, 'id');
    $workspaceName = decodedNestedWorkspaceText($workspace->name, 'name', 160);
    $createdByUserId = decodedNestedWorkspaceUuid(
        $workspace->created_by_user_id,
        'created_by_user_id',
    );
    $creatorValue = $workspace->creator;
    $creator = null;

    if ($creatorValue !== null) {
        if (
            !$creatorValue instanceof stdClass
            || count(get_object_vars($creatorValue)) !== 3
            || !property_exists($creatorValue, 'id')
            || !property_exists($creatorValue, 'display_name')
            || !property_exists($creatorValue, 'avatar_url')
        ) {
            throw new UnexpectedValueException('Nested workspace creator fields are incompatible.');
        }

        $creatorId = decodedNestedWorkspaceUuid($creatorValue->id, 'creator.id');

        if ($creatorId !== $createdByUserId) {
            throw new UnexpectedValueException('Nested creator id does not match created_by_user_id.');
        }

        $displayName = decodedNestedWorkspaceText(
            $creatorValue->display_name,
            'creator.display_name',
            160,
        );
        $avatarUrlValue = $creatorValue->avatar_url;
        $avatarUrl = $avatarUrlValue === null
            ? null
            : decodedNestedWorkspaceAvatarUrl($avatarUrlValue);
        $creator = [
            'id' => $creatorId,
            'display_name' => $displayName,
            'avatar_url' => $avatarUrl,
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
function decodedNestedWorkspaceUuid(mixed $value, string $field): string
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
function decodedNestedWorkspaceText(mixed $value, string $field, int $maximumBytes): string
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
function decodedNestedWorkspaceAvatarUrl(mixed $value): string
{
    if (
        !is_string($value)
        || $value === ''
        || strlen($value) > 2_048
        || preg_match('/^https:\/\/[!-~]+$/D', $value) !== 1
    ) {
        throw new UnexpectedValueException('Nested workspace creator avatar is not a bounded HTTPS URL.');
    }

    return $value;
}

function requireNestedWorkspaceDecoderRejection(string $body): void
{
    try {
        decodeNestedWorkspacePage($body);
    } catch (JsonException | UnexpectedValueException) {
        return;
    }

    throw new RuntimeException('Expected incompatible nested workspace JSON to be rejected.');
}

/** @return non-empty-string */
function nestedWorkspaceFixtureUuid(string $prefix, int $sequence): string
{
    if (
        !in_array($prefix, ['10000000', '20000000'], true)
        || $sequence < 1
        || $sequence > 50
    ) {
        throw new InvalidArgumentException('Nested workspace fixture UUID input is outside its finite set.');
    }

    return sprintf('%s-0000-4000-8000-%012d', $prefix, $sequence);
}

function createNestedWorkspaceDatabase(string $root, string $name, int $workspaceCount): string
{
    if (!in_array($workspaceCount, [0, 1, 50], true)) {
        throw new InvalidArgumentException('Nested workspace fixture count must be 0, 1, or 50.');
    }

    $directory = $root . '/tmp/query-scaling';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the nested workspace fixture directory.');
    }

    $databasePath = $directory . '/' . $name . '.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset a nested workspace fixture database.');
    }

    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(4),
        new QueryTrace(4),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE users (
                id TEXT PRIMARY KEY,
                tenant_id INTEGER NOT NULL,
                display_name TEXT NOT NULL,
                email TEXT NOT NULL,
                avatar_url TEXT NULL,
                status TEXT NOT NULL,
                role TEXT NOT NULL,
                is_visible INTEGER NOT NULL,
                authorized_principal_id INTEGER NOT NULL
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE workspaces (
                id TEXT PRIMARY KEY,
                tenant_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL,
                created_by_user_id TEXT NOT NULL,
                is_visible INTEGER NOT NULL,
                authorized_principal_id INTEGER NOT NULL
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            WITH RECURSIVE sequence(value) AS (
                SELECT 1
                WHERE :creator_count_start >= 1
                UNION ALL
                SELECT value + 1
                FROM sequence
                WHERE value < :creator_count_next
            )
            INSERT INTO users (
                id,
                tenant_id,
                display_name,
                email,
                avatar_url,
                status,
                role,
                is_visible,
                authorized_principal_id
            )
            SELECT
                printf('10000000-0000-4000-8000-%012d', sequence.value),
                CASE WHEN sequence.value = 50 THEN 99 ELSE 42 END,
                'Creator ' || sequence.value,
                'creator' || sequence.value || '@example.com',
                CASE
                    WHEN sequence.value = 1 THEN 'https://example.com/avatars/1.png'
                    ELSE NULL
                END,
                'active',
                'workspace_creator',
                CASE WHEN sequence.value = 2 THEN 0 ELSE 1 END,
                CASE WHEN sequence.value = 3 THEN 8 ELSE 7 END
            FROM sequence
            SQL,
        [
            'creator_count_start' => $workspaceCount,
            'creator_count_next' => $workspaceCount,
        ],
    );
    $connection->executeStatement(
        <<<'SQL'
            WITH RECURSIVE sequence(value) AS (
                SELECT 1
                WHERE :workspace_count_start >= 1
                UNION ALL
                SELECT value + 1
                FROM sequence
                WHERE value < :workspace_count_next
            )
            INSERT INTO workspaces (
                id,
                tenant_id,
                name,
                slug,
                created_by_user_id,
                is_visible,
                authorized_principal_id
            )
            SELECT
                printf('20000000-0000-4000-8000-%012d', sequence.value),
                42,
                'Workspace ' || sequence.value,
                'workspace-' || sequence.value,
                CASE
                    WHEN sequence.value = 4 THEN '10000000-0000-4000-8000-000000000050'
                    ELSE printf('10000000-0000-4000-8000-%012d', sequence.value)
                END,
                1,
                7
            FROM sequence
            UNION ALL
            SELECT
                '00000000-0000-4000-8000-000000000001',
                42,
                'Hidden Workspace',
                'hidden-workspace',
                '10000000-0000-4000-8000-000000000001',
                0,
                7
            UNION ALL
            SELECT
                '00000000-0000-4000-8000-000000000002',
                99,
                'Other Tenant Workspace',
                'other-tenant-workspace',
                '10000000-0000-4000-8000-000000000001',
                1,
                7
            UNION ALL
            SELECT
                '00000000-0000-4000-8000-000000000003',
                42,
                'Denied Workspace',
                'denied-workspace',
                '10000000-0000-4000-8000-000000000001',
                1,
                8
            SQL,
        [
            'workspace_count_start' => $workspaceCount,
            'workspace_count_next' => $workspaceCount,
        ],
    );

    return $databasePath;
}

function createScalingDatabase(string $root, string $name, int $userCount): string
{
    if ($userCount < 1 || $userCount > 125) {
        throw new InvalidArgumentException('Scaling fixture count must be between 1 and 125.');
    }

    $directory = $root . '/tmp/query-scaling';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the query-scaling fixture directory.');
    }

    $databasePath = $directory . '/' . $name . '.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset a query-scaling database.');
    }

    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(5),
        new QueryTrace(5),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE user_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
            SQL,
    );
    $connection->executeStatement(
        'CREATE INDEX user_events_user_id_idx ON user_events (user_id)',
    );
    $connection->executeStatement(
        <<<'SQL'
            WITH RECURSIVE sequence(value) AS (
                SELECT 1
                UNION ALL
                SELECT value + 1
                FROM sequence
                WHERE value < :user_count
            )
            INSERT INTO users (name, email)
            SELECT
                'User ' || sequence.value,
                'user' || sequence.value || '@example.com'
            FROM sequence
            SQL,
        ['user_count' => $userCount],
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO user_events (user_id, event_type)
            SELECT users.id, :first_event_type
            FROM users
            UNION ALL
            SELECT users.id, :second_event_type
            FROM users
            SQL,
        ['first_event_type' => 'seed.first', 'second_event_type' => 'seed.second'],
    );

    return $databasePath;
}

function requireScalingProof(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
