<?php

declare(strict_types=1);

use CatalogImageRecipe\CatalogForbidden;
use CatalogImageRecipe\CatalogLock;
use CatalogImageRecipe\CatalogRoot;
use CatalogImageRecipe\CatalogUnauthenticated;
use CatalogImageRecipe\CatalogUnavailable;
use CatalogImageRecipe\DownloadCatalogImage;
use CatalogImageRecipe\HttpConfiguration;
use CatalogImageRecipe\InspectionDefinition;
use CatalogImageRecipe\PendingImageUpload;
use CatalogImageRecipe\UploadCatalogImage;
use CatalogImageRecipe\WorkerConfiguration;
use CatalogImageRecipe\Tests\Fixture;
use CatalogImageRecipe\Tests\FixtureClock;
use CatalogImageRecipe\Tests\SyntheticPolicy;
use PHPThis\Http\MultipartForm;
use PHPThis\Http\MultipartLimits;
use PHPThis\Http\Request;
use PHPThis\Routing\PathParameters;
use PHPThis\Verification\EnvironmentAccessProfile;
use PHPThis\Verification\SyntaxProfile;

require __DIR__ . '/SyntheticPolicy.php';
require dirname(__DIR__, 2) . '/process-support.php';
require dirname(__DIR__, 3) . '/verification/SyntaxProfile.php';
require dirname(__DIR__, 3) . '/verification/EnvironmentAccessProfile.php';

function verifyCatalogContracts(): void
{
    $root = dirname(__DIR__);
    $diagnostics = runBoundedMaintainerProcess(
        ['python3', $root . '/test_parser_diagnostics.py', PHP_BINARY], $root, [], 10_000, 4096, 4096,
    );
    requireCatalogProof($diagnostics === [
        'exit_code' => 0, 'stdout' => "PASS PNG parser diagnostic controls\n", 'stderr' => '',
    ]);
    foreach (InspectionDefinition::sources() as $relative) {
        if (!str_ends_with($relative, '.php')) { continue; }
        $source = file_get_contents($root . '/' . $relative);
        if (!is_string($source)) { throw new RuntimeException('Catalog source missing.'); }
        requireCatalogProof(SyntaxProfile::failures($source, $relative) === []);
        $environment = EnvironmentAccessProfile::inspect($source, $relative);
        requireCatalogProof($environment['failures'] === []);
        requireCatalogProof($relative === 'src/ReferenceEnvironment.php' || $environment['reads'] === []);
    }
    foreach ([false, '', '/tmp/../unknown', 'relative', '/tmp/space here'] as $invalid) {
        try { HttpConfiguration::fromInput($invalid); throw new LogicException('Invalid configuration accepted.'); }
        catch (InvalidArgumentException) {}
    }
    try { WorkerConfiguration::fromInputs('/tmp/catalog', 'mutable:tag'); throw new LogicException('Mutable image accepted.'); }
    catch (InvalidArgumentException) {}
    foreach ([['unknown'], ['images:inspect-one', 'extra'], ['images:retry'], ['images:retry', 'not-an-id']] as $arguments) {
        $result = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', ...$arguments], $root, [], 3000, 4096, 4096);
        requireCatalogProof($result['exit_code'] === 2 && $result['stdout'] === ''
            && $result['stderr'] === ($arguments[0] === 'unknown' ? "{\"error\":\"unknown_command\"}\n" : "{\"error\":\"invalid_arguments\"}\n"));
    }
    $missing = runBoundedMaintainerProcess([PHP_BINARY, $root . '/console.php', 'images:inspect-one'], $root, [], 3000, 4096, 4096);
    requireCatalogProof($missing === ['exit_code' => 1, 'stdout' => '', 'stderr' => "{\"error\":\"command_failed\"}\n"]);

    $absent = CatalogRoot::fromInput(dirname(__DIR__, 3) . '/tmp/png-proof-absent-' . bin2hex(random_bytes(8)));
    $clock = new FixtureClock(time());
    $policy = new SyntheticPolicy();
    $handler = new UploadCatalogImage($absent, new CatalogLock($absent), $clock, $policy, $policy);
    try { $handler->handle(new Request('POST', '/catalog-images')); throw new LogicException('Unauthenticated upload passed.'); }
    catch (CatalogUnauthenticated) {}
    requireCatalogProof($policy->takeCalls() === ['authenticate']);
    try { $handler->handle(new Request('POST', '/catalog-images', headers: ['authorization' => 'Bearer fixture-forbidden'])); throw new LogicException('Forbidden upload passed.'); }
    catch (CatalogForbidden) {}
    requireCatalogProof($policy->takeCalls() === ['authenticate', 'authorize_upload']);
    $download = new DownloadCatalogImage($absent, new CatalogLock($absent), $clock, $policy, $policy);
    try { $download->handle(new Request('GET', '/catalog-images/' . str_repeat('1', 32), headers: ['authorization' => 'Bearer fixture-forbidden'],
        pathParameters: PathParameters::fromValues([], ['image_id' => str_repeat('1', 32)]))); throw new LogicException('Forbidden download looked up storage.'); }
    catch (CatalogForbidden) {}
    requireCatalogProof($policy->takeCalls() === ['authenticate', 'authorize_download'] && !file_exists($absent->path));

    // A local path with plausible PHP metadata is not an uploaded-file provenance proof.
    $form = MultipartForm::fromPhp([], ['image' => ['name' => 'fake.png', 'type' => 'image/png',
        'tmp_name' => __FILE__, 'error' => 0, 'size' => 1]], 1000, new MultipartLimits(2_097_152, 1_048_576, 1_048_576));
    try { PendingImageUpload::fromRequest(new Request('POST', '/catalog-images', multipart: $form)); throw new LogicException('Forged upload provenance accepted.'); }
    catch (CatalogUnavailable) {}

    $fixture = new Fixture(dirname(__DIR__, 3) . '/tmp/png-proof-' . bin2hex(random_bytes(8)));
    try {
        $copy = $fixture->root->path . '/definition';
        mkdir($copy, 0700);
        foreach (InspectionDefinition::sources() as $relative) {
            if (!is_dir(dirname($copy . '/' . $relative))) { mkdir(dirname($copy . '/' . $relative), 0700, true); }
            copy($root . '/' . $relative, $copy . '/' . $relative);
        }
        $baseline = InspectionDefinition::fromImageId($fixture->definition->image, $copy);
        requireCatalogProof($baseline->value === $fixture->definition->value);
        $policyBytes = file_get_contents($copy . '/inspection-policy.json');
        if (!is_string($policyBytes)) { throw new RuntimeException('Policy fixture unavailable.'); }
        foreach (['toolchain', 'parser_configuration', 'scanner_signatures', 'format_allowlist', 'application_policy', 'limits', 'isolation'] as $field) {
            $changed = str_replace('"' . $field . '": "', '"' . $field . '": "changed-', $policyBytes);
            requireCatalogProof($changed !== $policyBytes);
            file_put_contents($copy . '/inspection-policy.json', $changed);
            requireCatalogProof(InspectionDefinition::fromImageId($baseline->image, $copy)->value !== $baseline->value);
        }
        file_put_contents($copy . '/inspection-policy.json', $policyBytes);
        file_put_contents($copy . '/src/PngHeader.php', "\n", FILE_APPEND);
        requireCatalogProof(InspectionDefinition::fromImageId($baseline->image, $copy)->value !== $baseline->value);
    } finally { $fixture->cleanup(); }
}
