<?php

declare(strict_types=1);

use Example\Coordination\RedisScheduleRunLease;
use Example\Coordination\RedisScheduleRunLeaseAcquireOutcome;
use Example\Coordination\RedisScheduleRunLeaseReleaseOutcome;
use Example\Coordination\RedisScheduleRunLeaseRenewOutcome;
use Example\Coordination\RedisScheduleRunLeaseTrace;

require dirname(__DIR__) . '/autoload.php';

$environment = $argv[1] ?? null;

if (!is_string($environment) || preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/D', $environment) !== 1) {
    throw new InvalidArgumentException('Redis schedule lease-holder environment is invalid.');
}

$host = getenv('PHPTHIS_REDIS_LEASE_HOST');
$host = is_string($host) && $host !== '' ? $host : '127.0.0.1';
$portValue = getenv('PHPTHIS_REDIS_LEASE_PORT');
$port = is_string($portValue) && preg_match('/\A[1-9][0-9]{0,4}\z/D', $portValue) === 1
    ? (int) $portValue
    : 6380;

if ($port < 1 || $port > 65_535) {
    throw new InvalidArgumentException('Redis schedule lease-holder port is invalid.');
}

$lease = RedisScheduleRunLease::connect(
    $host,
    $port,
    0,
    'phpthis_example:' . $environment . ':schedule_run:v1',
    new RedisScheduleRunLeaseTrace(),
);

if ($lease->acquire() !== RedisScheduleRunLeaseAcquireOutcome::Acquired) {
    throw new RuntimeException('Schedule lease holder could not acquire the Redis lease.');
}

if ($lease->renew() !== RedisScheduleRunLeaseRenewOutcome::Renewed) {
    throw new RuntimeException('Schedule lease holder could not renew the Redis lease.');
}

fwrite(STDOUT, "READY\n");

if (fgets(STDIN) !== "RELEASE\n") {
    throw new RuntimeException('Schedule lease holder did not receive its release signal.');
}

if ($lease->release() !== RedisScheduleRunLeaseReleaseOutcome::Released) {
    throw new RuntimeException('Schedule lease holder could not release the Redis lease.');
}
