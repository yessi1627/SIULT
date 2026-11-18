<?php
declare(strict_types=1);

// Cargo las clases de la API (autoload de Composer) y las funciones puras de notas.
// Las pruebas unitarias no abren la base de datos: prueban la logica de forma aislada.
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/funciones_notas.php';
