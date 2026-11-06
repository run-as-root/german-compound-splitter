<?php

declare(strict_types=1);

// Standalone bootstrap: lets the package tests run without the package being installed via Composer.
spl_autoload_register(static function (string $class): void {
    $map = [
        'RunAsRoot\\GermanCompoundSplitter\\Test\\' => __DIR__ . '/',
        'RunAsRoot\\GermanCompoundSplitter\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
