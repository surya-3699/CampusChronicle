<?php
require_once "app/Controllers/AuthController.php";

$auth = new AuthController();
$auth->forgotPassword();
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Forgot Password | Campus Chronicle</title>

<link rel="stylesheet" href="public/css/auth.css">
<link rel="stylesheet" href="public/css/liquid-glass.css">

<link rel="stylesheet" href="public/css/video-glass-modern.css">

<meta name="cc-csrf" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><script src="public/js/security.js"></script><link rel="stylesheet" href="public/css/performance.css">
<script src="public/js/scene3d.js" defer></script>
</head>

<body class="auth-liquid-glass cc-auth-video-page">

<?php include "app/Views/layouts/background.php"; ?>

<div class="cc-video-overlay" aria-hidden="true"></div>

<div class="auth-container">

    <div class="brand-panel">

        <img
            src="public/images/logo/loading-logo.png"
            class="brand-logo"
            alt="Campus Chronicle">

        <h1 class="brand-title">
            Campus <span>Chronicle</span>
        </h1>

        <p class="brand-subtitle">
            Reset your password securely.
        </p>

    </div>

    <div class="auth-card">

        <h2 class="auth-title">Forgot Password</h2>

        <p class="auth-subtitle">
            Open your campus support recovery link, then choose a new password.
        </p>

        <?php if(isset($_SESSION['success'])): ?>

            <div class="message success">
                <?= htmlspecialchars((string)$_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <?php unset($_SESSION['success']); ?>

        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>

            <div class="message error">
                <?= htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>

        <form method="POST" action="index.php?page=forgot-password"><?= cc_csrf_field(); ?><input type="hidden" name="reset_token" value="<?= htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    required>

            </div>

            <div class="form-group">

                <label>New Password</label>

                <input
                    type="password"
                    name="password"
                    required>

            </div>

            <div class="form-group">

                <label>Confirm Password</label>

                <input
                    type="password"
                    name="confirm_password"
                    required>

            </div>

            <button
                type="submit"
                name="reset_password"
                class="auth-btn">

                Reset Password

            </button>

        </form>

        <div class="bottom-link">

            Remember your password?

            <a href="index.php?page=login">
                Sign In
            </a>

        </div>

    </div>

</div>

</body>
</html>