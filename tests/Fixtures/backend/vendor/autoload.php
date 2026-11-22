<?php

spl_autoload_register(static function (string $class): void {
    $path = __DIR__.'/src/'.str_replace('\\', '/', $class).'.php';
    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__.'/src/Illuminate/Collections/helpers.php';
