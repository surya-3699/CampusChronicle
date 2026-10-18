<?php
require_once "app/Controllers/AuthController.php";

$auth = new AuthController();
$auth->register();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Create Account | Campus Chronicle</title>

<link rel="stylesheet"
href="public/css/auth.css">

<link rel="stylesheet"
href="public/css/liquid-glass.css">

<link rel="stylesheet" href="public/css/video-glass-modern.css">

<meta name="cc-csrf" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><script src="public/js/security.js"></script><link rel="stylesheet" href="public/css/performance.css">
<script src="public/js/scene3d.js" defer></script>
</head>

<body class="auth-liquid-glass cc-auth-video-page">

<?php include "app/Views/layouts/background.php"; ?>

<div class="cc-video-overlay" aria-hidden="true"></div>

<div class="auth-container">

    <!-- Left Panel -->

    <div class="brand-panel">

        <img
            src="public/images/logo/loading-logo.png"
            class="brand-logo"
            alt="Campus Chronicle">

        <h1 class="brand-title">

            Campus <span>Chronicle</span>

        </h1>

        <p class="brand-subtitle">

            Every Event. Every Memory. One Platform.

        </p>

        <p style="margin-top:25px;line-height:1.8;color:#f2f2f2;">

            Join Campus Chronicle to register for workshops,
            hackathons, cultural events and technical seminars.

        </p>

        <ul class="brand-features">

            <li>Workshops & Seminars</li>

            <li>Hackathons & Competitions</li>

            <li>Technical & Cultural Events</li>

            <li>Certificates & Gallery</li>

        </ul>

    </div>

    <!-- Right Panel -->

    <div class="auth-card">

<h1 class="auth-title">

Create Account

</h1>

<p class="auth-subtitle">

Join Campus Chronicle

</p>

<?php

if(isset($_SESSION['success'])){

echo "<div class='message success'>".htmlspecialchars((string)$_SESSION['success'], ENT_QUOTES, 'UTF-8')."</div>";

unset($_SESSION['success']);

}

if(isset($_SESSION['error'])){

echo "<div class='message error'>".htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8')."</div>";

unset($_SESSION['error']);

}

?>

<?php $googleButtonText = 'signup_with'; require 'app/Views/auth/social-signup.php'; ?>

<form method="POST" action="index.php?page=signup" id="signupForm"><?= cc_csrf_field(); ?>

<div class="avatar-preview" id="avatarPreview">

<span id="avatarLetter">?</span>

</div>

<div class="form-group">

<label>Full Name</label>

<input
type="text"
name="fullname"
id="fullname"
required>

</div>

<div class="form-group">

<label>Email Address</label>

<input
type="email"
name="email"
required>

</div>

<div class="form-group">

<label>Password</label>

<div class="password-wrapper">

<input
type="password"
name="password"
id="password"
required>

<span
class="toggle-password"
id="togglePassword">

👁

</span>

</div>
<div class="password-info">

    <p class="password-title">
        Password Requirements
    </p>

    <div class="password-strength-container" style="background:rgba(255,255,255,0.1); height:6px; border-radius:3px; margin:10px 0; overflow:hidden;">
        <div id="strengthBar" style="height:100%; width:0%; transition:width 0.3s ease, background 0.3s ease;"></div>
    </div>

    <ul class="requirements">

        <li id="lengthRule">
            At least 8 characters
        </li>

        <li id="upperRule">
            One uppercase letter
        </li>

        <li id="lowerRule">
            One lowercase letter
        </li>

        <li id="numberRule">
            One number
        </li>

        <li id="specialRule">
            One special character
        </li>

    </ul>

    <div
        id="passwordSuccess"
        class="password-success">

        Password meets all requirements.

    </div>

</div>

</div>

<div class="form-group">

<label>Confirm Password</label>

<input
    type="password"
    name="confirm_password"
    id="confirmPassword"
    required>

<div
    id="passwordMatchMessage"
    class="password-match">

</div>

</div>

<button
class="auth-btn"
type="submit"
name="register">

Create Account

</button>

</form>

<div class="bottom-link">

Already have an account?

<a href="index.php?page=login">

Sign In

</a>

</div>

</div>

<script nonce="<?= cc_nonce(); ?>">

const fullname=document.getElementById("fullname");

const avatar=document.getElementById("avatarLetter");

fullname.addEventListener("keyup",()=>{

avatar.innerHTML=

fullname.value.length?

fullname.value.charAt(0).toUpperCase():

"?";

});

const password=document.getElementById("password");

const bar=document.getElementById("strengthBar");

const rules = {
    length: document.getElementById("lengthRule"),
    upper: document.getElementById("upperRule"),
    lower: document.getElementById("lowerRule"),
    number: document.getElementById("numberRule"),
    special: document.getElementById("specialRule")
};

password.addEventListener("keyup",()=>{

let value=password.value;

let score=0;

const checks={

length:value.length>=8,

upper:/[A-Z]/.test(value),

lower:/[a-z]/.test(value),

number:/[0-9]/.test(value),

special:/[^A-Za-z0-9]/.test(value)

};

Object.keys(checks).forEach(key=>{

    if(checks[key]){

        rules[key].classList.remove("invalid");
        rules[key].classList.add("valid");

        score++;

    }else{

        rules[key].classList.remove("valid");
        rules[key].classList.add("invalid");

    }

});

document.getElementById("passwordSuccess").style.display =
score===5 ? "block" : "none";

bar.style.width=(score*20)+"%";

if(score<=2){

bar.style.background="#e74c3c";

}else{

bar.style.background="#2ecc71";

}

});

document.getElementById("togglePassword").onclick=function(){

password.type=

password.type==="password"

?

"text"

:

"password";

};

const confirmPassword =
document.getElementById("confirmPassword");

const passwordMessage =
document.getElementById("passwordMatchMessage");

function validatePasswords(){

    if(confirmPassword.value===""){

        passwordMessage.innerHTML="";

        confirmPassword.classList.remove(
            "input-success",
            "input-error"
        );

        return;
    }

    if(password.value===confirmPassword.value){

        passwordMessage.innerHTML="Passwords match.";

        passwordMessage.className=
        "password-match success";

        confirmPassword.classList.remove("input-error");

        confirmPassword.classList.add("input-success");

    }

    else{

        passwordMessage.innerHTML=
        "Passwords do not match.";

        passwordMessage.className=
        "password-match error";

        confirmPassword.classList.remove("input-success");

        confirmPassword.classList.add("input-error");

    }

}

password.addEventListener("keyup",validatePasswords);

confirmPassword.addEventListener("keyup",validatePasswords);

document.getElementById("signupForm").onsubmit=function(){

    if(password.value!==confirmPassword.value){

        password.focus();

        return false;

    }

    return true;

};

</script>

</body>

</html>
