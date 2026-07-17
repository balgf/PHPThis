<?php

declare(strict_types=1);

$prefixes = [
    'PHPThis\\' => __DIR__ . '/src/',
    'Example\\' => __DIR__ . '/example/src/',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $directory . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});
