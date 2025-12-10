<a href="/" class="logo">Chorizo<span>SQL</span></a>

<ul class="nav-links">
    <li><a href="/">Home</a></li>
    <li><a href="/paginas/projects/">Projects</a></li>

    <?php if (isset($_COOKIE['SSO_TOKEN']) && isset($_SESSION['username'])): ?>
        
        <!-- ICONO DE USUARIO -->
        <li class="user-menu">
            <a href="#" class="user-icon">
                <i class="fas fa-user-circle"></i>
            </a>

            <!-- DESPLEGABLE -->
            <div class="user-dropdown">
                <p class="user-name"><strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong></p>
                <p class="user-email"><?php echo htmlspecialchars($_SESSION["email"] ?? ""); ?></p>
                <hr>
                <a href="/paginas/administration/">Panel</a>
                <a href="/paginas/perfil.php">Profile</a>
                <a href="/sso/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </li>

    <?php else: ?>
        <li>
            <a href="/sso/" title="Log in">
                <i class="fas fa-sign-in-alt"></i>
            </a>
        </li>
    <?php endif; ?>
</ul>

<div class="hamburger">
    <div></div>
    <div></div>
    <div></div>
</div>

<style>
    .user-menu {
        position: relative;
    }

    .user-icon i {
        font-size: 1.9rem;
        color: var(--light);
        cursor: pointer;
        transition: .2s;
    }

    .user-icon i:hover {
        color: var(--primary);
    }

    .user-dropdown {
        position: absolute;
        top: 130%;               /* un poco más separado del icono */
        right: 0;
        width: 320px;            /* MÁS ANCHO */
        background: rgba(16,16,26,0.97);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 18px;     /* bordes más redondeados */
        padding: 1.2rem 1.4rem;  /* MÁS PADDING */
        display: none;
        flex-direction: column;
        gap: .6rem;              /* más espacio entre líneas */
        box-shadow: 0 14px 32px rgba(0,0,0,.55);
        animation: dropdownFade .25s ease forwards;
        z-index: 50;
    }

    .user-dropdown p {
        margin: 0;
    }

    .user-dropdown .user-name {
        font-size: 1rem;
        color: #ffffff;
    }

    .user-dropdown .user-email {
        font-size: 0.9rem;
        color: rgba(230,230,240,0.85);
    }

    .user-dropdown hr {
        margin: 0.6rem 0 0.4rem;
        border: none;
        border-top: 1px solid rgba(255,255,255,0.08);
    }

    .user-dropdown a {
        color: var(--light);
        text-decoration: none;
        padding: .35rem 0;
        display: block;
        font-size: 0.9rem;
    }

    .user-dropdown a:hover {
        color: var(--primary);
    }

    .logout {
        color: #ff6b81 !important;
        margin-top: 0.4rem;
    }

    @keyframes dropdownFade {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const userIcon = document.querySelector(".user-icon");
        const dropdown = document.querySelector(".user-dropdown");

        if (userIcon && dropdown) {
            userIcon.addEventListener("click", (e) => {
                e.preventDefault();
                dropdown.style.display = dropdown.style.display === "flex" ? "none" : "flex";
            });

            document.addEventListener("click", (e) => {
                if (!e.target.closest(".user-menu")) {
                    dropdown.style.display = "none";
                }
            });
        }
    });
</script>
