<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

final class ReferenceEnvironment
{
    public static function forFixture(): FixtureRun
    {
        return FixtureRun::fromInput(\getenv('PHPTHIS_MYSQL_JOBS_RUN_ID'));
    }

    public static function forProducer(): ProducerConfiguration
    {
        return ProducerConfiguration::fromInputs(\getenv('PHPTHIS_MYSQL_JOBS_PRODUCER_DSN'), \getenv('PHPTHIS_MYSQL_JOBS_PRODUCER_PASSWORD'));
    }

    public static function forWorker(): WorkerConfiguration
    {
        return WorkerConfiguration::fromInputs(\getenv('PHPTHIS_MYSQL_JOBS_WORKER_DSN'), \getenv('PHPTHIS_MYSQL_JOBS_WORKER_PASSWORD'));
    }

    public static function forVerification(): VerificationConfiguration
    {
        return VerificationConfiguration::fromInputs(\getenv('PHPTHIS_MYSQL_JOBS_VERIFICATION_DSN'), \getenv('PHPTHIS_MYSQL_JOBS_VERIFICATION_PASSWORD'));
    }
}
