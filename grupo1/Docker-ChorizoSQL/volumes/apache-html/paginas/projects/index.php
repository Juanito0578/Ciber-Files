<?php
session_start();
require_once '../../sso/sso-check.php';

// --- Grupos / permisos ---
$displayGroups = '—';
if (isset($_SESSION['groups'])) {
    if (is_array($_SESSION['groups']) && count($_SESSION['groups']) > 0) {
        $escaped = array_map('htmlspecialchars', $_SESSION['groups']);
        $displayGroups = implode(', ', $escaped);
    } elseif (is_string($_SESSION['groups']) && $_SESSION['groups'] !== '') {
        $displayGroups = htmlspecialchars($_SESSION['groups']);
    } else {
        $displayGroups = 'No groups';
    }
}
$displayUser = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '—';

$groups = [];
if (!empty($_SESSION['groups']) && is_array($_SESSION['groups'])) {
    $groups = array_map('mb_strtolower', $_SESSION['groups']);
}
$hasGroup = function(array $needles) use ($groups) {
    foreach ($needles as $g) {
        if (in_array(mb_strtolower($g), $groups, true)) return true;
    }
    return false;
};

$isAdmin = $hasGroup(['admins','administrators']);

// PROJECT 1 (tarjetas)
$empresa = $hasGroup(['chorizados','empresa']);
$canProject1 = $isAdmin || $empresa;
$canProject2 = $isAdmin || $empresa;
$canProject3 = $isAdmin || $empresa;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ChorizoSQL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css"/>
</head>
<body>
  <!-- Fondo -->
  <div id="particles-js"></div>

  <!-- Header fijo -->
  <header id="header">
    <?php include '../inc/header.php'; ?>
  </header>

  <!-- Sección proyectos -->
  <section class="hero" id="projects">
    <div class="hero-content">
      <div class="hero-text">
        <h2>Available <span>Projects</span></h2>
        <p>Choose a project to continue.</p>

        <div class="projects-grid" style="margin-top:1.5rem;">

          <?php if ($canProject1): ?>
          <!-- PROJECT 1 -->
          <div class="project-card">
              <div class="project-icon">
                  <svg xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 128 80"
                      width="48" height="48"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="3"
                      stroke-linecap="round"
                      stroke-linejoin="round">
                      <title>Bank</title>
                      <path d="M64 8L24 28h80Z" />
                      <line x1="28" y1="30" x2="100" y2="30" />
                      <path d="M36 32v36M54 32v36M74 32v36M92 32v36" />
                      <path d="M28 70h72M24 74h80M20 78h88" />
                  </svg>
              </div>

              <h3>Secure Card Management</h3>
              <p>Encrypted transactions • Role-based access • Real-time card switching</p>

              <div class="project-actions">
                  <a href="/paginas/projects/project1/" class="btn btn-primary">Register</a>
                  <a href="/paginas/projects/project1/gastos.php" class="btn btn-secondary">Expenses</a>
              </div>
          </div>
          <?php endif; ?>

          <?php if ($canProject2): ?>
          <!-- PROJECT 2: WordPress SSO -->
          <div class="project-card">
              <div class="project-icon">
                  <i class="fab fa-wordpress" style="font-size:3rem;"></i>
              </div>
              <h3>Customer Portal (WordPress)</h3>
              <p>Single Sign-On from ChorizoSQL to the WordPress site.</p>
              <a href="/paginas/projects/project2/" class="btn btn-primary">Open WordPress</a>
          </div>
          <?php endif; ?>

          <?php if ($canProject3): ?>
          <!-- PROJECT 3: Vulnerability scanners -->
          <div class="project-card">
              <div class="project-icon">
                  <i class="fas fa-shield-alt" style="font-size:3rem;"></i>
              </div>
              <h3>Vulnerability Scanners</h3>
              <p>Security tools and vulnerability checks.</p>
              </br>
              <a href="/paginas/projects/project3/" class="btn btn-primary">Open</a>
          </div>
          <?php endif; ?>

          <?php if (!$canProject1 && !$canProject2 && !$canProject3): ?>
            <div class="project-card">
              <h3>No projects available</h3>
              <p>You don't have access to any projects.</p>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <?php include '../inc/footer.php'; ?>
  </footer>

  <!-- Scroll top -->
  <div class="scroll-top"><i class="fas fa-arrow-up"></i></div>

  <!-- Scripts -->
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
        hamburger.classList.remove('active');
        links.forEach(l => l.style.animation = '');
      });
    });

    window.addEventListener('scroll', () => {
      document.getElementById('header').classList.toggle('scrolled', window.scrollY > 100);
    });

    const scrollTopBtn = document.querySelector('.scroll-top');
    window.addEventListener('scroll', () => {
      scrollTopBtn.classList.toggle('active', window.pageYOffset > 300);
    });
    scrollTopBtn.addEventListener('click', () => {
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
