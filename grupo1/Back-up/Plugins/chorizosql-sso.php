<?php
/**
 * Plugin Name: ChorizoSQL SSO
 * Description: Simple SSO from external ChorizoSQL app. Creates user if not exists and logs them in.
 * Version: 2.0.0
 * Author: ChorizoSQL
 */

if (!defined('ABSPATH')) {
    exit; // No direct access
}


$chorizo_sso_vendor = __DIR__ . '/vendor/autoload.php';

if (file_exists($chorizo_sso_vendor)) {
    require_once $chorizo_sso_vendor;

    if (class_exists(\Dotenv\Dotenv::class)) {
        // Carga variables desde .env del plugin
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad(); // no peta si no existe .env
    }
} else {
    // Si no hay vendor, log para que puedas verlo en debug.log
    if (function_exists('error_log')) {
        error_log('[ChorizoSQL SSO] vendor/autoload.php not found inside plugin.');
    }
}

/*
|--------------------------------------------------------------------------
| Constante del secreto (sale de .env)
|--------------------------------------------------------------------------
|
| Si quieres, puedes sobreescribirla en wp-config.php con:
| define('CHORIZOSQL_SSO_SECRET', 'otro_secreto');
|
*/
if (!defined('CHORIZOSQL_SSO_SECRET')) {
    // Primero mira $_ENV, luego $_SERVER
    $secret = $_ENV['CHORIZOSQL_SSO_SECRET'] ?? ($_SERVER['CHORIZOSQL_SSO_SECRET'] ?? '');
    define('CHORIZOSQL_SSO_SECRET', $secret);
}

/**
 * Valida token + firma HMAC que vienen desde tu app
 */
function chorizosql_sso_verify_token(string $token, string $sig) {
    if (CHORIZOSQL_SSO_SECRET === '') {
        return [false, 'Secret not configured'];
    }

    // Logging de depuración
    error_log("[ChorizoSQL SSO] Token recibido: $token");
    error_log("[ChorizoSQL SSO] Firma enviada: $sig");

    // HMAC esperado
    $rawExpected = hash_hmac('sha256', $token, CHORIZOSQL_SSO_SECRET, true);
    $expectedB64 = rtrim(strtr(base64_encode($rawExpected), '+/', '-_'), '=');

    error_log("[ChorizoSQL SSO] Firma esperada: $expectedB64");

    if (!hash_equals($expectedB64, $sig)) {
        return [false, 'Invalid signature'];
    }

    $json = base64_decode($token, true);
    if ($json === false) {
        return [false, 'Invalid base64 token'];
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [false, 'Invalid JSON token'];
    }

    // Expiración de 5 minutos
    $now = time();
    $t   = isset($data['t']) ? (int)$data['t'] : 0;
    if ($t <= 0 || abs($now - $t) > 300) {
        return [false, 'Token expired'];
    }

    return [true, $data];
}

/**
 * Hook en init para procesar SSO
 * URL típica desde tu app:
 *   https://tusitio/wp-login.php?chorizosql_sso=1&token=XXX&sig=YYY
 */
function chorizosql_sso_maybe_login() {
    if (!isset($_GET['chorizosql_sso'])) {
        return;
    }

    $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
    $sig   = isset($_GET['sig'])   ? (string)$_GET['sig']   : '';

    if ($token === '' || $sig === '') {
        wp_die('Missing SSO parameters.', 'ChorizoSQL SSO');
    }

    list($ok, $info) = chorizosql_sso_verify_token($token, $sig);
    if (!$ok) {
        wp_die('SSO error: ' . esc_html($info), 'ChorizoSQL SSO');
    }

    // Datos del token
    $username = isset($info['u']) ? sanitize_user($info['u'], true) : '';
    $email    = isset($info['e']) ? sanitize_email($info['e'])      : '';
    $groups   = isset($info['g']) && is_array($info['g']) ? $info['g'] : [];

    // ✅ Contraseña que viene desde tu app (LDAP)
    $password = isset($info['p']) ? (string)$info['p'] : '';

    if ($username === '') {
        wp_die('SSO error: empty username.', 'ChorizoSQL SSO');
    }

    // ¿Existe usuario?
    $user = get_user_by('login', $username);
    if (!$user && $email !== '') {
        $user = get_user_by('email', $email);
    }

    if (!$user) {
        // No existe → creamos usuario
        if ($email === '') {
            $email = $username . '@chorizosql.local';
        }

        // Si tenemos contraseña en el token, la usamos, si no generamos random
        $pass_to_set = ($password !== '') ? $password : wp_generate_password(24, true, true);

        $user_id = wp_create_user($username, $pass_to_set, $email);
        if (is_wp_error($user_id)) {
            wp_die('Cannot create WP user: ' . esc_html($user_id->get_error_message()), 'ChorizoSQL SSO');
        }

        // Mapeo simple de grupos LDAP → role de WP
        // 🔹 Admins → administrator
        // 🔹 Resto → editor
        $role = 'editor';
        if (!empty($groups)) {
            $lower = array_map('strtolower', $groups);
            if (in_array('admins', $lower, true) || in_array('administrators', $lower, true)) {
                $role = 'administrator';
            }
        }

        $user = get_user_by('id', $user_id);
        if ($user && !is_wp_error($user)) {
            $user->set_role($role);
        }

    } else {
        // Usuario ya existe en WP → si nos llega contraseña, la sincronizamos
        if ($password !== '') {
            wp_set_password($password, $user->ID);
        }

        // (Opcional) Si quieres actualizar el rol también cuando ya existe:
        if (!empty($groups)) {
            $lower = array_map('strtolower', $groups);
            if (in_array('admins', $lower, true) || in_array('administrators', $lower, true)) {
                $user->set_role('administrator');
            } else {
                $user->set_role('editor');
            }
        }
    }

    // Loguear al usuario en WordPress
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    // Redirección después del SSO
    $redirect = admin_url(); // o home_url('/')
    wp_redirect($redirect);
    exit;
}

add_action('init', 'chorizosql_sso_maybe_login');
