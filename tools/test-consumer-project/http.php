<?php

declare(strict_types=1);

/** @param array<string, string> $environment */
function proveInstalledStructuredJsonSuccessEnvelopeDistribution(
    string $project,
    string $installedFramework,
    array $environment,
): string {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Define or change a structured JSON resource success representation, including nested child data | installed `vendor/phpthis/framework/docs/frontend-integration.md`, then installed `vendor/phpthis/framework/docs/request-handling.md`;',
            'operation-owned `data` and optional `meta` fields',
            'fixed bounded query/cache/external-call counts independent of parent-page cardinality',
            'add no generic wrapper, relationship loader, serializer, paginator, or generator',
        ],
        $installedFramework . '/docs/frontend-integration.md' => [
            '## Recommended structured JSON resource success envelope',
            'Put one resource in a top-level `data` object.',
            'Put a resource collection in a top-level `data` array, including `[]` when the collection is empty.',
            'Put optional pagination or other operation-owned non-resource information in a top-level `meta` object.',
            'Pagination continuation names, grammar, ordering, bounds, filter compatibility, invalidation, snapshot behavior, and end-of-list representation remain distinct operation contracts.',
            '`next_after_user_id` and `next_cursor` may both live under `meta` without becoming interchangeable.',
            'A missing resource normally follows the operation\'s recorded `404` failure contract instead of returning a successful `{"data":null}`.',
            'Errors remain separate from this success envelope and retain their explicit status, media type, and stable public error shape.',
            'Bodyless `204`, `205`, and `304` responses, explicit `HEAD`, downloads, HTML, plain text, health responses, and other non-resource representations are not automatically wrapped.',
            'Every adopting operation records and proves its exact `Content-Type`, status, field set, native JSON types, null-versus-absence behavior, scalar and collection bounds, identifier representation, temporal representation, collection ordering, and compatibility policy.',
            'Frontend fixtures and decoders reject incompatible media types, malformed JSON, unknown or missing fields where the operation forbids them, wrong native types, out-of-bound values, and incompatible envelope changes as decode or contract failures.',
            'A top-level `data` member alone is not JSON:API.',
            'Existing published resource-named or bare responses remain valid application contracts; moving one to `data` is a breaking API change',
            'This recommendation adds no runtime wrapper, serializer, resource class, paginator, middleware, helper, reflection, discovery, OpenAPI or JSON Schema artifact, SDK, or client generator.',
            '## Embed nested resources without N+1 I/O',
            'Give the relationship an operation-specific role name.',
            'This is neutral notation, not a framework resource schema or a recommendation to emit fields literally named `child` or `child_id`.',
            'The operation records whether tenant scope or authorization applies to either relationship role and, when applicable, keeps those predicates and the policy for a related row the principal may not see explicit.',
            'The complete I/O plan remains fixed and bounded independently of parent-page cardinality.',
            'Perform all database, cache, and external-service operations before resource mapping and JSON encoding;',
            '`PHT003` catches direct lexical database calls inside loops, but it does not prove that an indirectly called mapper, cache client, or integration performs no I/O.',
            'Parent pagination remains the controlling contract.',
            'The frontend decoder owns the same exactness.',
            'Nested representations add no PHPThis relationship loader, ORM, lazy loading, resource class, serializer, generic batcher, expansion syntax, or JSON:API relationship support.',
        ],
        $installedFramework . '/docs/request-handling.md' => [
            '## Recommended structured JSON resource success envelope',
            'An empty collection is `{"data":[]}`, not `null`.',
            'Continuation names such as `next_after_user_id` and `next_cursor` deliberately remain distinct',
            'their grammar, ordering, bounds, filter compatibility, invalidation, snapshot behavior, null-versus-absence policy, and end-of-list semantics do not become a framework pagination contract',
            'Errors keep their separate explicit status, exact media type, and stable public error representation.',
            'HTTP status remains authoritative; do not add a body-level success flag or duplicated status field.',
            'The convention does not wrap bodyless `204`, `205`, or `304` responses, explicit `HEAD`, downloads, HTML, plain text, health responses, or other non-resource representations.',
            'proves the exact field set, native JSON types, null-versus-absence behavior, scalar and collection bounds, identifier representation, temporal representation, collection ordering, and compatibility policy.',
            'encodes its concrete application-owned array with `JSON_THROW_ON_ERROR`',
            'changing one to `data` is a breaking API change requiring an explicit migration or versioning decision.',
            'PHPThis adds no runtime wrapper, serializer, resource class, paginator, middleware, facade, helper, reflection, discovery, OpenAPI or JSON Schema artifact, SDK, or client generator.',
            '### Nested child data',
            'Give each relationship an operation-specific role name; this guidance uses `parent.child` only as neutral notation and does not prescribe either response field name.',
            'whether authorization or tenant scope applies to either role and, where applicable, the exact policy and evidence',
            'Mapping, serialization, callbacks, and recursive traversal perform no database, cache, or external-service I/O.',
            'Never query one child per parent.',
            '`PHT003` rejects direct lexical database calls inside loops but cannot prove that an indirect mapper, cache client, or integration is I/O-free',
            'Join fan-out must not alter the parent limit, stable order, continuation, or duplicate-parent behavior.',
            'PHPThis adds no relationship mechanism, loader, serializer, generic batcher, or expansion API.',
        ],
        $installedFramework . '/docs/database.md' => [
            '## N+1-safe nested resource plans',
            'The number of database statements, cache operations, and external calls remains fixed as the bounded parent page grows.',
            'For an ordinary to-one `parent.child` relationship, prefer one explicit bounded join',
            'every operation-owned scope or authorization predicate that applies to either side.',
            'every applicable operation-owned scope-isolation and authorization-denial path, an explicit N/A record for each concern that does not apply',
            'A finite batch plan may instead use a fixed number of reviewed statements when one join is inappropriate',
            'Never execute one child query per parent, hide repeated I/O behind a mapper, or introduce a repository, relationship loader, generic batcher, or generated placeholder list.',
            '`PHT003` catches direct lexical calls to `selectAllRows`, `selectOneRow`, or `executeStatement` inside a loop.',
            'The existing isolated N+1 negative control in `tools/test-query-scaling.php` demonstrates both query growth and budget containment',
            'This guidance adds no PHPThis runtime relationship mechanism, ORM, lazy loading, resource serializer, paginator, or new Strict Profile diagnostic.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Define, change, or review a structured JSON resource success representation, including nested child objects or collections |',
            'exact application response construction and `Content-Type`',
            'fixed bounded query/cache/external-call counts independent of parent-page cardinality',
            'authorization and tenant-scope applicability and policy',
            'preserve the advisory application-owned boundary',
            '| Choose or assess HTTP caching or server-side derived-data caching |',
            '| Adopt, change, or review the optional Redis cache and schedule-lease recipe |',
            '| Adopt, change, or review the accepted optional backend-neutral application-owned durable-job contract |',
            '| Adopt, change, or review ADR 024\'s optional SQLite durable-job recipe |',
            '| Connect to, read, write, or assess SQL safety or database authority |',
            '| Adopt, change, or review ADR 022\'s optional SQLite protected document-list recipe |',
            '| Add or assess an application-owned cursor or bounded list filter |',
            '| Adopt, change, or review ADR 022\'s optional versioned document cursor and bounded category-filter recipe |',
            'verify fail-closed no-skip/no-mock release behavior and that PHPThis provides no runtime, adapter, generic validator or backend checker',
            'do not generalize its transaction, lease, query bounds, one-shot lifecycle or outcomes to another backend or a framework queue or worker API',
            'do not infer the optional document-list recipe',
            'do not treat those names or semantics as a generic paginator or filter contract',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'An application deliberately adopting ADR 024\'s checked SQLite recipe',
            'These are obligations of that deliberately adopted recipe, not defaults for every application-owned deferred-work design.',
            'Delivery under that checked recipe remains at least once.',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Define or change a structured JSON resource success representation, including nested child data | installed `vendor/phpthis/framework/docs/frontend-integration.md`, then installed `vendor/phpthis/framework/docs/request-handling.md`;',
            'fixed bounded query/cache/external-call counts independent of parent-page cardinality',
            'add no generic wrapper, relationship loader, serializer, paginator, or generator',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'structured JSON success-envelope guidance, task routes, exact checked example shapes, isolated installed response and decoder controls, and dependency checks preserve the advisory application-owned `data` and optional `meta` convention',
            'without adding a framework wrapper, serializer, resource class, paginator, middleware, helper, discovery mechanism, generator, runtime dependency, checker rule, or `PHT` diagnostic',
            'The structured JSON success-envelope guard pins the two installed public guides',
            'The isolated installed proof constructs explicit application-owned `Response` values',
            'Framework, default-skeleton, installed-framework, copied-project, and package-inventory path checks',
            'This deterministic name/path evidence does not prove absence of differently named hidden behavior',
            'It adds no framework response mechanism or consumer-checker rule and does not make the example envelope a consumer-validity rule.',
            'N+1-safe nested-resource guidance, a focused checked neutral `parent.child` fixture, query-scaling and `PHT003` controls, installed nested decoder evidence',
            'The N+1-safe nested-resource extension pins the maintainer, public, knowledge-map, skeleton, and application-template routes',
            'a deny-before-connection control uses zero statements;',
            'Retained phase snapshots stay `[1, 1, 1]` after load, mapping, and encoding for every accepted page and `[0, 0, 0]` for denial',
            'exposes `[1, 2, 2]` and `[1, 51, 51]` phase counts',
            'The intentionally invalid `.php.fixture` performs one child query inside the parent loop',
            'The isolated installed proof accepts present and null children plus reordered object members',
            'Those deterministic source/name checks do not prove the absence of indirect or differently named database, cache, or integration I/O;',
            'Neutral `parent.child` notation is neither a framework resource schema nor a recommendation to emit those literal fields.',
            'This adds no framework relationship mechanism, response mechanism, consumer-checker rule, Contract or Strict Profile change, or new `PHT` diagnostic.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'structured JSON success-envelope guidance');

    $installedComposer = jsonFile($installedFramework . '/composer.json');
    $installedRuntimeRequirements = $installedComposer['require'] ?? null;

    if (!is_array($installedRuntimeRequirements)) {
        throw new RuntimeException('Installed framework runtime requirements must be an explicit Composer map.');
    }

    foreach (array_keys($installedRuntimeRequirements) as $runtimePackage) {
        if (
            !is_string($runtimePackage)
            || (
                $runtimePackage !== 'php'
                && !str_starts_with($runtimePackage, 'ext-')
            )
        ) {
            throw new RuntimeException(
                'Installed structured JSON guidance must not add a framework runtime dependency.',
            );
        }
    }

    $consumerComposer = jsonFile($project . '/composer.json');
    $consumerRuntimeRequirements = $consumerComposer['require'] ?? null;

    if (!is_array($consumerRuntimeRequirements)) {
        throw new RuntimeException('Installed skeleton runtime requirements must be an explicit Composer map.');
    }

    $consumerRuntimePackages = array_keys($consumerRuntimeRequirements);

    foreach ($consumerRuntimePackages as $consumerRuntimePackage) {
        if (!is_string($consumerRuntimePackage)) {
            throw new RuntimeException('Installed skeleton runtime requirement names must be strings.');
        }
    }

    sort($consumerRuntimePackages, SORT_STRING);

    if ($consumerRuntimePackages !== ['php', 'phpthis/framework']) {
        throw new RuntimeException(
            'Installed structured JSON guidance must not add a default-skeleton runtime dependency.',
        );
    }

    $forbiddenRuntimePathPattern = '/(?:\A|\/)(?:[A-Za-z0-9]*(?:Envelope|Wrapper|Serializer|Resource|ResourceCollection|Paginator|Pagination)|[A-Za-z0-9]*JsonResponse|(?:Response|Json|Resource|Success|Pagination)[A-Za-z0-9]*(?:Builder|Factory|Responder|Formatter|Transformer|Middleware|Facade|Helper|Reflection|Discovery)|(?:Response|Json|Resource|Success|Pagination)\/(?:Builder|Factory|Responder|Formatter|Transformer|Middleware|Facade|Helper|Reflection|Discovery)|[A-Za-z0-9]*(?:RelationshipLoader|RelationLoader|LazyRelationship|LazyRelation|EagerRelationship|EagerRelation|BatchLoader|DataLoader)|(?:Relationships?|Relations?)\/(?:Loader|Resolver|Mapper|Batcher)|[A-Za-z0-9]*(?:SchemaGenerator|ClientGenerator|SdkGenerator)|(?:OpenApi|JsonSchema)[A-Za-z0-9]*)(?:\.php|\/)/i';
    $installedSourceRoots = [
        $installedFramework . '/src' => 'installed framework',
        $project . '/src' => 'installed default skeleton',
    ];

    foreach ($installedSourceRoots as $installedSourceRoot => $installedSourceOwner) {
        $installedSourceFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($installedSourceRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($installedSourceFiles as $installedSourceFile) {
            if (!$installedSourceFile instanceof SplFileInfo || !$installedSourceFile->isFile()) {
                continue;
            }

            $relativePath = substr($installedSourceFile->getPathname(), strlen(dirname($installedSourceRoot)) + 1);

            if (preg_match($forbiddenRuntimePathPattern, $relativePath) === 1) {
                throw new RuntimeException(
                    "Structured JSON response runtime mechanism must remain outside the {$installedSourceOwner}: {$relativePath}.",
                );
            }
        }
    }

    $proofPath = $project . '/installed-structured-json-success-envelope-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Http\Response;

require __DIR__ . '/vendor/autoload.php';

/** @return array{id: int, name: string} */
function decodeGetUserSuccess(Response $response): array
{
    requireStructuredJsonSuccessResponse($response);
    $decoded = json_decode($response->body, false, 8, JSON_THROW_ON_ERROR);

    if (
        !$decoded instanceof stdClass
        || count(get_object_vars($decoded)) !== 1
        || !property_exists($decoded, 'data')
        || !$decoded->data instanceof stdClass
        || count(get_object_vars($decoded->data)) !== 2
        || !property_exists($decoded->data, 'id')
        || !property_exists($decoded->data, 'name')
        || !is_int($decoded->data->id)
        || $decoded->data->id < 1
        || !is_string($decoded->data->name)
        || $decoded->data->name === ''
        || preg_match('//u', $decoded->data->name) !== 1
    ) {
        throw new UnexpectedValueException('GetUser success representation is incompatible.');
    }

    return ['id' => $decoded->data->id, 'name' => $decoded->data->name];
}

/** @return array{data: list<array{id: int, name: string, event_count: int}>, meta: array{next_after_user_id: string|null}} */
function decodeListUsersSuccess(Response $response): array
{
    requireStructuredJsonSuccessResponse($response);
    $decoded = json_decode($response->body, false, 16, JSON_THROW_ON_ERROR);

    if (
        !$decoded instanceof stdClass
        || count(get_object_vars($decoded)) !== 2
        || !property_exists($decoded, 'data')
        || !property_exists($decoded, 'meta')
        || !is_array($decoded->data)
        || !array_is_list($decoded->data)
        || count($decoded->data) > 50
        || !$decoded->meta instanceof stdClass
        || count(get_object_vars($decoded->meta)) !== 1
        || !property_exists($decoded->meta, 'next_after_user_id')
        || (!is_string($decoded->meta->next_after_user_id) && $decoded->meta->next_after_user_id !== null)
    ) {
        throw new UnexpectedValueException('ListUsers success representation is incompatible.');
    }

    $nextAfterUserId = $decoded->meta->next_after_user_id;

    if ($nextAfterUserId !== null) {
        if (
            $nextAfterUserId === ''
            || strlen($nextAfterUserId) > strlen((string) PHP_INT_MAX)
        ) {
            throw new UnexpectedValueException('ListUsers continuation is incompatible.');
        }
    }

    $users = [];

    foreach ($decoded->data as $user) {
        if (
            !$user instanceof stdClass
            || count(get_object_vars($user)) !== 3
            || !property_exists($user, 'id')
            || !property_exists($user, 'name')
            || !property_exists($user, 'event_count')
            || !is_int($user->id)
            || $user->id < 1
            || !is_string($user->name)
            || $user->name === ''
            || preg_match('//u', $user->name) !== 1
            || !is_int($user->event_count)
            || $user->event_count < 0
        ) {
            throw new UnexpectedValueException('ListUsers data item is incompatible.');
        }

        $users[] = ['id' => $user->id, 'name' => $user->name, 'event_count' => $user->event_count];
    }

    return ['data' => $users, 'meta' => ['next_after_user_id' => $nextAfterUserId]];
}

/** @return array{data: list<array{document_key: string, title: string, category: string, sort_rank: int}>, meta: array{next_cursor: string|null}} */
function decodeListDocumentsSuccess(Response $response): array
{
    requireStructuredJsonSuccessResponse($response);
    $decoded = json_decode($response->body, false, 16, JSON_THROW_ON_ERROR);

    if (
        !$decoded instanceof stdClass
        || count(get_object_vars($decoded)) !== 2
        || !property_exists($decoded, 'data')
        || !property_exists($decoded, 'meta')
        || !is_array($decoded->data)
        || !array_is_list($decoded->data)
        || count($decoded->data) > 50
        || !$decoded->meta instanceof stdClass
        || count(get_object_vars($decoded->meta)) !== 1
        || !property_exists($decoded->meta, 'next_cursor')
        || (!is_string($decoded->meta->next_cursor) && $decoded->meta->next_cursor !== null)
    ) {
        throw new UnexpectedValueException('ListDocuments success representation is incompatible.');
    }

    $nextCursor = $decoded->meta->next_cursor;

    if ($nextCursor !== null) {
        $maximumCursorBytes = strlen('v1:rank_desc:1000000:') + 64;

        if ($nextCursor === '' || strlen($nextCursor) > $maximumCursorBytes) {
            throw new UnexpectedValueException('ListDocuments continuation is incompatible.');
        }
    }

    $documents = [];

    foreach ($decoded->data as $document) {
        if (
            !$document instanceof stdClass
            || count(get_object_vars($document)) !== 4
            || !property_exists($document, 'document_key')
            || !property_exists($document, 'title')
            || !property_exists($document, 'category')
            || !property_exists($document, 'sort_rank')
            || !is_string($document->document_key)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $document->document_key) !== 1
            || !is_string($document->title)
            || $document->title === ''
            || preg_match('//u', $document->title) !== 1
            || !is_string($document->category)
            || $document->category === ''
            || strlen($document->category) > 64
            || preg_match('//u', $document->category) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $document->category) === 1
            || !is_int($document->sort_rank)
            || $document->sort_rank < 0
            || $document->sort_rank > 1_000_000
        ) {
            throw new UnexpectedValueException('ListDocuments data item is incompatible.');
        }

        $documents[] = [
            'document_key' => $document->document_key,
            'title' => $document->title,
            'category' => $document->category,
            'sort_rank' => $document->sort_rank,
        ];
    }

    return ['data' => $documents, 'meta' => ['next_cursor' => $nextCursor]];
}

