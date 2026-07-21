<?php

declare(strict_types=1);

use Example\Coordination\RedisScheduleRunLease;
use Example\Coordination\RedisScheduleRunLeaseAcquireOutcome;
use Example\Coordination\RedisScheduleRunLeaseReleaseOutcome;
use Example\Coordination\RedisScheduleRunLeaseRenewOutcome;
use Example\Coordination\RedisScheduleRunLeaseTrace;
use Example\Coordination\RedisScheduleRunLeaseUnavailable;

/** @return array<string, Closure(): void> */
function redisCoordinationTests(): array
{
    return [
        'Redis schedule lease uses one expiring NX acquisition and a 128-bit owner token' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setResults = [true];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );

            $outcome = $lease->acquire();
            $call = $client->setCalls[0] ?? null;

            if (
                $outcome !== RedisScheduleRunLeaseAcquireOutcome::Acquired
                || count($client->setCalls) !== 1
                || !is_array($call)
                || $call['key'] !== 'phpthis_example:test:schedule_run:v1'
                || preg_match('/\A[0-9a-f]{32}\z/D', $call['value']) !== 1
                || $call['options'] !== ['NX', 'PX' => 30_000]
                || $client->getOption(Redis::OPT_MAX_RETRIES) !== 0
                || $client->getOption(Redis::OPT_SERIALIZER) !== Redis::SERIALIZER_NONE
                || $client->getOption(Redis::OPT_COMPRESSION) !== Redis::COMPRESSION_NONE
                || $client->getOption(Redis::OPT_REPLY_LITERAL) !== 0
                || $trace->snapshot() !== ['acquired']
            ) {
                throw new RuntimeException('Redis lease acquisition must use one bounded SET NX PX owner token.');
            }
        },

        'Redis schedule lease reports contention without renewal or release ownership' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setResults = [false];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );

            $outcome = $lease->acquire();
            $renewRejected = false;
            $releaseRejected = false;

            try {
                $lease->renew();
            } catch (LogicException) {
                $renewRejected = true;
            }

            try {
                $lease->release();
            } catch (LogicException) {
                $releaseRejected = true;
            }

            if (
                $outcome !== RedisScheduleRunLeaseAcquireOutcome::Contended
                || !$renewRejected
                || !$releaseRejected
                || count($client->evalCalls) !== 0
                || $trace->snapshot() !== ['contended']
            ) {
                throw new RuntimeException('A contended Redis lease must never create local ownership.');
            }
        },

        'Redis schedule lease distinguishes server rejection from ordinary contention' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setResults = [false];
            $client->setLastErrors = ['OOM command not allowed'];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );
            $failureTrace = null;

            try {
                $lease->acquire();
            } catch (RedisScheduleRunLeaseUnavailable $failure) {
                $failureTrace = $failure->coordination;
            }

            if (
                $failureTrace !== ['acquire_failed']
                || $trace->snapshot() !== ['acquire_failed']
                || count($client->setCalls) !== 1
            ) {
                throw new RuntimeException('Redis server errors must fail closed instead of becoming contention.');
            }
        },

        'Redis schedule lease failure retains a primary command failure without exposing it' => static function (): void {
            $primary = new RuntimeException('Private primary command failure.');
            $failure = new RedisScheduleRunLeaseUnavailable(['release_failed'], $primary);

            if (
                $failure->coordination !== ['release_failed']
                || $failure->getPrevious() !== $primary
                || str_contains($failure->getMessage(), 'Private')
                || $failure->stderrLine()
                    !== "{\"error\":\"command_failed\",\"coordination\":[\"release_failed\"]}\n"
            ) {
                throw new RuntimeException(
                    'Lease failure output must retain but never expose the primary failure.',
                );
            }
        },

        'Redis schedule lease renews and releases only through fixed token-checked scripts' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setResults = [true];
            $client->evalResults = [1, 1];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );

            $acquired = $lease->acquire();
            $renewed = $lease->renew();
            $released = $lease->release();
            $ownerToken = $client->setCalls[0]['value'] ?? null;
            $renewCall = $client->evalCalls[0] ?? null;
            $releaseCall = $client->evalCalls[1] ?? null;
            $expectedRenewScript = <<<'LUA'
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('pexpire', KEYS[1], ARGV[2])
                end
                return 0
                LUA;
            $expectedReleaseScript = <<<'LUA'
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('del', KEYS[1])
                end
                return 0
                LUA;

            if (
                $acquired !== RedisScheduleRunLeaseAcquireOutcome::Acquired
                || $renewed !== RedisScheduleRunLeaseRenewOutcome::Renewed
                || $released !== RedisScheduleRunLeaseReleaseOutcome::Released
                || !is_string($ownerToken)
                || $renewCall !== [
                    'script' => $expectedRenewScript,
                    'arguments' => [
                        'phpthis_example:test:schedule_run:v1',
                        $ownerToken,
                        '30000',
                    ],
                    'key_count' => 1,
                ]
                || $releaseCall !== [
                    'script' => $expectedReleaseScript,
                    'arguments' => [
                        'phpthis_example:test:schedule_run:v1',
                        $ownerToken,
                    ],
                    'key_count' => 1,
                ]
                || $trace->snapshot() !== ['acquired', 'renewed', 'released']
            ) {
                throw new RuntimeException('Redis renewal and release must atomically compare the acquired token.');
            }
        },

        'Redis schedule lease cannot renew or delete a successor lease' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setResults = [true];
            $client->evalResults = [0, 0];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );

            $lease->acquire();
            $renewed = $lease->renew();
            $released = $lease->release();

            if (
                $renewed !== RedisScheduleRunLeaseRenewOutcome::Lost
                || $released !== RedisScheduleRunLeaseReleaseOutcome::Lost
                || count($client->evalCalls) !== 2
                || $trace->snapshot() !== ['acquired', 'lost_on_renewal', 'lost_before_release']
            ) {
                throw new RuntimeException('A stale owner token must leave a successor lease unchanged.');
            }
        },

        'Redis schedule lease performs no hidden retry after a backend failure' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setFailures = [new RedisException('private acquisition failure')];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );
            $failed = false;

            try {
                $lease->acquire();
            } catch (RedisScheduleRunLeaseUnavailable $failure) {
                $failed = $failure->getMessage() === 'The Redis schedule-run lease is unavailable.';
            }

            if (
                !$failed
                || count($client->setCalls) !== 1
                || count($client->evalCalls) !== 0
                || $trace->snapshot() !== ['acquire_failed']
            ) {
                throw new RuntimeException('Redis lease acquisition failure must fail closed after one attempt.');
            }
        },

        'Redis schedule lease preserves safe cleanup after an uncertain renewal' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setResults = [true];
            $client->evalFailures = [new RedisException('private renewal failure'), null];
            $client->evalResults = [1];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );

            $lease->acquire();
            $failed = false;

            try {
                $lease->renew();
            } catch (RedisScheduleRunLeaseUnavailable) {
                $failed = true;
            }

            $released = $lease->release();

            if (
                !$failed
                || $released !== RedisScheduleRunLeaseReleaseOutcome::Released
                || count($client->evalCalls) !== 2
                || $trace->snapshot() !== ['acquired', 'renew_failed', 'released']
            ) {
                throw new RuntimeException('An uncertain renewal must stop renewal but retain token-checked cleanup.');
            }
        },

        'Redis schedule lease bounds renewals and its structured outcome trace' => static function (): void {
            $client = new RedisScheduleRunLeaseTestRedis();
            $client->setResults = [true];
            $client->evalResults = [1, 1, 1, 1, 1];
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::fromConnectedRedis(
                $client,
                'phpthis_example:test:schedule_run:v1',
                $trace,
            );

            $lease->acquire();
            $lease->renew();
            $lease->renew();
            $lease->renew();
            $lease->renew();
            $limitReached = false;

            try {
                $lease->renew();
            } catch (LogicException) {
                $limitReached = true;
            }

            $released = $lease->release();

            if (
                !$limitReached
                || $released !== RedisScheduleRunLeaseReleaseOutcome::Released
                || count($client->evalCalls) !== 5
                || $trace->snapshot() !== [
                    'acquired',
                    'renewed',
                    'renewed',
                    'renewed',
                    'renewed',
                    'renewal_limit_reached',
                    'released',
                ]
            ) {
                throw new RuntimeException('Redis lease renewal and trace cardinality must remain finite.');
            }
        },

        'Redis schedule lease renews TTL against the recorded server endpoint' => static function (): void {
            $target = coordinationRedisTarget();
            $redis = coordinationRedisConnection();
            $key = coordinationRedisKey('renewal');
            $redis->del($key);
            $trace = new RedisScheduleRunLeaseTrace();
            $lease = RedisScheduleRunLease::connect(
                $target['host'],
                $target['port'],
                0,
                $key,
                $trace,
            );

            $acquired = $lease->acquire();
            $initialTtl = $redis->pttl($key);
            usleep(50_000);
            $decreasedTtl = $redis->pttl($key);
            $renewed = $lease->renew();
            $renewedTtl = $redis->pttl($key);
            $released = $lease->release();

            if (
                $acquired !== RedisScheduleRunLeaseAcquireOutcome::Acquired
                || $initialTtl < 29_000
                || $initialTtl > 30_000
                || $decreasedTtl >= $initialTtl
                || $renewed !== RedisScheduleRunLeaseRenewOutcome::Renewed
                || $renewedTtl < 29_000
                || $renewedTtl > 30_000
                || $released !== RedisScheduleRunLeaseReleaseOutcome::Released
                || $redis->get($key) !== false
                || $trace->snapshot() !== ['connected', 'acquired', 'renewed', 'released']
            ) {
                throw new RuntimeException('A real Redis renewal must atomically restore the finite lease TTL.');
            }
        },

        'Redis schedule lease stale owner cannot change a real successor lease' => static function (): void {
            $target = coordinationRedisTarget();
            $redis = coordinationRedisConnection();
            $key = coordinationRedisKey('stale-owner');
            $redis->del($key);
            $firstTrace = new RedisScheduleRunLeaseTrace();
            $first = RedisScheduleRunLease::connect(
                $target['host'],
                $target['port'],
                0,
                $key,
                $firstTrace,
            );
            $first->acquire();

            if (!$redis->pexpire($key, 75)) {
                throw new RuntimeException('Unable to bound the first real Redis lease fixture.');
            }

            coordinationWaitUntilAbsent($redis, $key, 2_000);
            $secondTrace = new RedisScheduleRunLeaseTrace();
            $second = RedisScheduleRunLease::connect(
                $target['host'],
                $target['port'],
                0,
                $key,
                $secondTrace,
            );
            $second->acquire();
            $successorValue = $redis->get($key);
            $firstRenewal = $first->renew();
            $firstRelease = $first->release();
            $valueAfterStaleOwner = $redis->get($key);
            $secondRelease = $second->release();

            if (
                !is_string($successorValue)
                || $firstRenewal !== RedisScheduleRunLeaseRenewOutcome::Lost
                || $firstRelease !== RedisScheduleRunLeaseReleaseOutcome::Lost
                || $valueAfterStaleOwner !== $successorValue
                || $secondRelease !== RedisScheduleRunLeaseReleaseOutcome::Released
                || $redis->get($key) !== false
                || $firstTrace->snapshot() !== [
                    'connected',
                    'acquired',
                    'lost_on_renewal',
                    'lost_before_release',
                ]
                || $secondTrace->snapshot() !== ['connected', 'acquired', 'released']
            ) {
                throw new RuntimeException('A real stale owner must leave its successor lease unchanged.');
            }
        },

        'Redis schedule lease real server rejection fails closed' => static function (): void {
            $target = coordinationRedisTarget();
            $redis = coordinationRedisConnection();
            $key = coordinationRedisKey('server-rejection');
            $redis->del($key);
            $maxmemory = $redis->config('GET', 'maxmemory');

            if (!is_array($maxmemory) || !isset($maxmemory['maxmemory']) || !is_string($maxmemory['maxmemory'])) {
                throw new RuntimeException('Unable to inspect the Redis lease capacity setting.');
            }

            $trace = new RedisScheduleRunLeaseTrace();
            $failed = false;

            try {
                if ($redis->config('SET', 'maxmemory', '1') !== true) {
                    throw new RuntimeException('Unable to constrain the Redis lease endpoint for rejection evidence.');
                }

                $lease = RedisScheduleRunLease::connect(
                    $target['host'],
                    $target['port'],
                    0,
                    $key,
                    $trace,
                );

                try {
                    $lease->acquire();
                } catch (RedisScheduleRunLeaseUnavailable $failure) {
                    $failed = $failure->coordination === ['connected', 'acquire_failed'];
                }
            } finally {
                if ($redis->config('SET', 'maxmemory', $maxmemory['maxmemory']) !== true) {
                    throw new RuntimeException('Unable to restore the Redis lease capacity setting.');
                }
            }

            if (!$failed || $trace->snapshot() !== ['connected', 'acquire_failed']) {
                throw new RuntimeException('A real Redis server rejection must not be reported as contention.');
            }
        },

        'Redis schedule lease rejects invalid targets and keys before backend I/O' => static function (): void {
            $trace = new RedisScheduleRunLeaseTrace();
            $invalid = [
                ['', 6379, 1, 'phpthis_example:test:schedule_run:v1'],
                ["redis\nprivate", 6379, 1, 'phpthis_example:test:schedule_run:v1'],
                ['127.0.0.1', 0, 1, 'phpthis_example:test:schedule_run:v1'],
                ['127.0.0.1', 65_536, 1, 'phpthis_example:test:schedule_run:v1'],
                ['127.0.0.1', 6379, -1, 'phpthis_example:test:schedule_run:v1'],
                ['127.0.0.1', 6379, 16, 'phpthis_example:test:schedule_run:v1'],
                ['127.0.0.1', 6379, 1, 'foo'],
                ['127.0.0.1', 6379, 1, 'phpthis_example:wrong'],
                ['127.0.0.1', 6379, 1, 'Uppercase-is-not-canonical'],
                ['127.0.0.1', 6379, 1, str_repeat('a', 129)],
            ];

            foreach ($invalid as [$host, $port, $database, $key]) {
                $rejected = false;

                try {
                    RedisScheduleRunLease::connect(
                        $host,
                        $port,
                        $database,
                        $key,
                        $trace,
                    );
                } catch (InvalidArgumentException) {
                    $rejected = true;
                }

                if (!$rejected) {
                    throw new RuntimeException('Invalid Redis connection input unexpectedly reached backend I/O.');
                }
            }

            if ($trace->snapshot() !== []) {
                throw new RuntimeException('Pre-I/O Redis lease validation must not record a backend outcome.');
            }
        },
    ];
}

