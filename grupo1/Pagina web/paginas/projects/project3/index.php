<?php
session_start();
require_once '../../../sso/sso-check.php';
require_once '../../../Conf/config.php';

$pdo = db();

/* ========================================= ACCESS CONTROL BY ROLES (LDAP) ========================================= */
$groups = [];
if (!empty($_SESSION['groups']) && is_array($_SESSION['groups'])) {
    $groups = array_map('mb_strtolower', $_SESSION['groups']);
}

$hasGroup = function (array $needles) use ($groups) {
    foreach ($needles as $g) {
        if (in_array(mb_strtolower($g), $groups, true)) {
            return true;
        }
    }
    return false;
};

$isAdmin = $hasGroup(['admins', 'administrators']);

if (!$isAdmin) {
    http_response_code(403);
    header("Location: /paginas/projects/project3/pago.php");
    exit;
}

$displayUser = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'MKDEH USER';

/* ===================================== DELETE SCAN (?delete_scan=ID) ===================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_scan'])) {
    $scanId = (int)$_GET['delete_scan'];

    if ($scanId > 0) {
        try {
            // Comprobar que existe
            $stmt = $pdo->prepare("SELECT id FROM scans WHERE id = ?");
            $stmt->execute([$scanId]);
            $scan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$scan) {
                $_SESSION['error_message'] = "Scan #{$scanId} not found.";
            } else {
                // Si era el seleccionado, limpiarlo de la sesión
                if (isset($_SESSION['selected_scan_id']) && (int)$_SESSION['selected_scan_id'] === $scanId) {
                    unset($_SESSION['selected_scan_id']);
                }

                // ON DELETE CASCADE borrará también sus services
                $pdo->beginTransaction();
                $del = $pdo->prepare("DELETE FROM scans WHERE id = ?");
                $del->execute([$scanId]);
                $pdo->commit();

                $_SESSION['success_message'] = "Scan #{$scanId} deleted successfully.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error_message'] = "Error deleting scan #{$scanId}: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid scan id.";
    }

    header("Location: ?p=project3/index.php");
    exit;
}

/* ===================================== CLEAR VIEW ===================================== */
if (isset($_GET['clear_view'])) {
    // -1 = ver todos los servicios (sin filtrar por scan)
    $_SESSION['selected_scan_id'] = -1;
    header("Location: ?p=project3/index.php");
    exit;
}

/* ===================================== MESSAGES FROM OPERATIONS ===================================== */
$success_message = $_SESSION['success_message'] ?? null;
$error_message   = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

/* ===================================== FLASH MESSAGE (scan feedback) ===================================== */
$scan_feedback      = $_SESSION['scan_feedback'] ?? null;
$scan_feedback_type = $_SESSION['scan_feedback_type'] ?? 'info';
unset($_SESSION['scan_feedback'], $_SESSION['scan_feedback_type']);

