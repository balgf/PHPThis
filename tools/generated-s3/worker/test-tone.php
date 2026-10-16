<?php

declare(strict_types=1);

// Private fixed producer: no application bootstrap, credentials, input, filesystem writes or descendants.
if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80401 || PHP_VERSION_ID >= 80500 || $argc !== 1
    || ini_get('memory_limit') !== '16M' || ini_get('max_execution_time') !== '2'
    || php_ini_loaded_file() !== false || php_ini_scanned_files() !== false) { exit(1); }
$cycle = pack('v*', 0, 1567, 2896, 3784, 4096, 3784, 2896, 1567,
    0, 63969, 62640, 61752, 61440, 61752, 62640, 63969);
$bytes = 'RIFF' . pack('V', 32036) . 'WAVEfmt '
    . pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16)
    . 'data' . pack('V', 32000) . str_repeat($cycle, 1000);
if (fwrite(STDOUT, $bytes) !== 32_044) { exit(1); }
