<?php
// Conf/config.php

// Ruta del .env
$env_path = '/var/www/.env';

// Cargar el archivo .env
if (file_exists($env_path)) {
    // parse_ini_file respeta comillas si usas INI_SCANNER_RAW
    $env_vars = parse_ini_file($env_path, false, INI_SCANNER_RAW);
    foreach ($env_vars as $key => $value) {
        putenv("$key=$value");
    }
} else {
    die("❌ No se encontró el archivo .env en $env_path");
}

// ================================
//  CONFIG LDAP
// ================================
$ldap_host    = getenv('LDAP_HOST');
$admin_dn     = getenv('LDAP_ADMIN_DN');
$admin_pass   = getenv('LDAP_ADMIN_PASS');
$base_users   = getenv('LDAP_BASE_USERS');
$base_groups  = getenv('LDAP_BASE_GROUPS');

// ================================
//  CONFIG BANCO (API TARJETAS)
// ================================

// Base URL de la API del banco (mock)
// Ejemplo: http://10.11.0.25:4000  o  http://localhost:4000
$bank_api_baseurl = getenv('BANK_API_BASEURL');

// TALDE (1–5). Tú eres el 1
$talde_id = getenv('TALDE_ID');

// Usuario de la API (en el mock suele ser user1)
$bank_api_user = getenv('BANK_API_USER');

// Clave AES del banco
$bank_aes_key = getenv('BANK_AES_KEY');

// Definimos constantes para uso cómodo en el código
if (!defined('BANK_API_BASEURL')) {
    define('BANK_API_BASEURL', $bank_api_baseurl);
}
if (!defined('TALDE_ID')) {
    define('TALDE_ID', (int)$talde_id);
}
if (!defined('BANK_API_USER')) {
    define('BANK_API_USER', $bank_api_user);
}
if ($bank_aes_key && !defined('BANK_AES_KEY')) {
    define('BANK_AES_KEY', $bank_aes_key);
}


// ================================
//  CONFIG DB (MariaDB / MySQL)
// ================================
$db_host = getenv('DB_HOST');   
$db_port = getenv('DB_PORT');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

/**
 * Conexión PDO reutilizable
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // IMPORTANTE: para MariaDB se sigue usando "mysql:"
    $dsn = 'mysql:host=' . $GLOBALS['db_host'] .
           ';port=' . $GLOBALS['db_port'] .
           ';dbname=' . $GLOBALS['db_name'] .
           ';charset=utf8mb4';

    $pdo = new PDO($dsn, $GLOBALS['db_user'], $GLOBALS['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

// config.php (versión corta y limpia)
define('WP_SSO_SECRET', getenv('WP_SSO_SECRET'));
define('WP_SSO_BASEURL', getenv('WP_SSO_BASEURL'));