/**
 * @return array{
 *   data: list<array{
 *     id: string,
 *     label: string,
 *     child_id: string,
 *     child: array{id: string, label: string, public_url: string|null}|null
 *   }>
 * }
 */
function decodeListParentsSuccess(Response $response): array
{
    requireStructuredJsonSuccessResponse($response);
    $decoded = json_decode($response->body, false, 16, JSON_THROW_ON_ERROR);

    if (
        !$decoded instanceof stdClass
        || count(get_object_vars($decoded)) !== 1
        || !property_exists($decoded, 'data')
        || !is_array($decoded->data)
        || !array_is_list($decoded->data)
        || count($decoded->data) > 50
    ) {
        throw new UnexpectedValueException('ListParents success representation is incompatible.');
    }

    $parents = [];

    foreach ($decoded->data as $parent) {
        if (
            !$parent instanceof stdClass
            || count(get_object_vars($parent)) !== 4
            || !property_exists($parent, 'id')
            || !property_exists($parent, 'label')
            || !property_exists($parent, 'child_id')
            || !property_exists($parent, 'child')
            || !isCanonicalUuidString($parent->id)
            || !is_string($parent->label)
            || $parent->label === ''
            || strlen($parent->label) > 160
            || preg_match('//u', $parent->label) !== 1
            || !isCanonicalUuidString($parent->child_id)
        ) {
            throw new UnexpectedValueException('ListParents data item is incompatible.');
        }

        $child = $parent->child;
        $decodedChild = null;

        if ($child !== null) {
            if (
                !$child instanceof stdClass
                || count(get_object_vars($child)) !== 3
                || !property_exists($child, 'id')
                || !property_exists($child, 'label')
                || !property_exists($child, 'public_url')
                || !isCanonicalUuidString($child->id)
                || $child->id !== $parent->child_id
                || !is_string($child->label)
                || $child->label === ''
                || strlen($child->label) > 160
                || preg_match('//u', $child->label) !== 1
                || (
                    $child->public_url !== null
                    && (
                        !is_string($child->public_url)
                        || strlen($child->public_url) > 2_048
                        || preg_match('//u', $child->public_url) !== 1
                        || preg_match('/\Ahttps:\/\/[!-~]+\z/D', $child->public_url) !== 1
                    )
                )
            ) {
                throw new UnexpectedValueException('ListParents child is incompatible.');
            }

            $decodedChild = [
                'id' => $child->id,
                'label' => $child->label,
                'public_url' => $child->public_url,
            ];
        }

        $parents[] = [
            'id' => $parent->id,
            'label' => $parent->label,
            'child_id' => $parent->child_id,
            'child' => $decodedChild,
        ];
    }

    return ['data' => $parents];
}

