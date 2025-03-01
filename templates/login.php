<?php
// Generate CSRF token if one doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Track login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

// Check if user is rate limited
$rateLimited = false;
if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < 300) {
    $rateLimited = true;
    $waitTime = 300 - (time() - $_SESSION['last_attempt']);
}

// Handle login form submission
if (isset($_POST['login']) && !$rateLimited) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        // Update attempt tracking
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt'] = time();

        $username = $_POST['username'];
        $password = $_POST['password'];
        // We'll handle remember me with JavaScript

        $loginResult = $userManager->verifyLogin($username, $password);
        if ($loginResult['success']) {
            // Reset login attempts on success
            $_SESSION['login_attempts'] = 0;

            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $loginResult['role'];
            $_SESSION['show_landing'] = true;

            // Log successful login
            if (isset($activityLogger)) {
                $activityLogger->logActivity($username, 'login_success', '');
            }

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Invalid username or password!";

            // Log failed login attempt
            if (isset($activityLogger)) {
                $activityLogger->logActivity($username, 'login_failed', '');
            }
        }
    }
}
?>

<div class="login-container">
    <div class="login-card">
        <form method="post" class="login-form" id="loginForm" novalidate>
            <h4 class="text-center mb-4">
                <i class="bi bi-shield-lock me-2"></i>
                Sign In
            </h4>

            <?php if (file_exists('users.json')): ?>
                <?php
                $usersJson = file_get_contents('users.json');
                $users = json_decode($usersJson, true);
                if (isset($users['admin']) && isset($users['admin']['is_default']) && $users['admin']['is_default'] === true):
                    ?>
                    <div class="alert alert-info mb-4 d-flex align-items-center" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <div>Default admin credentials are available:<br>Username: <strong>admin</strong><br>Password: <strong>admin123</strong></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <?php if (isset($error)): ?>
                <div class="alert alert-danger mb-4 d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($rateLimited): ?>
                <div class="alert alert-warning mb-4 d-flex align-items-center" role="alert">
                    <i class="bi bi-clock-fill me-2"></i>
                    <div>Too many login attempts. Please try again in <?php echo ceil($waitTime / 60); ?> minutes.</div>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" name="username" id="username" class="form-control"
                           required autocomplete="username"
                           placeholder="Enter your username"
                           <?php echo $rateLimited ? 'disabled' : ''; ?>>
                    <div class="invalid-feedback">Please enter your username</div>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label for="password" class="form-label">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-key"></i>
                    </span>
                    <input type="password" name="password" id="password" class="form-control"
                           required autocomplete="current-password"
                           placeholder="Enter your password"
                           <?php echo $rateLimited ? 'disabled' : ''; ?>>
                    <button class="btn btn-outline-secondary password-toggle" type="button" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                    <div class="invalid-feedback">Please enter your password</div>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                <label class="form-check-label" for="remember_me">
                    <i class="bi bi-person-check me-1"></i>
                    Remember my username
                </label>
            </div>

            <button type="submit" name="login" class="btn btn-primary w-100 py-2 mb-3 icon-btn"
                <?php echo $rateLimited ? 'disabled' : ''; ?>>
                <i class="bi bi-box-arrow-in-right"></i>
                Sign In
            </button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Username remember feature using localStorage
        const usernameField = document.getElementById('username');
        const rememberCheckbox = document.getElementById('remember_me');
        
        // Check if we have a stored username
        const storedUsername = localStorage.getItem('rememberedUsername');
        if (storedUsername) {
            usernameField.value = storedUsername;
            rememberCheckbox.checked = true;
        }
        
        // Handle form submission to save username
        const form = document.getElementById('loginForm');
        if (form) {
            form.addEventListener('submit', function() {
                if (rememberCheckbox.checked) {
                    localStorage.setItem('rememberedUsername', usernameField.value);
                } else {
                    localStorage.removeItem('rememberedUsername');
                }
            });
            
            // Form validation
            form.addEventListener('submit', function (event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                this.classList.add('was-validated');
            });
        }
        
        // Password visibility toggle
        const passwordToggleBtn = document.querySelector('.password-toggle');
        const passwordInput = document.getElementById('password');

        if (passwordToggleBtn && passwordInput) {
            passwordToggleBtn.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Toggle icon
                const icon = this.querySelector('i');
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            });
        }
        
        // Add logout handler to clear localStorage if needed
        const logoutButtons = document.querySelectorAll('button[name="logout"]');
        logoutButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                if (!rememberCheckbox.checked) {
                    localStorage.removeItem('rememberedUsername');
                }
            });
        });
    });
</script>
