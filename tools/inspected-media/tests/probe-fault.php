<?php

declare(strict_types=1);

// Deliberate tool replacement only in the four private test images.
$mode = file_get_contents('/probe-mode');
if ($mode === 'stdout') { fwrite(STDOUT, str_repeat('x', 65_537)); sleep(30); }
elseif ($mode === 'stderr') { fwrite(STDERR, str_repeat('x', 8193)); sleep(30); }
elseif ($mode === 'timeout') { sleep(30); }
exit(42);