final class RedisScheduleRunLeaseTestRedis extends Redis
{
    /** @var list<bool> */
    public array $setResults = [];

    /** @var list<RedisException> */
    public array $setFailures = [];

    /** @var list<string|null> */
    public array $setLastErrors = [];

    public ?string $lastError = null;

    /** @var list<int> */
    public array $evalResults = [];

    /** @var list<RedisException|null> */
    public array $evalFailures = [];

    /** @var list<array{key: string, value: string, options: mixed}> */
    public array $setCalls = [];

    /** @var list<array{script: string, arguments: array<mixed>, key_count: int}> */
    public array $evalCalls = [];

    /** @param array<array-key, mixed> $options */
    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        if (!is_string($value)) {
            throw new RuntimeException('Redis lease tests require string values.');
        }

        $this->setCalls[] = ['key' => $key, 'value' => $value, 'options' => $options];
        $failure = array_shift($this->setFailures);

        if ($failure instanceof RedisException) {
            throw $failure;
        }

        $result = array_shift($this->setResults);

        if (!is_bool($result)) {
            throw new RuntimeException('Redis lease test SET result is missing.');
        }

        $this->lastError = array_shift($this->setLastErrors);

        return $result;
    }

    public function clearLastError(): bool
    {
        $this->lastError = null;

        return true;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** @param array<array-key, mixed> $args */
    public function eval(string $script, array $args = [], int $num_keys = 0): mixed
    {
        $this->evalCalls[] = [
            'script' => $script,
            'arguments' => $args,
            'key_count' => $num_keys,
        ];
        $failure = array_shift($this->evalFailures);

        if ($failure instanceof RedisException) {
            throw $failure;
        }

        $result = array_shift($this->evalResults);

        if (!is_int($result)) {
            throw new RuntimeException('Redis lease test EVAL result is missing.');
        }

        return $result;
    }
}