function isCanonicalUuidString(mixed $value): bool
{
    return is_string($value)
        && preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
            $value,
        ) === 1;
}

function requireStructuredJsonSuccessResponse(Response $response): void
{
    if ($response->status !== 200) {
        throw new UnexpectedValueException('Expected a successful response before decoding.');
    }

    if (($response->headers['Content-Type'] ?? null) !== 'application/json; charset=utf-8') {
        throw new UnexpectedValueException('Expected the exact structured JSON response media type.');
    }
}

/** @param callable(): mixed $operation */
function requireDecoderRejection(callable $operation): void
{
    try {
        $operation();
    } catch (JsonException|UnexpectedValueException) {
        return;
    }

    throw new RuntimeException('An incompatible structured JSON success response was accepted.');
}

$getUser = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
    "{\"data\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n",
);
$listUsersContinuation = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
    "{\"data\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}],\"meta\":{\"next_after_user_id\":\"50\"}}\n",
);
$listUsersEnd = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
    "{\"data\":[{\"id\":1,\"name\":\"Ada Lovelace\",\"event_count\":1}],\"meta\":{\"next_after_user_id\":null}}\n",
);
$listDocumentsEmpty = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'],
    "{\"data\":[],\"meta\":{\"next_cursor\":null}}\n",
);
$listDocumentsContinuation = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'],
    "{\"data\":[{\"document_key\":\"Doc_001\",\"title\":\"Plan\",\"category\":\"active\",\"sort_rank\":1}],\"meta\":{\"next_cursor\":\"v1:rank_asc:1:Doc_001\"}}\n",
);
$listDocumentsEnd = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'],
    "{\"data\":[{\"document_key\":\"Doc_001\",\"title\":\"Plan\",\"category\":\"active\",\"sort_rank\":1}],\"meta\":{\"next_cursor\":null}}\n",
);
$missingUser = new Response(
    404,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
    "{\"error\":{\"code\":\"user_not_found\",\"message\":\"User was not found.\"}}\n",
);
$reorderedGetUser = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":{\"name\":\"Ada Lovelace\",\"id\":1}}\n",
);
$reorderedListDocuments = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"meta\":{\"next_cursor\":null},\"data\":[]}\n",
);
$opaqueListUsersContinuation = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_after_user_id\":\"01\"}}\n",
);
$opaqueListDocumentsContinuation = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_cursor\":\"50\"}}\n",
);
$parentId = 'cd7fcaf6-7b5c-4b25-a2f6-01ecad54f86b';
$childId = '3f9a5f00-3509-47b0-ac2f-4d648956381a';
$parentWithChild = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[{\"id\":\"{$parentId}\",\"label\":\"Parent 2\",\"child_id\":\"{$childId}\",\"child\":{\"id\":\"{$childId}\",\"label\":\"Child 1\",\"public_url\":null}}]}\n",
);
$parentWithoutVisibleChild = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[{\"id\":\"{$parentId}\",\"label\":\"Parent 2\",\"child_id\":\"{$childId}\",\"child\":null}]}\n",
);
$parentWithPublicUrl = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[{\"id\":\"{$parentId}\",\"label\":\"Parent 2\",\"child_id\":\"{$childId}\",\"child\":{\"id\":\"{$childId}\",\"label\":\"Child 1\",\"public_url\":\"https://example.com/children/1\"}}]}\n",
);
$reorderedParentWithChild = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[{\"child\":{\"public_url\":null,\"label\":\"Child 1\",\"id\":\"{$childId}\"},\"child_id\":\"{$childId}\",\"label\":\"Parent 2\",\"id\":\"{$parentId}\"}]}\n",
);
$emptyParents = new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[]}\n",
);
$expectedParent = [
    'id' => $parentId,
    'label' => 'Parent 2',
    'child_id' => $childId,
    'child' => [
        'id' => $childId,
        'label' => 'Child 1',
        'public_url' => null,
    ],
];

if (
    $getUser->body !== "{\"data\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n"
    || decodeGetUserSuccess($getUser) !== ['id' => 1, 'name' => 'Ada Lovelace']
    || decodeGetUserSuccess($reorderedGetUser) !== ['id' => 1, 'name' => 'Ada Lovelace']
    || decodeListUsersSuccess($listUsersContinuation)['meta']['next_after_user_id'] !== '50'
    || decodeListUsersSuccess($listUsersEnd)['meta']['next_after_user_id'] !== null
    || decodeListDocumentsSuccess($listDocumentsEmpty) !== ['data' => [], 'meta' => ['next_cursor' => null]]
    || decodeListDocumentsSuccess($listDocumentsContinuation)['meta']['next_cursor'] !== 'v1:rank_asc:1:Doc_001'
    || decodeListDocumentsSuccess($listDocumentsEnd)['meta']['next_cursor'] !== null
    || decodeListDocumentsSuccess($reorderedListDocuments) !== ['data' => [], 'meta' => ['next_cursor' => null]]
    || decodeListUsersSuccess($opaqueListUsersContinuation)['meta']['next_after_user_id'] !== '01'
    || decodeListDocumentsSuccess($opaqueListDocumentsContinuation)['meta']['next_cursor'] !== '50'
    || decodeListParentsSuccess($parentWithChild) !== ['data' => [$expectedParent]]
    || decodeListParentsSuccess($reorderedParentWithChild) !== ['data' => [$expectedParent]]
    || decodeListParentsSuccess($parentWithoutVisibleChild)['data'][0]['child'] !== null
    || decodeListParentsSuccess($parentWithPublicUrl)['data'][0]['child']['public_url'] !== 'https://example.com/children/1'
    || decodeListParentsSuccess($emptyParents) !== ['data' => []]
    || $missingUser->status !== 404
    || str_contains($missingUser->body, '"data":null')
) {
    throw new RuntimeException('Installed structured JSON success-envelope positive control failed.');
}

requireDecoderRejection(static fn (): array => decodeGetUserSuccess($missingUser));
requireDecoderRejection(static fn (): array => decodeGetUserSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":\n",
)));
requireDecoderRejection(static fn (): array => decodeGetUserSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"user\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n",
)));
requireDecoderRejection(static fn (): array => decodeGetUserSuccess(new Response(
    200,
    ['Content-Type' => 'application/json'],
    "{\"data\":{\"id\":1,\"name\":\"Ada Lovelace\"}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListUsersSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_cursor\":\"v1:rank_asc:1:Doc_001\"}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListUsersSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_after_user_id\":\"\"}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListDocumentsSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_after_user_id\":\"50\"}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListDocumentsSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_cursor\":\"\"}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListUsersSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":{},\"meta\":{\"next_after_user_id\":null}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListDocumentsSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":{},\"meta\":{\"next_cursor\":null}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListUsersSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_after_user_id\":50}}\n",
)));
requireDecoderRejection(static fn (): array => decodeListDocumentsSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    "{\"data\":[],\"meta\":{\"next_cursor\":50}}\n",
)));
$malformedParentBody =
    "{\"data\":[{\"id\":\"{$parentId}\",\"label\":\"Parent 2\",\"child_id\":\"{$childId}\",\"child\":\n";
