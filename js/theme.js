/**
 * theme.js
 * Handles theme switching and persistence via Toggle Switch.
 */


// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
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
});


document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[action="admin_action.php"]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            const action = f.querySelector('input[name="action"]').value;
            if (action === 'assign_next' && !confirm('Assign next queued booking to available slot?')) {
                e.preventDefault();
            }
            if (action === 'toggle_maintenance' && !confirm('Change maintenance status for this slot?')) {
                e.preventDefault();
            }
            if (action === 'cancel_booking' && !confirm('Cancel this booking?')) {
                e.preventDefault();
            }
        });
    });
});