function coordinationRedisConnection(): Redis
{
    $target = coordinationRedisTarget();
    $redis = new Redis();

    if (
        !$redis->connect($target['host'], $target['port'], 0.25, null, 0, 0.25)
        || !$redis->select(0)
    ) {
        throw new RuntimeException('Unable to connect to the Redis coordination proof endpoint.');
    }

    return $redis;
}

/** @return array{host: non-empty-string, port: int<1, 65535>} */
function coordinationRedisTarget(): array
{
    $hostValue = getenv('PHPTHIS_REDIS_LEASE_HOST');
    $host = is_string($hostValue) && $hostValue !== '' ? $hostValue : '127.0.0.1';
    $portValue = getenv('PHPTHIS_REDIS_LEASE_PORT');
    $port = is_string($portValue) && preg_match('/\A[1-9][0-9]{0,4}\z/D', $portValue) === 1
        ? (int) $portValue
        : 6380;

    if ($port < 1 || $port > 65_535) {
        throw new RuntimeException('Redis coordination proof port is outside the TCP port range.');
    }

    return ['host' => $host, 'port' => $port];
}

function coordinationRedisKey(string $purpose): string
{
    return 'phpthis_example:t' . substr(
        hash('sha256', $purpose . bin2hex(random_bytes(8))),
        0,
        20,
    ) . ':schedule_run:v1';
}

function coordinationWaitUntilAbsent(
    Redis $redis,
    string $key,
    int $maximumWaitMilliseconds,
): void {
    $deadline = hrtime(true) + ($maximumWaitMilliseconds * 1_000_000);

    while (hrtime(true) < $deadline) {
        if ($redis->get($key) === false) {
            return;
        }

        usleep(10_000);
    }

    throw new RuntimeException('Redis coordination key did not expire within the bounded wait.');
}
