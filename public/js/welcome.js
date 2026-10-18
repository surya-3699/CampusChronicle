// ===========================================
// CAMPUS CHRONICLE
// PREMIUM WELCOME SCRIPT
// ===========================================

const loadingScreen = document.getElementById("loading-screen");
const welcomeScreen = document.getElementById("welcome-screen");
const enterBtn = document.getElementById("enterBtn");
const loadingMessage = document.getElementById("loadingMessage");

let redirected = false;

/* ===========================================
   Loading Text Animation
=========================================== */

const messages = [
    "Preparing Your Experience",
    "Preparing Your Experience.",
    "Preparing Your Experience..",
    "Preparing Your Experience..."
];

let msgIndex = 0;

setInterval(() => {

    if (loadingMessage) {
        loadingMessage.innerText = messages[msgIndex];
        msgIndex = (msgIndex + 1) % messages.length;
    }

}, 400);

/* ===========================================
   Show Welcome Screen
=========================================== */

setTimeout(() => {

    loadingScreen.style.opacity = "0";

    setTimeout(() => {

        loadingScreen.style.display = "none";

        welcomeScreen.style.display = "flex";

        setTimeout(() => {

            welcomeScreen.style.opacity = "1";

        }, 100);

    }, 800);

}, 2000);

/* ===========================================
   Enter Button
=========================================== */

function goHome() {

    if (redirected) return;

    redirected = true;

    welcomeScreen.style.opacity = "0";

    setTimeout(() => {

        window.location.href = "index.php?page=home";

    }, 700);

}

if (enterBtn) {

    enterBtn.addEventListener("click", goHome);

}

/* ===========================================
   Auto Redirect
=========================================== */

setTimeout(() => {

    goHome();

}, 5000);