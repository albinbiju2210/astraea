/**
 * theme.js
 * Handles theme switching and persistence via Toggle Switch.
 */

(function () {
    const toggle = document.getElementById('theme-toggle');

    // 1. Check for saved theme preference
    const savedTheme = localStorage.getItem('astraea-theme');

    // Apply saved theme
    if (savedTheme === 'dark') {
        document.body.classList.add('theme-dark');
        if (toggle) toggle.checked = true;
    } else {
        // Default is light
        document.body.classList.remove('theme-dark');
        if (toggle) toggle.checked = false;
    }

    // 2. Event Listener for toggle
    if (toggle) {
        toggle.addEventListener('change', (e) => {
            if (e.target.checked) {
                document.body.classList.add('theme-dark');
                localStorage.setItem('astraea-theme', 'dark');
            } else {
                document.body.classList.remove('theme-dark');
                localStorage.setItem('astraea-theme', 'light');
            }
        });
    }
})();
