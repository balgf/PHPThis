<?php

declare(strict_types=1);

use Example\Documents\AccountId;
use Example\Documents\AuthenticateDocumentRequest;
use Example\Documents\AuthenticatedPrincipal;
use Example\Documents\DocumentRoutes;
use Example\Documents\DocumentKey;
use Example\Documents\Forbidden;
use Example\Documents\GetDocument\DocumentDetails;
use Example\Documents\GetDocument\DocumentDetailsCacheTrace;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\RedisDocumentDetailsCache;
use Example\Documents\GetDocument\RedisDocumentDetailsInvalidationOutcome;
use Example\Documents\GetDocument\RetrieveAuthorizedDocument;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\Documents\ResolvedTenant;
use Example\Documents\ResolveDocumentTenant;
use Example\Documents\ListDocuments\AuthorizeListDocuments;
use Example\Documents\UpdateDocumentTitle\DocumentTitleUpdateOutcome;
use Example\Documents\UpdateDocumentTitle\RedisInvalidatingDocumentTitleUpdate;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Routing\Router;

final class TestAuthoritativeDocumentDetails implements RetrieveAuthorizedDocument
{
    public int $calls = 0;

    public function __construct(public ?DocumentDetails $document)
    {
    }

    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?DocumentDetails {
        $this->calls++;

        return $this->document;
    }
}

final class InterleavedAuthoritativeDocumentDetails implements RetrieveAuthorizedDocument
{
    public int $calls = 0;

    public function __construct(
        private RedisDocumentDetailsCache $otherCache,
        private DocumentDetails $document,
    ) {
    }

    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): DocumentDetails {
        $this->calls++;
        $this->otherCache->retrieve($principal, $tenant, $accountId, $documentKey);

        return $this->document;
    }
}

final class StaleRefillAuthoritativeDocumentDetails implements RetrieveAuthorizedDocument
{
    public function __construct(
        private DocumentDetails $staleDocument,
        private RedisInvalidatingDocumentTitleUpdate $writer,
    ) {
    }

    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): DocumentDetails {
        $this->writer->update(
            $principal,
            $tenant,
            $accountId,
            $documentKey,
            'Committed newer title',
        );

        return $this->staleDocument;
    }
}