requireDecoderRejection(static fn (): array => decodeListParentsSuccess(new Response(
    200,
    ['Content-Type' => 'application/json; charset=utf-8'],
    $malformedParentBody,
)));
$baseParent = [
    'id' => $parentId,
    'label' => 'Parent 2',
    'child_id' => $childId,
    'child' => [
        'id' => $childId,
        'label' => 'Child 1',
        'public_url' => null,
    ],
];
$invalidParentBodies = [
    json_encode(['data' => ['parent' => $baseParent]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        ...$baseParent,
        'id' => 'cd7fcaf6-7b5c-0b25-a2f6-01ecad54f86b',
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        ...$baseParent,
        'id' => 'cd7fcaf6-7b5c-4b25-72f6-01ecad54f86b',
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'related' => $baseParent['child'],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => [],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => ['id' => $childId, 'label' => 'Child 1'],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => [
            'id' => $childId,
            'label' => 'Child 1',
            'public_url' => null,
            'private_value' => 'must-not-be-exposed',
        ],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => ['id' => $parentId, 'label' => 'Child 1', 'public_url' => null],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => ['id' => $childId, 'label' => 7, 'public_url' => null],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => ['id' => $childId, 'label' => str_repeat('n', 161), 'public_url' => null],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => ['id' => $childId, 'label' => 'Child 1', 'public_url' => 'http://example.com/child'],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => [[
        'id' => $parentId,
        'label' => 'Parent 2',
        'child_id' => $childId,
        'child' => [
            'id' => $childId,
            'label' => 'Child 1',
            'public_url' => 'https://example.com/' . str_repeat('a', 2_030),
        ],
    ]]], JSON_THROW_ON_ERROR) . "\n",
    json_encode(['data' => array_fill(0, 51, $baseParent)], JSON_THROW_ON_ERROR) . "\n",
];

foreach ($invalidParentBodies as $invalidParentBody) {
    requireDecoderRejection(static fn (): array => decodeListParentsSuccess(new Response(
        200,
        ['Content-Type' => 'application/json; charset=utf-8'],
        $invalidParentBody,
    )));
}
$tooManyUsersBody = json_encode(
    [
        'data' => array_fill(0, 51, ['id' => 1, 'name' => 'Ada Lovelace', 'event_count' => 0]),
        'meta' => ['next_after_user_id' => null],
    ],
    JSON_THROW_ON_ERROR,
) . "\n";
$overlongUserContinuationBody = json_encode(
    [
        'data' => [],
        'meta' => ['next_after_user_id' => str_repeat('9', strlen((string) PHP_INT_MAX) + 1)],
    ],
    JSON_THROW_ON_ERROR,
) . "\n";
$tooManyDocumentsBody = json_encode(
    [
        'data' => array_fill(
            0,
            51,
            ['document_key' => 'Doc_001', 'title' => 'Plan', 'category' => 'active', 'sort_rank' => 1],
        ),
        'meta' => ['next_cursor' => null],
    ],
    JSON_THROW_ON_ERROR,
) . "\n";
$overlongDocumentCursorBody = json_encode(
    ['data' => [], 'meta' => ['next_cursor' => str_repeat('c', 86)]],
    JSON_THROW_ON_ERROR,
) . "\n";
$invalidDocumentScalarsBody = json_encode(
    [
        'data' => [[
            'document_key' => str_repeat('k', 65),
            'title' => 'Plan',
            'category' => str_repeat('c', 65),
            'sort_rank' => 1_000_001,
        ]],
        'meta' => ['next_cursor' => null],
    ],
    JSON_THROW_ON_ERROR,
) . "\n";

foreach (
    [
        [decodeListUsersSuccess(...), $tooManyUsersBody],
        [decodeListUsersSuccess(...), $overlongUserContinuationBody],
        [decodeListDocumentsSuccess(...), $tooManyDocumentsBody],
        [decodeListDocumentsSuccess(...), $overlongDocumentCursorBody],
        [decodeListDocumentsSuccess(...), $invalidDocumentScalarsBody],
    ] as [$decoder, $body]
) {
    requireDecoderRejection(static fn (): array => $decoder(new Response(
        200,
        ['Content-Type' => 'application/json; charset=utf-8'],
        $body,
    )));
}

fwrite(STDOUT, "PASS installed structured JSON success-envelope and nested-resource runtime\n");
PHP,
    );

    try {
        $proofResult = runProcess([PHP_BINARY, $proofPath], $project, $environment);
        requireExactProcessResult(
            $proofResult,
            0,
            "PASS installed structured JSON success-envelope and nested-resource runtime\n",
            '',
            'Installed structured JSON success-envelope runtime proof failed.',
        );
    } finally {
        unlink($proofPath);
    }

    fwrite(STDOUT, "PASS installed structured JSON success-envelope and nested-resource guidance distribution\n");

    return 'installed-structured-json-and-nested-resource-proof-complete';
}

