<?php

declare(strict_types=1);

require_once __DIR__ . '/test-consumer-project/support.php';
require_once __DIR__ . '/test-consumer-project/guidance.php';
require_once __DIR__ . '/test-consumer-project/http.php';
require_once __DIR__ . '/test-consumer-project/file-transfers.php';
require_once __DIR__ . '/test-consumer-project/amazon-s3-file-transfers.php';
require_once __DIR__ . '/test-consumer-project/jobs.php';
require_once __DIR__ . '/test-consumer-project/observability.php';
require_once __DIR__ . '/test-consumer-project/application.php';
require_once __DIR__ . '/test-consumer-project/data.php';
require_once __DIR__ . '/test-consumer-project/configuration.php';
require_once __DIR__ . '/test-consumer-project/local-environment-launcher.php';
require_once __DIR__ . '/test-consumer-project/profile-controls.php';

$root = dirname(__DIR__);
$composerBinary = composerBinary($root);
$workspace = sys_get_temp_dir() . '/phpthis-consumer-proof-' . bin2hex(random_bytes(12));

if (!mkdir($workspace, 0700)) {
    throw new RuntimeException('Unable to create the isolated consumer-proof directory.');
}

try {
    $environment = processEnvironment([
        'COMPOSER_CACHE_DIR' => $workspace . '/composer-cache',
        'COMPOSER_DISABLE_NETWORK' => '1',
        'COMPOSER_ROOT_VERSION' => 'dev-main',
    ]);
    $archiveDirectory = $workspace . '/archive';

    if (!mkdir($archiveDirectory, 0700)) {
        throw new RuntimeException('Unable to create the package-archive directory.');
    }

    $archiveResult = runProcess(
        composerCommand($composerBinary, [
            'archive',
            '--format=tar',
            '--dir=' . $archiveDirectory,
            '--file=phpthis-framework',
        ]),
        $root,
        $environment,
    );
    requireSuccess($archiveResult, 'Framework archive creation failed.');

    $archivePath = $archiveDirectory . '/phpthis-framework.tar';

    if (!is_file($archivePath)) {
        throw new RuntimeException('Composer did not create the expected framework archive.');
    }

    $expectedArchiveFiles = expectedArchiveFiles($root);
    $archiveFiles = archiveFiles($archivePath);
    proveGitExportParityStates($workspace, $environment);
    $gitExportParity = verifyExportPolicies(
        $root,
        $workspace,
        $expectedArchiveFiles,
        $environment,
    );
    verifySkeletonPublicationBoundary($root);

    if ($archiveFiles !== $expectedArchiveFiles) {
        throw new RuntimeException(inventoryDifference($expectedArchiveFiles, $archiveFiles));
    }

    proveInstalledGuidanceReferencesResolve(
        $root,
        $workspace,
        $archivePath,
        $composerBinary,
        $environment,
    );

    $project = $workspace . '/application';
    copyDirectory($root . '/skeleton', $project);
    configureIsolatedConsumer($root, $project, $archivePath);

    $installResult = runProcess(
        composerCommand($composerBinary, [
            'install',
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ]),
        $project,
        $environment,
    );
    requireSuccess($installResult, 'Isolated consumer dependency installation failed.');

    $validateResult = runProcess(
        composerCommand($composerBinary, ['validate', '--strict', '--no-check-publish']),
        $project,
        $environment,
    );
    requireSuccess($validateResult, 'Isolated consumer Composer validation failed.');

    $installedFramework = $project . '/vendor/phpthis/framework';

    if (!is_dir($installedFramework) || is_link($installedFramework)) {
        throw new RuntimeException('The consumer must install a mirrored framework package, not a symlink.');
    }

    if (
        !is_executable($installedFramework . '/bin/phpthis')
        || !is_executable($project . '/vendor/bin/phpthis')
    ) {
        throw new RuntimeException('The installed PHPThis consumer command is not executable.');
    }

    $installedFiles = directoryFiles($installedFramework);

    if ($installedFiles !== $expectedArchiveFiles) {
        throw new RuntimeException('The installed framework inventory differs from the verified archive.');
    }

    $profileCommand = [$project . '/vendor/bin/phpthis', 'check'];
    proveInstalledReleaseGuidanceDistribution($installedFramework);
    proveInstalledReferenceClarityDistribution($installedFramework);
    proveInstalledNativeDateTimeGuidanceDistribution($project, $installedFramework);
    proveInstalledFrontendIntegrationGuidanceDistribution($project, $installedFramework);
    proveInstalledApplicationOwnedOperationCoordinationGuidanceDistribution(
        $project,
        $installedFramework,
    );
    $installedStructuredJsonProofCompletion =
        proveInstalledStructuredJsonSuccessEnvelopeDistribution(
            $project,
            $installedFramework,
            $environment,
        );

    if (
        $installedStructuredJsonProofCompletion
            !== 'installed-structured-json-and-nested-resource-proof-complete'
    ) {
        throw new RuntimeException('Installed structured JSON and nested-resource proof did not complete.');
    }
    $installedFieldValidationProofCompletion =
        proveInstalledFieldValidationErrorGuidanceDistribution(
            $project,
            $installedFramework,
            $environment,
        );

    if (
        $installedFieldValidationProofCompletion
            !== 'installed-field-validation-error-guidance-proof-complete'
    ) {
        throw new RuntimeException('Installed field-validation error guidance proof did not complete.');
    }
    $installedProtectedFileTransferProof = proveInstalledProtectedFileTransferReference(
        $project,
        $installedFramework,
        $environment,
    );

    if (
        $installedProtectedFileTransferProof
            !== 'installed-protected-file-transfer-reference-proved'
    ) {
        throw new RuntimeException('The installed protected file-transfer proof did not complete.');
    }
    $installedAmazonS3FileTransferVerificationProof =
        proveInstalledAmazonS3FileTransferVerificationReference(
            $project,
            $installedFramework,
            $composerBinary,
            $environment,
        );

    if (
        $installedAmazonS3FileTransferVerificationProof
            !== 'installed-amazon-s3-file-transfer-verification-reference-proved'
    ) {
        throw new RuntimeException(
            'The installed Amazon S3 file-transfer verification proof did not complete.',
        );
    }
    $installedJobsVerificationProof = proveInstalledBackendNeutralJobsVerificationReference(
        $project,
        $installedFramework,
        $composerBinary,
        $environment,
    );

    if (
        $installedJobsVerificationProof
            !== 'installed-backend-neutral-jobs-verification-reference-proved'
    ) {
        throw new RuntimeException('The installed backend-neutral jobs verification proof did not complete.');
    }
    $installedDestinationRecordProof = proveInstalledRequestSummaryDestinationRecordReference(
        $project,
        $installedFramework,
        $environment,
    );

    if (
        $installedDestinationRecordProof
            !== 'installed-request-summary-destination-record-reference-proved'
    ) {
        throw new RuntimeException('The installed request-summary destination-record proof did not complete.');
    }
    proveInstalledTransactionalEmailGuidanceDistribution($project, $installedFramework);
    proveInstalledOneShotWorkerSupervisionGuidanceDistribution($project, $installedFramework);
    proveInstalledTestRunnerModularizationGuidanceDistribution($project, $installedFramework);
    proveInstalledStatelessAuthenticationGuidanceDistribution($project, $installedFramework);
    proveInstalledAgentEvaluationGuidanceDistribution($installedFramework, $archiveFiles);
    proveInstalledDatabaseSetupGuidanceDistribution($project, $installedFramework);
    proveInstalledStartupProbeGuidanceDistribution($project, $installedFramework);
    proveInstalledSessionCleanupAndResponseFramingDistribution($project, $installedFramework);
    proveInstalledBoundedResponseCookieProfileDistribution(
        $project,
        $installedFramework,
        $environment,
    );
    proveInstalledBoundedTaskRoutedContextGuidanceDistribution($project, $installedFramework);
    proveInstalledCrudAccessSurfaceGuidanceDistribution($project, $installedFramework);
    proveInstalledIdentifierRepresentationGuidanceDistribution($project, $installedFramework);
    proveInstalledDatabaseAuthorityLifecycleGuidanceDistribution($project, $installedFramework);
    proveInstalledEngineSpecificMigrationInvariantGuidanceDistribution(
        $project,
        $installedFramework,
    );
    proveInstalledMigrationStructureGuidanceDistribution(
        $project,
        $installedFramework,
        $profileCommand,
        $environment,
    );
    $installedWorkbenchGuidanceProof = proveInstalledWorkbenchGuidanceDistribution(
        $project,
        $installedFramework,
        $profileCommand,
        $environment,
    );

    if ($installedWorkbenchGuidanceProof !== 'installed-workbench-guidance-proved') {
        throw new RuntimeException('The installed Workbench guidance proof did not return its success sentinel.');
    }
    proveInstalledUuidAndUlidRouting($project, $environment);
    proveDatabaseContextConnectionConsistency($project, $profileCommand, $environment);
    proveInstalledTypedConfiguration($project, $profileCommand, $environment);
    proveInstalledConfigurationEvidenceReference(
        $project,
        $installedFramework,
        $profileCommand,
        $environment,
    );
    $installedLocalEnvironmentLauncherProof = proveInstalledLocalEnvironmentLauncherReference(
        $project,
        $installedFramework,
        $environment,
    );

    if (
        $installedLocalEnvironmentLauncherProof
            !== 'installed-local-environment-launcher-reference-proved'
    ) {
        throw new RuntimeException('The installed local environment launcher proof did not complete.');
    }
    $requestHandlerDecoratorProofPath = proveInstalledRequestHandlerDecorator($project, $environment);

    try {
        $profileResult = runProcess($profileCommand, $project, $environment);
        requireSuccess($profileResult, 'The clean skeleton and request-handler decorator proof failed the installed profile check.');
        requireStdoutContains(
            $profileResult,
            'PASS application duplication advisory: no possible groups (minimum 48 normalized tokens)',
        );
        requireStdoutNotContains($profileResult, 'ADVISORY');
        requireOutputContains($profileResult, 'PASS PHPThis application check');
        requireOutputNotContains($profileResult, $project . '/bootstrap.php');
    } finally {
        if (is_file($requestHandlerDecoratorProofPath) && !unlink($requestHandlerDecoratorProofPath)) {
            throw new RuntimeException('Unable to remove the installed request-handler decorator proof.');
        }
    }

    if (!is_file($project . '/vendor/.phpthis/phpstan/resultCache.php')) {
        throw new RuntimeException('The normal application check did not create its persistent PHPStan cache.');
    }

    $debugResult = runProcess(
        [$project . '/vendor/bin/phpthis', 'check', '--debug'],
        $project,
        $environment,
    );
    requireSuccess($debugResult, 'The explicit diagnostic profile check failed.');
    requireStdoutContains(
        $debugResult,
        'PASS application duplication advisory: no possible groups (minimum 48 normalized tokens)',
    );
    requireStdoutNotContains($debugResult, 'ADVISORY');
    requireOutputContains($debugResult, $project . '/bootstrap.php');

    $completeResult = runProcess(
        composerCommand($composerBinary, ['check']),
        $project,
        $environment,
    );
    requireSuccess($completeResult, 'The clean skeleton failed its complete application check.');
    requireOutputContains($completeResult, 'PASS application behavior and front controller');

    proveDuplicationAdvisoryIsReportOnly(
        $project,
        $composerBinary,
        $profileCommand,
        $environment,
    );
    proveObservabilityContextIsRequired($project, $profileCommand, $environment);
    proveConfigurationContextIsRequired($project, $profileCommand, $environment);
    proveEveryApplicationDirectoryIsChecked($project, $profileCommand, $environment);
    proveValidExtensionlessExecutableIsChecked($project, $profileCommand, $environment);
    proveMagicMethodsAreRejected($project, $profileCommand, $environment);
    proveEvalIdentifiersAreAllowedAndLanguageConstructIsRejected($project, $profileCommand, $environment);
    proveDependencyDirectoryIsExcluded($project, $profileCommand, $environment);
    proveMixedCoercionIsRejected($project, $profileCommand, $environment);
    proveDirectPdoConstructionIsRejected($project, $profileCommand, $environment);
    proveNativeSessionAccessIsRejected($project, $profileCommand, $environment);
    proveEnvironmentAccessIsRejected($project, $profileCommand, $environment);
    proveDynamicSqlIsRejected($project, $profileCommand, $environment);
    proveConfigurationCannotReplaceProfile($project, $profileCommand, $environment);
    proveBaselinesAndInlineIgnoresAreRejected($project, $profileCommand, $environment);
    proveComposerGateCannotDrift($project, $composerBinary, $profileCommand, $environment);
    proveSymlinkedSourceIsRejected($workspace, $project, $profileCommand, $environment);

    $restoredResult = runProcess($profileCommand, $project, $environment);
    requireSuccess($restoredResult, 'The skeleton did not return to a valid state after negative controls.');

    fwrite(STDOUT, gitExportParityResultLine(count($archiveFiles), $gitExportParity));
} finally {
    removeDirectory($workspace);
}
