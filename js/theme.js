/**
 * theme.js
 * Handles theme switching and persistence via Toggle Switch.
 */


// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('theme-toggle');
    const body = document.body;

    // 1. Check for saved theme preference
    const savedTheme = localStorage.getItem('astraea-theme');

    // Logic: Default is Dark (No Class). 'theme-light' class enables Light mode.
    // Toggle Switch: Checked = Light Mode, Unchecked = Dark Mode 
    // (Or vice versa? usually Toggle ON is the "Active" non-default state. 
    // Since default is Dark, let's make Toggle ON = Light Mode).

    const isLight = savedTheme === 'light';

    if (isLight) {
        body.classList.add('theme-light');
        if (toggle) toggle.checked = true;
    } else {
        body.classList.remove('theme-light');
        if (toggle) toggle.checked = false;
    }

    // 2. Event Listener
    if (toggle) {
        toggle.addEventListener('change', (e) => {
            if (e.target.checked) {
                // Switch to Light
                body.classList.add('theme-light');
                localStorage.setItem('astraea-theme', 'light');
            } else {
                // Switch back to Dark (Default)
                body.classList.remove('theme-light');
                localStorage.setItem('astraea-theme', 'dark');
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
