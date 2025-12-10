<?php
session_start();
require_once '../../../sso/sso-check.php';
require_once '../../../Conf/config.php';

$displayUser = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'MKDEH USER';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ChorizoSQL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Global CSS -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <style>
        .pricing-section {
            width: 100%;
            padding: 7rem 5% 4rem;
            display: flex;
            justify-content: center;
            align-items: stretch;
            position: relative;
            z-index: 1;
            flex: 1 0 auto;
        }

        .pricing-container {
            width: 100%;
            max-width: 1100px;
            background: rgba(26, 26, 46, 0.95);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 20px 45px rgba(0,0,0,0.6);
            padding: 2.5rem 2.2rem;
        }

        .pricing-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .pricing-title {
            font-size: 1.9rem;
            color: var(--light);
            margin-bottom: 0.5rem;
        }

        .pricing-subtitle {
            font-size: 0.95rem;
            color: rgba(245,245,247,0.8);
            max-width: 650px;
            margin: 0 auto;
        }

        .pricing-subtitle strong {
            color: var(--primary);
        }

        .pricing-user {
            font-size: 0.9rem;
            margin-top: 0.4rem;
            color: rgba(245,245,247,0.7);
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .pricing-card {
            background: rgba(14, 14, 30, 0.9);
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            padding: 1.7rem 1.6rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .pricing-card.best {
            border-color: var(--primary);
            box-shadow: 0 0 0 1px rgba(108,99,255,0.4),
                        0 18px 40px rgba(0,0,0,0.7);
        }

        .pricing-badge {
            position: absolute;
            top: 1.2rem;
            right: 1.3rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            background: rgba(108,99,255,0.16);
            color: #c1bdff;
            border: 1px solid rgba(108,99,255,0.6);
        }

        .pricing-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 0.3rem;
        }

        .pricing-description {
            font-size: 0.85rem;
            color: rgba(245,245,247,0.75);
            margin-bottom: 1rem;
        }

        .pricing-price {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.2rem;
        }

        .pricing-price span {
            font-size: 0.8rem;
            font-weight: 400;
            color: rgba(245,245,247,0.7);
        }

        .pricing-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0 1.3rem;
            font-size: 0.85rem;
            color: rgba(245,245,247,0.85);
        }

        .pricing-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
            margin-bottom: 0.4rem;
        }

        .pricing-list li i {
            margin-top: 0.12rem;
            font-size: 0.75rem;
        }

        .pricing-list .enabled i {
            color: #2ecc71;
        }

        .pricing-list .disabled {
            color: rgba(245,245,247,0.4);
        }
        .pricing-list .disabled i {
            color: rgba(245,245,247,0.3);
        }

        .pricing-cta {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .pricing-card button,
        .pricing-card a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.6rem 1.4rem;
            border-radius: 999px;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--primary);
            color: var(--light);
            text-decoration: none;
            transition: var(--transition);
        }

        .pricing-card button:hover,
        .pricing-card a.btn:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }

        .pricing-note {
            font-size: 0.8rem;
            color: rgba(245,245,247,0.55);
        }

        .pricing-footer {
            text-align: center;
            margin-top: 2.5rem;
            font-size: 0.85rem;
            color: rgba(245,245,247,0.7);
        }

        .pricing-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        .pricing-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .pricing-container {
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

<section class="hero" id="pricing">
    <div class="pricing-section">
        <div class="pricing-container">

            <div class="pricing-header">
                <h1 class="pricing-title">Upgrade your plan</h1>
                <p class="pricing-subtitle">
                    Hi <strong><?php echo $displayUser; ?></strong>, you don’t currently have access
                    to the <strong>Vulnerability scanner</strong>. Choose a plan or contact your
                    administrator to request access.
                </p>
                <p class="pricing-user">
                    If you think this is a mistake, please contact the IT team.
                </p>
            </div>

            <div class="pricing-grid">
                <!-- Basic plan -->
                <div class="pricing-card">
                    <div>
                        <div class="pricing-name">Basic</div>
                        <div class="pricing-description">
                            For standard users who only need to view their own data
                            and basic reports.
                        </div>
                        <div class="pricing-price">
                            Free
                            <span>/ included in your account</span>
                        </div>

                        <ul class="pricing-list">
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Access to personal dashboard</span>
                            </li>
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Basic statistics and activity logs</span>
                            </li>
                            <li class="disabled">
                                <i class="fas fa-minus"></i>
                                <span>No access to network vulnerability scanning</span>
                            </li>
                            <li class="disabled">
                                <i class="fas fa-minus"></i>
                                <span>No export of advanced security reports</span>
                            </li>
                        </ul>
                    </div>
                    <div class="pricing-cta">
                        <button type="button" disabled>
                            Current plan
                        </button>
                        <span class="pricing-note">
                            You are currently using this plan.
                        </span>
                    </div>
                </div>

                <!-- Pro plan -->
                <div class="pricing-card best">
                    <div class="pricing-badge">Recommended</div>
                    <div>
                        <div class="pricing-name">Pro (Security)</div>
                        <div class="pricing-description">
                            Ideal for administrators and security teams that need
                            on-demand vulnerability scans.
                        </div>
                        <div class="pricing-price">
                            19€ <span>/ month per admin user</span>
                        </div>

                        <ul class="pricing-list">
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Everything included in <strong>Basic</strong></span>
                            </li>
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Access to the <strong>Vulnerability scanner</strong></span>
                            </li>
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Export of security reports (CSV / PDF)</span>
                            </li>
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Priority support with the IT team</span>
                            </li>
                        </ul>
                    </div>
                    <div class="pricing-cta">
                        <a class="btn" href="mailto:it@example.com?subject=Request%20Pro%20plan%20access">
                            <i class="fas fa-paper-plane"></i>
                            Request upgrade
                        </a>
                        <span class="pricing-note">
                            This will open your email client to contact the
                            administrators. Adjust the address in the code.
                        </span>
                    </div>
                </div>

                <!-- Enterprise plan -->
                <div class="pricing-card">
                    <div>
                        <div class="pricing-name">Enterprise</div>
                        <div class="pricing-description">
                            For companies that need custom integrations, additional
                            security controls and dedicated support.
                        </div>
                        <div class="pricing-price">
                            Custom
                            <span>/ per company</span>
                        </div>

                        <ul class="pricing-list">
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Everything included in <strong>Pro</strong></span>
                            </li>
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Integration with SIEM / ticketing tools</span>
                            </li>
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Custom security policies and workflows</span>
                            </li>
                            <li class="enabled">
                                <i class="fas fa-check"></i>
                                <span>Dedicated support and onboarding</span>
                            </li>
                        </ul>
                    </div>
                    <div class="pricing-cta">
                        <a class="btn" href="mailto:sales@example.com?subject=Enterprise%20plan%20information">
                            <i class="fas fa-handshake"></i>
                            Contact sales
                        </a>
                        <span class="pricing-note">
                            Use this for larger internal teams or external clients.
                        </span>
                    </div>
                </div>
            </div>

            <div class="pricing-footer">
                Don’t want to upgrade now?&nbsp;
                <a href="/index.php">Go back to main dashboard</a>
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