/** @return array<string, Closure(): void> */
function cacheTests(): array
{
    return [
        'Redis proof uses distinct recorded cache and noeviction lease endpoints' => static function (): void {
            $cacheTarget = cacheRedisTarget();
            $leaseTarget = cacheLeaseRedisTarget();
            $cache = cacheRedisConnection();
            $lease = cacheLeaseRedisConnection();
            $cacheInfo = $cache->info('server');
            $leaseInfo = $lease->info('server');
            $clientVersion = phpversion('redis');

            if (
                $cache->config('SET', 'maxmemory', '16777216') !== true
                || $cache->config('SET', 'maxmemory-policy', 'allkeys-lru') !== true
            ) {
                throw new RuntimeException('Unable to configure the isolated Redis cache proof endpoint.');
            }

            $cachePolicy = $cache->config('GET', 'maxmemory-policy');
            $leasePolicy = $lease->config('GET', 'maxmemory-policy');

            if (
                $cacheTarget === $leaseTarget
                || !is_array($cacheInfo)
                || !is_array($leaseInfo)
                || !isset($cacheInfo['run_id'], $leaseInfo['run_id'])
                || !is_string($cacheInfo['run_id'])
                || !is_string($leaseInfo['run_id'])
                || $cacheInfo['run_id'] === $leaseInfo['run_id']
                || !isset($cacheInfo['redis_version'], $leaseInfo['redis_version'])
                || !is_string($cacheInfo['redis_version'])
                || !is_string($leaseInfo['redis_version'])
                || version_compare($cacheInfo['redis_version'], '7.4.0', '<')
                || version_compare($cacheInfo['redis_version'], '9.0.0', '>=')
                || version_compare($leaseInfo['redis_version'], '7.4.0', '<')
                || version_compare($leaseInfo['redis_version'], '9.0.0', '>=')
                || !is_string($clientVersion)
                || version_compare($clientVersion, '6.3.0', '<')
                || version_compare($clientVersion, '7.0.0', '>=')
                || $cache->getOption(Redis::OPT_MAX_RETRIES) !== 0
                || $lease->getOption(Redis::OPT_MAX_RETRIES) !== 0
                || $cachePolicy !== ['maxmemory-policy' => 'allkeys-lru']
                || $leasePolicy !== ['maxmemory-policy' => 'noeviction']
            ) {
                throw new RuntimeException('Redis cache and lease evidence requires two recorded backend roles.');
            }
        },

        'Redis document cache proves miss hit eviction expiry and bounded authoritative reads' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('hit-expiry');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('cache-hit');
            $source = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'Current title']),
            );
            $firstTrace = new DocumentDetailsCacheTrace();
            $first = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 120, $firstTrace),
                $accountId,
                $documentKey,
            );
            $secondTrace = new DocumentDetailsCacheTrace();
            $second = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 120, $secondTrace),
                $accountId,
                $documentKey,
            );
            cacheDeleteKey($redis, cacheTestKey($environment, $accountId, $documentKey));
            $evictedTrace = new DocumentDetailsCacheTrace();
            $evicted = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 120, $evictedTrace),
                $accountId,
                $documentKey,
            );

            cacheWaitUntilAbsent(
                $redis,
                cacheTestKey($environment, $accountId, $documentKey),
                2_000,
            );

            $thirdTrace = new DocumentDetailsCacheTrace();
            $third = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 120, $thirdTrace),
                $accountId,
                $documentKey,
            );

            if (
                $first?->title !== 'Current title'
                || $second?->title !== 'Current title'
                || $evicted?->title !== 'Current title'
                || $third?->title !== 'Current title'
                || $source->calls !== 3
                || $firstTrace->snapshot() !== cacheTrace('miss', 'stored')
                || $secondTrace->snapshot() !== cacheTrace('hit', 'not_attempted')
                || $evictedTrace->snapshot() !== cacheTrace('miss', 'stored')
                || $thirdTrace->snapshot() !== cacheTrace('miss', 'stored')
            ) {
                throw new RuntimeException('Redis cache must preserve typed results across miss, hit, eviction, and expiry.');
            }

            cacheDeleteKey($redis, cacheTestKey($environment, $accountId, $documentKey));
        },

        'Redis document cache uses the exact versioned key value and configured TTL' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('exact-encoding');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('Exact_9-z');
            $key = cacheTestKey($environment, $accountId, $documentKey);
            $trace = new DocumentDetailsCacheTrace();
            $result = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    new TestAuthoritativeDocumentDetails(
                        DocumentDetails::fromDatabaseRow(['title' => 'Exact title']),
                    ),
                    $environment,
                    30_000,
                    $trace,
                ),
                $accountId,
                $documentKey,
            );
            $payload = $redis->get($key);
            $ttl = $redis->pttl($key);
            $expectedPayload = json_encode(
                [
                    'schema_version' => 1,
                    'tenant_account_id' => 42,
                    'document_key' => 'Exact_9-z',
                    'title' => 'Exact title',
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );

            if (
                $result?->title !== 'Exact title'
                || strlen($key) > 192
                || $payload !== $expectedPayload
                || $ttl < 29_000
                || $ttl > 30_000
                || $trace->snapshot() !== cacheTrace('miss', 'stored')
            ) {
                throw new RuntimeException('Redis cache encoding and TTL must remain exact and bounded.');
            }

            cacheDeleteKey($redis, $key);
        },

        'Redis document cache rejects corrupt and wrong-tenant payloads' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('corruption');
            $accountId = AccountId::fromPositiveInteger(42);
            $otherAccountId = AccountId::fromPositiveInteger(43);
            $documentKey = DocumentKey::fromToken('tenant-safe');
            $key = cacheTestKey($environment, $accountId, $documentKey);
            $source = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'Authoritative title']),
            );

            if ($redis->set($key, '{broken', ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed malformed Redis cache payload.');
            }

            $malformedTrace = new DocumentDetailsCacheTrace();
            $malformed = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 2_000, $malformedTrace),
                $accountId,
                $documentKey,
            );
            $wrongTenantPayload = json_encode(
                [
                    'schema_version' => 1,
                    'tenant_account_id' => $otherAccountId->value,
                    'document_key' => $documentKey->value,
                    'title' => 'Other tenant title',
                ],
                JSON_THROW_ON_ERROR,
            );

            if ($redis->set($key, $wrongTenantPayload, ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed wrong-tenant Redis cache payload.');
            }

            $wrongTenantTrace = new DocumentDetailsCacheTrace();
            $wrongTenant = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 2_000, $wrongTenantTrace),
                $accountId,
                $documentKey,
            );
            $wrongVersionPayload = json_encode(
                [
                    'schema_version' => 2,
                    'tenant_account_id' => $accountId->value,
                    'document_key' => $documentKey->value,
                    'title' => 'Wrong version title',
                ],
                JSON_THROW_ON_ERROR,
            );

            if ($redis->set($key, $wrongVersionPayload, ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed wrong-version Redis cache payload.');
            }

            $wrongVersionTrace = new DocumentDetailsCacheTrace();
            $wrongVersion = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 2_000, $wrongVersionTrace),
                $accountId,
                $documentKey,
            );
            $wrongDocumentPayload = json_encode(
                [
                    'schema_version' => 1,
                    'tenant_account_id' => $accountId->value,
                    'document_key' => 'another-document',
                    'title' => 'Wrong document title',
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );

            if ($redis->set($key, $wrongDocumentPayload, ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed wrong-document Redis cache payload.');
            }

            $wrongDocumentTrace = new DocumentDetailsCacheTrace();
            $wrongDocument = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 2_000, $wrongDocumentTrace),
                $accountId,
                $documentKey,
            );

            $duplicateFieldPayload = '{"schema_version":1,"tenant_account_id":42,'
                . '"document_key":"tenant-safe","title":"first","title":"second"}';

            if ($redis->set($key, $duplicateFieldPayload, ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed duplicate-field Redis cache payload.');
            }

            $duplicateFieldTrace = new DocumentDetailsCacheTrace();
            $duplicateField = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 2_000, $duplicateFieldTrace),
                $accountId,
                $documentKey,
            );

            if ($redis->set($key, str_repeat('x', 1_025), ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed oversized Redis cache payload.');
            }

            $oversizedTrace = new DocumentDetailsCacheTrace();
            $oversized = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 2_000, $oversizedTrace),
                $accountId,
                $documentKey,
            );

            if (
                $malformed?->title !== 'Authoritative title'
                || $wrongTenant?->title !== 'Authoritative title'
                || $wrongVersion?->title !== 'Authoritative title'
                || $wrongDocument?->title !== 'Authoritative title'
                || $duplicateField?->title !== 'Authoritative title'
                || $oversized?->title !== 'Authoritative title'
                || $source->calls !== 6
                || $malformedTrace->snapshot() !== cacheTrace('corrupt', 'stored')
                || $wrongTenantTrace->snapshot() !== cacheTrace('corrupt', 'stored')
                || $wrongVersionTrace->snapshot() !== cacheTrace('corrupt', 'stored')
                || $wrongDocumentTrace->snapshot() !== cacheTrace('corrupt', 'stored')
                || $duplicateFieldTrace->snapshot() !== cacheTrace('corrupt', 'stored')
                || $oversizedTrace->snapshot() !== cacheTrace('corrupt', 'stored')
                || cacheTestKey($environment, $accountId, $documentKey)
                    === cacheTestKey($environment, $otherAccountId, $documentKey)
            ) {
                throw new RuntimeException('Corrupt or wrong-tenant cache data must remain an authoritative miss.');
            }

            cacheDeleteKey($redis, $key);
        },

        'Redis document cache outage falls back without retry or payload leakage' => static function (): void {
            $source = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'Database survives cache outage']),
            );
            $trace = new DocumentDetailsCacheTrace();
            $result = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    $source,
                    cacheTestEnvironment('outage'),
                    2_000,
                    $trace,
                    1,
                ),
                AccountId::fromPositiveInteger(42),
                DocumentKey::fromToken('cache-outage'),
            );

            if (
                $result?->title !== 'Database survives cache outage'
                || $source->calls !== 1
                || $trace->snapshot() !== [
                    'read' => 'backend_unavailable',
                    'write' => 'not_attempted',
                    'invalidation' => 'not_attempted',
                ]
            ) {
                throw new RuntimeException('Redis outage must preserve one explicit authoritative fallback.');
            }
        },

        'Redis document cache retains read evidence when authoritative retrieval fails' => static function (): void {
            $environment = cacheTestEnvironment('source-failure');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('source-failure');
            $trace = new DocumentDetailsCacheTrace();
            $failed = false;
            $cache = cacheDocumentDetailsService(
                new class implements RetrieveAuthorizedDocument {
                    public function retrieve(
                        AuthenticatedPrincipal $principal,
                        ResolvedTenant $tenant,
                        AccountId $accountId,
                        DocumentKey $documentKey,
                    ): never {
                        throw new RuntimeException('Private authoritative failure.');
                    }
                },
                $environment,
                2_000,
                $trace,
            );

            try {
                cacheTestRetrieve($cache, $accountId, $documentKey);
            } catch (RuntimeException $failure) {
                $failed = $failure->getMessage() === 'Private authoritative failure.';
            }

            if (!$failed || $trace->snapshot() !== cacheTrace('miss', 'not_attempted')) {
                throw new RuntimeException('Cache read evidence must survive an authoritative source failure.');
            }
        },

        'Redis document cache records a failed refill without changing authoritative truth' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('write-failure');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('write-failure');
            $key = cacheTestKey($environment, $accountId, $documentKey);
            cacheDeleteKey($redis, $key);
            $maxmemory = $redis->config('GET', 'maxmemory');
            $policy = $redis->config('GET', 'maxmemory-policy');

            if (
                !is_array($maxmemory)
                || !isset($maxmemory['maxmemory'])
                || !is_string($maxmemory['maxmemory'])
                || !is_array($policy)
                || !isset($policy['maxmemory-policy'])
                || !is_string($policy['maxmemory-policy'])
            ) {
                throw new RuntimeException('Unable to inspect the Redis cache capacity policy.');
            }

            $trace = new DocumentDetailsCacheTrace();
            $result = null;

            try {
                if (
                    $redis->config('SET', 'maxmemory-policy', 'noeviction') !== true
                    || $redis->config('SET', 'maxmemory', '1') !== true
                ) {
                    throw new RuntimeException('Unable to constrain the Redis cache endpoint.');
                }

                $result = cacheTestRetrieve(
                    cacheDocumentDetailsService(
                        new TestAuthoritativeDocumentDetails(
                            DocumentDetails::fromDatabaseRow(['title' => 'Still authoritative']),
                        ),
                        $environment,
                        2_000,
                        $trace,
                    ),
                    $accountId,
                    $documentKey,
                );
            } finally {
                if (
                    $redis->config('SET', 'maxmemory', $maxmemory['maxmemory']) !== true
                    || $redis->config(
                        'SET',
                        'maxmemory-policy',
                        $policy['maxmemory-policy'],
                    ) !== true
                ) {
                    throw new RuntimeException('Unable to restore the Redis cache capacity policy.');
                }
            }

            if (
                $result?->title !== 'Still authoritative'
                || $trace->snapshot() !== cacheTrace('miss', 'backend_unavailable')
                || $redis->get($key) !== false
            ) {
                throw new RuntimeException('Cache refill failure must preserve the authoritative result.');
            }
        },

        'Redis document cache isolates the same document across tenants and environments' => static function (): void {
            $redis = cacheRedisConnection();
            $firstEnvironment = cacheTestEnvironment('isolation-first');
            $secondEnvironment = cacheTestEnvironment('isolation-second');
            $firstAccount = AccountId::fromPositiveInteger(42);
            $secondAccount = AccountId::fromPositiveInteger(43);
            $documentKey = DocumentKey::fromToken('same-document');
            $firstSource = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'First environment']),
            );
            $secondEnvironmentSource = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'Second environment']),
            );
            $secondTenantSource = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'Second tenant']),
            );

            cacheTestRetrieve(
                cacheDocumentDetailsService(
                    $firstSource,
                    $firstEnvironment,
                    2_000,
                    new DocumentDetailsCacheTrace(),
                ),
                $firstAccount,
                $documentKey,
            );
            cacheTestRetrieve(
                cacheDocumentDetailsService(
                    $secondEnvironmentSource,
                    $secondEnvironment,
                    2_000,
                    new DocumentDetailsCacheTrace(),
                ),
                $firstAccount,
                $documentKey,
            );
            cacheTestRetrieve(
                cacheDocumentDetailsService(
                    $secondTenantSource,
                    $firstEnvironment,
                    2_000,
                    new DocumentDetailsCacheTrace(),
                ),
                $secondAccount,
                $documentKey,
            );
            $firstHit = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    new TestAuthoritativeDocumentDetails(null),
                    $firstEnvironment,
                    2_000,
                    new DocumentDetailsCacheTrace(),
                ),
                $firstAccount,
                $documentKey,
            );
            $environmentHit = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    new TestAuthoritativeDocumentDetails(null),
                    $secondEnvironment,
                    2_000,
                    new DocumentDetailsCacheTrace(),
                ),
                $firstAccount,
                $documentKey,
            );
            $tenantHit = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    new TestAuthoritativeDocumentDetails(null),
                    $firstEnvironment,
                    2_000,
                    new DocumentDetailsCacheTrace(),
                ),
                $secondAccount,
                $documentKey,
            );

            if (
                $firstHit?->title !== 'First environment'
                || $environmentHit?->title !== 'Second environment'
                || $tenantHit?->title !== 'Second tenant'
                || $firstSource->calls !== 1
                || $secondEnvironmentSource->calls !== 1
                || $secondTenantSource->calls !== 1
            ) {
                throw new RuntimeException('Tenant and environment cache namespaces must remain isolated.');
            }

            cacheDeleteKey($redis, cacheTestKey($firstEnvironment, $firstAccount, $documentKey));
            cacheDeleteKey($redis, cacheTestKey($secondEnvironment, $firstAccount, $documentKey));
            cacheDeleteKey($redis, cacheTestKey($firstEnvironment, $secondAccount, $documentKey));
        },

        'document authorization denial performs no cache or protected source work' => static function (): void {
            $source = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'Never returned']),
            );
            $cacheTrace = new DocumentDetailsCacheTrace();
            $cache = cacheDocumentDetailsService(
                $source,
                cacheTestEnvironment('authorization-denial'),
                2_000,
                $cacheTrace,
                1,
            );
            $application = new Application(new Router(DocumentRoutes::create(
                new class implements AuthenticateDocumentRequest {
                    public function authenticate(Request $request): AuthenticatedPrincipal
                    {
                        return AuthenticatedPrincipal::fromPositiveInteger(7);
                    }
                },
                new class implements ResolveDocumentTenant {
                    public function resolve(
                        AuthenticatedPrincipal $principal,
                        AccountId $accountId,
                    ): ResolvedTenant {
                        return ResolvedTenant::forAccount($accountId);
                    }
                },
                new class implements AuthorizeGetDocument {
                    public function authorize(
                        AuthenticatedPrincipal $principal,
                        ResolvedTenant $tenant,
                        DocumentKey $documentKey,
                    ): void {
                        throw new Forbidden();
                    }
                },
                $cache,
                new class implements AuthorizeListDocuments {
                    public function authorizeList(
                        AuthenticatedPrincipal $principal,
                        ResolvedTenant $tenant,
                    ): void {
                        throw new Forbidden();
                    }
                },
                Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
            )));
            $denied = false;

            try {
                $application->handle(new Request(
                    'GET',
                    '/accounts/42/documents/authorization-denial',
                ));
            } catch (Forbidden) {
                $denied = true;
            }

            if (
                !$denied
                || $source->calls !== 0
                || $cacheTrace->snapshot() !== cacheTrace('not_attempted', 'not_attempted')
            ) {
                throw new RuntimeException('Authorization denial must precede every cache and protected-data call.');
            }
        },

        'Redis document cache deliberately permits duplicate reads on interleaved cold misses' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('interleaved-misses');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('interleaved-misses');
            $document = DocumentDetails::fromDatabaseRow(['title' => 'Shared source title']);
            $secondSource = new TestAuthoritativeDocumentDetails($document);
            $secondTrace = new DocumentDetailsCacheTrace();
            $secondCache = cacheDocumentDetailsService(
                $secondSource,
                $environment,
                2_000,
                $secondTrace,
            );
            $firstSource = new InterleavedAuthoritativeDocumentDetails($secondCache, $document);
            $firstTrace = new DocumentDetailsCacheTrace();
            $first = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    $firstSource,
                    $environment,
                    2_000,
                    $firstTrace,
                ),
                $accountId,
                $documentKey,
            );

            if (
                $first?->title !== 'Shared source title'
                || $firstSource->calls !== 1
                || $secondSource->calls !== 1
                || $firstTrace->snapshot() !== cacheTrace('miss', 'stored')
                || $secondTrace->snapshot() !== cacheTrace('miss', 'stored')
            ) {
                throw new RuntimeException('The no-coalescing policy must expose duplicate cold source reads.');
            }

            cacheDeleteKey($redis, cacheTestKey($environment, $accountId, $documentKey));
        },

        'Redis document cache preserves constant authoritative SQL on cold small and large fixtures' => static function (): void {
            $small = runColdCacheDocumentScenario('cache-cold-small', 0);
            $large = runColdCacheDocumentScenario('cache-cold-large', 500);

            if (
                $small['title'] !== 'Example document'
                || $large['title'] !== 'Example document'
                || $small['budget_used'] !== 1
                || $large['budget_used'] !== 1
                || $small['statements'] !== 1
                || $large['statements'] !== 1
                || $small['cache'] !== cacheTrace('miss', 'stored')
                || $large['cache'] !== cacheTrace('miss', 'stored')
            ) {
                throw new RuntimeException('Cold cache evidence must preserve one authoritative SQL statement at scale.');
            }
        },

        'Redis document cache bounds the accepted stale-refill race with finite TTL' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('stale-refill');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('updated-document');
            $key = cacheTestKey($environment, $accountId, $documentKey);
            $recoveryBudget = new QueryBudget(8);
            $recoveryQueryTrace = new QueryTrace(8);
            $connection = cacheUpdateDatabase($recoveryBudget, $recoveryQueryTrace);
            $writerTrace = new DocumentDetailsCacheTrace();
            $writerCache = cacheDocumentDetailsService(
                new TestAuthoritativeDocumentDetails(null),
                $environment,
                500,
                $writerTrace,
            );
            $source = new StaleRefillAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => 'Original title']),
                new RedisInvalidatingDocumentTitleUpdate($connection, $writerCache),
            );
            $refillTrace = new DocumentDetailsCacheTrace();
            $refilled = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 500, $refillTrace),
                $accountId,
                $documentKey,
            );
            $hitTrace = new DocumentDetailsCacheTrace();
            $staleHit = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    new TestAuthoritativeDocumentDetails(
                        DocumentDetails::fromDatabaseRow(['title' => 'Committed newer title']),
                    ),
                    $environment,
                    500,
                    $hitTrace,
                ),
                $accountId,
                $documentKey,
            );
            $row = $connection->selectOneRow(
                'SELECT title FROM documents WHERE account_id = :account_id AND document_key = :document_key',
                ['account_id' => 42, 'document_key' => 'updated-document'],
            );
            $remainingTtl = $redis->pttl($key);

            cacheWaitUntilAbsent($redis, $key, 2_000);
            $budgetBeforeRecovery = $recoveryBudget->used();
            $statementsBeforeRecovery = $recoveryQueryTrace->snapshot()['statements'];
            $recoveryTrace = new DocumentDetailsCacheTrace();
            $recovered = cacheTestRetrieve(
                cacheDocumentDetailsService(
                    new SelectAuthorizedDocument($connection),
                    $environment,
                    500,
                    $recoveryTrace,
                ),
                $accountId,
                $documentKey,
            );

            if (
                $refilled?->title !== 'Original title'
                || $staleHit?->title !== 'Original title'
                || $recovered?->title !== 'Committed newer title'
                || $recoveryBudget->used() !== $budgetBeforeRecovery + 1
                || $recoveryQueryTrace->snapshot()['statements'] !== $statementsBeforeRecovery + 1
                || $row !== ['title' => 'Committed newer title']
                || $remainingTtl < 1
                || $remainingTtl > 500
                || $writerTrace->snapshot() !== cacheTrace(
                    'not_attempted',
                    'not_attempted',
                    'absent',
                )
                || $refillTrace->snapshot() !== cacheTrace('miss', 'stored')
                || $hitTrace->snapshot() !== cacheTrace('hit', 'not_attempted')
                || $recoveryTrace->snapshot() !== cacheTrace('miss', 'stored')
            ) {
                throw new RuntimeException('The accepted stale refill must stay visible and TTL-bounded.');
            }

            cacheDeleteKey($redis, $key);
        },

        'Redis document cache skips an oversized authoritative payload' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('payload-bound');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('oversized-title');
            $title = str_repeat('x', 513);
            $source = new TestAuthoritativeDocumentDetails(
                DocumentDetails::fromDatabaseRow(['title' => $title]),
            );
            $trace = new DocumentDetailsCacheTrace();
            $result = cacheTestRetrieve(
                cacheDocumentDetailsService($source, $environment, 2_000, $trace),
                $accountId,
                $documentKey,
            );
            $cached = $redis->get(cacheTestKey($environment, $accountId, $documentKey));

            if (
                $result?->title !== $title
                || $source->calls !== 1
                || $trace->snapshot() !== cacheTrace('miss', 'payload_rejected')
                || $cached !== false
            ) {
                throw new RuntimeException('An oversized source value must remain usable without entering Redis.');
            }
        },

        'authoritative document update commits before explicit Redis invalidation' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('update-invalidate');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('updated-document');
            $key = cacheTestKey($environment, $accountId, $documentKey);
            $connection = cacheUpdateDatabase();
            $cacheTrace = new DocumentDetailsCacheTrace();
            $cache = cacheDocumentDetailsService(
                new TestAuthoritativeDocumentDetails(null),
                $environment,
                2_000,
                $cacheTrace,
            );

            if ($redis->set($key, '{"cached":"old"}', ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed the document cache before update.');
            }

            $result = (new RedisInvalidatingDocumentTitleUpdate($connection, $cache))->update(
                AuthenticatedPrincipal::fromPositiveInteger(7),
                ResolvedTenant::forAccount($accountId),
                $accountId,
                $documentKey,
                'Updated title',
            );
            $row = $connection->selectOneRow(
                'SELECT title FROM documents WHERE account_id = :account_id AND document_key = :document_key',
                ['account_id' => $accountId->value, 'document_key' => $documentKey->value],
            );

            if (
                $result->update !== DocumentTitleUpdateOutcome::Updated
                || $result->invalidation !== RedisDocumentDetailsInvalidationOutcome::Deleted
                || $row !== ['title' => 'Updated title']
                || $redis->get($key) !== false
                || $cacheTrace->snapshot() !== cacheTrace(
                    'not_attempted',
                    'not_attempted',
                    'deleted',
                )
            ) {
                throw new RuntimeException('Authoritative update must precede an explicit affected-key invalidation.');
            }
        },

        'failed authoritative document update never invalidates the existing cache key' => static function (): void {
            $redis = cacheRedisConnection();
            $environment = cacheTestEnvironment('update-rejected');
            $accountId = AccountId::fromPositiveInteger(42);
            $documentKey = DocumentKey::fromToken('updated-document');
            $key = cacheTestKey($environment, $accountId, $documentKey);
            $connection = cacheUpdateDatabase();
            $connection->executeStatement(
                <<<'SQL'
                    CREATE TRIGGER reject_document_title_update
                    BEFORE UPDATE ON documents
                    BEGIN
                        SELECT RAISE(ABORT, 'rejected update');
                    END
                    SQL,
            );
            $cacheTrace = new DocumentDetailsCacheTrace();
            $cache = cacheDocumentDetailsService(
                new TestAuthoritativeDocumentDetails(null),
                $environment,
                2_000,
                $cacheTrace,
            );
            $cachedPayload = '{"schema_version":1,"tenant_account_id":42,'
                . '"document_key":"updated-document","title":"Original title"}';

            if ($redis->set($key, $cachedPayload, ['px' => 2_000]) !== true) {
                throw new RuntimeException('Unable to seed cache state before a rejected update.');
            }

            $failed = false;

            try {
                (new RedisInvalidatingDocumentTitleUpdate($connection, $cache))->update(
                    AuthenticatedPrincipal::fromPositiveInteger(7),
                    ResolvedTenant::forAccount($accountId),
                    $accountId,
                    $documentKey,
                    'Rejected title',
                );
            } catch (Throwable) {
                $failed = true;
            }

            $row = $connection->selectOneRow(
                'SELECT title FROM documents WHERE account_id = :account_id AND document_key = :document_key',
                ['account_id' => 42, 'document_key' => 'updated-document'],
            );

            if (
                !$failed
                || $row !== ['title' => 'Original title']
                || $redis->get($key) !== $cachedPayload
                || $cacheTrace->snapshot() !== cacheTrace('not_attempted', 'not_attempted')
            ) {
                throw new RuntimeException('Cache invalidation must occur only after authoritative commit.');
            }

            cacheDeleteKey($redis, $key);
        },

        'authoritative document update survives explicit invalidation outage' => static function (): void {
            $connection = cacheUpdateDatabase();
            $cacheTrace = new DocumentDetailsCacheTrace();
            $cache = cacheDocumentDetailsService(
                new TestAuthoritativeDocumentDetails(null),
                cacheTestEnvironment('update-outage'),
                2_000,
                $cacheTrace,
                1,
            );
            $result = (new RedisInvalidatingDocumentTitleUpdate($connection, $cache))->update(
                AuthenticatedPrincipal::fromPositiveInteger(7),
                ResolvedTenant::forAccount(AccountId::fromPositiveInteger(42)),
                AccountId::fromPositiveInteger(42),
                DocumentKey::fromToken('updated-document'),
                'Committed despite invalidation outage',
            );
            $row = $connection->selectOneRow(
                'SELECT title FROM documents WHERE account_id = :account_id AND document_key = :document_key',
                ['account_id' => 42, 'document_key' => 'updated-document'],
            );

            if (
                $result->update !== DocumentTitleUpdateOutcome::Updated
                || $result->invalidation !== RedisDocumentDetailsInvalidationOutcome::BackendUnavailable
                || $row !== ['title' => 'Committed despite invalidation outage']
                || $cacheTrace->snapshot() !== cacheTrace(
                    'not_attempted',
                    'not_attempted',
                    'backend_unavailable',
                )
            ) {
                throw new RuntimeException('Invalidation outage must be visible without undoing committed truth.');
            }
        },
    ];
}