/** @param array<string, string> $environment */
function proveInstalledFieldValidationErrorGuidanceDistribution(
    string $project,
    string $installedFramework,
    array $environment,
): string {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $installedFramework . '/docs/errors.md' => [
            '## Optional application-owned field issues',
            "The generic `400` and `422` responses above remain PHPThis's recommended default and require no field details.",
            'This documentation-only advisory adds no PHPThis runtime or core API, dependency, Consumer Contract requirement, Strict Profile or `PHT` rule, checker diagnostic, generated code, or mandatory response schema.',
            'No member accepts `null`; no member is optional; unknown members and a per-issue `message` member are absent from this profile.',
            'The outer `code` and `message` are exactly `validation_failed` and `One or more fields are invalid.`',
            'The reference response uses `Content-Type: application/json; charset=utf-8` and `Cache-Control: no-store`; a protected or personalized operation uses `Cache-Control: private, no-store`.',
            'The `issues` array contains from 1 through 20 entries and at most one entry for each field path.',
            'The complete UTF-8 JSON representation is at most 16,384 bytes',
            'The exact envelope fixes the semantic nesting; a decoder with a configurable depth uses a maximum of 8',
            'Each segment and list index is one path component. A path has at most eight components and 256 bytes.',
            '`code` is a 1-to-64-byte lowercase ASCII identifier matching `[a-z][a-z0-9_]{0,63}` and selected from a finite operation-owned allowlist.',
            'It orders paths by the parser\'s fixed schema order, orders list items by ascending index, and places the whole-request `$` issue last.',
            'A cross-field rule either assigns one fixed issue to each participating code-owned path or assigns one issue to `$`',
            'Mixed structural and unacceptable-value inputs remain `400` regardless of submitted property order.',
            'Do not put data-dependent issue rendering in `ErrorResponseRegistry`, add a catch-all validation exception, or introduce a validator, error bag, string-rule language, response wrapper, reflection hydrator, middleware, discovery mechanism, or generated consumer code.',
            'Never include submitted values, unknown submitted keys, free-form exception or database text, credentials, tokens, principal or tenant details, resource identifiers, or authorization reasons.',
            'Per-issue human messages are deliberately absent.',
            'Rejecting an unknown code as a decode or contract failure is the safe reference behavior; a fallback or ignore policy must be explicit and tested.',
            'changing issue priority or array order can break clients',
            'this reference decoder does not claim rejection of duplicate object-member names',
        ],
        $installedFramework . '/docs/type-safety.md' => [
            '[Error responses](errors.md#optional-application-owned-field-issues) is the sole detailed owner of an optional finite reference profile',
            'preserves the complete structural `400` phase and keeps its value-issue `422` branch literal, bounded, code-owned, and before operation-owned I/O',
        ],
        $installedFramework . '/docs/frontend-integration.md' => [
            '[Optional application-owned field issues](errors.md#optional-application-owned-field-issues) defines the sole detailed reference profile',
            'a frontend must not extract internal rules from generic messages or guess between response shapes',
        ],
        $installedFramework . '/docs/request-handling.md' => [
            'The generic `400`/`422` contract remains valid',
            '[Optional application-owned field issues](errors.md#optional-application-owned-field-issues)',
            'without adding a validator, error bag, response wrapper, or dynamic error renderer',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Adopt, change, or review field-addressable value issues |',
            'exact finite code-owned path templates and code allowlists, literal bounded response construction before operation-owned I/O',
            'preserve the generic `400`/`422` default and verify that no validator, error bag, response wrapper, renderer, generator, core API, dependency, checker diagnostic, or universal schema was introduced',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'application-owned field-validation error guidance',
            'installed decoder fixture',
            'adds no framework validator, error bag, response wrapper, renderer, generated consumer code, runtime dependency, checker rule, or `PHT` diagnostic',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'field-validation error guidance');

    $installedComposer = jsonFile($installedFramework . '/composer.json');
    $installedRuntimeRequirements = $installedComposer['require'] ?? null;

    if (!is_array($installedRuntimeRequirements)) {
        throw new RuntimeException('Installed framework runtime requirements must be an explicit Composer map.');
    }

    foreach (array_keys($installedRuntimeRequirements) as $runtimePackage) {
        if (
            !is_string($runtimePackage)
            || (
                $runtimePackage !== 'php'
                && !str_starts_with($runtimePackage, 'ext-')
            )
        ) {
            throw new RuntimeException(
                'Installed field-validation error guidance must not add a framework runtime dependency.',
            );
        }
    }

    $consumerComposer = jsonFile($project . '/composer.json');
    $consumerRuntimeRequirements = $consumerComposer['require'] ?? null;

    if (!is_array($consumerRuntimeRequirements)) {
        throw new RuntimeException('Installed skeleton runtime requirements must be an explicit Composer map.');
    }

    $consumerRuntimePackages = array_keys($consumerRuntimeRequirements);

    foreach ($consumerRuntimePackages as $consumerRuntimePackage) {
        if (!is_string($consumerRuntimePackage)) {
            throw new RuntimeException('Installed skeleton runtime requirement names must be strings.');
        }
    }

    sort($consumerRuntimePackages, SORT_STRING);

    if ($consumerRuntimePackages !== ['php', 'phpthis/framework']) {
        throw new RuntimeException(
            'Installed field-validation error guidance must not add a default-skeleton runtime dependency.',
        );
    }

    $forbiddenRuntimePathPattern = '/(?:\A|\/)(?:Validation|[A-Za-z0-9]*Validator|(?:Field|Request)?Validation(?:Error|Errors|Issue|Issues|Failure|Failures|Result|Results|Response|Exception|Validator|Rule|Rules|RuleSet|StringRule|ErrorBag|Renderer|Middleware|Helper|Factory|Builder|Hydrator|Discovery)|Field(?:Error|Errors|Issue|Issues)(?:Bag|List|Response|Renderer|Helper)?|ErrorBag|Validation\/(?:Validator|Rule|Rules|RuleSet|StringRule|ErrorBag|Renderer|Middleware|Hydrator|Discovery))(?:\.php|\/)/i';
    $installedSourceRoots = [
        $installedFramework . '/src' => 'installed framework',
        $installedFramework . '/verification' => 'installed checker',
        $project . '/src' => 'installed default skeleton',
    ];

    foreach ($installedSourceRoots as $installedSourceRoot => $installedSourceOwner) {
        $installedSourceFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($installedSourceRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($installedSourceFiles as $installedSourceFile) {
            if (!$installedSourceFile instanceof SplFileInfo || !$installedSourceFile->isFile()) {
                continue;
            }

            $relativePath = substr(
                $installedSourceFile->getPathname(),
                strlen(dirname($installedSourceRoot)) + 1,
            );

            if (preg_match($forbiddenRuntimePathPattern, $relativePath) === 1) {
                throw new RuntimeException(
                    "Field-validation runtime mechanism must remain outside the {$installedSourceOwner}: {$relativePath}.",
                );
            }
        }
    }

    $proofPath = $project . '/installed-field-validation-error-advisory-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Http\Response;

require __DIR__ . '/vendor/autoload.php';

function matchesReferenceFieldPathGrammar(string $field): bool
{
    if ($field === '$') {
        return true;
    }

    return strlen($field) <= 256
        && preg_match(
            '/\A[a-z][a-z0-9_]{0,63}(?:(?:\.[a-z][a-z0-9_]{0,63})|(?:\[(?:0|[1-9][0-9]{0,5})\]))*\z/D',
            $field,
        ) === 1
        && substr_count($field, '.') + substr_count($field, '[') + 1 <= 8;
}

/**
 * @return array{
 *   code: string,
 *   message: string,
 *   issues: non-empty-list<array{field: string, code: string}>
 * }
 */
function decodeAdoptedFieldValidationFailure(Response $response): array
{
    if ($response->status !== 422) {
        throw new UnexpectedValueException('Expected the adopted field-validation failure status.');
    }

    if (($response->headers['Content-Type'] ?? null) !== 'application/json; charset=utf-8') {
        throw new UnexpectedValueException('Expected the exact field-validation failure media type.');
    }

    if (($response->headers['Cache-Control'] ?? null) !== 'private, no-store') {
        throw new UnexpectedValueException('Expected the adopted field-validation failure cache policy.');
    }

    if (strlen($response->body) > 16_384) {
        throw new UnexpectedValueException('Field-validation failure body exceeds its adopted bound.');
    }

    $decoded = json_decode($response->body, false, 8, JSON_THROW_ON_ERROR);

    if (
        !$decoded instanceof stdClass
        || count(get_object_vars($decoded)) !== 1
        || !property_exists($decoded, 'error')
        || !$decoded->error instanceof stdClass
        || count(get_object_vars($decoded->error)) !== 3
        || !property_exists($decoded->error, 'code')
        || !property_exists($decoded->error, 'message')
        || !property_exists($decoded->error, 'issues')
        || $decoded->error->code !== 'validation_failed'
        || $decoded->error->message !== 'One or more fields are invalid.'
        || !is_array($decoded->error->issues)
        || !array_is_list($decoded->error->issues)
        || $decoded->error->issues === []
        || count($decoded->error->issues) > 20
    ) {
        throw new UnexpectedValueException('Field-validation failure envelope is incompatible.');
    }

    $priorityByFieldAndCode = [
        'parent.child|invalid_format' => 0,
        'items[0].field_name|out_of_range' => 1,
        'items[1].field_name|out_of_range' => 2,
        'items[2].field_name|out_of_range' => 3,
        'items[3].field_name|out_of_range' => 4,
        'items[4].field_name|out_of_range' => 5,
        'items[5].field_name|out_of_range' => 6,
        'items[6].field_name|out_of_range' => 7,
        'items[7].field_name|out_of_range' => 8,
        'items[8].field_name|out_of_range' => 9,
        'items[9].field_name|out_of_range' => 10,
        'items[10].field_name|out_of_range' => 11,
        'items[11].field_name|out_of_range' => 12,
        'items[12].field_name|out_of_range' => 13,
        'items[13].field_name|out_of_range' => 14,
        'items[14].field_name|out_of_range' => 15,
        'items[15].field_name|out_of_range' => 16,
        'items[16].field_name|out_of_range' => 17,
        'items[17].field_name|out_of_range' => 18,
        'items[18].field_name|out_of_range' => 19,
        'items[19].field_name|out_of_range' => 20,
        'ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss|zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz' => 21,
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc.ddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd|at_limit' => 22,
        'a.b.c.d.e.f.g.h|at_limit' => 23,
        '$|inconsistent_values' => 24,
    ];
    $previousPriority = -1;
    $issues = [];

    foreach ($decoded->error->issues as $issue) {
        if (!$issue instanceof stdClass) {
            throw new UnexpectedValueException('Field-validation issue must be an object.');
        }

        $issueKeys = array_keys(get_object_vars($issue));
        sort($issueKeys, SORT_STRING);

        if (
            $issueKeys !== ['code', 'field']
            || !is_string($issue->field)
            || !is_string($issue->code)
            || $issue->code === ''
            || strlen($issue->code) > 64
            || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $issue->code) !== 1
        ) {
            throw new UnexpectedValueException('Field-validation issue shape is incompatible.');
        }

        if (!matchesReferenceFieldPathGrammar($issue->field)) {
            throw new UnexpectedValueException('Field-validation issue path is incompatible.');
        }

        $priority = $priorityByFieldAndCode[$issue->field . '|' . $issue->code] ?? null;

        if (!is_int($priority) || $priority <= $previousPriority) {
            throw new UnexpectedValueException('Field-validation issue identity or order is incompatible.');
        }

        $issues[] = ['field' => $issue->field, 'code' => $issue->code];
        $previousPriority = $priority;
    }

    return [
        'code' => $decoded->error->code,
        'message' => $decoded->error->message,
        'issues' => $issues,
    ];
}

function requireFieldValidationDecoderRejection(Response $response, string $sensitiveSentinel = ''): void
{
    try {
        decodeAdoptedFieldValidationFailure($response);
    } catch (JsonException|UnexpectedValueException $failure) {
        if ($sensitiveSentinel !== '' && str_contains($failure->getMessage(), $sensitiveSentinel)) {
            throw new RuntimeException('Field-validation decoder disclosed submitted or internal data.');
        }

        return;
    }

    throw new RuntimeException('An incompatible field-validation failure was accepted.');
}

/** @param array<mixed> $body */
function fieldValidationResponse(array $body): Response
{
    return new Response(
        422,
        ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'],
        json_encode($body, JSON_THROW_ON_ERROR) . "\n",
    );
}

if (
    !matchesReferenceFieldPathGrammar('$')
    || !matchesReferenceFieldPathGrammar('items[999999].field_name')
    || matchesReferenceFieldPathGrammar('items[1000000].field_name')
    || matchesReferenceFieldPathGrammar('items[00].field_name')
) {
    throw new RuntimeException(
        'Reference path grammar boundaries changed; they do not select an application list bound.',
    );
}

$singleIssue = fieldValidationResponse([
    'error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'parent.child',
            'code' => 'invalid_format',
        ]],
    ],
]);
$multipleIssues = fieldValidationResponse([
    'error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [
            ['field' => 'parent.child', 'code' => 'invalid_format'],
            ['field' => 'items[0].field_name', 'code' => 'out_of_range'],
            ['field' => '$', 'code' => 'inconsistent_values'],
        ],
    ],
]);
$maximumIssueList = [
    ['field' => 'items[0].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[1].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[2].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[3].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[4].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[5].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[6].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[7].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[8].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[9].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[10].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[11].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[12].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[13].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[14].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[15].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[16].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[17].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[18].field_name', 'code' => 'out_of_range'],
    ['field' => 'items[19].field_name', 'code' => 'out_of_range'],
];
$boundaryIssueList = [
    [
        'field' => 'ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss',
        'code' => 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz',
    ],
    [
        'field' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc.ddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
        'code' => 'at_limit',
    ],
    ['field' => 'a.b.c.d.e.f.g.h', 'code' => 'at_limit'],
];
$maximumIssues = fieldValidationResponse([
    'error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => $maximumIssueList,
    ],
]);
$boundaryIssues = fieldValidationResponse([
    'error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => $boundaryIssueList,
    ],
]);
$reorderedObjectMembers = fieldValidationResponse([
    'error' => [
        'issues' => [[
            'code' => 'invalid_format',
            'field' => 'parent.child',
        ]],
        'message' => 'One or more fields are invalid.',
        'code' => 'validation_failed',
    ],
]);
$genericStructuralFailure = new Response(
    400,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'],
    "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
);
$genericUnacceptableFailure = new Response(
    422,
    ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'],
    "{\"error\":{\"code\":\"unprocessable_content\",\"message\":\"Request content is unacceptable.\"}}\n",
);

