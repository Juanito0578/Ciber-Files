<?php
session_start();
require_once '../../../sso/sso-check.php';
require_once '../../../Conf/config.php';

/* =========================================
   ACCESS CONTROL BY ROLES (LDAP)
========================================= */
$groups = [];
if (!empty($_SESSION['groups']) && is_array($_SESSION['groups'])) {
    // Normalize to lowercase for consistency
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

$isAdmin    = $hasGroup(['admins', 'administrators']);
$isProject1 = $hasGroup(['chorizados', 'proyecto1', 'proj1', 'empresa']);

if (!$isAdmin && !$isProject1) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

$uid_ldap    = $_SESSION['uid_ldap'] ?? ($_SESSION['username'] ?? 'unknown_user');
$displayUser = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'MKDEH USER';

$pdo = db();

/* =====================================
   CSRF TOKEN
===================================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

/* =====================================
   DELETE EXPENSE (POST, only admin)
   - Borra el registro de la BBDD
   - Borra también el ticket asociado (fichero)
===================================== */
$deleteMessage = null;
$deleteError   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
        ) {
            throw new Exception("Invalid CSRF token.");
        }

        if (!$isAdmin) {
            throw new Exception("You are not allowed to delete expenses.");
        }

        $expenseId = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;
        if ($expenseId <= 0) {
            throw new Exception("Invalid expense ID.");
        }

        // 1) Buscar el ticket_path antes de borrar el gasto
        $stmtSel = $pdo->prepare("SELECT ticket_path FROM gastos WHERE id = :id");
        $stmtSel->execute([':id' => $expenseId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("Expense not found.");
        }

        $ticketPath = $row['ticket_path'] ?? '';

        // 2) Intentar borrar el fichero asociado (si existe)
        if (!empty($ticketPath)) {
            // Si empieza por "/" → relativo al DOCUMENT_ROOT (ej: /uploads/tickets/...)
            if ($ticketPath[0] === '/') {
                $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
                $fullPath = $docRoot . $ticketPath;
            } else {
                // Si es relativo (ej: uploads/tickets/...), lo resolvemos desde la raíz del proyecto
                $baseDir  = realpath(__DIR__ . '/../../../');
                $fullPath = $baseDir ? $baseDir . '/' . $ticketPath : $ticketPath;
            }

            $realFullPath = realpath($fullPath);
            if ($realFullPath && is_file($realFullPath)) {
                @unlink($realFullPath);
            }
        }

        // 3) Borrar el gasto en la BBDD
        $stmtDel = $pdo->prepare("DELETE FROM gastos WHERE id = :id");
        $stmtDel->execute([':id' => $expenseId]);

        $deleteMessage = "Expense and associated receipt deleted successfully.";
    } catch (Throwable $e) {
        $deleteError = $e->getMessage();
    }
}

/* =====================================
   READ FILTERS (GET)
   - Date range: date_from, date_to
   - Amount range: amount_min, amount_max
===================================== */
$date_from   = isset($_GET['date_from'])   ? trim((string)$_GET['date_from'])   : '';
$date_to     = isset($_GET['date_to'])     ? trim((string)$_GET['date_to'])     : '';
$amount_min  = isset($_GET['amount_min'])  ? trim((string)$_GET['amount_min'])  : '';
$amount_max  = isset($_GET['amount_max'])  ? trim((string)$_GET['amount_max'])  : '';

$filters = [];
$params  = [];

/* Filtro por usuario (solo empresa) */
if (!$isAdmin) {
    $filters[]         = "uid_ldap = :uid";
    $params[':uid']    = $uid_ldap;
}

/* Filtro fechas */
if ($date_from !== '') {
    $filters[]            = "fecha >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $filters[]          = "fecha <= :date_to";
    $params[':date_to'] = $date_to;
}

/* Filtro importes */
if ($amount_min !== '') {
    $normalized = str_replace(',', '.', $amount_min);
    if (is_numeric($normalized)) {
        $filters[]             = "importe >= :amount_min";
        $params[':amount_min'] = (float)$normalized;
    }
}
if ($amount_max !== '') {
    $normalized = str_replace(',', '.', $amount_max);
    if (is_numeric($normalized)) {
        $filters[]             = "importe <= :amount_max";
        $params[':amount_max'] = (float)$normalized;
    }
}

/* Montamos WHERE dinámico */
$whereSql = '';
if ($filters) {
    $whereSql = 'WHERE ' . implode(' AND ', $filters);
}

/* =====================================
   EXPENSES QUERY (WITH FILTERS)
===================================== */

$sqlBase = "
    FROM gastos
    $whereSql
";

$sqlList = "
    SELECT id, uid_ldap, grupo, fecha, descripcion, tarjeta, importe, ticket_path
    $sqlBase
    ORDER BY fecha DESC, id DESC
