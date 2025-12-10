<?php
session_start();
require_once '../../../sso/sso-check.php';
require_once '../../../Conf/config.php';

// Asegurarnos de que tenemos todo
if (!isset($_SESSION['username'])) {
    // sin sesión SSO → al login
    header('Location: /sso/index.php');
    exit;
}

$username = $_SESSION['username'];
$email    = $_SESSION['email']  ?? '';
$groups   = $_SESSION['groups'] ?? [];

// ⚠️ Contraseña en texto plano guardada en el login LDAP
$password = $_SESSION['ldap_plain_password'] ?? '';

if (!defined('WP_SSO_SECRET') || WP_SSO_SECRET === '') {
    die('WP SSO secret not configured. Define WP_SSO_SECRET in config.php');
}
if (!defined('WP_SSO_BASEURL') || WP_SSO_BASEURL === '') {
    die('WP SSO base URL not configured. Define WP_SSO_BASEURL in config.php');
}

// 1) Construimos el payload
$payload = [
    'u' => $username,
    'e' => $email,
    'g' => $groups,
    't' => time(),   // timestamp para evitar replays
    'p' => $password // ✅ añadimos la contraseña al payload
];

// 2) Lo serializamos y codificamos
$json  = json_encode($payload, JSON_UNESCAPED_UNICODE);
$token = base64_encode($json);

// 3) Firmamos el token con HMAC-SHA256
$rawSig = hash_hmac('sha256', $token, WP_SSO_SECRET, true);

// 4) Pasamos la firma a base64 url-safe
$sig = rtrim(strtr(base64_encode($rawSig), '+/', '-_'), '=');

// 5) Construimos la URL de destino en WordPress
$wpLoginUrl = rtrim(WP_SSO_BASEURL, '/') . '/wp-login.php';

$query = http_build_query([
    'chorizosql_sso' => 1,
    'token'          => $token,
    'sig'            => $sig,
], '', '&', PHP_QUERY_RFC3986);

$redirectUrl = $wpLoginUrl . '?' . $query;

// 6) Redirigimos
header('Location: ' . $redirectUrl);
exit;