$decodedSingle = decodeAdoptedFieldValidationFailure($singleIssue);
$decodedMultiple = decodeAdoptedFieldValidationFailure($multipleIssues);
$decodedMaximum = decodeAdoptedFieldValidationFailure($maximumIssues);
$decodedBoundaries = decodeAdoptedFieldValidationFailure($boundaryIssues);
$localizedTextByCode = [
    'invalid_format' => 'Use the accepted format.',
    'out_of_range' => 'Use a value in the accepted range.',
    'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz' => 'Use a supported boundary value.',
    'at_limit' => 'Use a value within the documented limit.',
    'inconsistent_values' => 'Use a consistent set of values.',
];
$localizedCodes = [];

foreach (
    [
        ...$decodedSingle['issues'],
        ...$decodedMaximum['issues'],
        ...$decodedBoundaries['issues'],
        ...$decodedMultiple['issues'],
    ] as $decodedIssue
) {
    $localizedText = $localizedTextByCode[$decodedIssue['code']] ?? null;

    if (!is_string($localizedText) || $localizedText === '') {
        throw new RuntimeException('Installed field-validation localization fixture is incomplete.');
    }

    $localizedCodes[$decodedIssue['code']] = true;
}

if (
    $decodedSingle['issues'] !== [[
        'field' => 'parent.child',
        'code' => 'invalid_format',
    ]]
    || $decodedMultiple['issues'][1] !== [
        'field' => 'items[0].field_name',
        'code' => 'out_of_range',
    ]
    || $decodedMultiple['issues'][2] !== [
        'field' => '$',
        'code' => 'inconsistent_values',
    ]
    || count($decodedMaximum['issues']) !== 20
    || $decodedMaximum['issues'][0]['field'] !== 'items[0].field_name'
    || $decodedMaximum['issues'][19]['field'] !== 'items[19].field_name'
    || strlen($decodedBoundaries['issues'][0]['field']) !== 64
    || strlen($decodedBoundaries['issues'][0]['code']) !== 64
    || strlen($decodedBoundaries['issues'][1]['field']) !== 256
    || substr_count($decodedBoundaries['issues'][2]['field'], '.') + 1 !== 8
    || array_keys($localizedCodes) !== [
        'invalid_format',
        'out_of_range',
        'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz',
        'at_limit',
        'inconsistent_values',
    ]
    || decodeAdoptedFieldValidationFailure($reorderedObjectMembers) !== $decodedSingle
    || $genericStructuralFailure->status !== 400
    || $genericStructuralFailure->body
        !== "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n"
    || $genericUnacceptableFailure->status !== 422
    || $genericUnacceptableFailure->body
        !== "{\"error\":{\"code\":\"unprocessable_content\",\"message\":\"Request content is unacceptable.\"}}\n"
) {
    throw new RuntimeException('Installed field-validation error positive control failed.');
}

requireFieldValidationDecoderRejection($genericStructuralFailure);
requireFieldValidationDecoderRejection($genericUnacceptableFailure);
requireFieldValidationDecoderRejection(new Response(
    422,
    [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'private, no-store',
    ],
    "{\"error\":\n",
));
requireFieldValidationDecoderRejection(new Response(
    422,
    [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'private, no-store',
    ],
    $singleIssue->body,
));
requireFieldValidationDecoderRejection(new Response(
    422,
    ['Content-Type' => 'application/json; charset=utf-8'],
    $singleIssue->body,
));
requireFieldValidationDecoderRejection(new Response(
    422,
    [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
    ],
    $singleIssue->body,
));
$separateFailures = [
    [401, 'unauthenticated'],
    [403, 'forbidden'],
    [404, 'resource_not_found'],
    [409, 'conflict'],
    [412, 'precondition_failed'],
    [413, 'request_body_too_large'],
    [415, 'unsupported_media_type'],
    [429, 'rate_limited'],
    [500, 'internal_error'],
];

foreach ($separateFailures as [$status, $code]) {
    requireFieldValidationDecoderRejection(new Response(
        $status,
        [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ],
        json_encode(
            ['error' => ['code' => $code, 'message' => 'This failure remains separate.']],
            JSON_THROW_ON_ERROR,
        ) . "\n",
    ));
}

$sensitiveSentinel = 'submitted-secret-do-not-disclose';
$invalidBodies = [
    [],
    ['error' => null],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => 'invalid',
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => ['issue' => ['field' => 'parent.child', 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => ['invalid'],
    ]],
    [
        'error' => [
            'code' => 'validation_failed',
            'message' => 'One or more fields are invalid.',
            'issues' => [['field' => 'parent.child', 'code' => 'invalid_format']],
        ],
        'meta' => [],
    ],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 'parent.child', 'code' => 'invalid_format']],
        'debug' => 'fixed-debug-member',
    ]],
    ['error' => [
        'code' => 'unprocessable_content',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 'parent.child', 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'A submitted value is invalid.',
        'issues' => [['field' => 'parent.child', 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => null,
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 'parent.child', 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => false,
        'issues' => [['field' => 'parent.child', 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [
            ['field' => 'parent.child', 'code' => 'invalid_format'],
            ['field' => 'parent.child', 'code' => 'invalid_format'],
        ],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 'parent.child']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 1, 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => null, 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 'submitted_unknown', 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 'parent.child', 'code' => 'unknown_rule']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => '$', 'code' => 'invalid_format']],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'parent.child',
            'code' => 'invalid_format',
            'message' => null,
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'parent.child',
            'code' => 'invalid_format',
            'value' => $sensitiveSentinel,
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [
            ['field' => 'items[0].field_name', 'code' => 'out_of_range'],
            ['field' => 'parent.child', 'code' => 'invalid_format'],
        ],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [
            ...$maximumIssueList,
            ['field' => '$', 'code' => 'inconsistent_values'],
        ],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => str_repeat('f', 257),
            'code' => 'invalid_format',
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'parent.' . str_repeat('s', 65),
            'code' => 'invalid_format',
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'items[20].field_name',
            'code' => 'out_of_range',
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'items[00].field_name',
            'code' => 'out_of_range',
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'parent.child',
            'code' => str_repeat('c', 65),
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [[
            'field' => 'a.b.c.d.e.f.g.h.i.j.k.l.m.n.o.p.q',
            'code' => 'invalid_format',
        ]],
    ]],
    ['error' => [
        'code' => 'validation_failed',
        'message' => 'One or more fields are invalid.',
        'issues' => [['field' => 'parent.child', 'code' => 'invalid_format']],
        'debug' => str_repeat('d', 16_384),
    ]],
];

foreach ($invalidBodies as $invalidBody) {
    requireFieldValidationDecoderRejection(
        fieldValidationResponse($invalidBody),
        $sensitiveSentinel,
    );
}

fwrite(STDOUT, "PASS installed application-owned field-validation error decoder\n");
PHP,
    );

    try {
        $proofSource = file_get_contents($proofPath);

        if (!is_string($proofSource)) {
            throw new RuntimeException('Unable to read the installed field-validation error proof.');
        }

        foreach (
            [
                'PDO',
                'Connection',
                'selectAllRows',
                'selectOneRow',
                'executeStatement',
                'curl_',
                'fsockopen',
                'Redis',
            ] as $forbiddenIoMarker
        ) {
            if (str_contains($proofSource, $forbiddenIoMarker)) {
                throw new RuntimeException(
                    "Installed field-validation decoder proof contains forbidden I/O marker: {$forbiddenIoMarker}.",
                );
            }
        }

        $proofResult = runProcess([PHP_BINARY, $proofPath], $project, $environment);
        requireExactProcessResult(
            $proofResult,
            0,
            "PASS installed application-owned field-validation error decoder\n",
            '',
            'Installed field-validation error decoder proof failed.',
        );
    } finally {
        if (is_file($proofPath) && !unlink($proofPath)) {
            throw new RuntimeException('Unable to remove the installed field-validation error proof.');
        }
    }

    fwrite(STDOUT, "PASS installed field-validation error guidance distribution\n");

    return 'installed-field-validation-error-guidance-proof-complete';
}

