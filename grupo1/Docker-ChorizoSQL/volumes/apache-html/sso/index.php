<?php
require_once '../Conf/config.php';
session_start();

/**
 * ============================================
 *   SSO LOGIN con LDAP + Rate Limiting
 *   - Mantiene misma API de sesión/cookies
 *   - Busca grupos: posixGroup (memberUid) o groupOfNames/AD (member=DN)
 *     + fallback leyendo memberOf del propio usuario
 * ============================================
 */

// Conexión a la BBDD para registrar accesos
$pdo = db();

/* --------------------------------------------
 * 1) Cerrar sesión si ?logout
 *    - Elimina cookie SSO_TOKEN
 *    - Destruye la sesión
 *    - Redirige a index.php
 * -------------------------------------------- */
if (isset($_GET['logout'])) {
    setcookie("SSO_TOKEN", "", time() - 3600, "/");

    // Limpiamos también la contraseña almacenada en sesión
    unset($_SESSION['ldap_plain_password']);

    session_destroy();
    header("Location: index.php");
    exit;
}

/* --------------------------------------------
 * 2) Si ya hay sesión válida, no muestres login
 *    - Si existen cookie SSO_TOKEN y username en sesión
 *    - Redirige a la home
 * -------------------------------------------- */
if (isset($_COOKIE['SSO_TOKEN']) && isset($_SESSION['username'])) {
    header("Location: /index.php");
    exit;
}

/* --------------------------------------------
 * 3) Rate limiting por IP
 *    - Máx 3 intentos por minuto (60s)
 *    - Guarda contadores y timestamps en sesión
 * -------------------------------------------- */
$attempts_key     = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
$last_attempt_key = 'last_attempt_' . $_SERVER['REMOTE_ADDR'];

if (!isset($_SESSION[$attempts_key]))     $_SESSION[$attempts_key] = 0;
if (!isset($_SESSION[$last_attempt_key])) $_SESSION[$last_attempt_key] = 0;

$current_time = time();
$time_diff    = $current_time - $_SESSION[$last_attempt_key];