function cacheRedisConnection(): Redis
{
    ['host' => $host, 'port' => $port] = cacheRedisTarget();

    $redis = new Redis();
    $connected = $redis->connect($host, $port, 0.25, null, 0, 0.25);

    if (!$connected) {
        throw new RuntimeException('Unable to connect to the Redis integration-test service.');
    }

    if (
        !$redis->setOption(Redis::OPT_MAX_RETRIES, 0)
        || !$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE)
        || !$redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE)
        || !$redis->select(0)
    ) {
        throw new RuntimeException('Unable to select raw Redis test values.');
    }

    return $redis;
}

function cacheDocumentDetailsService(
    RetrieveAuthorizedDocument $source,
    string $environment,
    int $ttlMilliseconds,
    DocumentDetailsCacheTrace $trace,
    ?int $port = null,
): RedisDocumentDetailsCache {
    $target = cacheRedisTarget();

    return new RedisDocumentDetailsCache(
        $target['host'],
        $port ?? $target['port'],
        0,
        $source,
        $environment,
        $ttlMilliseconds,
        $trace,
    );
}

/** @return array{host: non-empty-string, port: int<1, 65535>} */
function cacheRedisTarget(): array
{
    $submittedHost = getenv('PHPTHIS_REDIS_CACHE_HOST');
    $host = is_string($submittedHost) && $submittedHost !== ''
        ? $submittedHost
        : '127.0.0.1';
    $submittedPort = getenv('PHPTHIS_REDIS_CACHE_PORT');
    $port = is_string($submittedPort) && preg_match('/\A[1-9][0-9]{0,4}\z/D', $submittedPort) === 1
        ? (int) $submittedPort
        : 6379;

    if ($port < 1 || $port > 65_535) {
        throw new RuntimeException('Redis test port is outside the TCP port range.');
    }

    return ['host' => $host, 'port' => $port];
}

