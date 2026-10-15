<?php

declare(strict_types=1);

require __DIR__ . '/inspected-media/bootstrap.php';
require __DIR__ . '/inspected-media/tests/parser.php';
require __DIR__ . '/inspected-media/tests/lifecycle.php';
require __DIR__ . '/inspected-media/tests/contracts.php';

verifyPngParser();
verifyCatalogLifecycle();
verifyCatalogContracts();
fwrite(STDOUT, "PASS inspected PNG parser and catalog lifecycle (local reference)\n");
