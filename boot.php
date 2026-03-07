<?php

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

spl_autoload_register(function (string $classe): void {
    if (str_contains($classe, DIRECTORY_SEPARATOR) || str_contains($classe, '/')) {
        return;
    }
    $fichier = __DIR__ . '/src/' . $classe . '.php';
    if (file_exists($fichier)) {
        require_once $fichier;
    }
});
