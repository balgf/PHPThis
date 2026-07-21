<?php

declare(strict_types=1);

namespace Example\Coordination;

use InvalidArgumentException;
use LogicException;
use Redis;
use RedisException;

final class RedisScheduleRunLease
{
    private const float CONNECT_TIMEOUT_SECONDS = 0.25;
    private const float READ_TIMEOUT_SECONDS = 0.25;
    private const int LEASE_TTL_MILLISECONDS = 30_000;
    private const int MAXIMUM_RENEWALS = 4;

    private const string RENEW_SCRIPT = <<<'LUA'
        if redis.call('get', KEYS[1]) == ARGV[1] then
            return redis.call('pexpire', KEYS[1], ARGV[2])
        end
        return 0
        LUA;

    private const string RELEASE_SCRIPT = <<<'LUA'
        if redis.call('get', KEYS[1]) == ARGV[1] then
            return redis.call('del', KEYS[1])
        end
        return 0
        LUA;

    private bool $acquisitionAttempted = false;
    private ?string $ownerToken = null;
    private bool $renewalPermitted = false;
    private int $renewals = 0;

    private function __construct(
        private readonly Redis $redis,
        private readonly string $key,
        private readonly RedisScheduleRunLeaseTrace $trace,
    ) {
        self::validateKey($key);
    }

