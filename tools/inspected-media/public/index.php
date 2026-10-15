<?php

declare(strict_types=1);

use CatalogImageRecipe\CatalogHttp;
use CatalogImageRecipe\CatalogLock;
use CatalogImageRecipe\DenyCatalogAccess;
use CatalogImageRecipe\ReferenceEnvironment;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseEmitter;
use PHPThis\Http\ResponseEmissionFailed;

umask(0077);
$lock = null;
try {
    require dirname(__DIR__) . '/bootstrap.php';
    $deny = new DenyCatalogAccess();
    $configuration = ReferenceEnvironment::http();
    $lock = new CatalogLock($configuration->root);
    $coordinator = new CatalogHttp($configuration, $deny, $deny, $deny, $lock);
    $response = $coordinator->handle($_SERVER, $_GET);
    try { (new ResponseEmitter())->emit($response); }
    catch (ResponseEmissionFailed $failure) {
        if (!$failure->responseStarted) {
            (new ResponseEmitter())->emit(new Response(500, ['Cache-Control' => 'private, no-store'], "Request failed\n"));
        }
    }
} catch (Throwable) {
    if (!headers_sent() && array_sum(array_column(ob_get_status(true), 'buffer_used')) === 0) {
        http_response_code(500);
        header('Cache-Control: private, no-store');
        echo "Request failed\n";
    }
} finally {
    // Keep the shared serving lock through the entire emitter, including failure.
    if ($lock instanceof CatalogLock) { $lock->release(); }
}