function proveInstalledSessionCleanupAndResponseFramingDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/testing.md' => [
            'Cleanup evidence proves exact primary identity after success; redacted retention after cleanup failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'terminal reset after finish or abort; no retry after cleanup failure',
            '`HTTP_RESPONSE_FRAMING`',
            'A `HEAD` route is explicit and returns an empty body without inferred representation length.',
        ],
        $installedFramework . '/docs/decisions/045-bounded-session-cleanup-and-response-framing.md' => [
            '# ADR 045: Bounded session cleanup and response framing',
            'Status: accepted',
            'When cleanup also fails, it throws the narrow redacted `SessionCleanupFailed` failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'Cleanup follows prerequisite order; it does not retry or attempt an unsafe dependent action after its prerequisite fails.',
            '`Response` accepts final response statuses from `200` through `599`',
            '`HEAD` remains application-owned and explicit.',
            'Strict Profile version 3 remains unchanged',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'Contract version: 16',
            'A final `Response` uses a status from `200` through `599`, never `Transfer-Encoding`',
            'a second cleanup failure becomes the narrow redacted `SessionCleanupFailed` retaining both failures',
            'Contract version 11 carries contract version 10 forward and retains Strict Profile version 3.',
        ],
        $installedFramework . '/docs/request-handling.md' => [
            'An ordinary final response has a status from `200` through `599`, no `Transfer-Encoding`',
            'A `204`, `205`, or `304` has no ordinary body and no `Content-Length`.',
            '`ResponseEmitter` receives only a `Response`',
        ],
        $installedFramework . '/docs/sessions.md' => [
            '## Cleanup failure precedence',
            'Failed invalidation cleanup likewise clears live pending-cookie ownership before it escapes.',
            'Cleanup follows prerequisite order and does not retry or attempt an unsafe dependent action after its prerequisite fails.',
            'If cleanup also fails, `SessionCleanupFailed` retains the original and cleanup failures',
            'PHPThis does not log, retry, suppress, or turn either failure into a response inside session code.',
        ],
        $installedFramework . '/src/Http/Response.php' => [
            '$status < 200 || $status > 599',
            "isset(\$normalizedHeaderNames['transfer-encoding'])",
            'in_array($status, [204, 205, 304], true)',
            '$contentLength !== (string) strlen($body)',
            '$contentLength !== (string) $fileBody->bytes',
        ],
        $installedFramework . '/src/Http/ResponseEmitter.php' => [
            'public function emit(Response $response): void',
            'echo $response->body;',
            'private function emitFile(Response $response, LocalFileBody $body): void',
        ],
        $installedFramework . '/src/Session/SessionCleanupFailed.php' => [
            'final class SessionCleanupFailed extends \\RuntimeException',
            'public readonly \\Throwable $primaryFailure',
            'public readonly \\Throwable $cleanupFailure',
            "parent::__construct('Session cleanup failed after a primary failure.');",
        ],
        $installedFramework . '/src/Session/SessionLifecycle.php' => [
            'private function failAfterCleanup(Throwable $primaryFailure, ?string $firstUnissuedId, ?string $secondUnissuedId = null, bool $abortActive = true): never',
            'throw new SessionCleanupFailed($primaryFailure, $cleanupFailure);',
            'if (!$this->cleanupFailed)',
            "} catch (Throwable \$failure) {\n            if (\$this->cleanupFailed) {\n                throw \$failure;\n            }\n            \$this->failAfterCleanup(\$failure, \$createdId);",
            '$this->cleanupFailed && session_status() !== PHP_SESSION_NONE',
            '$this->failAfterCleanup($failure, $newId, null, false);',
            "if (!session_start(\$options)) {\n            \$this->failAfterCleanup(new RuntimeException('Unable to start native session storage.'), null);\n        }",
            "new RuntimeException('Unable to invalidate native session state.')",
            "\$unissuedId = \$this->unissuedId;\n        \$this->unissuedId = \$this->pendingCookie = null;\n\n        \$this->start(\$incomingId, false);",
            '$this->unissuedId = $this->pendingCookie = null;',
            "if (session_status() === PHP_SESSION_NONE && session_id('') === false) {\n            \$this->cleanupFailed = true;\n            throw new RuntimeException('Unable to clear native session request state.');",
            "if (session_status() === PHP_SESSION_ACTIVE && !session_abort()) {\n            \$this->cleanupFailed = true;\n            throw new RuntimeException('Unable to abort native session state.');",
            'if ($abortActive)',
            '$previousCleanupFailed = $this->cleanupFailed;',
            '$this->cleanupFailed = true;',
            '$this->resetRequestState();',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Cleanup evidence proves exact primary identity after success; redacted retention after cleanup failure',
            'invalidation commit-failure precedence without a stale live cookie',
            'terminal reset after finish or abort; no retry after cleanup failure',
            'Every response test asserts the final status, body, and headers selected by the route.',
            'a `HEAD` route remains explicit with an empty body and no inferred representation length.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'session-cleanup and response-framing');

    $installedEmitter = file_get_contents($installedFramework . '/src/Http/ResponseEmitter.php');

    if (is_string($installedEmitter) && str_contains($installedEmitter, 'Request $request')) {
        throw new RuntimeException('The installed ResponseEmitter gained request knowledge.');
    }

    fwrite(STDOUT, "PASS installed session cleanup and response framing distribution\n");
}

