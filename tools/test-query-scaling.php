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
require_once __DIR__ . '/process-support.php';

use PHPThis\Verification\SyntaxProfile;

$root = dirname(__DIR__);
$fixtureRelativePath = 'tests/fixtures/list-users.n-plus-one.php.fixture';
$fixturePath = $root . '/' . $fixtureRelativePath;
$fixtureSource = file_get_contents($fixturePath);
$nestedAcceptedRelativePath = 'tests/fixtures/nested-parents-with-children.accepted.php';
$nestedAcceptedPath = $root . '/' . $nestedAcceptedRelativePath;
$nestedRejectedRelativePath = 'tests/fixtures/nested-parents-with-children.n-plus-one.php.fixture';
$nestedRejectedPath = $root . '/' . $nestedRejectedRelativePath;
$nestedAcceptedSource = file_get_contents($nestedAcceptedPath);
$nestedRejectedSource = file_get_contents($nestedRejectedPath);

if (!is_string($fixtureSource)) {
    throw new RuntimeException('Unable to read the N+1 negative-control fixture.');
}

if (!is_string($nestedAcceptedSource) || !is_string($nestedRejectedSource)) {
    throw new RuntimeException('Unable to read the nested parent relationship fixtures.');
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
    'PHT003 tests/fixtures/nested-parents-with-children.n-plus-one.php.fixture:61 calls a database method inside a loop.',
];

requireScalingProof(
    $nestedAcceptedProfileFailures === [],
    'The accepted nested parent fixture must pass the Strict Profile.',
);
requireScalingProof(
    $nestedRejectedProfileFailures === $expectedNestedRejectedProfileFailures,
    'The nested parent N+1 control must be rejected by exactly one stable PHT003 diagnostic.',
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
    throw new RuntimeException('The accepted nested parent mapping and encoding phases changed.');
}

$nestedMappingAndEncodingSource = substr($nestedAcceptedSource, $nestedMappingOffset);

