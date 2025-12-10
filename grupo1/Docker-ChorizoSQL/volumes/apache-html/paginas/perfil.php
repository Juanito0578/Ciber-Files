<?php
session_start();
require_once '../sso/sso-check.php';
require_once '../Conf/config.php';

$pdo = db();

// ===============================
//  Basic session data
// ===============================
$username = $_SESSION['username']      ?? '—';
$email    = $_SESSION['email']         ?? '—';
$uid_ldap = $_SESSION['uid_ldap']      ?? null;
$groups   = $_SESSION['groups']        ?? [];

// Company: we use LDAP groups
$empresa = '—';
if (!empty($groups) && is_array($groups)) {
    $empresa = implode(', ', $groups);
}

// ===============================
//  CIF (employeeNumber) from LDAP
// ===============================
$cif = '—';

if ($uid_ldap) {
    try {
        global $ldap_host, $admin_dn, $admin_pass, $base_users;

        $conn = ldap_connect($ldap_host);
        if ($conn) {
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

            if (@ldap_bind($conn, $admin_dn, $admin_pass)) {
                // escape uid for filter
                if (function_exists('ldap_escape')) {
                    $safeUid = ldap_escape($uid_ldap, '', LDAP_ESCAPE_FILTER);
                } else {
                    $safeUid = str_replace(['\\','(',')',"\0",'*'], ['\\5c','\\28','\\29','\\00','\\2a'], $uid_ldap);
                }

                $filter = sprintf('(uid=%s)', $safeUid);
                $attrs  = ['employeeNumber'];

                $sr = @ldap_search($conn, $base_users, $filter, $attrs);
                if ($sr) {
                    $entries = ldap_get_entries($conn, $sr);
                    if (!empty($entries) && ($entries['count'] ?? 0) > 0) {
                        // lowercase keys
                        $cifAttr = $entries[0]['employeenumber'][0] ?? null;
                        if ($cifAttr !== null && $cifAttr !== '') {
                            $cif = $cifAttr;
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // if LDAP fails, we leave CIF as "—"
    }
}

// ===============================
//  Registration date & last login
//  - registration -> first time logged in
//  - last         -> previous access
// ===============================
$fechaRegistro  = '—';
$ultimaConexion = '—';

if ($uid_ldap) {
    try {
        // Get ALL dates ordered (newest first)
        $stmt = $pdo->prepare("
            SELECT fecha 
            FROM accesos
            WHERE uid_ldap = :u
            ORDER BY fecha DESC
        ");
        $stmt->execute([':u' => $uid_ldap]);
        $fechas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($fechas)) {
            // Most recent date (current login)
            $fechaRegistro = $fechas[0];

            // Previous login (if exists)
            $ultimaConexion = $fechas[1] ?? '—';
        }

    } catch (Throwable $e) {
        // leave defaults
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ChorizoSQL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Global styles -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <style>
        .profile-section {
            width: 100%;
            padding: 7rem 5% 4rem;
            display: flex;
            justify-content: center;
            align-items: stretch;
            position: relative;
            z-index: 1;
            flex: 1 0 auto;
        }

        /* CARD STYLE "AVAILABLE PROJECTS" */
        .profile-container {
            width: 100%;
            max-width: 900px;
            background: rgba(20, 20, 35, 0.94);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.07);
            box-shadow: 0 22px 50px rgba(0,0,0,0.7);
            overflow: hidden;
        }

        .profile-header-bar {
            padding: 2.2rem 2.4rem 1.6rem;
            background: transparent;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .profile-body {
            padding: 1.9rem 2.4rem 2.3rem;
        }

        .profile-title {
            font-size: 2.2rem;
            color: #ffffff;
            margin-bottom: 0.35rem;
            font-weight: 700;
        }

        .profile-subtitle {
            font-size: 0.98rem;
            color: rgba(245,245,247,0.82);
        }

        /* DATA TABLE */
        .profile-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.4rem;
        }

        .profile-table tr {
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .profile-table tr:last-child {
            border-bottom: none;
        }

        .profile-table th,
        .profile-table td {
            padding: 0.95rem 0;
            font-size: 0.95rem;
        }

        .profile-table th {
            width: 40%;
            font-weight: 600;
            color: rgba(245,245,247,0.8);
            text-align: left;
        }

        .profile-table td {
            color: rgba(245,245,247,0.96);
            text-align: right;
        }

        /* PILL FOR GROUPS (company) */
        .profile-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.9rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: rgba(88, 82, 245, 0.3);
            border: 1px solid rgba(150, 140, 255, 0.55);
            color: #e5e3ff;
        }

        .profile-pill span + span::before {
            content: "•";
            margin: 0 0.35rem;
            opacity: 0.7;
        }

        @media (max-width: 900px) {
            .profile-container {
                border-radius: 20px;
            }
            .profile-header-bar,
            .profile-body {
                padding: 1.8rem 1.6rem;
            }
            .profile-table th,
            .profile-table td {
                padding: 0.8rem 0;
            }
        }
    </style>
</head>
<body>
<div id="particles-js"></div>

<header id="header">
    <?php include 'inc/header.php'; ?>
</header>

<section class="hero" id="perfil">
    <div class="profile-section">
        <div class="profile-container">

            <div class="profile-header-bar">
                <h1 class="profile-title">User Profile</h1>
                <p class="profile-subtitle">
                    Information associated with your account in
                    <span style="color:#a29bff;font-weight:600;">ChorizoSQL</span>.
                </p>
            </div>

            <div class="profile-body">
                <table class="profile-table">
                    <tr>
                        <th>Username</th>
                        <td><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($email ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Group(s)</th>
                        <td>
                            <?php if ($empresa !== '—'): ?>
                                <span class="profile-pill">
                                    <?php foreach (explode(',', $empresa) as $g): ?>
                                        <span><?= htmlspecialchars(trim($g), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ID Number</th>
                        <td><?= htmlspecialchars($cif, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Current Session</th>
                        <td><?= htmlspecialchars($fechaRegistro, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Last Session</th>
                        <td><?= htmlspecialchars($ultimaConexion, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                </table>
            </div>

        </div>
    </div>
</section>

<footer>
    <?php include 'inc/footer.php'; ?>
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
