<?php
// projects/router.php

// 1) Ruta interna pedida: viene desde ?p=...
$path = $_GET['p'] ?? '';

// Si no viene nada (has entrado directamente al hash sin ?p=)
// usamos index.php por defecto dentro de /projects
if ($path === '' || $path === null) {
    $path = 'index.php';
}

// 2) Normalizamos: quitamos / inicial y cosas raras
$path = trim($path, '/');
$path = str_replace(['..', '\\'], '', $path);

// 3) Construimos ruta física dentro de /projects
$baseDir = realpath(__DIR__); 
$fullPath = realpath($baseDir . '/' . $path);

// Verificación de seguridad: debe estar dentro de /projects
if ($fullPath === false || strpos($fullPath, $baseDir) !== 0) {
    http_response_code(404);
    echo "Not allowed: " . htmlspecialchars($path);
    exit;
}

// Si es un directorio, asumimos index.php
if (is_dir($fullPath)) {
    $fullPath = rtrim($fullPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo "Index not found in directory: " . htmlspecialchars($path);
        exit;
    }
}

// 4) Si no existe el archivo -> 404 (NO 500)
if (!file_exists($fullPath)) {
    http_response_code(404);
    echo "Not found: " . htmlspecialchars($path);
    exit;
}

// 5) MUY IMPORTANTE: cambiamos el directorio actual a la carpeta del script
//    para que los include/require relativos dentro de ese script sigan funcionando
$scriptDir = dirname($fullPath);
chdir($scriptDir);

// 6) Incluimos el script real
include $fullPath;
exit;
