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
            session_start();
            include './paginas/inc/header.php';
        ?>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-text">
                <h4>Welcome to</h4>
                <h1>Chorizo <span>SQL</span></h1>
                <p>Advanced database security and SQL management solutions for modern enterprises.</p>

            </div>
        </div>
    </section>
    <!-- Footer section -->
    <footer>
        <?php 
            include './paginas/inc/footer.php';
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

        // Particles.js Configuration for background animation
                particlesJS("particles-js", {
            "particles": { "number": { "value": 80, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#6c63ff" }, "shape": { "type": "circle", "stroke": { "width": 0, "color": "#000000" },
            "polygon": { "nb_sides": 5 } }, "opacity": { "value": 0.5, "random": false,
            "anim": { "enable": false, "speed": 1, "opacity_min": 0.1, "sync": false } }, "size": { "value": 3, "random": true,
            "anim": { "enable": false, "speed": 40, "size_min": 0.1, "sync": false } }, "line_linked": { "enable": true,
            "distance": 150, "color": "#6c63ff", "opacity": 0.4, "width": 1 }, "move": { "enable": true, "speed": 2,
            "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false, "attract": {
            "enable": false, "rotateX": 600, "rotateY": 1200 } } },
            "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "grab" },
            "onclick": { "enable": true, "mode": "push" }, "resize": true }, "modes": { "grab": { "distance": 140,
            "line_linked": { "opacity": 1 } }, "bubble": { "distance": 400, "size": 40, "duration": 2, "opacity": 8,
            "speed": 3 }, "repulse": { "distance": 200, "duration": 0.4 }, "push": { "particles_nb": 4 },
            "remove": { "particles_nb": 2 } } }, "retina_detect": true
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
