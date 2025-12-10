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

$isAdmin    = $hasGroup(['admins','administrators']);
$isProject1 = $hasGroup(['chorizados', 'empresa']);

if (!$isAdmin && !$isProject1) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

/**
 * Group(s) to store in DB
 * - $groups comes from $_SESSION['groups']
 * - We store it as "group1,group2,group3"
 */
$currentGroup = !empty($groups)
    ? implode(',', $groups)
    : 'no_group';

// UID from LDAP
$uid_ldap = $_SESSION['uid_ldap'] ?? ($_SESSION['username'] ?? 'unknown_user');


// ================================
//  CSRF TOKEN
// ================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

/* ================================
API / CRYPTO FUNCTIONS
- Integration with Bank REST API
================================ */

/**
 * Decrypts payload sent by the bank API.
 * Expected response: JSON with a Base64 string.
 * Cipher: AES-256-ECB (key BANK_AES_KEY from .env/config.php)
 */
function decryptBankPayload(string $cipherJson): string
{
    if (!defined('BANK_AES_KEY') || BANK_AES_KEY === '' || BANK_AES_KEY === false) {
        throw new Exception("The bank encryption key (BANK_AES_KEY) is not configured.");
    }

    $encryptedBase64 = json_decode($cipherJson, true);

    if (!is_string($encryptedBase64)) {
        throw new Exception("Unexpected format in encrypted API response.");
    }

    $cipherBinary = base64_decode($encryptedBase64, true);
    if ($cipherBinary === false) {
        throw new Exception("Failed to decode Base64 from API response.");
    }

    $plaintext = openssl_decrypt(
        $cipherBinary,
        'AES-256-ECB',
        BANK_AES_KEY,
        OPENSSL_RAW_DATA
    );

    if ($plaintext === false) {
        throw new Exception("Error decrypting API response. Check BANK_AES_KEY.");
    }

    return $plaintext;
}

/**
 * Calls /infocards{talde}/{user}
 */
function getCardInfo(): array
{
    $url = BANK_API_BASEURL . "/infocards" . TALDE_ID . "/" . BANK_API_USER;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FAILONERROR    => false,
    ]);
    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Connection error with cards API: $curlErr");
    }
    if ($http !== 200) {
        throw new Exception("Error retrieving cards (HTTP $http).");
    }

    $jsonPlain = decryptBankPayload($response);
    $data      = json_decode($jsonPlain, true);
    if (!is_array($data)) {
        throw new Exception("Invalid decrypted JSON from cards API.");
    }

    return $data;
}

/**
 * Calls /enablecard{talde}/{user}/{cardType}
 * cardType: CREDIT | DEBIT
 */
function enableCard(string $cardType): array
{
    $cardType = strtoupper($cardType);
    if (!in_array($cardType, ['CREDIT', 'DEBIT'], true)) {
        throw new Exception("Invalid card type.");
    }

    $url = BANK_API_BASEURL . "/enablecard" . TALDE_ID . "/" . BANK_API_USER . "/" . $cardType;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FAILONERROR    => false,
    ]);
    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Connection error while enabling card ($cardType): $curlErr");
    }
    if ($http !== 200) {
        throw new Exception("Error enabling card ($cardType). HTTP $http");
    }

    $jsonPlain = decryptBankPayload($response);
    $data      = json_decode($jsonPlain, true);
    if (!is_array($data)) {
        throw new Exception("Invalid decrypted JSON when enabling card.");
    }

    return $data;
}

/**
 * MKDEH business rule:
 *  - Amount < 500  -> DEBIT
 *  - Amount >= 500 -> CREDIT
 * Also ensures only that card is active,
 * calling enableCard if needed.
 */
function ensureCardForAmount(float $amount): array
{
    $info    = getCardInfo();
    $current = $info['current_card'] ?? null;

    $required = ($amount < 500) ? 'DEBIT' : 'CREDIT';

    if ($current !== $required) {
        $info = enableCard($required);
    }

    return [$info, $required];
}

/**
 * Format card number 4-4-4-4
 */
function formatCardNumber(?string $num): string
{
    if (!$num) return "•••• •••• •••• ••••";
    $digits = preg_replace('/\D+/', '', $num);
    if ($digits === '') return "•••• •••• •••• ••••";
    return trim(chunk_split($digits, 4, ' '));
}

/* ================================
USER INFO
================================ */
$displayUser = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'MKDEH USER';