requireScalingProof(
    substr_count($nestedAcceptedSource, '->selectAllRows(') === 1
    && substr_count($nestedAcceptedSource, '->selectOneRow(') === 0
    && substr_count($nestedAcceptedSource, '->executeStatement(') === 0
    && !str_contains($nestedAcceptedSource, 'SELECT *')
    && str_contains($nestedAcceptedSource, 'parents.tenant_id = :parent_tenant_id')
    && str_contains($nestedAcceptedSource, 'parents.is_visible = :parent_is_visible')
    && str_contains(
        $nestedAcceptedSource,
        'parents.authorized_principal_id = :parent_authorized_principal_id',
    )
    && str_contains($nestedAcceptedSource, 'children.tenant_id = :child_tenant_id')
    && str_contains($nestedAcceptedSource, 'children.is_visible = :child_is_visible')
    && str_contains(
        $nestedAcceptedSource,
        'children.authorized_principal_id = :child_authorized_principal_id',
    )
    && str_contains($nestedAcceptedSource, 'LIMIT :parent_limit')
    && !str_contains($nestedAcceptedSource, 'parents.private_value')
    && !str_contains($nestedAcceptedSource, 'children.private_value')
    && !str_contains($nestedAcceptedSource, 'children.internal_state')
    && !str_contains($nestedAcceptedSource, 'children.internal_kind'),
    'The accepted nested parent fixture must retain one minimized tenant-visible JOIN query.',
);
requireScalingProof(
    !str_contains($nestedMappingAndEncodingSource, '->selectAllRows(')
    && !str_contains($nestedMappingAndEncodingSource, '->selectOneRow(')
    && !str_contains($nestedMappingAndEncodingSource, '->executeStatement('),
    'Nested parent mapping and JSON encoding must perform zero database calls.',
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

$nestedEmptyDatabase = createNestedParentDatabase($root, 'nested-empty', 0);
$nestedOneDatabase = createNestedParentDatabase($root, 'nested-one', 1);
$nestedMaximumDatabase = createNestedParentDatabase($root, 'nested-maximum', 50);
$nestedEmptyAccepted = runNestedParentFixture($root, $nestedAcceptedPath, $nestedEmptyDatabase, 1);
$nestedOneAccepted = runNestedParentFixture($root, $nestedAcceptedPath, $nestedOneDatabase, 1);
$nestedMaximumAccepted = runNestedParentFixture(
    $root,
    $nestedAcceptedPath,
    $nestedMaximumDatabase,
    1,
);
$nestedDenied = runNestedParentFixture(
    $root,
    $nestedAcceptedPath,
    $nestedMaximumDatabase,
    1,
    'deny',
);
$nestedEmptyRejected = runNestedParentFixture($root, $nestedRejectedPath, $nestedEmptyDatabase, 1);
$nestedOneRejected = runNestedParentFixture($root, $nestedRejectedPath, $nestedOneDatabase, 2);
$nestedMaximumRejected = runNestedParentFixture(
    $root,
    $nestedRejectedPath,
    $nestedMaximumDatabase,
    51,
);
$nestedBudgetRejected = runNestedParentFixture(
    $root,
    $nestedRejectedPath,
    $nestedMaximumDatabase,
    3,
);
$nestedEmptyData = decodeNestedParentPage($nestedEmptyAccepted['body']);
$nestedOneData = decodeNestedParentPage($nestedOneAccepted['body']);
$nestedMaximumData = decodeNestedParentPage($nestedMaximumAccepted['body']);
$parentOneId = nestedParentFixtureUuid('20000000', 1);
$childOneId = nestedParentFixtureUuid('10000000', 1);
$expectedOneData = [[
    'id' => $parentOneId,
    'label' => 'Parent 1',
    'child_id' => $childOneId,
    'child' => [
        'id' => $childOneId,
        'label' => 'Child 1',
        'public_url' => 'https://example.com/children/1',
    ],
]];
$expectedMaximumIds = [];

foreach (range(1, 50) as $sequence) {
    $expectedMaximumIds[] = nestedParentFixtureUuid('20000000', $sequence);
}

$actualMaximumIds = [];

foreach ($nestedMaximumData as $parent) {
    $actualMaximumIds[] = $parent['id'];
}

$reorderedNestedData = decodeNestedParentPage(
    '{"data":[{"child":{"public_url":"https://example.com/children/1",'
    . '"label":"Child 1","id":"' . $childOneId . '"},'
    . '"child_id":"' . $childOneId . '","label":"Parent 1",'
    . '"id":"' . $parentOneId . '"}]}' . "\n",
);

requireScalingProof($nestedEmptyData === [], 'The empty nested parent page changed.');
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
    'Nested parent authorization denial must perform zero database work.',
);
requireScalingProof(
    $nestedEmptyAccepted['body'] === "{\"data\":[]}\n",
    'The empty nested parent body changed.',
);
requireScalingProof($nestedOneData === $expectedOneData, 'The one-parent nested parent page changed.');
requireScalingProof(
    $reorderedNestedData === $expectedOneData,
    'Nested parent decoding must ignore JSON object-member order.',
);
requireScalingProof(count($nestedMaximumData) === 50, 'The nested parent page maximum changed.');
requireScalingProof(
    $actualMaximumIds === $expectedMaximumIds,
    'Tenant or visibility filtering changed the bounded parent order.',
);
requireScalingProof(
    ($nestedMaximumData[1]['child_id'] ?? null)
        === nestedParentFixtureUuid('10000000', 2)
    && $nestedMaximumData[1]['child'] === null,
    'A hidden child must remain an explicit null child.',
);
requireScalingProof(
    ($nestedMaximumData[3]['child_id'] ?? null)
        === nestedParentFixtureUuid('10000000', 50)
    && $nestedMaximumData[3]['child'] === null,
    'A cross-tenant child must remain an explicit null child.',
);
requireScalingProof(
    ($nestedMaximumData[2]['child_id'] ?? null)
        === nestedParentFixtureUuid('10000000', 3)
    && $nestedMaximumData[2]['child'] === null,
    'A child denied by the fixed principal predicate must remain an explicit null child.',
);
requireScalingProof(
    ($nestedMaximumData[4]['child']['id'] ?? null)
        === ($nestedMaximumData[4]['child_id'] ?? null),
    'A present nested child id must equal child_id.',
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
    'Maximum N+1 child lookup count changed.',
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
        '{"data":[{"id":"' . $parentOneId . '","label":"Parent 1",'
            . '"child_id":"' . $childOneId . '","child_reference":"'
            . $childOneId . '"}]}',
        '{"data":[{"id":"' . $parentOneId . '","label":"Parent 1",'
            . '"child_id":"' . $childOneId . '","child":[]}]}',
        '{"data":[{"id":"' . $parentOneId . '","label":"Parent 1",'
            . '"child_id":"' . $childOneId . '","child":{"id":"'
            . nestedParentFixtureUuid('10000000', 2)
            . '","label":"Child 1","public_url":null}}]}',
        '{"data":[{"id":"' . $parentOneId . '","label":"Parent 1",'
            . '"child_id":"' . $childOneId . '","child":{"id":"'
            . $childOneId
            . '","label":"Child 1","public_url":"http://example.com/child"}}]}',
        '{"data":[{"id":"' . $parentOneId . '","label":"Parent 1",'
            . '"child_id":"' . $childOneId . '","child":{"id":"'
            . $childOneId
            . '","label":"Child 1","public_url":null,"private_value":"hidden"}}]}',
    ] as $incompatibleNestedBody
) {
    requireNestedParentDecoderRejection($incompatibleNestedBody . "\n");
}

