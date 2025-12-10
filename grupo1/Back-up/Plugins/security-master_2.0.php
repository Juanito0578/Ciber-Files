<?php
/*
Plugin Name: Security Master
Description: Seguridad ligera para WordPress 4.7.2 con logs, dashboard y blacklist temporal.
Version: 2.2
Author:ChorizoSQL
*/

if (!defined('ABSPATH')) exit;

define('SM_DIR', plugin_dir_path(__FILE__));
define('SM_LOG_FAIL', SM_DIR . 'login-fail.txt');
define('SM_LOG_SUCCESS', SM_DIR . 'login-success.txt');

// ---------------------------------------------------------
// ACTIVACIÓN
// ---------------------------------------------------------

register_activation_hook(__FILE__, function() {
    if (!file_exists(SM_LOG_FAIL)) file_put_contents(SM_LOG_FAIL, "");
    if (!file_exists(SM_LOG_SUCCESS)) file_put_contents(SM_LOG_SUCCESS, "");
    if (!get_option('sm_block_minutes')) update_option('sm_block_minutes', 30);
    if (!get_option('sm_fail_limit')) update_option('sm_fail_limit', 5);
});

// ---------------------------------------------------------
// ADMIN MENU (Pestañas internas)
// ---------------------------------------------------------

add_action('admin_menu', function () {
    add_menu_page(
        'Security Master',
        'Security Master',
        'manage_options',
        'security-master',
        'sm_main_page',
        '',
        80
    );
});

// ---------------------------------------------------------
// PÁGINA PRINCIPAL CON TABS
// ---------------------------------------------------------

function sm_main_page() {

    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

    echo '<div class="wrap"><h1>Security Master</h1>';

    echo '<h2 class="nav-tab-wrapper">
            <a href="?page=security-master&tab=dashboard" class="nav-tab '.($tab=='dashboard'?'nav-tab-active':'').'">Dashboard</a>
            <a href="?page=security-master&tab=logs" class="nav-tab '.($tab=='logs'?'nav-tab-active':'').'">Logs</a>
            <a href="?page=security-master&tab=blacklist" class="nav-tab '.($tab=='blacklist'?'nav-tab-active':'').'">Blacklist</a>
            <a href="?page=security-master&tab=config" class="nav-tab '.($tab=='config'?'nav-tab-active':'').'">Config</a>
          </h2>';

    if ($tab == 'dashboard') sm_tab_dashboard();
    if ($tab == 'logs') sm_tab_logs();
    if ($tab == 'blacklist') sm_tab_blacklist();
    if ($tab == 'config') sm_tab_config();

    echo '</div>';
}

// ---------------------------------------------------------
// DASHBOARD – gráficos uno encima del otro
// ---------------------------------------------------------