/* ===================================== STOP SCAN (botón Stop explícito) ===================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stop_scan'])) {
    $raspberryApiUrlStop = 'http://10.11.0.152:5000/api/scan/stop';
    $ch = curl_init($raspberryApiUrlStop);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr) {
        $scan_feedback      = "Error while contacting the Raspberry API to stop scan: " . htmlspecialchars($curlErr);
        $scan_feedback_type = 'error';
    } else {
        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && is_array($json)) {
            $status = $json['status'] ?? 'ok';
            $msg    = $json['message'] ?? 'Scan stopped.';
            if ($status === 'error') {
                $scan_feedback      = "Error while stopping scan: " . htmlspecialchars($msg);
                $scan_feedback_type = 'error';
            } else {
                $scan_feedback      = "Scan stop requested successfully.";
                $scan_feedback_type = 'success';
            }
        } else {
            $scan_feedback      = "The stop API returned HTTP $httpCode. Response: " . htmlspecialchars($response);
            $scan_feedback_type = 'error';
        }
    }
    $_SESSION['scan_feedback']      = $scan_feedback;
    $_SESSION['scan_feedback_type'] = $scan_feedback_type;

    header('Location: ?p=project3/index.php');
    exit;
}

/* ===================================== LAUNCH SCAN (con auto-stop previo) ===================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_scan'])) {
    // 0) Intentar parar cualquier scan previo (best effort)
    try {
        $chStop = curl_init('http://10.11.0.152:5000/api/scan/stop');
        curl_setopt_array($chStop, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_TIMEOUT        => 2,
        ]);
        $respStop = curl_exec($chStop);
        curl_close($chStop);
    } catch (Throwable $e) {
        // Si falla el stop, seguimos igualmente
    }

    $target    = trim($_POST['target'] ?? '');
    $intensity = $_POST['intensity'] ?? 'normal'; // low | normal | high
    $scan_feedback      = null;
    $scan_feedback_type = 'info';

    // Basic validation
    if ($target === '') {
        $scan_feedback      = "You must provide at least one target: single IP, multiple IPs, range or subnet (e.g. 10.11.0.15,10.11.0.16 or 10.11.0.0/24).";
        $scan_feedback_type = 'error';
    }

    // Ports: single field: empty/all or 22,80-100,443
    $portsExpr = trim($_POST['ports_expr'] ?? '');
    if ($portsExpr === '' || strtolower($portsExpr) === 'all') {
        $ports = 'all';
    } else {
        if (!preg_match('/^[0-9,\-\s]+$/', $portsExpr)) {
            $scan_feedback      = "Invalid ports format. Use 'all' or numbers, commas and dashes (e.g. 22,80-100,443).";
            $scan_feedback_type = 'error';
        } else {
            $ports = $portsExpr;
        }
    }

    if ($scan_feedback === null) {
        if (!in_array($intensity, ['low', 'normal', 'high'], true)) {
            $intensity = 'normal';
        }

        // 1) Build payload for Raspberry API
        $payload = [
            'target'    => $target,
            'ports'     => $ports,
            'intensity' => $intensity,
        ];

        // 2) HTTP call to Raspberry
        $raspberryApiUrl = 'http://10.11.0.152:5000/api/scan';
        $ch = curl_init($raspberryApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlErr) {
            $scan_feedback      = "Error while connecting to the Raspberry API: " . htmlspecialchars($curlErr);
            $scan_feedback_type = 'error';
        } else {
            $json = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300 && is_array($json)) {
                $status = $json['status'] ?? 'ok';
                $msg    = $json['message'] ?? 'Scan launched successfully.';
                if ($status === 'error') {
                    $scan_feedback      = "Error: " . htmlspecialchars($msg);
                    $scan_feedback_type = 'error';
                } else {
                    $scan_feedback      = "OK: " . htmlspecialchars($msg);
                    $scan_feedback_type = 'success';
                    $_SESSION['scan_force_running_once'] = true;
                }
            } else {
                $scan_feedback      = "The API returned HTTP error $httpCode. Response: " . htmlspecialchars($response);
                $scan_feedback_type = 'error';
            }
        }
    }

    $_SESSION['scan_feedback']      = $scan_feedback;
    $_SESSION['scan_feedback_type'] = $scan_feedback_type;

    // 0 = auto-seleccionar el último scan disponible en el siguiente GET
    $_SESSION['selected_scan_id'] = 0;

    header('Location: ?p=project3/index.php');
    exit;
}

/* ===================================== SCAN HISTORY ===================================== */
try {
    $stmtHistory = $pdo->prepare("
        SELECT id, started_at, finished_at, network, notes 
        FROM scans 
        WHERE deleted_at IS NULL 
        ORDER BY started_at DESC 
        LIMIT 10
    ");
    $stmtHistory->execute();
    $scanHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $scanHistory = [];
    error_log("Error fetching scan history: " . $e->getMessage());
}

/* ===================================== VIEW SPECIFIC SCAN (AUTO-LAST LOGIC) ===================================== */
$selectedScanId = null;

// Caso 1: clic en "View"
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['view_scan'])) {

    $selectedScanId = (int)$_GET['view_scan'];
    $_SESSION['selected_scan_id'] = $selectedScanId;

// Caso 2: ya hay algo en sesión
} elseif (isset($_SESSION['selected_scan_id'])) {

    $sessVal = (int)$_SESSION['selected_scan_id'];

    if ($sessVal > 0) {
        $selectedScanId = $sessVal;

    } elseif ($sessVal === 0) {
        // Auto-seleccionar el último
        if (!empty($scanHistory)) {
            $selectedScanId = (int)$scanHistory[0]['id'];
        }

    } else {
        // -1 = ver todos
        $selectedScanId = null;
    }

// Caso 3: primera vez → último scan
} else {

    if (!empty($scanHistory)) {
        $selectedScanId = (int)$scanHistory[0]['id'];
        $_SESSION['selected_scan_id'] = $selectedScanId;
    }
}

/* ===================================== SCAN STATUS + PROGRESS ===================================== */
$scan_in_progress  = false;
$scan_status_state = 'idle';
$total_hosts       = 0;
$scanned_hosts     = 0;
$current_host      = null;

