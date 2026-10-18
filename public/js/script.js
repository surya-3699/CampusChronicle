document.addEventListener("DOMContentLoaded", () => {

    /* Button Click Animation */

    document.querySelectorAll("button").forEach(btn => {

        btn.addEventListener("mousedown", () => {
            btn.style.transform = "scale(.95)";
        });

        btn.addEventListener("mouseup", () => {
            btn.style.transform = "scale(1)";
        });

        btn.addEventListener("mouseleave", () => {
            btn.style.transform = "scale(1)";
        });

    });

    /* Navbar Active Effect */

    const links = document.querySelectorAll("nav ul li a");

    links.forEach(link => {

        link.addEventListener("click", () => {
            links.forEach(l => l.classList.remove("active"));
            link.classList.add("active");
        });

    });

    /* ===========================
       PROFILE DROPDOWN
    =========================== */

    const profileDropdown = document.getElementById("profileDropdown");

    if (profileDropdown) {

        const toggle = profileDropdown.querySelector(".cc-dropdown-toggle");

        if (toggle) {
            toggle.addEventListener("click", function (e) {
                e.stopPropagation();
                profileDropdown.classList.toggle("open");
            });
        }

        document.addEventListener("click", function () {
            profileDropdown.classList.remove("open");
        });

    }

});