function cacheLeaseRedisConnection(): Redis
{
    ['host' => $host, 'port' => $port] = cacheLeaseRedisTarget();
    $redis = new Redis();

    if (
        !$redis->connect($host, $port, 0.25, null, 0, 0.25)
        || !$redis->setOption(Redis::OPT_MAX_RETRIES, 0)
        || !$redis->select(0)
    ) {
        throw new RuntimeException('Unable to connect to the Redis lease proof endpoint.');
    }

    return $redis;
}

/** @return array{host: non-empty-string, port: int<1, 65535>} */
function cacheLeaseRedisTarget(): array
{
    $submittedHost = getenv('PHPTHIS_REDIS_LEASE_HOST');
    $host = is_string($submittedHost) && $submittedHost !== ''
        ? $submittedHost
        : '127.0.0.1';
    $submittedPort = getenv('PHPTHIS_REDIS_LEASE_PORT');
    $port = is_string($submittedPort) && preg_match('/\A[1-9][0-9]{0,4}\z/D', $submittedPort) === 1
        ? (int) $submittedPort
        : 6380;

    if ($port < 1 || $port > 65_535) {
        throw new RuntimeException('Redis lease proof port is outside the TCP port range.');
    }

    return ['host' => $host, 'port' => $port];
}

/**
 * @param 'not_attempted'|'hit'|'miss'|'corrupt'|'backend_unavailable' $read
 * @param 'not_attempted'|'stored'|'payload_rejected'|'backend_unavailable' $write
 * @param 'not_attempted'|'deleted'|'absent'|'backend_unavailable' $invalidation
 * @return array{read: string, write: string, invalidation: string}
 */