try {
    $ch = curl_init('http://10.11.0.152:5000/api/scan/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2,
    ]);
    $statusRaw = curl_exec($ch);
    $curlErr   = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusRaw !== false && !$curlErr && $httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($statusRaw, true);
        if (is_array($data)) {
            $scan_status_state = $data['state'] ?? ($data['status'] ?? 'idle');
            $total_hosts       = (int)($data['total_hosts'] ?? 0);
            $scanned_hosts     = (int)($data['scanned_hosts'] ?? 0);
            $current_host      = $data['current_host'] ?? null;
            if ($scan_status_state === 'running' || $scan_status_state === 'discovering') {
                $scan_in_progress = true;
            }
        }
    }
} catch (Throwable $e) {
    // valores por defecto
}

/* Fallback primera carga tras START */
if (!empty($_SESSION['scan_force_running_once'])) {
    $scan_in_progress  = true;
    $scan_status_state = 'running';
    unset($_SESSION['scan_force_running_once']);
}

/* ===================================== QUERY VULNERABILITY RESULTS ===================================== */
$params  = [];
$sqlBase = " FROM services ";
$sqlList = " SELECT ip, port, service_name, product, version, cve_id, severity $sqlBase ORDER BY ip ASC, port ASC ";
$sqlCount = " SELECT COUNT(*) $sqlBase ";

if ($selectedScanId) {
    $sqlBase  = " FROM services WHERE scan_id = ? ";
    $sqlList  = " SELECT ip, port, service_name, product, version, cve_id, severity $sqlBase ORDER BY ip ASC, port ASC ";
    $sqlCount = " SELECT COUNT(*) $sqlBase ";
    $params[] = $selectedScanId;
}

$stmt = $pdo->prepare($sqlList);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalServices = (int)($stmtCount->fetchColumn() ?: 0);

