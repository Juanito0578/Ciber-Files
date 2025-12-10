<?php
session_start();

// Check if user is logged in

if (!isset($_COOKIE['SSO_TOKEN']) || !isset($_SESSION['username'])) {
    // Redirigir al SSO sin parámetros
    header("Location: /sso/");
    exit;
}

// If logged in, continue with the page
?>


