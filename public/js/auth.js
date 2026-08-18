document.addEventListener("DOMContentLoaded", () => {
    // Password visibility
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;

            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            button.textContent = isPassword ? "Hide" : "Show";
            button.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
        });
    });

    // Account type cards
    document.querySelectorAll("[data-account-type]").forEach((card) => {
        card.addEventListener("click", () => {
            const type = card.dataset.accountType;
            const target = card.dataset.target;

            if (target) {
                const url = new URL(target, window.location.origin);
                url.searchParams.set("type", type);
                window.location.href = url.toString();
            }
        });

        card.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                card.click();
            }
        });
    });
});