/* ================================
FORM LOGIC
================================ */
$cardInfo    = null;
$cardError   = null;
$cardMessage = null;
$predicted   = null; // card according to amount

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // CSRF
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
        ) {
            throw new Exception("Invalid CSRF token. Please try again.");
        }

        $action = $_POST['action'] ?? '';

        // --- Adjust card according to amount AND SAVE EXPENSE ---
        if ($action === 'set_by_amount') {
            $rawAmount   = trim((string)($_POST['amount'] ?? ''));
            $fecha       = $_POST['fecha'] ?? date('Y-m-d');
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));

            if ($rawAmount === '') {
                throw new Exception("You must enter an amount.");
            }
            if ($descripcion === '') {
                throw new Exception("You must enter a description.");
            }

            $normalized = str_replace(',', '.', $rawAmount);
            if (!is_numeric($normalized)) {
                throw new Exception("The amount entered is not valid.");
            }

            $amount = (float)$normalized;
            if ($amount <= 0) {
                throw new Exception("The amount must be greater than 0.");
            }

            // ===========================
            // Ticket upload (optional)
            // ===========================
            $ticket_path = null;

            if (!empty($_FILES['ticket']['name'])) {

                if ($_FILES['ticket']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error uploading receipt (code " . $_FILES['ticket']['error'] . ").");
                }

                if (!is_uploaded_file($_FILES['ticket']['tmp_name'])) {
                    throw new Exception("The receipt file was not received correctly.");
                }

                $upload_dir = '/var/www/html/img/';

                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true)) {
                    throw new Exception("Could not create images directory: $upload_dir");
                }

                if (!is_writable($upload_dir)) {
                    throw new Exception("Images directory is not writable: $upload_dir");
                }

                $allowed_ext = ['jpg','jpeg','png','gif','webp','pdf'];
                $ext = strtolower(pathinfo($_FILES['ticket']['name'], PATHINFO_EXTENSION));

                if ($ext === '' || !in_array($ext, $allowed_ext, true)) {
                    $ext = 'jpg';
                }

                $timestamp = date('Ymd_His');
                $random    = bin2hex(random_bytes(4));
                $fname     = $timestamp . '_' . $random . '.' . $ext;

                $dest = $upload_dir . $fname;

                if (!move_uploaded_file($_FILES['ticket']['tmp_name'], $dest)) {
                    throw new Exception("Could not move receipt to images directory.");
                }

                $ticket_path = '/img/' . $fname;
            }

            // 1) Adjust card according to amount
            [$cardInfo, $required] = ensureCardForAmount($amount);
            $predicted   = $required;

            // 2) Store expense in DB
            $pdo = db();

            $stmt = $pdo->prepare("
                INSERT INTO gastos (uid_ldap, grupo, tarjeta, importe, fecha, descripcion, ticket_path)
                VALUES (:uid_ldap, :grupo, :tarjeta, :importe, :fecha, :descripcion, :ticket_path)
            ");
            $stmt->execute([
                ':uid_ldap'    => $uid_ldap,
                ':grupo'       => $currentGroup,
                ':tarjeta'     => $required,
                ':importe'     => $amount,
                ':fecha'       => $fecha,
                ':descripcion' => $descripcion,
                ':ticket_path' => $ticket_path,
            ]);

            $cardMessage = "Expense successfully recorded: " .
                        number_format($amount, 2, ',', '.') . " € " .
                        "(card used: $required).";
        }
    }

    if ($cardInfo === null) {
        $cardInfo = getCardInfo();
    }
} catch (Exception $e) {
    $cardError = $e->getMessage();
}

/* ================================
Data for card rendering
================================ */
$currentCardType = strtoupper($cardInfo['current_card'] ?? 'DEBIT');
$displayType     = $predicted ?: $currentCardType;

