<?php
session_start();

// Clear session variables
session_unset();
session_destroy();

// Clear cookies
setcookie("SSO_TOKEN", "", time() - 3600, "/");

// Redirect to home page
header("Location: /");
exit;
?>