$expectedOneChild = $expectedOneData[0]['child'];

$oversizedNestedItemsBody = json_encode(
    ['data' => array_fill(0, 51, $expectedOneData[0])],
    JSON_THROW_ON_ERROR,
) . "\n";
$oversizedNestedParentLabelBody = json_encode(
    ['data' => [[
        ...$expectedOneData[0],
        'label' => str_repeat('p', 161),
    ]]],
    JSON_THROW_ON_ERROR,
) . "\n";
$oversizedNestedLabelBody = json_encode(
    ['data' => [[
        ...$expectedOneData[0],
        'child' => [
            ...$expectedOneChild,
            'label' => str_repeat('c', 161),
        ],
    ]]],
    JSON_THROW_ON_ERROR,
) . "\n";
$oversizedNestedPublicUrlBody = json_encode(
    ['data' => [[
        ...$expectedOneData[0],
        'child' => [
            ...$expectedOneChild,
            'public_url' => 'https://' . str_repeat('a', 2_041),
        ],
    ]]],
    JSON_THROW_ON_ERROR,
) . "\n";

foreach (
    [
        $oversizedNestedItemsBody,
        $oversizedNestedParentLabelBody,
        $oversizedNestedLabelBody,
        $oversizedNestedPublicUrlBody,
    ] as $incompatibleNestedBody
) {
    requireNestedParentDecoderRejection($incompatibleNestedBody);
}