    public static function connect(
        string $host,
        int $port,
        int $database,
        string $key,
        RedisScheduleRunLeaseTrace $trace,
    ): self {
        self::validateConnectionTarget($host, $port, $database);
        self::validateKey($key);
        $redis = new Redis();

        try {
            $connected = $redis->connect(
                $host,
                $port,
                self::CONNECT_TIMEOUT_SECONDS,
                null,
                0,
                self::READ_TIMEOUT_SECONDS,
            );

            if (
                !$connected
                || !$redis->setOption(Redis::OPT_MAX_RETRIES, 0)
                || !$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE)
                || !$redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE)
                || !$redis->setOption(Redis::OPT_REPLY_LITERAL, false)
                || !$redis->select($database)
            ) {
                $trace->record(RedisScheduleRunLeaseTraceOutcome::ConnectFailed);

                throw new RedisScheduleRunLeaseUnavailable($trace->snapshot());
            }
        } catch (RedisException) {
            $trace->record(RedisScheduleRunLeaseTraceOutcome::ConnectFailed);

            throw new RedisScheduleRunLeaseUnavailable($trace->snapshot());
        }

        $trace->record(RedisScheduleRunLeaseTraceOutcome::Connected);

        return new self($redis, $key, $trace);
    }

    public static function fromConnectedRedis(
        Redis $redis,
        string $key,
        RedisScheduleRunLeaseTrace $trace,
    ): self {
        self::validateKey($key);

        if (
            !$redis->setOption(Redis::OPT_MAX_RETRIES, 0)
            || !$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE)
            || !$redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE)
            || !$redis->setOption(Redis::OPT_REPLY_LITERAL, false)
        ) {
            $trace->record(RedisScheduleRunLeaseTraceOutcome::ConnectFailed);

            throw new RedisScheduleRunLeaseUnavailable($trace->snapshot());
        }

        return new self($redis, $key, $trace);
    }

    public function acquire(): RedisScheduleRunLeaseAcquireOutcome
    {
        if ($this->acquisitionAttempted) {
            throw new LogicException('The Redis schedule-run lease acquisition was already attempted.');
        }

        $this->acquisitionAttempted = true;
        $ownerToken = bin2hex(random_bytes(16));

        try {
            if (!$this->redis->clearLastError()) {
                $this->trace->record(RedisScheduleRunLeaseTraceOutcome::AcquireFailed);

                throw new RedisScheduleRunLeaseUnavailable($this->trace->snapshot());
            }

            $result = $this->redis->set(
                $this->key,
                $ownerToken,
                [
                    'NX',
                    'PX' => self::LEASE_TTL_MILLISECONDS,
                ],
            );
            $lastError = $this->redis->getLastError();
        } catch (RedisException) {
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::AcquireFailed);

            throw new RedisScheduleRunLeaseUnavailable($this->trace->snapshot());
        }

        if ($result === false && $lastError === null) {
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::Contended);

            return RedisScheduleRunLeaseAcquireOutcome::Contended;
        }

        if ($result !== true || $lastError !== null) {
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::AcquireFailed);

            throw new RedisScheduleRunLeaseUnavailable($this->trace->snapshot());
        }

        $this->ownerToken = $ownerToken;
        $this->renewalPermitted = true;
        $this->trace->record(RedisScheduleRunLeaseTraceOutcome::Acquired);

        return RedisScheduleRunLeaseAcquireOutcome::Acquired;
    }

    public function renew(): RedisScheduleRunLeaseRenewOutcome
    {
        $ownerToken = $this->ownedToken();

        if (!$this->renewalPermitted) {
            throw new LogicException('The Redis schedule-run lease can no longer be renewed.');
        }

        if ($this->renewals >= self::MAXIMUM_RENEWALS) {
            $this->renewalPermitted = false;
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::RenewalLimitReached);

            throw new LogicException('The Redis schedule-run lease renewal limit was reached.');
        }

        ++$this->renewals;

        try {
            $result = $this->redis->eval(
                self::RENEW_SCRIPT,
                [$this->key, $ownerToken, (string) self::LEASE_TTL_MILLISECONDS],
                1,
            );
        } catch (RedisException) {
            $this->renewalPermitted = false;
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::RenewFailed);

            throw new RedisScheduleRunLeaseUnavailable($this->trace->snapshot());
        }

        if ($result === 0) {
            $this->renewalPermitted = false;
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::LostOnRenewal);

            return RedisScheduleRunLeaseRenewOutcome::Lost;
        }

        if ($result !== 1) {
            $this->renewalPermitted = false;
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::RenewFailed);

            throw new RedisScheduleRunLeaseUnavailable($this->trace->snapshot());
        }

        $this->trace->record(RedisScheduleRunLeaseTraceOutcome::Renewed);

        return RedisScheduleRunLeaseRenewOutcome::Renewed;
    }

    public function release(): RedisScheduleRunLeaseReleaseOutcome
    {
        $ownerToken = $this->ownedToken();
        $this->renewalPermitted = false;
        $this->ownerToken = null;

        try {
            $result = $this->redis->eval(
                self::RELEASE_SCRIPT,
                [$this->key, $ownerToken],
                1,
            );
        } catch (RedisException) {
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::ReleaseFailed);

            throw new RedisScheduleRunLeaseUnavailable($this->trace->snapshot());
        }

        if ($result === 0) {
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::LostBeforeRelease);

            return RedisScheduleRunLeaseReleaseOutcome::Lost;
        }

        if ($result !== 1) {
            $this->trace->record(RedisScheduleRunLeaseTraceOutcome::ReleaseFailed);

            throw new RedisScheduleRunLeaseUnavailable($this->trace->snapshot());
        }

        $this->trace->record(RedisScheduleRunLeaseTraceOutcome::Released);

        return RedisScheduleRunLeaseReleaseOutcome::Released;
    }

    private function ownedToken(): string
    {
        if ($this->ownerToken === null) {
            throw new LogicException('The Redis schedule-run lease is not owned by this process.');
        }

        return $this->ownerToken;
    }

    private static function validateConnectionTarget(string $host, int $port, int $database): void
    {
        if (
            $host === ''
            || strlen($host) > 255
            || preg_match('/[\x00-\x20\x7f]/D', $host) === 1
            || $port < 1
            || $port > 65_535
            || $database < 0
            || $database > 15
        ) {
            throw new InvalidArgumentException('Redis schedule-run lease connection target is invalid.');
        }
    }

    private static function validateKey(string $key): void
    {
        if (
            preg_match(
                '/\Aphpthis_example:[a-z][a-z0-9_-]{0,31}:schedule_run:v1\z/D',
                $key,
            ) !== 1
        ) {
            throw new InvalidArgumentException('Redis schedule-run lease key is invalid.');
        }
    }
}