";

$sqlSum = "
    SELECT SUM(importe)
    $sqlBase
";

$stmt = $pdo->prepare($sqlList);
$stmt->execute($params);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtTot = $pdo->prepare($sqlSum);
$stmtTot->execute($params);
$total = (float) ($stmtTot->fetchColumn() ?: 0);

// para el enlace de reset necesitamos el valor actual de p (del router)
$currentP = isset($_GET['p']) ? (string)$_GET['p'] : '';
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
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <style>
        .expenses-section {
            width: 100%;
            padding: 7rem 5% 4rem;
            display: flex;
            justify-content: center;
            align-items: stretch;
            position: relative;
            z-index: 1;
            flex: 1 0 auto;
        }

        .expenses-container {
            width: 100%;
            max-width: 1100px;
            background: rgba(26, 26, 46, 0.9);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 45px rgba(0,0,0,0.6);
            padding: 2.5rem 2.2rem;
        }

        .expenses-header {
            margin-bottom: 1.5rem;
        }

        .expenses-title {
            font-size: 1.8rem;
            color: var(--light);
            margin-bottom: 0.3rem;
        }

        .expenses-subtitle {
            font-size: 0.95rem;
            color: rgba(245,245,247,0.8);
        }

        .expenses-total {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            color: var(--light);
        }

        .expenses-total strong {
            color: var(--primary);
        }

        /* Alerts for delete */
        .expenses-alert {
            border-radius: 10px;
            padding: 0.7rem 0.9rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .expenses-alert-success {
            border: 1px solid #4caf50;
            background: rgba(76,175,80,0.14);
            color: #c8ffd0;
        }

        .expenses-alert-error {
            border: 1px solid #ff6b81;
            background: rgba(255,107,129,0.15);
            color: #ffd7df;
        }

        /* FILTERS FORM */
        .expenses-filters {
            margin-top: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem 1.2rem;
            border-radius: 14px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .expenses-filters h3 {
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
            color: var(--light);
        }

        .expenses-filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.9rem 1.2rem;
        }

        .expenses-filters .form-group {
            text-align: left;
        }

        .expenses-filters label {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--light);
        }

        .expenses-filters input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border-radius: 8px;
            border: 2px solid rgba(255,255,255,0.18);
            background-color: rgba(255,255,255,0.05);
            color: var(--light);
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .expenses-filters input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: rgba(255,255,255,0.09);
        }

        .expenses-filters-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }

        .expenses-filters button[type="submit"] {
            padding: 0.6rem 1.4rem;
            border-radius: 999px;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--primary);
            color: var(--light);
            transition: var(--transition);
        }

        .expenses-filters button[type="submit"]:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }

        .expenses-filters a.reset-link {
            font-size: 0.85rem;
            color: rgba(245,245,247,0.8);
            text-decoration: none;
            align-self: center;
        }

        .expenses-filters a.reset-link:hover {
            text-decoration: underline;
        }

        .expenses-table-wrapper {
            margin-top: 1rem;
            overflow-x: auto;
        }

        .expenses-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .expenses-table thead {
            background: rgba(255,255,255,0.05);
        }

        .expenses-table th,
        .expenses-table td {
            padding: 0.75rem 0.9rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: left;
            color: rgba(245,245,247,0.92);
        }

        .expenses-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            color: rgba(245,245,247,0.75);
        }

        .expenses-table tr:hover {
            background: rgba(255,255,255,0.04);
        }

        .text-end {
            text-align: right;
        }

        .expenses-ticket-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .expenses-ticket-link:hover {
            text-decoration: underline;
        }

        .expenses-empty {
            text-align: center;
            padding: 1rem 0.5rem;
            color: rgba(245,245,247,0.7);
        }

        /* Delete button */
        .expenses-delete-form {
            margin: 0;
        }

        .expenses-delete-btn {
            border: none;
            background: transparent;
            color: #ff6b81;
            cursor: pointer;
            font-size: 0.95rem;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            transition: var(--transition);
        }

        .expenses-delete-btn:hover {
            background: rgba(255,107,129,0.16);
        }

        @media (max-width: 768px) {
            .expenses-container {
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

<section class="hero" id="gastos">
    <div class="expenses-section">
        <div class="expenses-container">

            <div class="expenses-header">
                <h1 class="expenses-title">
                    Expenses history
                    <?php if (!$isAdmin): ?>
                        <small style="font-size:0.9rem;color:rgba(245,245,247,0.75);">
                            (<?php echo $displayUser; ?>)
                        </small>
                    <?php else: ?>
                        <small style="font-size:0.9rem;color:rgba(245,245,247,0.75);">
                            (admin view)
                        </small>
                    <?php endif; ?>
                </h1>
                <p class="expenses-subtitle">
                    Review recorded expenses and their associated cards.
                    Use the filters below to search by date and amount ranges.
                </p>
            </div>

            <?php if ($deleteError): ?>
                <div class="expenses-alert expenses-alert-error">
                    <i class="fas fa-triangle-exclamation"></i>
                    <?php echo htmlspecialchars($deleteError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php elseif ($deleteMessage): ?>
                <div class="expenses-alert expenses-alert-success">
                    <i class="fas fa-circle-check"></i>
                    <?php echo htmlspecialchars($deleteMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- FILTERS FORM -->
            <form method="get" class="expenses-filters">
                <!-- mantener el parámetro p del router -->
                <?php if ($currentP !== ''): ?>
                    <input type="hidden" name="p"
                           value="<?php echo htmlspecialchars($currentP, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>

                <h3>Search filters</h3>
                <div class="expenses-filters-grid">
                    <div class="form-group">
                        <label for="date_from">Start date</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="date_to">End date</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="amount_min">Min amount (€)</label>
                        <input
                            type="number"
                            step="0.01"
                            id="amount_min"
                            name="amount_min"
                            placeholder="e.g. 40"
                            value="<?php echo htmlspecialchars($amount_min, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="amount_max">Max amount (€)</label>
                        <input
                            type="number"
                            step="0.01"
                            id="amount_max"
                            name="amount_max"
                            placeholder="e.g. 60"
                            value="<?php echo htmlspecialchars($amount_max, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                </div>

                <div class="expenses-filters-actions">
                    <button type="submit">
                        <i class="fas fa-filter"></i>&nbsp;Apply filters
                    </button>

                    <!-- Clear filters: mantenemos p y nada más -->
                    <?php if ($currentP !== ''): ?>
                        <a class="reset-link"
                           href="?p=<?php echo urlencode($currentP); ?>">
                            Clear filters
                        </a>
                    <?php else: ?>
                        <a class="reset-link"
                           href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
                            Clear filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="expenses-total">
                <strong>Total amount (filtered):</strong>
                <?php echo number_format($total, 2, ',', '.'); ?> €
            </div>

            <div class="expenses-table-wrapper">
                <table class="expenses-table">
                    <thead>
                    <tr>
                        <?php if ($isAdmin): ?>
                            <th>User UID</th>
                            <th>Group(s)</th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Card</th>
                        <th class="text-end">Amount (€)</th>
                        <th>Receipt</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-end">Actions</th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($gastos): ?>
                        <?php foreach ($gastos as $g): ?>
                            <tr>
                                <?php if ($isAdmin): ?>
                                    <td><?= htmlspecialchars($g['uid_ldap']) ?></td>
                                    <td><?= htmlspecialchars($g['grupo']) ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($g['fecha']) ?></td>
                                <td><?= htmlspecialchars($g['descripcion']) ?></td>
                                <td><?= htmlspecialchars($g['tarjeta']) ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$g['importe'], 2, ',', '.'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($g['ticket_path'])): ?>
                                        <a class="expenses-ticket-link"
                                           href="<?= htmlspecialchars($g['ticket_path'], ENT_QUOTES, 'UTF-8') ?>"
                                           download>
                                            Download receipt
                                        </a>
                                    <?php else: ?>
                                        –
                                    <?php endif; ?>
                                </td>

                                <?php if ($isAdmin): ?>
                                    <td class="text-end">
                                        <form method="post" class="expenses-delete-form">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="expense_id"
                                                   value="<?php echo (int)$g['id']; ?>">
                                            <button type="submit" class="expenses-delete-btn" title="Delete expense">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="expenses-empty"
                                colspan="<?= $isAdmin ? 8 : 5; ?>">
                                No expenses recorded for the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
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
    const navLinks  = document.querySelector('.nav-links');
    const links     = document.querySelectorAll('.nav-links li');

    hamburger?.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        hamburger.classList.toggle('active');
        links.forEach((link, index) => {
            link.style.animation = link.style.animation
                ? ''
                : `navLinkFade 0.5s ease forwards ${index / 7 + 0.3}s`;
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
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    particlesJS("particles-js", {
        "particles": {
            "number": { "value": 80, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#6c63ff" },
            "shape": { "type": "circle" },
            "opacity": { "value": 0.5 },
            "size": { "value": 3, "random": true },
            "line_linked": { "enable": true, "distance": 150, "color": "#6c63ff", "opacity": 0.4, "width": 1 },
            "move": { "enable": true, "speed": 2 }
        },
        "interactivity": {
            "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" } },
            "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } } }
        },
        "retina_detect": true
    });
</script>
</body>
</html>