fwrite(
    STDOUT,
    "PASS query scaling: users accepted 1/page and rejected 3 -> 51; parent.child accepted 1/page and rejected 2 -> 51; budget stopped statement 4; mapping/JSON 0 database calls\n",
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
    $result = runBoundedMaintainerProcess(
        [PHP_BINARY, $fixturePath, $root, $databasePath, (string) $budget],
        $root,
        null,
        30_000,
        1_048_576,
        1_048_576,
    );

    $stdout = $result['stdout'];
    $stderr = $result['stderr'];
    $exitCode = $result['exit_code'];

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
function runNestedParentFixture(
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

    $result = runBoundedMaintainerProcess(
        $arguments,
        $root,
        null,
        30_000,
        1_048_576,
        1_048_576,
    );

    $stdout = $result['stdout'];
    $stderr = $result['stderr'];
    $exitCode = $result['exit_code'];

    if ($exitCode !== 0) {
        throw new RuntimeException("Nested parent relationship fixture failed.\n{$stderr}\n{$stdout}");
    }

    $decoded = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException('Nested parent relationship fixture did not return an object.');
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
        throw new RuntimeException('Nested parent relationship fixture returned an invalid result shape.');
    }

    $statements = $trace['statements'] ?? null;
    $maximumExecutions = $trace['maximum_executions_per_fingerprint'] ?? null;
    $truncated = $trace['truncated'] ?? null;

    if (!is_int($statements) || !is_int($maximumExecutions) || !is_bool($truncated)) {
        throw new RuntimeException('Nested parent relationship fixture returned an invalid trace.');
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
 *     label: non-empty-string,
 *     child_id: non-empty-string,
 *     child: array{id: non-empty-string, label: non-empty-string, public_url: non-empty-string|null}|null
 * }>
 */
function decodeNestedParentPage(string $body): array
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
        throw new UnexpectedValueException('Nested parent page is incompatible.');
    }

    $parents = [];

    foreach ($decoded->data as $parent) {
        if (!$parent instanceof stdClass) {
            throw new UnexpectedValueException('Nested parent item must be an object.');
        }

        $parents[] = decodeNestedParentItem($parent);
    }

    return $parents;
}

/**
 * @return array{
 *     id: non-empty-string,
 *     label: non-empty-string,
 *     child_id: non-empty-string,
 *     child: array{id: non-empty-string, label: non-empty-string, public_url: non-empty-string|null}|null
 * }
 */
