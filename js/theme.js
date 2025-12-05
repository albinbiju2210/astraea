/**
 * theme.js
 * Handles theme switching and persistence.
 */

(function () {
    // 1. Check for saved theme preference
    const savedTheme = localStorage.getItem('astraea-theme');
    if (savedTheme) {
        document.body.classList.add(`theme-${savedTheme}`);
    } else {
        // Optional user system preference check could go here
    }

    // 2. Event Listener for theme buttons
    // Using delegation or just selecting all buttons
    // Since the buttons are in the header, they should be available when DOM is ready.

    document.addEventListener('DOMContentLoaded', () => {
        const buttons = document.querySelectorAll('.theme-btn');

        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const theme = btn.getAttribute('data-theme');

                // Clear existing themes (assuming theme-light, theme-dark, theme-blue)
                document.body.classList.remove('theme-light', 'theme-dark', 'theme-blue');

                if (theme !== 'light') {
                    // "light" is default (no class), so only add if not light
                    // But wait, css style.css says:
                    // body.theme-dark { ... }
                    // body.theme-blue { ... }
                    // Default is light vars in root.
                    // So we modify this slightly.
                    document.body.classList.add(`theme-${theme}`);
                }

                // Save preference
                localStorage.setItem('astraea-theme', theme);
            });
        });
    });
})();