function cacheTrace(
    string $read,
    string $write,
    string $invalidation = 'not_attempted',
): array {
    return [
        'read' => $read,
        'write' => $write,
        'invalidation' => $invalidation,
    ];
}

function cacheTestEnvironment(string $purpose): string
{
    return 't' . substr(hash('sha256', $purpose . bin2hex(random_bytes(8))), 0, 20);
}

function cacheTestKey(
    string $environment,
    AccountId $accountId,
    DocumentKey $documentKey,
): string {
    return sprintf(
        'phpthis_example:%s:tenant:%d:document_details:v1:%s',
        $environment,
        $accountId->value,
        $documentKey->value,
    );
}

function cacheTestRetrieve(
    RedisDocumentDetailsCache $cache,
    AccountId $accountId,
    DocumentKey $documentKey,
): ?DocumentDetails {
    return $cache->retrieve(
        AuthenticatedPrincipal::fromPositiveInteger(7),
        ResolvedTenant::forAccount($accountId),
        $accountId,
        $documentKey,
    );
}

function cacheWaitUntilAbsent(Redis $redis, string $key, int $maximumWaitMilliseconds): void
{
    $deadline = hrtime(true) + ($maximumWaitMilliseconds * 1_000_000);

    while (hrtime(true) < $deadline) {
        if ($redis->get($key) === false) {
            return;
        }

        usleep(10_000);
    }

    throw new RuntimeException('Redis cache key did not expire within the bounded test wait.');
}

