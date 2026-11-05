document.addEventListener("DOMContentLoaded", () => {
    const savedLoginFields = [...document.querySelectorAll("[data-saved-login]")];
    if (savedLoginFields.length) {
        const loginForm = savedLoginFields[0].form;
        const unlocked=new WeakSet();
        const clearRestoredLogin = () => {
            savedLoginFields.forEach((input) => {
                if(unlocked.has(input))return;
                input.value = "";
                input.readOnly = true;
            });
        };
        if (performance.getEntriesByType("navigation")[0]?.type === "reload") {
            clearRestoredLogin();
        }
        savedLoginFields.forEach((input) => {
            const enableSavedLogin = () => { unlocked.add(input);input.readOnly = false; };
            input.addEventListener("pointerdown", enableSavedLogin);
            input.addEventListener("keydown", enableSavedLogin);
        });
        window.addEventListener("pageshow", (event) => {
            if (event.persisted || performance.getEntriesByType('navigation')[0]?.type==='reload') clearRestoredLogin();
        });
        if(performance.getEntriesByType('navigation')[0]?.type==='reload') [100,500,1500].forEach(delay=>setTimeout(clearRestoredLogin,delay));
        loginForm?.addEventListener("submit", (event) => {
            savedLoginFields.forEach((input) => { input.readOnly = false; });
            if (!loginForm.reportValidity()) event.preventDefault();
        });
    }

    // Password visibility
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;

            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            if (button.hasAttribute("data-password-icon")) {
                button.dataset.visible = String(isPassword);
                button.setAttribute("aria-pressed", String(isPassword));
            } else {
                button.textContent = isPassword ? "Hide" : "Show";
            }
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