$isDebit  = ($displayType === 'DEBIT');
$cardNum  = $isDebit ? ($cardInfo['card_debit'] ?? null) : ($cardInfo['card_credit'] ?? null);
$cardNumF = formatCardNumber($cardNum);
$holder   = strtoupper($displayUser);
$expires  = '12/29'; // demo / mock

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>ChorizoSQL</title>

    <!-- Global fonts / icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Global CSS -->
    <link rel="stylesheet" href="/css/style.css"/>

    <!-- Component-specific styles -->
    <style>
        .cc-section {
            width: 100%;
            padding: 7rem 5% 4rem;
            display: flex;
            justify-content: center;
            align-items: stretch;
            position: relative;
            z-index: 1;
            flex: 1 0 auto;
        }

        /* Contenedor principal, parecido a vuln-container */
        .cc-container {
            max-width: 1100px;
            width: 100%;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
            gap: 2rem;
            background: rgba(26, 26, 46, 0.9);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 45px rgba(0,0,0,0.6);
            padding: 2.5rem 2.2rem;
        }

        .cc-panel-left {
            padding-right: 1rem;
            border-right: 1px solid rgba(255,255,255,0.06);
        }

        /* Lado derecho con look de “tarjeta/tabla” como el historial de scans */
        .cc-panel-right {
            padding-left: 1rem;
            display: flex;
            flex-direction: column;
        }

        .cc-logo-user {
            font-size: 0.9rem;
            margin-bottom: 1.2rem;
            color: rgba(245,245,247,0.85);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .cc-logo-user strong {
            color: var(--primary);
        }

        .cc-title {
            font-size: 1.7rem;
            margin-bottom: 0.4rem;
            color: var(--light);
        }

        .cc-subtitle {
            font-size: 0.95rem;
            color: rgba(245,245,247,0.8);
            margin-bottom: 1.6rem;
        }

        .cc-subtitle span {
            color: var(--primary);
            font-weight: 500;
        }

        .cc-alert {
            border-radius: 10px;
            padding: 0.7rem 0.9rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .cc-alert-error {
            border: 1px solid #ff6b81;
            background: rgba(255,107,129,0.15);
            color: #ffd7df;
        }

        .cc-alert-success {
            border: 1px solid #4caf50;
            background: rgba(76,175,80,0.14);
            color: #c8ffd0;
        }

        .cc-form {
            width: 100%;
        }

        .cc-form-group {
            margin-bottom: 1.2rem;
        }

        .cc-form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--light);
        }

        .cc-form-group input,
        .cc-form-group textarea {
            width: 100%;
            padding: 0.8rem 0.9rem;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.18);
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--light);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .cc-form-group input::placeholder,
        .cc-form-group textarea::placeholder {
            color: rgba(245,245,247,0.6);
        }

        .cc-form-group input:focus,
        .cc-form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background-color: rgba(255,255,255,0.09);
        }

        .cc-btn-full {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 12px 28px rgba(0,0,0,0.45);
            transition: var(--transition);
        }

        .cc-btn-primary {
            background-color: var(--primary);
            color: var(--light);
        }

        .cc-btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }

        .cc-amount-hint {
            font-size: 0.8rem;
            margin-top: 0.35rem;
            color: rgba(245,245,247,0.75);
        }

        .cc-badge-active {
            margin-top: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            border: 1px solid rgba(108,99,255,0.9);
            background: rgba(108,99,255,0.1);
            font-size: 0.8rem;
            color: var(--light);
        }

        .cc-badge-active strong {
            color: var(--primary);
        }

        .cc-card-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--light);
        }

        /* Caja de la tarjeta con el mismo estilo de “tarjeta/tabla” que el historial de scans */
        .cc-card-box {
            background: rgba(255,255,255,0.03);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.06);
            padding: 1.5rem 1.4rem;
            margin-bottom: 1.1rem;
        }

        .cc-card-wrapper {
            width: 100%;
            max-width: 380px;
            margin: 0 auto;
        }

        .cc-card {
            width: 100%;
            height: 210px;
            border-radius: 16px;
            background: radial-gradient(circle at top left, #7f77ff, #3b338f 70%);
            box-shadow: 0 18px 40px rgba(0,0,0,0.6);
            padding: 1.2rem 1.3rem;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
        }

        .cc-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .cc-card-chip {
            width: 36px;
            height: 26px;
            border-radius: 6px;
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(200,200,200,0.4));
            box-shadow: 0 2px 5px rgba(0,0,0,0.35);
        }

        .cc-card-tag {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .cc-card-number {
            font-family: 'Source Code Pro', monospace;
            font-size: 1.1rem;
            letter-spacing: 0.18em;
            margin: 0.9rem 0 1rem;
            text-shadow: 0 2px 5px rgba(0,0,0,0.45);
        }

        .cc-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .cc-card-label {
            opacity: 0.8;
            margin-bottom: 0.15rem;
        }

        .cc-card-value {
            font-size: 0.9rem;
            letter-spacing: 0.08em;
        }

        .cc-card-numbers-inline {
            width: 100%;
            max-width: 380px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: rgba(245,245,247,0.9);
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .cc-card-numbers-inline span strong {
            font-family: 'Source Code Pro', monospace;
        }

        @media (max-width: 992px) {
            .cc-container {
                grid-template-columns: 1fr;
                padding: 2rem 1.6rem;
            }
            .cc-panel-left {
                padding-right: 0;
                border-right: none;
                border-bottom: 1px solid rgba(255,255,255,0.06);
                margin-bottom: 1.5rem;
                padding-bottom: 1.5rem;
            }
            .cc-panel-right {
                padding-left: 0;
            }
        }

        @media (max-width: 576px) {
            .cc-container {
                padding: 1.8rem 1.4rem;
            }
        }
    </style>
</head>
<body>
<div id="particles-js"></div>

<header id="header">
    <?php include '../../inc/header.php'; ?>
</header>

<section class="hero" id="projects">
    <div class="cc-section">
        <div class="cc-container">

            <!-- LEFT PANEL: Formulario -->
            <div class="cc-panel-left">

                <?php if ($cardError): ?>
                    <div class="cc-alert cc-alert-error" role="alert">
                        <i class="fas fa-triangle-exclamation"></i>
                        <?php echo htmlspecialchars($cardError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php elseif ($cardMessage): ?>
                    <div class="cc-alert cc-alert-success" role="status">
                        <i class="fas fa-circle-check"></i>
                        <?php echo htmlspecialchars($cardMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="cc-logo-user">
                    ChorizoSQL · <strong><?php echo $displayUser; ?></strong>
                </div>

                <h2 class="cc-title">Register expense & configure operation</h2>
                <p class="cc-subtitle">
                    Provide the expense details. The application will automatically decide which card to use based on the amount.<br>
                    <span>MKDEH policy:</span> &lt; 500€ → Debit · ≥ 500€ → Credit.
                </p>

                <!-- FORM -->
                <form method="post" class="cc-form" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="set_by_amount">

                    <div class="cc-form-group">
                        <label for="fecha">Expense date</label>
                        <input
                            type="date"
                            id="fecha"
                            name="fecha"
                            value="<?php echo date('Y-m-d'); ?>"
                            required
                        />
                    </div>

                    <div class="cc-form-group">
                        <label for="descripcion">Expense description</label>
                        <input
                            type="text"
                            id="descripcion"
                            name="descripcion"
                            placeholder="E.g.: Taxi to seminar, client dinner..."
                            required
                        />
                    </div>

                    <div class="cc-form-group">
                        <label for="amount">Payment amount (€)</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="amount"
                            name="amount"
                            placeholder="E.g.: 120.50"
                            inputmode="decimal"
                            required
                        />
                        <div id="ccAmountHint" class="cc-amount-hint"></div>
                    </div>

                    <div class="cc-form-group">
                        <label for="ticket">Receipt / invoice (optional)</label>
                        <input
                            type="file"
                            id="ticket"
                            name="ticket"
                            accept="image/*,application/pdf"
                        />
                    </div>

                    <button type="submit" class="cc-btn-full cc-btn-primary">
                        <i class="fas fa-wand-magic-sparkles"></i>
                        Save expense & adjust card
                    </button>
                </form>

                <div class="cc-badge-active">
                    <i class="fas fa-shield-alt"></i>
                    Current active card:
                    <strong><?php echo htmlspecialchars($currentCardType, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </div>

            <!-- RIGHT PANEL: “Tarjeta” con estilo de tabla -->
            <div class="cc-panel-right">
                <h3 class="cc-card-title">Selected card</h3>

                <div class="cc-card-box">
                    <div class="cc-card-wrapper">
                        <div class="cc-card">
                            <div class="cc-card-header">
                                <div class="cc-card-chip"></div>
                                <div class="cc-card-tag" id="ccCardTypeTag">
                                    <?php echo $displayType === 'DEBIT' ? 'Debit Card' : 'Credit Card'; ?>
                                </div>
                            </div>
                            <div class="cc-card-number" id="ccCardNumber">
                                <?php echo htmlspecialchars($cardNumF, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="cc-card-footer">
                                <div>
                                    <div class="cc-card-label">Card Holder</div>
                                    <div class="cc-card-value" id="ccCardHolder">
                                        <?php echo $holder; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="cc-card-label">Expires</div>
                                    <div class="cc-card-value" id="ccCardExpiry">
                                        <?php echo $expires; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cc-card-box">
                    <div class="cc-card-numbers-inline">
                        <span>Debit number: <strong><?php echo htmlspecialchars(formatCardNumber($cardInfo['card_debit'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                        <span>Credit number: <strong><?php echo htmlspecialchars(formatCardNumber($cardInfo['card_credit'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></span>
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

    const amountInput    = document.getElementById('amount');
    const amountHint     = document.getElementById('ccAmountHint');
    const cardTypeTag    = document.getElementById('ccCardTypeTag');

    function updateAmountHint() {
        if (!amountInput || !amountHint || !cardTypeTag) return;

        const raw = amountInput.value.replace(',', '.');
        const val = parseFloat(raw);

        if (isNaN(val) || val <= 0) {
            amountHint.textContent = '';
            return;
        }

        const required = val < 500 ? 'DEBIT' : 'CREDIT';
        const label    = required === 'DEBIT' ? 'debit' : 'credit';

        amountHint.textContent = `For this amount, MKDEH policy will use the ${label} card.`;
        cardTypeTag.textContent = required === 'DEBIT' ? 'Debit Card' : 'Credit Card';
    }

    amountInput?.addEventListener('input', updateAmountHint);
</script>
</body>
</html>
