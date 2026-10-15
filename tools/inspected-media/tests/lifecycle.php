<?php

declare(strict_types=1);

use CatalogImageRecipe\CatalogBusy;
use CatalogImageRecipe\CatalogLock;
use CatalogImageRecipe\CatalogNotFound;
use CatalogImageRecipe\CatalogPrincipal;
use CatalogImageRecipe\CatalogUnavailable;
use CatalogImageRecipe\InspectionDefinition;
use CatalogImageRecipe\InspectionOutcome;
use CatalogImageRecipe\Tests\Fixture;
use CatalogImageRecipe\Tests\FixtureInspector;

require __DIR__ . '/Fixture.php';

function verifyCatalogLifecycle(): void
{
    $fixture = new Fixture(dirname(__DIR__, 3) . '/tmp/png-proof-' . bin2hex(random_bytes(8)));
    $inspector = new FixtureInspector();
    $owner = new CatalogPrincipal(1);
    try {
        $first = $fixture->seed(str_repeat('1', 32), catalogPng());
        $fixture->lock->acquire(true);
        try { $fixture->images->read($first, $owner); throw new LogicException('Pending file served.'); }
        catch (CatalogNotFound) {}
        requireCatalogProof($fixture->images->inspectOne($fixture->definition, $inspector) === 'accepted');
        requireCatalogProof($fixture->record($first)?->attempts === 1);
        requireCatalogProof($fixture->images->read($first, $owner)->bytes === strlen(catalogPng()));
        $fixture->database->executeStatement('UPDATE catalog_policy SET source_fingerprint = :source WHERE singleton = 1', ['source' => str_repeat('0', 64)]);
        try { $fixture->images->read($first, $owner); throw new LogicException('Changed policy source inherited old acceptance.'); }
        catch (CatalogNotFound) {}
        $fixture->database->executeStatement('UPDATE catalog_policy SET source_fingerprint = :source WHERE singleton = 1', ['source' => $fixture->definition->sourceFingerprint]);
        try { $fixture->images->read($first, new CatalogPrincipal(2)); throw new LogicException('Cross-owner file served.'); }
        catch (CatalogNotFound) {}
        requireCatalogProof($fixture->images->inspectOne($fixture->definition, $inspector) === 'not_due');
        requireCatalogProof($inspector->calls === 1);

        $competing = new CatalogLock($fixture->root);
        try { $competing->acquire(false); throw new LogicException('Mutation overlapped serving.'); }
        catch (CatalogBusy) {}
        $fixture->lock->release();
        $fixture->lock->acquire(false);
        $competing->acquire(false);
        $writer = new CatalogLock($fixture->root);
        try { $writer->acquire(true); throw new LogicException('Serving overlapped mutation.'); }
        catch (CatalogBusy) {}
        $competing->release();
        $fixture->lock->release();
        $fixture->lock->acquire(true);

        $next = InspectionDefinition::fromImageId('sha256:' . str_repeat('2', 64), dirname(__DIR__));
        requireCatalogProof($next->value !== $fixture->definition->value);
        requireCatalogProof($fixture->images->activate($next) === 'activated');
        try { $fixture->images->read($first, $owner); throw new LogicException('Old inspection served.'); }
        catch (CatalogNotFound) {}
        $fixture->clock->value++;
        requireCatalogProof($fixture->images->inspectOne($next, $inspector) === 'accepted');
        $reinspected = $fixture->record($first);
        requireCatalogProof($reinspected !== null && $reinspected->definition === $next->value && $reinspected->attempts === 1);
        $fixture->images->read($first, $owner);
        $third = InspectionDefinition::fromImageId('sha256:' . str_repeat('3', 64), dirname(__DIR__));
        $fixture->images->activate($third);
        $inspector->outcome = InspectionOutcome::Rejected;
        $fixture->clock->value++;
        requireCatalogProof($fixture->images->inspectOne($third, $inspector) === 'rejected');
        try { $fixture->images->read($first, $owner); throw new LogicException('Rejected file served.'); }
        catch (CatalogNotFound) {}

        // A retired definition cannot restore old results or reset retry accounting.
        try { $fixture->images->activate($fixture->definition); throw new LogicException('Retired definition restored.'); }
        catch (CatalogUnavailable) {}
        $active = InspectionDefinition::fromImageId('sha256:' . str_repeat('4', 64), dirname(__DIR__));
        $fixture->images->activate($active);
        $fixture->clock->value++;
        $fixture->images->inspectOne($active, $inspector);
        $second = $fixture->seed(str_repeat('2', 32), catalogPng());
        $inspector->outcome = InspectionOutcome::ScannerError;
        $fixture->clock->value++;
        try { $fixture->images->inspectOne($active, $inspector); throw new LogicException('Scanner error succeeded.'); }
        catch (CatalogUnavailable) {}
        requireCatalogProof($fixture->record($second)?->state === 'scanner_error');
        $calls = $inspector->calls;
        $fixture->clock->value++;
        requireCatalogProof($fixture->images->inspectOne($active, $inspector) === 'empty' && $inspector->calls === $calls);
        try { $fixture->images->read($second, $owner); throw new LogicException('Scanner error served.'); }
        catch (CatalogNotFound) {}
        requireCatalogProof($fixture->images->retry($second, $active, $inspector) === 'retry_pending');
        try { $fixture->images->inspectOne($active, $inspector); throw new LogicException('Second scanner error succeeded.'); }
        catch (CatalogUnavailable) {}
        requireCatalogProof($fixture->record($second)?->attempts === 2);
        requireCatalogProof($fixture->images->retry($second, $active, $inspector) === 'ineligible');

        $crashed = $fixture->seed(str_repeat('3', 32), catalogPng(), 'inspecting', 1, str_repeat('a', 32));
        $inspector->cleanup = false;
        try { $fixture->images->reconcileOne($inspector); throw new LogicException('Unresolved container cleanup succeeded.'); }
        catch (CatalogUnavailable) {}
        requireCatalogProof($fixture->record($crashed)?->state === 'inspecting');
        $inspector->cleanup = true;
        requireCatalogProof($fixture->images->reconcileOne($inspector) === 'inspection_quarantined');
        requireCatalogProof($fixture->record($crashed)?->state === 'scanner_error');
        requireCatalogProof($fixture->images->retry($crashed, $active, $inspector) === 'retry_pending');
        $inspector->outcome = InspectionOutcome::Accepted;
        $fixture->clock->value++;
        requireCatalogProof($fixture->images->inspectOne($active, $inspector) === 'accepted');

        $reserved = $fixture->seed(str_repeat('4', 32), catalogPng(), 'reserved');
        file_put_contents($fixture->root->objectDirectory($reserved) . '/unexpected', 'not recursively removed');
        try { $fixture->images->reconcileOne($inspector); throw new LogicException('Partial cleanup released quota.'); }
        catch (CatalogUnavailable) {}
        requireCatalogProof($fixture->record($reserved)?->state === 'reserved');
        requireCatalogProof(is_file($fixture->root->objectDirectory($reserved) . '/unexpected'));
        unlink($fixture->root->objectDirectory($reserved) . '/unexpected');
        requireCatalogProof($fixture->images->reconcileOne($inspector) === 'reservation_removed');
        requireCatalogProof($fixture->record($reserved) === null);

        $tampered = $fixture->seed(str_repeat('5', 32), catalogPng());
        file_put_contents($fixture->root->contentPath($tampered), str_repeat('x', strlen(catalogPng())));
        $fixture->clock->value++;
        $calls = $inspector->calls;
        try { $fixture->images->inspectOne($active, $inspector); throw new LogicException('Changed bytes accepted.'); }
        catch (CatalogUnavailable) {}
        requireCatalogProof($inspector->calls === $calls && $fixture->record($tampered)?->state === 'scanner_error');
        file_put_contents($fixture->root->contentPath($crashed), str_repeat('y', strlen(catalogPng())));
        try { $fixture->images->read($crashed, $owner); throw new LogicException('Changed accepted bytes served.'); }
        catch (CatalogUnavailable) {}

        $cleanupFailure = $fixture->seed(str_repeat('6', 32), catalogPng());
        $inspector->cleanup = false;
        $fixture->clock->value++;
        try { $fixture->images->inspectOne($active, $inspector); throw new LogicException('Accepted before container cleanup.'); }
        catch (CatalogUnavailable) {}
        requireCatalogProof($fixture->record($cleanupFailure)?->state === 'scanner_error');
        $inspector->cleanup = true;

        $fixture->clock->value += 86_400;
        try { $fixture->images->read($crashed, $owner); throw new LogicException('Expired image served.'); }
        catch (CatalogNotFound) {}
        requireCatalogProof($fixture->images->expireOne($inspector) === 'expired');
        requireCatalogProof($fixture->record($first) === null && $fixture->record($second) !== null);
        requireCatalogProof(!file_exists($fixture->root->objectDirectory($first)));
    } finally {
        $fixture->cleanup();
    }

    // Definition changes must not overwrite the only handle to an unresolved container.
    $fixture = new Fixture(dirname(__DIR__, 3) . '/tmp/png-proof-' . bin2hex(random_bytes(8)));
    try {
        $pendingCleanup = $fixture->seed(str_repeat('7', 32), catalogPng(), 'scanner_error', 1, str_repeat('b', 32));
        $fixture->lock->acquire(true);
        $next = InspectionDefinition::fromImageId('sha256:' . str_repeat('2', 64), dirname(__DIR__));
        $fixture->images->activate($next);
        requireCatalogProof($fixture->images->inspectOne($next, $inspector) === 'empty');
        requireCatalogProof($fixture->record($pendingCleanup)?->token === str_repeat('b', 32));
        requireCatalogProof($fixture->images->reconcileOne($inspector) === 'inspection_quarantined');
        requireCatalogProof($fixture->images->inspectOne($next, $inspector) === 'accepted');
    } finally { $fixture->cleanup(); }
}
