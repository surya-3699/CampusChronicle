<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Campus Chronicle</title>
<?php include "app/Views/layouts/meta.php"; ?>

<link rel="stylesheet"
href="public/css/welcome.css">

<link rel="stylesheet"
href="public/css/liquid-glass.css">

<meta name="cc-csrf" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><script src="public/js/security.js"></script></head>

<body class="welcome-liquid-glass">

<!-- ===========================================
            LOADING SCREEN
============================================ -->

<section id="loading-screen">

    <div class="loader-wrapper">

        <!-- Animated Ring -->

        <div class="outer-ring"></div>

        <div class="inner-ring"></div>

        <!-- Logo -->

        <div class="logo-wrapper">

            <img
            src="public/images/logo/loading-logo.png"
            alt="Campus Chronicle"
            class="loading-logo">

        </div>

        <!-- Text -->

        <div class="loading-text">

            <h2>Campus Chronicle</h2>

            <p id="loadingMessage">

                Preparing Your Experience

            </p>

        </div>

    </div>

</section>


<!-- ===========================================
            WELCOME SCREEN
============================================ -->

<section id="welcome-screen">

    <div class="welcome-container">

        <img
        src="public/images/logo/loading-logo.png"
        class="welcome-logo"
        alt="Campus Chronicle">

        <h1>

            CAMPUS CHRONICLE

        </h1>

        <h3>

            Connect • Discover • Participate

        </h3>

        <p>

            Your Gateway to Campus Innovation

        </p>

        <button id="enterBtn">

            ENTER CAMPUS

        </button>

        <br>

        <a href="index.php?page=home" class="skip-intro">Skip intro &rarr;</a>

    </div>

</section>


<script src="public/js/welcome.js"></script>

</body>

</html>