function decodeNestedParentItem(stdClass $parent): array
{
    if (
        count(get_object_vars($parent)) !== 4
        || !property_exists($parent, 'id')
        || !property_exists($parent, 'label')
        || !property_exists($parent, 'child_id')
        || !property_exists($parent, 'child')
    ) {
        throw new UnexpectedValueException('Nested parent item fields are incompatible.');
    }

    $parentId = decodedNestedParentUuid($parent->id, 'id');
    $parentLabel = decodedNestedParentText($parent->label, 'label', 160);
    $relatedChildId = decodedNestedParentUuid(
        $parent->child_id,
        'child_id',
    );
    $childValue = $parent->child;
    $child = null;

    if ($childValue !== null) {
        if (
            !$childValue instanceof stdClass
            || count(get_object_vars($childValue)) !== 3
            || !property_exists($childValue, 'id')
            || !property_exists($childValue, 'label')
            || !property_exists($childValue, 'public_url')
        ) {
            throw new UnexpectedValueException('Nested parent child fields are incompatible.');
        }

        $childId = decodedNestedParentUuid($childValue->id, 'child.id');

        if ($childId !== $relatedChildId) {
            throw new UnexpectedValueException('Nested child id does not match child_id.');
        }

        $childLabel = decodedNestedParentText(
            $childValue->label,
            'child.label',
            160,
        );
        $publicUrlValue = $childValue->public_url;
        $publicUrl = $publicUrlValue === null
            ? null
            : decodedNestedParentPublicUrl($publicUrlValue);
        $child = [
            'id' => $childId,
            'label' => $childLabel,
            'public_url' => $publicUrl,
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
function decodedNestedParentUuid(mixed $value, string $field): string
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
function decodedNestedParentText(mixed $value, string $field, int $maximumBytes): string
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
function decodedNestedParentPublicUrl(mixed $value): string
{
    if (
        !is_string($value)
        || $value === ''
        || strlen($value) > 2_048
        || preg_match('/^https:\/\/[!-~]+$/D', $value) !== 1
    ) {
        throw new UnexpectedValueException('Nested parent child public URL is not a bounded HTTPS URL.');
    }

    return $value;
}

function requireNestedParentDecoderRejection(string $body): void
{
    try {
        decodeNestedParentPage($body);
    } catch (JsonException | UnexpectedValueException) {
        return;
    }

    throw new RuntimeException('Expected incompatible nested parent JSON to be rejected.');
}

/** @return non-empty-string */
function nestedParentFixtureUuid(string $prefix, int $sequence): string
{
    if (
        !in_array($prefix, ['10000000', '20000000'], true)
        || $sequence < 1
        || $sequence > 50
    ) {
        throw new InvalidArgumentException('Nested parent fixture UUID input is outside its finite set.');
    }

    return sprintf('%s-0000-4000-8000-%012d', $prefix, $sequence);
}

function createNestedParentDatabase(string $root, string $name, int $parentCount): string
{
    if (!in_array($parentCount, [0, 1, 50], true)) {
        throw new InvalidArgumentException('Nested parent fixture count must be 0, 1, or 50.');
    }

    $directory = $root . '/tmp/query-scaling';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the nested parent fixture directory.');
    }

    $databasePath = $directory . '/' . $name . '.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset a nested parent fixture database.');
    }

    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(4),
        new QueryTrace(4),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE children (
                id TEXT PRIMARY KEY,
                tenant_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                private_value TEXT NOT NULL,
                public_url TEXT NULL,
                internal_state TEXT NOT NULL,
                internal_kind TEXT NOT NULL,
                is_visible INTEGER NOT NULL,
                authorized_principal_id INTEGER NOT NULL
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE parents (
                id TEXT PRIMARY KEY,
                tenant_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                private_value TEXT NOT NULL,
                child_id TEXT NOT NULL,
                is_visible INTEGER NOT NULL,
                authorized_principal_id INTEGER NOT NULL
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            WITH RECURSIVE sequence(value) AS (
                SELECT 1
                WHERE :child_count_start >= 1
                UNION ALL
                SELECT value + 1
                FROM sequence
                WHERE value < :child_count_next
            )
            INSERT INTO children (
                id,
                tenant_id,
                label,
                private_value,
                public_url,
                internal_state,
                internal_kind,
                is_visible,
                authorized_principal_id
            )
            SELECT
                printf('10000000-0000-4000-8000-%012d', sequence.value),
                CASE WHEN sequence.value = 50 THEN 99 ELSE 42 END,
                'Child ' || sequence.value,
                'child-private-' || sequence.value,
                CASE
                    WHEN sequence.value = 1 THEN 'https://example.com/children/1'
                    ELSE NULL
                END,
                'internal-ready',
                'nested-child',
                CASE WHEN sequence.value = 2 THEN 0 ELSE 1 END,
                CASE WHEN sequence.value = 3 THEN 8 ELSE 7 END
            FROM sequence
            SQL,
        [
            'child_count_start' => $parentCount,
            'child_count_next' => $parentCount,
        ],
    );
    $connection->executeStatement(
        <<<'SQL'
            WITH RECURSIVE sequence(value) AS (
                SELECT 1
                WHERE :parent_count_start >= 1
                UNION ALL
                SELECT value + 1
                FROM sequence
                WHERE value < :parent_count_next
            )
            INSERT INTO parents (
                id,
                tenant_id,
                label,
                private_value,
                child_id,
                is_visible,
                authorized_principal_id
            )
            SELECT
                printf('20000000-0000-4000-8000-%012d', sequence.value),
                42,
                'Parent ' || sequence.value,
                'parent-private-' || sequence.value,
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
                'Hidden Parent',
                'hidden-parent-private',
                '10000000-0000-4000-8000-000000000001',
                0,
                7
            UNION ALL
            SELECT
                '00000000-0000-4000-8000-000000000002',
                99,
                'Other Tenant Parent',
                'other-tenant-parent-private',
                '10000000-0000-4000-8000-000000000001',
                1,
                7
            UNION ALL
            SELECT
                '00000000-0000-4000-8000-000000000003',
                42,
                'Denied Parent',
                'denied-parent-private',
                '10000000-0000-4000-8000-000000000001',
                1,
                8
            SQL,
        [
            'parent_count_start' => $parentCount,
            'parent_count_next' => $parentCount,
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