$has_results = $totalServices > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ChorizoSQL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Global CSS -->
    <link rel="stylesheet" href="/css/style.css">
    <!-- Optional icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        .vuln-section {
            width: 100%;
            padding: 7rem 5% 4rem;
            display: flex;
            justify-content: center;
            align-items: stretch;
            position: relative;
            z-index: 1;
            flex: 1 0 auto;
        }
        .vuln-container {
            width: 100%;
            max-width: 1400px;
            background: rgba(26, 26, 46, 0.9);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 45px rgba(0,0,0,0.6);
            padding: 2.5rem 2.2rem;
        }
        .main-content-wrapper {
            display: flex;
            gap: 2rem;
        }
        .left-content {
            flex: 1;
            min-width: 0;
        }
        .right-sidebar {
            width: 380px;
            flex-shrink: 0;
        }
        .vuln-header {
            margin-bottom: 1.5rem;
        }
        .vuln-title {
            font-size: 1.8rem;
            color: var(--light);
            margin-bottom: 0.3rem;
        }
        .vuln-subtitle {
            font-size: 0.95rem;
            color: rgba(245,245,247,0.8);
        }

        /* --- CARD FORM --- */
        .scan-form {
            margin-bottom: 1.5rem;
            padding: 1.4rem 1.4rem;
            border-radius: 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .scan-form h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--light);
        }

        .scan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem 1.3rem;
        }

        .scan-form .form-group {
            text-align: left;
            min-width: 0;
        }
        .scan-form label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--light);
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

            .scan-form select option {
                color: #000 !important;
                background: #fff !important;
            }

        .scan-form input,
        .scan-form select {
            width: 100%;
            box-sizing: border-box;
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.18);
            background-color: rgba(255,255,255,0.05);
            color: var(--light);
            font-size: 0.86rem;
            transition: var(--transition);
        }
        .scan-form input:focus,
        .scan-form select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: rgba(255,255,255,0.09);
        }

        .scan-form input::placeholder {
            color: rgba(245,245,247,0.6);
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .scan-actions {
            margin-top: 1.1rem;
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }
        .scan-actions button {
            padding: 0.6rem 1.4rem;
            border-radius: 999px;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--primary);
            color: var(--light);
            transition: var(--transition);
            white-space: nowrap;
        }
        .scan-actions button:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }

        .vuln-total {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            color: var(--light);
        }
        .vuln-total strong {
            color: var(--primary);
        }

        .vuln-table-wrapper {
            margin-top: 1rem;
            overflow-x: auto;
        }
        .vuln-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .vuln-table thead {
            background: rgba(255,255,255,0.05);
        }
        .vuln-table th, .vuln-table td {
            padding: 0.75rem 0.9rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: left;
            color: rgba(245,245,247,0.92);
        }
        .vuln-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            color: rgba(245,245,247,0.75);
        }
        .vuln-table tr:hover {
            background: rgba(255,255,255,0.04);
        }
        .text-end {
            text-align: right;
        }
        .vuln-empty {
            text-align: center;
            padding: 1rem 0.5rem;
            color: rgba(245,245,247,0.7);
        }

        .sev-HIGH, .sev-CRITICAL {
            color: #ff4d4d;
            font-weight: bold;
        }
        .sev-MEDIUM {
            color: #ffb347;
            font-weight: bold;
        }
        .sev-LOW {
            color: #7bd88f;
            font-weight: bold;
        }
        .sev-INFO {
            color: #8ab4f8;
            font-weight: bold;
        }

        .scan-status-box {
            background: rgba(108, 99, 255, 0.12);
            border: 1px solid rgba(108, 99, 255, 0.5);
            border-radius: 14px;
            padding: 1rem 1.3rem;
            margin-bottom: 1.4rem;
            font-size: 0.95rem;
        }
        .scan-status-box b {
            color: #9a93ff;
        }

        .scan-feedback {
            margin-bottom: 1rem;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .scan-feedback.success {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.5);
            color: #2ecc71;
        }
        .scan-feedback.error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.7);
            color: #e74c3c;
        }
        .scan-feedback.info {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.7);
            color: #3498db;
        }

        /* History Sidebar Styles */
        .history-sidebar {
            background: rgba(255,255,255,0.03);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.06);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        .history-header {
            margin-bottom: 1.2rem;
        }
        .history-header h3 {
            font-size: 1.1rem;
            color: var(--light);
            margin: 0 0 0.5rem 0;
        }
        .history-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .history-item {
            padding: 1rem;
            margin-bottom: 0.8rem;
            background: rgba(255,255,255,0.05); /* igual que thead tabla */
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.06);
            transition: all 0.2s ease;
        }
        .history-item:hover {
            background: rgba(255,255,255,0.07); /* un poco más claro al hover */
            border-color: rgba(255,255,255,0.10);
        }
        .history-item.selected {
            background: rgba(108, 99, 255, 0.18);
            border-color: var(--primary);
        }
        .history-network {
            font-weight: 600;
            color: var(--light);
            margin-bottom: 0.3rem;
            font-size: 0.95rem;
        }
        .history-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: rgba(245,245,247,0.7);
            margin-bottom: 0.8rem;
        }
        .history-actions {
            display: flex;
            gap: 0.8rem;
        }
        .history-actions a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: color 0.2s;
        }
        .history-actions a:hover {
            color: var(--secondary);
        }
        .history-actions .delete-btn {
            color: #e74c3c;
        }
        .history-actions .delete-btn:hover {
            color: #c0392b;
        }
        .no-history {
            text-align: center;
            color: rgba(245,245,247,0.5);
            font-style: italic;
            padding: 2rem 1rem;
            font-size: 0.9rem;
        }

        .scan-info-bar {
            background: rgba(108, 99, 255, 0.08);
            border-radius: 10px;
            padding: 0.8rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .scan-info-text {
            color: rgba(245,245,247,0.9);
        }
        .scan-info-text strong {
            color: var(--primary);
        }
        .clear-view-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: rgba(245,245,247,0.8);
            padding: 0.3rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        .clear-view-btn:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--primary);
            color: var(--light);
        }

        @media (max-width: 1024px) {
            .main-content-wrapper {
                flex-direction: column;
            }
            .right-sidebar {
                width: 100%;
            }
            .vuln-container {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
<div id="particles-js"></div>
<header id="header">
    <?php include '../../inc/header.php'; ?>
</header>
<section class="hero" id="vuln">
    <div class="vuln-section">
        <div class="vuln-container">
            <div class="vuln-header">
                <h1 class="vuln-title">
                    Vulnerability scanner
                    <small style="font-size:0.9rem;color:rgba(245,245,247,0.75);">
                        (<?php echo $displayUser; ?>)
                    </small>
                </h1>
                <p class="vuln-subtitle">
                    Launch new scans from here and view discovered services by IP and port.
                </p>
            </div>

            <div class="main-content-wrapper">
                <!-- LEFT -->
                <div class="left-content">
                    <?php if ($scan_in_progress): ?>
                        <div class="scan-status-box">
                            <b>Scan in progress:</b>
                            <?php echo htmlspecialchars($scan_status_state); ?>
                            <?php if ($total_hosts > 0): ?>
                                (<?php echo $scanned_hosts; ?>/<?php echo $total_hosts; ?> hosts scanned)
                                <?php if ($current_host): ?>
                                    - Currently scanning: <?php echo htmlspecialchars($current_host); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($scan_feedback): ?>
                        <div class="scan-feedback <?php echo $scan_feedback_type; ?>">
                            <?php echo $scan_feedback; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="scan-feedback success">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="scan-feedback error">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($selectedScanId): ?>
                        <div class="scan-info-bar">
                            <div class="scan-info-text">
                                Currently viewing services from <strong>Scan #<?php echo $selectedScanId; ?></strong>
                            </div>
                            <a href="?p=project3/index.php&clear_view=1" class="clear-view-btn">
                                <i class="fas fa-times"></i> Show All
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- FORM -->
                    <form method="post" action="?p=project3/index.php" class="scan-form">
                        <h3>Start new scan</h3>
                        <div class="scan-grid">
                            <div class="form-group">
                                <label for="target">Target (single IP, multiple IPs, range or subnet)</label>
                                <input type="text" id="target" name="target"
                                       placeholder="e.g. 10.11.0.15,10.11.0.16 or 10.11.0.0/24" required>
                            </div>
                            <div class="form-group">
                                <label for="ports_expr">Ports (all, list or ranges)</label>
                                <input type="text" id="ports_expr" name="ports_expr"
                                       placeholder="e.g. all or 22,80-100,443">
                            </div>
                            <div class="form-group">
                                <label for="intensity">Scan intensity</label>
                                <select id="intensity" name="intensity">
                                    <option value="low">Low (-T2)</option>
                                    <option value="normal" selected>Medium (-T3)</option>
                                    <option value="high">High (-T4)</option>
                                </select>
                            </div>
                        </div>
                        <div class="scan-actions">
                            <button type="submit" name="start_scan" value="1">
                                <i class="fas fa-bolt"></i>&nbsp;Start scan
                            </button>
                            <button type="submit" name="stop_scan" value="1" formnovalidate>
                                Stop scan
                            </button>
                            <button type="button" onclick="window.location.href='?p=project3/index.php';">
                                Refresh results
                            </button>
                        </div>
                    </form>

                    <div class="vuln-total">
                        <strong>Total services in database:</strong>
                        <?php echo number_format($totalServices, 0, ',', '.'); ?>
                        <?php if ($selectedScanId): ?>
                            <span style="font-size: 0.9rem; color: rgba(245,245,247,0.7);">
                                (from scan #<?php echo $selectedScanId; ?>)
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="vuln-table-wrapper">
                        <table class="vuln-table">
                            <thead>
                            <tr>
                                <th>IP address</th>
                                <th class="text-end">Port</th>
                                <th>Service</th>
                                <th>Product</th>
                                <th>Version</th>
                                <th>CVE</th>
                                <th>Risk</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($scan_in_progress): ?>
                                <tr>
                                    <td class="vuln-empty" colspan="7">
                                        Scan in progress… results will be shown when it finishes.
                                    </td>
                                </tr>
                            <?php elseif ($results): ?>
                                <?php foreach ($results as $row): ?>
                                    <?php
                                    $sev = strtoupper($row['severity'] ?? '');
                                    $sevClass = '';
                                    if ($sev === 'HIGH' || $sev === 'CRITICAL') {
                                        $sevClass = 'sev-HIGH';
                                    } elseif ($sev === 'MEDIUM') {
                                        $sevClass = 'sev-MEDIUM';
                                    } elseif ($sev === 'LOW') {
                                        $sevClass = 'sev-LOW';
                                    } elseif ($sev !== '') {
                                        $sevClass = 'sev-INFO';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end"><?php echo (int)$row['port']; ?></td>
                                        <td><?php echo htmlspecialchars($row['service_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['product'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['version'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['cve_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="<?php echo $sevClass; ?>">
                                            <?php echo htmlspecialchars($row['severity'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td class="vuln-empty" colspan="7">
                                        <?php if ($selectedScanId): ?>
                                            No services found for scan #<?php echo $selectedScanId; ?>.
                                        <?php else: ?>
                                            No services found in database.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RIGHT: Scan History -->
                <div class="right-sidebar">
                    <div class="history-sidebar">
                        <div class="history-header">
                            <h3>Scan History</h3>
                        </div>

                        <?php if (!empty($scanHistory)): ?>
                            <div class="history-list">
                                <?php foreach ($scanHistory as $scan): ?>
                                    <?php
                                    $started   = new DateTime($scan['started_at']);
                                    $finished  = new DateTime($scan['finished_at']);
                                    $duration  = $started->diff($finished);
                                    $durationStr = $duration->format('%Hh %Im %Ss');
                                    $isSelected  = ($selectedScanId == $scan['id']);
                                    ?>
                                    <div class="history-item <?php echo $isSelected ? 'selected' : ''; ?>">
                                        <div class="history-network">
                                            <i class="fas fa-network-wired"
                                               style="margin-right: 0.5rem; color: rgba(245,245,247,0.7);"></i>
                                            <?php echo htmlspecialchars($scan['network']); ?>
                                        </div>
                                        <div class="history-details">
                                            <span>
                                                <i class="far fa-calendar"
                                                   style="margin-right: 0.3rem; color: rgba(245,245,247,0.6);"></i>
                                                <?php echo htmlspecialchars($scan['started_at']); ?>
                                            </span>
                                            <span>
                                                <i class="far fa-clock"
                                                   style="margin-right: 0.3rem; color: rgba(245,245,247,0.6);"></i>
                                                <?php echo $durationStr; ?>
                                            </span>
                                        </div>
                                        <div class="history-actions">
                                            <a href="?p=project3/index.php&view_scan=<?php echo $scan['id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="?p=project3/index.php&delete_scan=<?php echo $scan['id']; ?>"
                                               onclick="return confirm('Are you sure you want to delete scan #<?php echo $scan['id']; ?>?');"
                                               class="delete-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-history">
                                <i class="fas fa-history"
                                   style="font-size: 2rem; margin-bottom: 1rem; color: rgba(245,245,247,0.3);"></i>
                                <p>No scan history available.</p>
                                <p style="font-size: 0.8rem; margin-top: 0.5rem;">Start a new scan to see it here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
<footer>
    <?php include '../../inc/footer.php'; ?>
</footer>
<div class="scroll-top"><i class="fas fa-arrow-up"></i></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"></script>
<script>
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    const links = document.querySelectorAll('.nav-links li');

    hamburger?.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        hamburger.classList.toggle('active');
        links.forEach((link, index) => {
            link.style.animation = link.style.animation ? '' : `navLinkFade 0.5s ease forwards ${index / 7 + 0.3}s`;
        });
    });

    links.forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            hamburger?.classList.remove('active');
            links.forEach(l => l.style.animation = '');
        });
    });

    const scrollTopBtn = document.querySelector('.scroll-top');
    window.addEventListener('scroll', () => {
        document.getElementById('header').classList.toggle('scrolled', window.scrollY > 100);
        scrollTopBtn.classList.toggle('active', window.pageYOffset > 300);
    });

    scrollTopBtn?.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    particlesJS("particles-js", {
        "particles": {
            "number": {
                "value": 80,
                "density": {
                    "enable": true,
                    "value_area": 800
                }
            },
            "color": {
                "value": "#6c63ff"
            },
            "shape": {
                "type": "circle"
            },
            "opacity": {
                "value": 0.5
            },
            "size": {
                "value": 3,
                "random": true
            },
            "line_linked": {
                "enable": true,
                "distance": 150,
                "color": "#6c63ff",
                "opacity": 0.4,
                "width": 1
            },
            "move": {
                "enable": true,
                "speed": 2
            }
        },
        "interactivity": {
            "events": {
                "onhover": {
                    "enable": true,
                    "mode": "grab"
                },
                "onclick": {
                    "enable": true,
                    "mode": "push"
                }
            },
            "modes": {
                "grab": {
                    "distance": 140,
                    "line_linked": {
                        "opacity": 1
                    }
                }
            }
        },
        "retina_detect": true
    });

    <?php if ($scan_in_progress): ?>
    setInterval(function () {
        window.location.href='?p=project3/index.php';
    }, 5000);
    <?php endif; ?>

    // Smooth scroll cuando haces View
    document.querySelectorAll('.history-item a[href*="view_scan"]').forEach(link => {
        link.addEventListener('click', function () {
            setTimeout(() => {
                const target = document.querySelector('.vuln-table-wrapper');
                if (!target) return;
                window.scrollTo({
                    top: target.offsetTop - 100,
                    behavior: 'smooth'
                });
            }, 100);
        });
    });
</script>
</body>
</html>