if ($_SESSION[$attempts_key] >= 3 && $time_diff < 60) {
    // Bloqueado temporalmente
    $remaining_time = 60 - $time_diff;
    $is_blocked = true;
} else {
    // Si pasó 1 minuto, resetea intentos
    if ($time_diff >= 60) {
        $_SESSION[$attempts_key] = 0;
    }

    /* --------------------------------------------
     * 4) Proceso de login (POST)
     *    - Conecta a LDAP
     *    - Busca DN del usuario con bind admin
     *    - Autentica con DN + password
     *    - Genera token y cookie
     *    - Resuelve grupos (1 búsqueda OR + fallback memberOf)
     * -------------------------------------------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Normaliza entradas de formulario
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Conexión LDAP y configuración
        $conn = ldap_connect($ldap_host);
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        // ldap_start_tls($conn); // ◀️ Descomenta si tu servidor LDAP soporta TLS y lo requieres

        // Bind admin para poder buscar el DN del usuario
        if (@ldap_bind($conn, $admin_dn, $admin_pass)) {

            // Escape seguro para usar $username en Filtros LDAP
            // (Evita LDAP Injection)
            if (function_exists('ldap_escape')) {
                $safeUser = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
            } else {
                // Fallback básico si ldap_escape no existe
                $safeUser = str_replace(['\\','(',')',"\0",'*'], ['\\5c','\\28','\\29','\\00','\\2a'], $username);
            }

            // 4.1) Buscar DN del usuario por atributo de login (uid / cn / mail...)
            //     - si tienes $ldap_login_attr en config.php, lo usamos; si no, 'uid'
            $loginAttr = isset($ldap_login_attr) ? $ldap_login_attr : 'uid';

            $filter  = sprintf("(%s=%s)", $loginAttr, $safeUser);
            $attrs   = ["dn", "uid", "cn", "mail"];

            $search  = @ldap_search($conn, $base_users, $filter, $attrs);
            $entries = $search ? ldap_get_entries($conn, $search) : null;

            if (!empty($entries) && ($entries["count"] ?? 0) > 0) {
                $user_dn = $entries[0]["dn"]; // DN encontrado
                // Intentamos sacar info extra del usuario
                $uid  = $entries[0]['uid'][0]  ?? $username;
                $cn   = $entries[0]['cn'][0]   ?? $username;
                $mail = $entries[0]['mail'][0] ?? null;

                // 4.2) Intento de autenticación real del usuario (bind con su DN+password)
                if (@ldap_bind($conn, $user_dn, $password)) {
                    // ✅ Credenciales correctas

                    // 4.3) Establece sesión y cookie SSO
                    $_SESSION['username']      = $username;
                    $_SESSION['uid_ldap']      = $uid;
                    $_SESSION['display_name']  = $cn;
                    $_SESSION['email']         = $mail;

                    // 🔴 Guardamos la contraseña en sesión para usarla en el SSO a WordPress
                    $_SESSION['ldap_plain_password'] = $password;

                    $token = bin2hex(random_bytes(16));
                    $_SESSION['token'] = $token;
                    setcookie("SSO_TOKEN", $token, time() + 3600, "/"); // 1h

                    // --------------------------------------------
                    // 4.4) Resolver grupos a los que pertenece el usuario
                    //      - Rebind como admin para leer OU=Groups
                    //      - Única búsqueda con OR: (|(memberUid=uid)(member=userDN))
                    //      - Fallback: memberOf desde el propio usuario
                    // --------------------------------------------
                    $user_groups = [];

                    if (@ldap_bind($conn, $admin_dn, $admin_pass)) {
                        // Escapar DN para filtro LDAP si es posible
                        if (function_exists('ldap_escape')) {
                            $safeDn = ldap_escape($user_dn, '', LDAP_ESCAPE_FILTER);
                        } else {
                            $safeDn = $user_dn; // DN normalmente es seguro en filtros simples
                        }

                        // (A) Única búsqueda en OU=Groups: posixGroup || groupOfNames/AD
                        //     - memberUid=<uid> cubre posixGroup
                        //     - member=<DN>    cubre groupOfNames/AD
                        $filter = "(|(memberUid={$safeUser})(member={$safeDn}))";
                        if ($sr = @ldap_search($conn, $base_groups, $filter, ['cn'])) {
                            $gr = ldap_get_entries($conn, $sr);
                            for ($i = 0, $n = $gr['count'] ?? 0; $i < $n; $i++) {
                                if (!empty($gr[$i]['cn'][0])) {
                                    $user_groups[] = $gr[$i]['cn'][0]; // añade CN del grupo
                                }
                            }
                        }

                        // (B) Fallback: si tu servidor expone memberOf en el usuario
                        if ($sr = @ldap_search($conn, $base_users, "(uid={$safeUser})", ['memberOf'])) {
                            $ur = ldap_get_entries($conn, $sr);
                            if (!empty($ur) && (($ur['count'] ?? 0) > 0)) {
                                $u = $ur[0];
                                // memberOf es multi-valor: iteramos y extraemos CN de cada DN
                                for ($i = 0, $m = $u['memberof']['count'] ?? 0; $i < $m; $i++) {
                                    $dn = $u['memberof'][$i];
                                    if (preg_match('/CN=([^,]+)/i', $dn, $mch)) {
                                        $user_groups[] = $mch[1]; // CN extraído del DN
                                    }
                                }
                            }
                        }
                    }

                    // Normaliza grupos: elimina duplicados y vacíos
                    $_SESSION['groups'] = array_values(array_unique(array_filter($user_groups)));

                    // 4.4.1) 🔴 REGISTRAR ACCESO EN BBDD
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO accesos (uid_ldap, fecha)
                            VALUES (:uid, NOW())
                        ");
                        $stmt->execute([
                            ':uid' => $uid
                        ]);
                    } catch (Exception $e) {
                        // Si falla el log, no rompemos el login
                        // error_log("Error registrando acceso: " . $e->getMessage());
                    }

                    // 4.5) Reset rate limit y redirige a home
                    $_SESSION[$attempts_key] = 0;
                    header("Location: /");
                    exit;
                }
            }
        }

        // ❌ Si algo falla (bind admin, no DN, password mala, etc.)
        //    - Aumenta intentos
        //    - Registra timestamp
        //    - Muestra error genérico (sin filtrar info sensible)
        $_SESSION[$attempts_key]++;
        $_SESSION[$last_attempt_key] = $current_time;
        $login_error = "❌ Invalid username or password.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChorizoSQL</title>
    <!-- Font Awesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts for typography -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <!-- Custom styles -->
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <!-- Particles.js background for visual effect -->
    <div id="particles-js"></div>

    <!-- Header section with navigation -->
    <header id="header">
        <?php 
            include '../paginas/inc/header.php';
        ?>
    </header>

    <!-- Hero section with login form -->
    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Secure <span>Login</span></h1>
                <p>Access your secure dashboard with our Single Sign-On system. Enter your credentials to continue to the admin panel.</p>
                <form method="post" class="login-form">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
                <?php if (isset($login_error)): ?>
                    <p style="color: #ff6b6b; margin-top: 1rem;" id="error-message"><?php echo $login_error; ?></p>
                <?php endif; ?>
                <div id="countdown-timer" style="display: none; color: #ff6b6b; margin-top: 0.5rem; font-weight: bold;"></div>
            </div>
        </div>
    </section>

    <!-- Footer section -->
    <footer>
        <?php 
            include '../paginas/inc/footer.php';
        ?>
    </footer>

    <!-- Scroll to top button -->
    <div class="scroll-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Scripts for interactivity -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // Mobile Navigation toggle
        const hamburger = document.querySelector('.hamburger');
        const navLinks = document.querySelector('.nav-links');
        const links = document.querySelectorAll('.nav-links li');

        hamburger.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            hamburger.classList.toggle('active');

            // Animate Links
            links.forEach((link, index) => {
                if (link.style.animation) {
                    link.style.animation = '';
                } else {
                    link.style.animation = `navLinkFade 0.5s ease forwards ${index / 7 + 0.3}s`;
                }
            });
        });

        // Close mobile menu when clicking a link
        links.forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
                hamburger.classList.remove('active');
                links.forEach(link => {
                    link.style.animation = '';
                });
            });
        });

        // Sticky Header on scroll
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            header.classList.toggle('scrolled', window.scrollY > 100);
        });

        // Scroll to Top Button visibility
        const scrollTopBtn = document.querySelector('.scroll-top');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('active');
            } else {
                scrollTopBtn.classList.remove('active');
            }
        });

        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Countdown timer for rate limiting
        <?php if (isset($is_blocked) && $is_blocked): ?>
        let remainingTime = <?php echo $remaining_time; ?>;
        const countdownElement = document.getElementById('countdown-timer');
        const errorMessage = document.getElementById('error-message');
        
        countdownElement.style.display = 'block';
        
        function updateCountdown() {
            if (remainingTime > 0) {
                countdownElement.textContent = `❌ Too many request. Time remaining: ${remainingTime} seconds`;
                remainingTime--;
                setTimeout(updateCountdown, 1000);
            } else {
                countdownElement.style.display = 'none';
                errorMessage.style.display = 'none';
                // Optionally refresh the page to reset the form
                location.reload();
            }
        }
        
        updateCountdown();
        <?php endif; ?>

        // Particles.js Configuration for background animation
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
                    "type": "circle",
                    "stroke": {
                        "width": 0,
                        "color": "#000000"
                    },
                    "polygon": {
                        "nb_sides": 5
                    }
                },
                "opacity": {
                    "value": 0.5,
                    "random": false,
                    "anim": {
                        "enable": false,
                        "speed": 1,
                        "opacity_min": 0.1,
                        "sync": false
                    }
                },
                "size": {
                    "value": 3,
                    "random": true,
                    "anim": {
                        "enable": false,
                        "speed": 40,
                        "size_min": 0.1,
                        "sync": false
                    }
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
                    "speed": 2,
                    "direction": "none",
                    "random": false,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false,
                    "attract": {
                        "enable": false,
                        "rotateX": 600,
                        "rotateY": 1200
                    }
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": {
                        "enable": true,
                        "mode": "grab"
                    },
                    "onclick": {
                        "enable": true,
                        "mode": "push"
                    },
                    "resize": true
                },
                "modes": {
                    "grab": {
                        "distance": 140,
                        "line_linked": {
                            "opacity": 1
                        }
                    },
                    "bubble": {
                        "distance": 400,
                        "size": 40,
                        "duration": 2,
                        "opacity": 8,
                        "speed": 3
                    },
                    "repulse": {
                        "distance": 200,
                        "duration": 0.4
                    },
                    "push": {
                        "particles_nb": 4
                    },
                    "remove": {
                        "particles_nb": 2
                    }
                }
            },
            "retina_detect": true
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();

                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);

                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
