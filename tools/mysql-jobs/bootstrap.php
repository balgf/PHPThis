<?php

declare(strict_types=1);

require_once __DIR__ . '/src/FixtureRun.php';

// The reference runs within this checkout. An adopting application uses its
// own one Composer autoload path and copies these concrete operations explicitly.
require dirname(__DIR__, 2) . '/autoload.php';
require __DIR__ . '/src/MySqlWelcomeJobLease.php';
require __DIR__ . '/src/WelcomeLeaseLost.php';
require __DIR__ . '/src/MySqlWelcomeClock.php';
require __DIR__ . '/src/PublishWelcomeRequest.php';
require __DIR__ . '/src/RecordMySqlWelcomeDelivery.php';
require __DIR__ . '/src/MySqlWelcomeJobWorker.php';
require __DIR__ . '/src/ProducerConfiguration.php';
require __DIR__ . '/src/WorkerConfiguration.php';
require __DIR__ . '/src/VerificationConfiguration.php';
require __DIR__ . '/src/ReferenceEnvironment.php';
require __DIR__ . '/src/MySqlWelcomeSession.php';