/** @param array<string, string> $environment */
function proveInstalledBoundedResponseCookieProfileDistribution(
    string $project,
    string $installedFramework,
    array $environment,
): void {
    $proofPath = $project . '/installed-response-cookie-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

namespace PHPThis\Http {
    function headers_sent(): bool
    {
        return false;
    }

    function header(string $header, bool $replace = true): void
    {
        $GLOBALS['installed_response_cookie_headers'][] = [$header, $replace];
    }

    function http_response_code(int $responseCode = 0): int
    {
        $GLOBALS['installed_response_cookie_status'] = $responseCode;

        return $responseCode;
    }
}

namespace {
use PHPThis\Http\CookieSameSite;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseCookie;
use PHPThis\Http\ResponseEmitter;
use PHPThis\Session\SessionConfiguration;

require __DIR__ . '/vendor/autoload.php';

/** @param callable(): object $operation */
function requireInvalidCookieOperation(callable $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Installed response-cookie proof accepted a rejected value.');
}

$maximumNameValue = new ResponseCookie(
    'n',
    str_repeat('v', 4_095),
    '/',
    false,
    false,
    CookieSameSite::Lax,
);
$maximumName = new ResponseCookie(
    str_repeat('n', 4_096),
    '',
    '/',
    false,
    false,
    CookieSameSite::Lax,
);
$maximumPath = new ResponseCookie(
    'path',
    'value',
    '/' . str_repeat('p', 1_023),
    false,
    false,
    CookieSameSite::Strict,
);
$maximumExpirationTimestamp = PHP_INT_SIZE >= 8 ? (int) '253402300799' : PHP_INT_MAX;
$maximumExpiration = new ResponseCookie(
    'maximum-expiration',
    'value',
    '/',
    false,
    false,
    CookieSameSite::Lax,
    $maximumExpirationTimestamp,
);
$maximumAge = new ResponseCookie(
    'maximum-age',
    'value',
    '/',
    false,
    false,
    CookieSameSite::Lax,
    maximumAgeSeconds: 34_560_000,
);
$prefixCase = new ResponseCookie(
    '__hOsT-HtTp-case',
    'value',
    '/',
    true,
    true,
    CookieSameSite::Lax,
);
$configuration = new SessionConfiguration(
    'INSTALLEDPROOF',
    '__Host-InstalledSession',
    true,
    CookieSameSite::Lax,
    __DIR__,
);
$live = $configuration->liveCookie(str_repeat('a', 32));
$expired = $configuration->expiredCookie();
$developmentConfiguration = new SessionConfiguration(
    'INSTALLEDDEVELOPMENT',
    'InstalledDevelopmentSession',
    false,
    CookieSameSite::Strict,
    __DIR__,
);
$developmentLive = $developmentConfiguration->liveCookie(str_repeat('d', 32));
$developmentExpired = $developmentConfiguration->expiredCookie();

if (
    strlen($maximumNameValue->name) + strlen($maximumNameValue->value) !== 4_096
    || strlen($maximumName->name) !== 4_096
    || strlen($maximumPath->path) !== 1_024
    || $maximumExpiration->headerValue() !== 'maximum-expiration=value; Path=/'
        . '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $maximumExpirationTimestamp)
        . '; SameSite=Lax'
    || $maximumAge->headerValue() !== 'maximum-age=value; Path=/; Max-Age=34560000; SameSite=Lax'
    || strlen(gmdate('Y', $maximumExpirationTimestamp)) !== 4
    || !str_starts_with($prefixCase->headerValue(), '__hOsT-HtTp-case=value;')
    || $live->headerValue() !== '__Host-InstalledSession=' . str_repeat('a', 32)
        . '; Path=/; Secure; HttpOnly; SameSite=Lax'
    || $expired->headerValue() !== '__Host-InstalledSession=; Path=/'
        . '; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; Secure; HttpOnly; SameSite=Lax'
    || $developmentLive->headerValue() !== 'InstalledDevelopmentSession=' . str_repeat('d', 32)
        . '; Path=/; HttpOnly; SameSite=Strict'
    || $developmentExpired->headerValue() !== 'InstalledDevelopmentSession=; Path=/'
        . '; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; HttpOnly; SameSite=Strict'
) {
    throw new RuntimeException('Installed response-cookie boundary serialization changed.');
}

$maximumCookies = [];

for ($index = 0; $index < 50; $index++) {
    $maximumCookies[] = new ResponseCookie(
        'cookie-' . $index,
        'value',
        '/',
        false,
        false,
        CookieSameSite::Lax,
    );
}

$caseSensitiveNames = new Response(200, [], '', [
    new ResponseCookie('Case', 'one', '/', false, false, CookieSameSite::Lax),
    new ResponseCookie('case', 'two', '/', false, false, CookieSameSite::Lax),
]);
$firstAggregate = new ResponseCookie(
    'first',
    str_repeat('v', 4_091),
    '/',
    false,
    false,
    CookieSameSite::Lax,
);
$secondAggregate = new ResponseCookie(
    'second',
    str_repeat('v', 4_044),
    '/',
    false,
    false,
    CookieSameSite::Lax,
);

if (
    count((new Response(200, [], '', $maximumCookies))->cookies) !== 50
    || count($caseSensitiveNames->cookies) !== 2
    || strlen($firstAggregate->headerValue()) + strlen($secondAggregate->headerValue()) !== 8_192
    || count((new Response(200, [], '', [$firstAggregate, $secondAggregate]))->cookies) !== 2
) {
    throw new RuntimeException('Installed response-cookie collection bounds changed.');
}

$invalidOperations = [
    static fn(): ResponseCookie => new ResponseCookie('bad name', 'value', '/', false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie(str_repeat('n', 4_097), '', '/', false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'bad;value', '/', false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', 'relative', false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/bad;attribute', false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/bad path', false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', "/bad\x1F", false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', "/bad\x7F", false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('n', str_repeat('v', 4_096), '/', false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/' . str_repeat('p', 1_024), false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', "/\x80", false, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, false, CookieSameSite::Lax, 0),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, false, CookieSameSite::Lax, maximumAgeSeconds: -1),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, false, CookieSameSite::Lax, maximumAgeSeconds: 34_560_001),
    static fn(): ResponseCookie => new ResponseCookie('__hOsT-name', 'value', '/', false, true, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('__sEcUrE-name', 'value', '/', false, true, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('__hTtP-name', 'value', '/', true, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('__HtTp-name', 'value', '/', false, true, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('__hOsT-name', 'value', '/nested', true, true, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('__hOsT-HtTp-name', 'value', '/nested', true, true, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('__HoSt-HtTp-name', 'value', '/', true, false, CookieSameSite::Lax),
    static fn(): ResponseCookie => new ResponseCookie('name', 'value', '/', false, true, CookieSameSite::None),
    static fn(): SessionConfiguration => new SessionConfiguration(
        'INSTALLEDPROOF',
        '__hOsT-insecure',
        false,
        CookieSameSite::Lax,
        __DIR__,
    ),
    static fn(): SessionConfiguration => new SessionConfiguration(
        'INSTALLEDPROOF',
        '__hTtP-insecure',
        false,
        CookieSameSite::Lax,
        __DIR__,
    ),
    static fn(): SessionConfiguration => new SessionConfiguration(
        'INSTALLEDPROOF',
        '__sEcUrE-insecure',
        false,
        CookieSameSite::Lax,
        __DIR__,
    ),
    static fn(): SessionConfiguration => new SessionConfiguration(
        'INSTALLEDPROOF',
        '__hOsT-HtTp-insecure',
        false,
        CookieSameSite::Lax,
        __DIR__,
    ),
    static fn(): Response => new Response(200, [], '', [
        new ResponseCookie('duplicate', 'one', '/', false, false, CookieSameSite::Lax),
        new ResponseCookie('duplicate', 'two', '/nested', false, false, CookieSameSite::Lax),
    ]),
    static fn(): Response => new Response(200, [], '', [
        new ResponseCookie('first', str_repeat('v', 4_091), '/', false, false, CookieSameSite::Lax),
        new ResponseCookie('second', str_repeat('v', 4_045), '/', false, false, CookieSameSite::Lax),
    ]),
    static fn(): Response => new Response(200, [], '', [
        ...array_map(
            static fn(int $index): ResponseCookie => new ResponseCookie(
                'overflow-' . $index,
                'value',
                '/',
                false,
                false,
                CookieSameSite::Lax,
            ),
            range(0, 50),
        ),
    ]),
    static fn(): Response => new Response(200, ['set-cookie' => 'manual=value'], ''),
];

if (PHP_INT_SIZE >= 8) {
    $invalidOperations[] = static fn(): ResponseCookie => new ResponseCookie(
        'name',
        'value',
        '/',
        false,
        false,
        CookieSameSite::Lax,
        (int) '253402300800',
    );
}

foreach ($invalidOperations as $invalidOperation) {
    requireInvalidCookieOperation($invalidOperation);
}

$GLOBALS['installed_response_cookie_headers'] = [];
$GLOBALS['installed_response_cookie_status'] = null;
(new ResponseEmitter())->emit(new Response(200, ['X-Installed-Proof' => 'present'], '', [
    new ResponseCookie('first-emitted', 'one', '/', false, true, CookieSameSite::Lax),
    new ResponseCookie('second-emitted', 'two', '/', true, true, CookieSameSite::Strict),
]));

if (
    $GLOBALS['installed_response_cookie_status'] !== 200
    || $GLOBALS['installed_response_cookie_headers'] !== [
        ['X-Installed-Proof: present', true],
        ['Set-Cookie: first-emitted=one; Path=/; HttpOnly; SameSite=Lax', false],
        ['Set-Cookie: second-emitted=two; Path=/; Secure; HttpOnly; SameSite=Strict', false],
    ]
) {
    throw new RuntimeException('Installed response emitter did not preserve separate Set-Cookie fields.');
}

fwrite(STDOUT, "PASS installed bounded response-cookie runtime\n");
}
PHP,
    );

    try {
        $result = runProcess([PHP_BINARY, $proofPath], $project, $environment);
        requireSuccess($result, 'The installed framework failed bounded response-cookie runtime proof.');
        requireOutputContains($result, 'PASS installed bounded response-cookie runtime');
    } finally {
        if (is_file($proofPath) && !unlink($proofPath)) {
            throw new RuntimeException('Unable to remove the installed response-cookie proof.');
        }
    }

    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/testing.md' => [
            "Assert the live cookie's exact configured name and identifier",
            "Assert the deletion cookie's same name and scope",
            'the limit that `HttpOnly` does not prevent script-initiated authenticated requests',
        ],
        $installedFramework . '/docs/decisions/049-bounded-response-cookie-profile.md' => [
            '# ADR 049: Bounded response-cookie profile',
            'Status: accepted',
            'One `Response` contains at most 50 cookies',
            'The accepted core ceiling is 2,620 physical lines for this response-cookie correction.',
            'The final readable implementation occupies 2,618 lines',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'Contract version: 16',
            '### Contract version 12',
            'Contract version 12 carries Contract version 11 forward and retains Strict Profile version 3',
            'one response contains at most 50 cookies, has no repeated case-sensitive cookie name regardless of path',
        ],
        $installedFramework . '/docs/request-handling.md' => [
            'A cookie name is a non-empty HTTP token, and its name plus cookie-safe ASCII value is at most 4,096 bytes.',
            'Prefix requirements are checked case-insensitively without changing the emitted case-sensitive cookie name.',
            'One response accepts at most 50 cookies and at most 8,192 bytes summed across their exact `headerValue()` strings.',
        ],
        $installedFramework . '/docs/sessions.md' => [
            'A live session cookie contains the configured exact name and the certified 32-character lowercase-hex identifier',
            'The deletion cookie keeps the exact configured name',
            'browser session restoration can retain the cookie',
            'the browser still attaches the cookie to eligible script-initiated requests',
        ],
        $installedFramework . '/docs/security.md' => [
            'Production authentication/session cookies normally use `Secure`',
            'Treat `HttpOnly` as protection from ordinary script access to cookie bytes, not from script-initiated authenticated requests.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Construct, emit, or review a generic response cookie |',
        ],
        $installedFramework . '/templates/application/.ai/operations.md' => [
            'Exact cookie name and prefix, canonical casing, host-only scope',
            'limit an insecure cookie to an explicitly isolated development profile',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            "Assert the live cookie's exact configured name and identifier",
            "Assert the deletion cookie's same name and scope",
        ],
        $installedFramework . '/src/Http/ResponseCookie.php' => [
            'MAXIMUM_NAME_VALUE_BYTES = 4_096',
            'MAXIMUM_PATH_BYTES = 1_024',
            "strlen(gmdate('Y', \$expiresAt)) !== 4",
            'MAXIMUM_AGE_SECONDS = 34_560_000',
            "str_starts_with(\$lowercaseName, '__http-')",
            "str_starts_with(\$lowercaseName, '__host-http-')",
        ],
        $installedFramework . '/src/Http/Response.php' => [
            'MAXIMUM_COOKIES = 50',
            'MAXIMUM_COOKIE_HEADER_BYTES = 8_192',
            'isset($cookieNames[$cookie->name])',
            '$cookieHeaderBytes += strlen($cookie->headerValue())',
        ],
        $installedFramework . '/src/Session/SessionConfiguration.php' => [
            '$lowercaseCookieName = strtolower($cookieName);',
            "str_starts_with(\$lowercaseCookieName, '__http-')",
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'bounded response-cookie profile');

    fwrite(STDOUT, "PASS installed bounded response-cookie profile distribution\n");
}
