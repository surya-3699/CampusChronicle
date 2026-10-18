(function () {
    const activityBtn = document.getElementById("activityBtn");
    const activityPanel = document.getElementById("activityPanel");
    const activityCount = document.getElementById("activityCount");

    if (!activityBtn || !activityPanel) return;

    const closePanel = () => {
        activityPanel.classList.remove("show");
        activityBtn.setAttribute("aria-expanded", "false");
        activityPanel.setAttribute("aria-hidden", "true");
    };

    const openPanel = () => {
        activityPanel.classList.add("show");
        activityBtn.setAttribute("aria-expanded", "true");
        activityPanel.setAttribute("aria-hidden", "false");
    };

    activityBtn.addEventListener("click", function (event) {
        event.stopPropagation();
        if (activityPanel.classList.contains("show")) {
            closePanel();
        } else {
            openPanel();
        }
    });

    activityPanel.addEventListener("click", function (event) {
        event.stopPropagation();
    });

    document.addEventListener("click", closePanel);

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") closePanel();
    });

    activityPanel.querySelectorAll(".activity-card[data-id]").forEach(function (card) {
        card.addEventListener("click", function (event) {
            event.preventDefault();

            const notificationId = this.dataset.id;
            const redirectUrl = this.href;

            if (!notificationId) {
                window.location.href = redirectUrl;
                return;
            }

            fetch("index.php?page=mark-notification-read", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                body: "id=" + encodeURIComponent(notificationId)
            })
                .then(function (response) {
                    if (!response.ok) throw new Error("Notification request failed");
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.success) {
                        this.classList.remove("unread");
                        this.remove();
                        if (activityCount) {
                            const current = parseInt(activityCount.textContent, 10) || 0;
                            if (current <= 1) { activityCount.remove(); }
                            else { activityCount.textContent = String(current - 1); }
                        }
                        if (!activityPanel.querySelector(".activity-card")) {
                            const empty = document.createElement("div");
                            empty.className = "activity-empty";
                            empty.innerHTML = '<div class="activity-empty-icon"><i class="bi bi-check2-circle"></i></div><h4>You are all caught up</h4><p>There are no new notifications right now.</p>';
                            activityPanel.querySelector(".activity-list")?.appendChild(empty);
                        }
                    }
                }.bind(this))
                .catch(function () {
                    // The navigation below still takes the user to the target page.
                })
                .finally(function () {
                    window.location.href = redirectUrl;
                });
        });
    });
})();
