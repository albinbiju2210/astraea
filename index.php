<?php
// index.php
// Public login page (uses includes/header.php and includes/footer.php)
// No direct DB dependency here; login is posted to login_process.php

// Show errors during development (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error = $_GET['error'] ?? '';
$msg   = $_GET['msg'] ?? '';

// include shared header (loads css and theme js)
include __DIR__ . '/includes/header.php';
?>
<div class="page-center">
  <div class="card" role="main" aria-labelledby="login-title">
    <h1 id="login-title">Astraea</h1>
    <div class="lead">Automated Parking made easy — Welcome back</div>

    <?php if ($msg): ?>
      <div class="msg-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="login-tabs" style="display:flex; justify-content:center; gap:20px; margin-bottom:20px;">
        <button type="button" class="tab-btn active" onclick="switchTab('email')" id="tab-email">Email Login</button>
        <button type="button" class="tab-btn" onclick="switchTab('vehicle')" id="tab-vehicle">Vehicle Login</button>
    </div>

    <form action="login_process.php" method="post" autocomplete="off" novalidate id="loginForm">
      
      <!-- Email Login Fields -->
      <div id="email-fields">
          <input class="input" type="email" name="email" id="email" placeholder="Enter Email" required autofocus>
          <input class="input" type="password" name="password" id="password" placeholder="Enter Password" required>
      </div>

      <!-- Vehicle Login Fields -->
      <div id="vehicle-fields" style="display:none;">
          <input class="input" type="text" name="vehicle_number" id="vehicle_number" placeholder="Vehicle Number (e.g. KL-07-AB-1234)" style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase()">
          <input class="input" type="tel" name="phone" id="phone" placeholder="Phone Number">
      </div>

      <button class="btn mt-4" type="submit">Login</button>
    </form>

    <script>
    function switchTab(type) {
        // Update Buttons
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + type).classList.add('active');

        // Toggle Fields
        if (type === 'email') {
            document.getElementById('email-fields').style.display = 'block';
            document.getElementById('vehicle-fields').style.display = 'none';
            
            // Set Required
            document.getElementById('email').required = true;
            document.getElementById('password').required = true;
            document.getElementById('vehicle_number').required = false;
            document.getElementById('phone').required = false;
            
            // Clear others
            document.getElementById('vehicle_number').value = '';
            document.getElementById('phone').value = '';

        } else {
            document.getElementById('email-fields').style.display = 'none';
            document.getElementById('vehicle-fields').style.display = 'block';

            // Set Required
            document.getElementById('email').required = false;
            document.getElementById('password').required = false;
            document.getElementById('vehicle_number').required = true;
            document.getElementById('phone').required = true;

            // Clear others
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
        }
    }
    </script>
    <style>
    .tab-btn {
        background: transparent;
        border: none;
        color: var(--muted);
        font-size: 1rem;
        padding-bottom: 5px;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    .tab-btn.active {
        color: var(--heading-text);
        border-bottom: 2px solid var(--text);
    }
    .tab-btn:hover {
        color: var(--text);
    }
    </style>

    <div class="helper" style="margin-top: 20px;">
      
      <!-- Google Sign In (GSI) -->
      <!-- We use a wrapper to center and ensure spacing -->
      <div style="margin-bottom: 15px; display:flex; justify-content:center; width:100%;">
          <div id="g_id_onload"
               data-client_id="716872942450-u04luilu4ihudm0lffqna22mo9hlo3a7.apps.googleusercontent.com"
               data-callback="handleCredentialResponse"
               data-auto_prompt="false">
          </div>
          <div class="g_id_signin" 
               data-type="standard" 
               data-shape="pill" 
               data-theme="outline" 
               data-text="sign_in_with"
               data-size="large"
               data-logo_alignment="left"
               data-width="380"> <!-- Match approx card width -->
          </div>
      </div>

      <!-- Admin Login as a secondary full-width button -->
      <div class="mb-3 mt-3">
        <a href="admin_login.php" class="btn btn-secondary w-100" style="margin-top: 0;">
            Admin Login
        </a>
      </div>

      <div class="links flex-between" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
        <div><a class="link" href="register.php" style="font-size:0.9rem;">Create account</a></div>
        <div><a class="link" href="forgot_password.php" style="font-size:0.9rem;">Forgot password?</a></div>
      </div>
    </div>
  </div>
</div>

<!-- Google Identity Services -->
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
    function handleCredentialResponse(response) {
        // Send the ID token to your server
        fetch('google_login_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ token: response.credential })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'home.php';
            } else {
                alert('Login failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred during authentication.');
        });
    }
</script>

<?php
// include shared footer (closes body/html)
include __DIR__ . '/includes/footer.php';
