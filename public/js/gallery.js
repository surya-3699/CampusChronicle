// Lightbox for gallery album photos.
// Guarded throughout so this file can be safely included on any page,
// even ones without a #lightbox element on it.
(function () {
    const lightbox = document.getElementById("lightbox");
    const lightboxImage = document.getElementById("lightbox-image");
    const closeBtn = document.querySelector(".close-lightbox");

    if (!lightbox || !lightboxImage) {
        return;
    }

    document.querySelectorAll(".lightbox-trigger").forEach(function (item) {
        item.addEventListener("click", function (e) {
            e.preventDefault();
            lightboxImage.src = this.dataset.image;
            lightbox.style.display = "flex";
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", function () {
            lightbox.style.display = "none";
        });
    }

    lightbox.addEventListener("click", function (e) {
        if (e.target === lightbox) {
            lightbox.style.display = "none";
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            lightbox.style.display = "none";
        }
    });
})();
