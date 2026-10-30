<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Carrega as classes de apoio dos testes sob demanda.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Elavora\\Api\\Extension\\DatabasePdo\\Tests\\Support\\';

    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/Support/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});