function sm_tab_dashboard() {

    $failed = sm_get_frequency(SM_LOG_FAIL);
    $success = sm_get_frequency(SM_LOG_SUCCESS);
    ?>

    <style>
        .sm-graph-container {
            width: 100%;
            height: 350px;
            margin-bottom: 60px;
        }
        .sm-graph-title {
            font-size: 20px;
            margin-bottom: 8px;
            font-weight: bold;
        }
    </style>

    <div class="sm-graph-container">
        <div class="sm-graph-title">Fallidos</div>
        <canvas id="sm_chart_fail"></canvas>
    </div>

    <div class="sm-graph-container">
        <div class="sm-graph-title">Exitosos</div>
        <canvas id="sm_chart_success"></canvas>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        new Chart(document.getElementById('sm_chart_fail'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($failed)) ?>,
                datasets: [{
                    label: 'Fallidos',
                    data: <?= json_encode(array_values($failed)) ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        new Chart(document.getElementById('sm_chart_success'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($success)) ?>,
                datasets: [{
                    label: 'Exitosos',
                    data: <?= json_encode(array_values($success)) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>

    <?php
}

// ---------------------------------------------------------
// LOGS
// ---------------------------------------------------------

function sm_tab_logs() {

    if (isset($_POST['sm_clear_fail'])) file_put_contents(SM_LOG_FAIL, "");
    if (isset($_POST['sm_clear_success'])) file_put_contents(SM_LOG_SUCCESS, "");

    $fails = file_get_contents(SM_LOG_FAIL);
    $success = file_get_contents(SM_LOG_SUCCESS);

    // Obtener zona horaria local
    $timezone = new DateTimeZone(date_default_timezone_get());

    // Convertir logs a zona horaria local
    $fails = sm_convert_log_to_local($fails, $timezone);
    $success = sm_convert_log_to_local($success, $timezone);

    ?>

    <h2>Intentos Fallidos</h2>
    <form method="post"><button name="sm_clear_fail" class="button">Borrar</button></form>
    <textarea style="width:100%;height:250px;"><?= htmlspecialchars($fails) ?></textarea>

    <h2>Intentos Exitosos</h2>
    <form method="post"><button name="sm_clear_success" class="button">Borrar</button></form>
    <textarea style="width:100%;height:250px;"><?= htmlspecialchars($success) ?></textarea>

    <?php
}

// ---------------------------------------------------------
// Convertir timestamps de logs a hora local
// ---------------------------------------------------------

function sm_convert_log_to_local($log, $timezone) {
    $lines = explode("\n", $log);
    foreach ($lines as &$line) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
            $dt = new DateTime($matches[1], new DateTimeZone('UTC'));
            $dt->setTimezone($timezone);
            $line = str_replace($matches[1], $dt->format('Y-m-d H:i:s'), $line);
        }
    }
    return implode("\n", $lines);
}

// ---------------------------------------------------------
// BLACKLIST – bloqueo temporal + manual
// ---------------------------------------------------------

function sm_tab_blacklist() {

    $list = get_option('sm_blacklist', []);
    $blocked_until = get_option('sm_block_until', []);

    // desbloqueo
    if (isset($_POST['sm_unblock_ip'])) {
        $ip = $_POST['sm_unblock_ip'];
        unset($list[$ip]);
        unset($blocked_until[$ip]);
        update_option('sm_blacklist', $list);
        update_option('sm_block_until', $blocked_until);
        echo '<div class="updated"><p>IP desbloqueada.</p></div>';
    }

    // bloqueo manual
    if (isset($_POST['sm_block_ip'])) {
        $ip = sanitize_text_field($_POST['sm_block_ip']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $block_minutes = get_option('sm_block_minutes', 30);
            $until = time() + ($block_minutes * 60);
            $list[$ip] = $until;
            update_option('sm_blacklist', $list);
            echo '<div class="updated"><p>IP agregada a la blacklist temporal.</p></div>';
        } else {
            echo '<div class="error"><p>IP no válida.</p></div>';
        }
    }

    ?>

    <h2>Agregar IP manualmente</h2>
    <form method="post">
        <input type="text" name="sm_block_ip" placeholder="Ej: 123.123.123.123">
        <button class="button button-primary">Bloquear</button>
    </form>

    <h2>IPs Bloqueadas Temporalmente</h2>

    <table class="widefat">
        <thead><tr><th>IP</th><th>Bloqueada hasta</th><th>Acción</th></tr></thead>
        <tbody>

        <?php
        $timezone = new DateTimeZone(date_default_timezone_get());
        foreach ($list as $ip => $timestamp): 
            $dt = new DateTime();
            $dt->setTimestamp($timestamp);
            $dt->setTimezone($timezone);
        ?>
            <tr>
                <td><?= $ip ?></td>
                <td><?= $dt->format("Y-m-d H:i") ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="sm_unblock_ip" value="<?= $ip ?>">
                        <button class="button">Desbloquear</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>

    <?php
}

// ---------------------------------------------------------
// CONFIG – intentos + minutos bloqueo
// ---------------------------------------------------------

function sm_tab_config() {

    if (isset($_POST['sm_save_config'])) {
        update_option('sm_fail_limit', intval($_POST['sm_fail_limit']));
        update_option('sm_block_minutes', intval($_POST['sm_block_minutes']));
        echo '<div class="updated"><p>Guardado.</p></div>';
    }

    $limit = get_option('sm_fail_limit');
    $mins = get_option('sm_block_minutes');

    ?>

    <h2>Configuración</h2>

    <form method="post">
        <label>Intentos fallidos antes de bloquear:</label><br>
        <input type="number" name="sm_fail_limit" value="<?= $limit ?>"><br><br>

        <label>Minutos de bloqueo por IP:</label><br>
        <input type="number" name="sm_block_minutes" value="<?= $mins ?>"><br><br>

        <button class="button button-primary" name="sm_save_config">Guardar</button>
    </form>

    <?php
}

// ---------------------------------------------------------
// LOGS AUTOMÁTICOS (Fallidos / Exitosos)
// ---------------------------------------------------------

add_action('wp_login_failed', function($user) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $t = gmdate("Y-m-d H:i:s"); // UTC

    file_put_contents(SM_LOG_FAIL, "$t - FAIL - $user - IP: $ip\n", FILE_APPEND);

    sm_add_fail($ip);
});

add_action('wp_login', function($user_login, $user) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $t = gmdate("Y-m-d H:i:s"); // UTC

    file_put_contents(SM_LOG_SUCCESS, "$t - SUCCESS - $user_login - IP: $ip\n", FILE_APPEND);

}, 10, 2);

// ---------------------------------------------------------
// FALLIDOS → BLOQUEO TEMPORAL
// ---------------------------------------------------------

function sm_add_fail($ip) {

    $fails = get_option('sm_fails', []);
    $limit = get_option('sm_fail_limit');
    $block_minutes = get_option('sm_block_minutes');

    if (!isset($fails[$ip])) $fails[$ip] = 0;

    $fails[$ip]++;

    if ($fails[$ip] >= $limit) {

        $until = time() + ($block_minutes * 60);

        $black = get_option('sm_blacklist', []);
        $black[$ip] = $until;

        update_option('sm_blacklist', $black);

        $fails[$ip] = 0;
    }

    update_option('sm_fails', $fails);
}

// ---------------------------------------------------------
// BLOQUEO EN INIT
// ---------------------------------------------------------

add_action('init', function() {
    $ip = $_SERVER['REMOTE_ADDR'];

    $list = get_option('sm_blacklist', []);

    if (isset($list[$ip])) {

        if (time() < $list[$ip]) {
            header("HTTP/1.1 403 Forbidden");
            exit("Access denied.");
        } else {
            $black = get_option('sm_blacklist', []);
            unset($black[$ip]);
            update_option('sm_blacklist', $black);
        }
    }
});

// ---------------------------------------------------------
// FUNCIÓN PARA OBTENER FRECUENCIA DE LOGS
// ---------------------------------------------------------

function sm_get_frequency($file) {
    if (!file_exists($file)) return [];
    $lines = file($file);
    $count = [];

    foreach ($lines as $line) {
        if (preg_match('/IP:\s*(.+)$/', $line, $m)) {
            $ip = trim($m[1]);
            if (!isset($count[$ip])) $count[$ip] = 0;
            $count[$ip]++;
        }
    }
    return $count;
}

// ---------------------------------------------------------
// PARCHE DE SEGURIDAD + INTEGRACIÓN CON BLACKLIST
// ---------------------------------------------------------

function sm_security_block_ip($ip) {
    $block_minutes = get_option('sm_block_minutes', 30);
    $until = time() + ($block_minutes * 60);

    $black = get_option('sm_blacklist', []);
    $black[$ip] = $until;
    update_option('sm_blacklist', $black);

    $fails = get_option('sm_fails', []);
    $fails[$ip] = 0;
    update_option('sm_fails', $fails);
}

// Bloquear REST API Content Injection
add_filter('rest_endpoints', function($endpoints) {
    if (isset($endpoints['/wp/v2/posts/(?P<id>[\d]+)'])) {
        unset($endpoints['/wp/v2/posts/(?P<id>[\d]+)']);
    }
    if (isset($endpoints['/wp/v2/pages/(?P<id>[\d]+)'])) {
        unset($endpoints['/wp/v2/pages/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

// Refuerzo de cabeceras de seguridad
add_action('send_headers', function() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
});

// Prevención de ataques REST sospechosos
add_action('init', function() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_REQUEST['rest_route'])) {
        $route = sanitize_text_field($_REQUEST['rest_route']);
        if (preg_match('/(\.\.|<|>|\%3C|\%3E)/', $route)) {
            sm_security_block_ip($ip);

            header("HTTP/1.1 403 Forbidden");
            exit("REST API blocked for security. Your IP has been temporarily blocked.");
        }
    }
});

// Prevención de CSRF en formularios de admin
add_action('admin_init', function() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        $referer = wp_get_referer();
        if ($referer && strpos($referer, admin_url()) !== 0) {
            sm_security_block_ip($ip);

            wp_die('Acción denegada por motivos de seguridad. Tu IP ha sido bloqueada temporalmente.');
        }
    }
});

// Prevención de queries sospechosas
add_action('parse_request', function($wp) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($_GET as $key => $value) {
        if (preg_match('/(\.\.|<|>)/', $value)) {
            sm_security_block_ip($ip);

            header("HTTP/1.1 403 Forbidden");
            exit("Malicious request blocked. Your IP has been temporarily blocked.");
        }
    }
});