function cacheDeleteKey(Redis $redis, string $key): void
{
    $deleted = $redis->del($key);

    if ($deleted !== 0 && $deleted !== 1) {
        throw new RuntimeException('Unable to remove a bounded Redis integration-test key.');
    }
}

function cacheUpdateDatabase(
    ?QueryBudget $budget = null,
    ?QueryTrace $queryTrace = null,
): Connection
{
    $connection = Connection::connect(
        'sqlite::memory:',
        $budget ?? new QueryBudget(8),
        $queryTrace ?? new QueryTrace(8),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE documents (
                account_id INTEGER NOT NULL,
                document_key TEXT NOT NULL,
                title TEXT NOT NULL,
                PRIMARY KEY (account_id, document_key)
            ) STRICT
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE account_memberships (
                principal_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                PRIMARY KEY (principal_id, account_id)
            ) STRICT
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO documents (account_id, document_key, title)
            VALUES (:account_id, :document_key, :title)
            SQL,
        [
            'account_id' => 42,
            'document_key' => 'updated-document',
            'title' => 'Original title',
        ],
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO account_memberships (principal_id, account_id)
            VALUES (:principal_id, :account_id)
            SQL,
        ['principal_id' => 7, 'account_id' => 42],
    );

    return $connection;
}

/**
 * @return array{
 *     title: string|null,
 *     budget_used: int,
 *     statements: int,
 *     cache: array{read: string, write: string, invalidation: string}
 * }
 */
function runColdCacheDocumentScenario(string $name, int $extraDocuments): array
{
    $databasePath = createRequestPolicyDatabaseFixture($name, $extraDocuments);
    $budget = new QueryBudget(1);
    $queryTrace = new QueryTrace(1);
    $cacheTrace = new DocumentDetailsCacheTrace();
    $environment = cacheTestEnvironment($name);
    $accountId = AccountId::fromPositiveInteger(42);
    $documentKey = DocumentKey::fromToken('Doc_9-z');
    $result = cacheTestRetrieve(
        cacheDocumentDetailsService(
            new SelectAuthorizedDocument(
                Connection::connect('sqlite:' . $databasePath, $budget, $queryTrace),
            ),
            $environment,
            2_000,
            $cacheTrace,
        ),
        $accountId,
        $documentKey,
    );
    cacheDeleteKey(
        cacheRedisConnection(),
        cacheTestKey($environment, $accountId, $documentKey),
    );

    return [
        'title' => $result?->title,
        'budget_used' => $budget->used(),
        'statements' => $queryTrace->snapshot()['statements'],
        'cache' => $cacheTrace->snapshot(),
    ];
}
