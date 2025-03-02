<?php

/**
 * Class UserManager
 *
 * Handles user account management including creation, authentication, and role management
 */
class UserManager
{
    // File path for storing user data in JSON format
    private string $usersFile = 'json/users.json';

    // Constants for roles
    public const ROLE_ADMIN = 'admin';
    public const ROLE_VIEWER = 'viewer';

    // Storage for remember-me tokens
    private string $tokensFile = 'json/remember_tokens.json';

    // Password requirements
    private int $minPasswordLength = 8;

    /**
     * Constructor with option to specify custom file path
     */
    public function __construct(?string $usersFilePath = null)
    {
        if ($usersFilePath !== null) {
            $this->usersFile = $usersFilePath;
        }

        // Create users file if it doesn't exist
        if (!file_exists($this->usersFile)) {
            file_put_contents($this->usersFile, json_encode([], JSON_PRETTY_PRINT));
            chmod($this->usersFile, 0600); // Secure file permissions
        }

        // Create tokens file if it doesn't exist
        if (!file_exists($this->tokensFile)) {
            file_put_contents($this->tokensFile, json_encode([], JSON_PRETTY_PRINT));
            chmod($this->tokensFile, 0600); // Secure file permissions
        }
    }

    /**
     * Retrieve all users, sorted alphabetically by username
     *
     * @return array User data
     */
    public function getUsers(): array
    {
        if (!file_exists($this->usersFile)) {
            return [];
        }

        $users = json_decode(file_get_contents($this->usersFile), true) ?: [];
        ksort($users, SORT_STRING | SORT_FLAG_CASE); // Sort users alphabetically, case-insensitive
        return $users;
    }

    /**
     * Add a new user
     *
     * @param string $username Username
     * @param string $password Password (will be hashed)
     * @param string $role User role (admin or viewer)
     * @return array Status of the operation
     */
    public function addUser(string $username, string $password, string $role): array
    {
        // Validate input parameters
        if (empty($username) || empty($password) || empty($role)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        // Validate username format (alphanumeric + underscore)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
        }

        // Validate password length
        if (strlen($password) < $this->minPasswordLength) {
            return ['success' => false, 'message' => "Password must be at least {$this->minPasswordLength} characters"];
        }

        // Validate role
        if ($role !== self::ROLE_ADMIN && $role !== self::ROLE_VIEWER) {
            return ['success' => false, 'message' => 'Invalid role specified'];
        }

        // Get current users
        $users = $this->getUsers();

        // Check if username already exists
        if (isset($users[$username])) {
            return ['success' => false, 'message' => 'Username already exists'];
        }

        // Add new user with hashed password
        $users[$username] = [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s'),
            'last_login' => null
        ];

        // Save updated users list
        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => 'User created successfully'];
        }

        return ['success' => false, 'message' => 'Error saving user'];
    }

    /**
     * Delete a user
     *
     * @param string $username Username to delete
     * @return array Status of the operation
     */
    public function deleteUser(string $username): array
    {
        $users = $this->getUsers();
        if (!isset($users[$username])) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Remove user from the array
        unset($users[$username]);

        // Also remove any remember-me tokens for this user
        $this->removeAllTokensForUser($username);

        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => 'User deleted successfully'];
        }

        return ['success' => false, 'message' => 'Error deleting user'];
    }

    /**
     * Change a user's password
     *
     * @param string $username Username
     * @param string $newPassword New password
     * @return array Status of the operation
     */
    public function changePassword(string $username, string $newPassword): array
    {
        // Validate password length
        if (strlen($newPassword) < $this->minPasswordLength) {
            return ['success' => false, 'message' => "Password must be at least {$this->minPasswordLength} characters"];
        }

        $users = $this->getUsers();
        if (!isset($users[$username])) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Update password with new hash
        $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[$username]['password_changed_at'] = date('Y-m-d H:i:s');

        // Invalidate existing remember-me tokens for security
        $this->removeAllTokensForUser($username);

        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        }

        return ['success' => false, 'message' => 'Error changing password'];
    }

    /**
     * Change a user's role
     *
     * @param string $username Username
     * @param string $newRole New role
     * @return array Status of the operation
     */
    public function changeRole(string $username, string $newRole): array
    {
        // Validate role
        if ($newRole !== self::ROLE_ADMIN && $newRole !== self::ROLE_VIEWER) {
            return ['success' => false, 'message' => 'Invalid role specified'];
        }

        $users = $this->getUsers();
        if (!isset($users[$username])) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Update user role
        $users[$username]['role'] = $newRole;
        $users[$username]['role_changed_at'] = date('Y-m-d H:i:s');

        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => 'Role changed successfully'];
        }

        return ['success' => false, 'message' => 'Error changing role'];
    }

    /**
     * Verify login credentials
     *
     * @param string $username Username
     * @param string $password Password
     * @return array Authentication result with success status and role if successful
     */
    public function verifyLogin(string $username, string $password): array
    {
        $users = $this->getUsers();
        if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
            // Check if password needs rehashing (if PHP's password hashing algorithm has been updated)
            if (password_needs_rehash($users[$username]['password'], PASSWORD_DEFAULT)) {
                $users[$username]['password'] = password_hash($password, PASSWORD_DEFAULT);
                $this->saveUsers($users);
            }

            // Update last login timestamp
            $users[$username]['last_login'] = date('Y-m-d H:i:s');
            $this->saveUsers($users);

            return [
                'success' => true,
                'role' => $users[$username]['role'],
                'message' => 'Login successful'
            ];
        }

        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    /**
     * Store a remember-me token for a user
     *
     * @param string $username Username
     * @param string $selector Token selector (public part)
     * @param string $hashedValidator Hashed validator (secret part)
     * @return bool Success status
     */
    public function storeRememberMeToken(string $username, string $selector, string $hashedValidator): bool
    {
        $tokens = $this->getTokens();

        // Add expiry date (30 days from now)
        $expiry = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

        $tokens[$selector] = [
            'username' => $username,
            'token' => $hashedValidator,
            'expires' => $expiry
        ];

        return file_put_contents($this->tokensFile, json_encode($tokens, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Verify a remember-me token
     *
     * @param string $selector Token selector
     * @param string $validator Raw validator value
     * @return array|null User data if token is valid, null otherwise
     */
    public function verifyRememberMeToken(string $selector, string $validator): ?array
    {
        $tokens = $this->getTokens();

        // Clean expired tokens first
        $this->cleanExpiredTokens();

        if (!isset($tokens[$selector])) {
            return null;
        }

        $tokenData = $tokens[$selector];

        // Check if token has expired
        if (strtotime($tokenData['expires']) < time()) {
            // Remove expired token
            unset($tokens[$selector]);
            file_put_contents($this->tokensFile, json_encode($tokens, JSON_PRETTY_PRINT));
            return null;
        }

        // Verify the validator hash
        if (!hash_equals($tokenData['token'], hash('sha256', $validator))) {
            // Security measure: if token is invalid, delete it to prevent brute force
            unset($tokens[$selector]);
            file_put_contents($this->tokensFile, json_encode($tokens, JSON_PRETTY_PRINT));
            return null;
        }

        // Token is valid, get user data
        $users = $this->getUsers();
        $username = $tokenData['username'];

        if (!isset($users[$username])) {
            // User no longer exists, clean up the token
            unset($tokens[$selector]);
            file_put_contents($this->tokensFile, json_encode($tokens, JSON_PRETTY_PRINT));
            return null;
        }

        return [
            'username' => $username,
            'role' => $users[$username]['role']
        ];
    }

    /**
     * Remove all remember-me tokens for a user
     *
     * @param string $username Username
     * @return bool Success status
     */
    public function removeAllTokensForUser(string $username): bool
    {
        $tokens = $this->getTokens();
        $updated = false;

        foreach ($tokens as $selector => $data) {
            if ($data['username'] === $username) {
                unset($tokens[$selector]);
                $updated = true;
            }
        }

        if ($updated) {
            return file_put_contents($this->tokensFile, json_encode($tokens, JSON_PRETTY_PRINT)) !== false;
        }

        return true;
    }

    /**
     * Remove expired tokens
     */
    private function cleanExpiredTokens(): void
    {
        $tokens = $this->getTokens();
        $updated = false;
        $now = time();

        foreach ($tokens as $selector => $data) {
            if (strtotime($data['expires']) < $now) {
                unset($tokens[$selector]);
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($this->tokensFile, json_encode($tokens, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Get all stored tokens
     *
     * @return array Token data
     */
    private function getTokens(): array
    {
        if (!file_exists($this->tokensFile)) {
            return [];
        }

        return json_decode(file_get_contents($this->tokensFile), true) ?: [];
    }

    /**
     * Save users to file with error handling
     *
     * @param array $users User data
     * @return bool Success status
     */
    private function saveUsers(array $users): bool
    {
        // Use an atomic write pattern to prevent data corruption
        $tempFile = $this->usersFile . '.tmp';

        // Encode with JSON_THROW_ON_ERROR to catch JSON encoding issues
        try {
            $json = json_encode($users, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

            // Write to temporary file first
            if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
                // Write failed
                return false;
            }

            // Set secure permissions
            chmod($tempFile, 0600);

            // Rename to actual file (atomic operation)
            if (!rename($tempFile, $this->usersFile)) {
                // Rename failed
                unlink($tempFile);
                return false;
            }

            return true;
        } catch (Exception $e) {
            // Log the error
            error_log('Error saving users: ' . $e->getMessage());

            // Remove temp file if it exists
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            return false;
        }
    }
}

/**
 * Generate HTML for user management modal dialog
 *
 * @param array $users List of users
 * @param string $currentUsername Currently logged in username
 * @return string HTML for the modal
 */
function renderUserManagementModal(array $users, string $currentUsername): string
{
    ob_start(); // Start output buffering
    ?>
    <div class="modal fade" id="usersModal" tabindex="-1" aria-labelledby="usersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="usersModalLabel">
                        <i class="bi bi-people me-2"></i>
                        Manage Users
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; padding-bottom: 65px;">
                    <!-- Add User Form -->
                    <form id="addUserForm" class="mb-3">
                        <h6 class="mb-3 fw-bold">
                            <i class="bi bi-person-plus me-2"></i>
                            Add New User
                        </h6>
                        <div class="row g-3">
                            <div class="col-sm">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control" name="new_username" placeholder="Username"
                                           required pattern="[a-zA-Z0-9_]+"
                                           title="Username can only contain letters, numbers, and underscores">
                                </div>
                            </div>
                            <div class="col-sm">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-key"></i>
                                    </span>
                                    <input type="password" class="form-control" name="new_password"
                                           placeholder="Password" required minlength="8">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-sm">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-shield"></i>
                                    </span>
                                    <select class="form-select" name="new_role" required>
                                        <option value="viewer">Viewer</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-auto">
                                <button type="submit" class="btn btn-primary icon-btn w-100">
                                    <i class="bi bi-plus-circle"></i>
                                    Add
                                </button>
                            </div>
                        </div>
                        <div class="feedback-message mt-2" style="display: none;"></div>
                    </form>

                    <!-- Main notification area for all user management notifications -->
                    <div id="mainNotificationArea" class="mb-4" style="display: none;"></div>

                    <!-- Users List -->
                    <h6 class="mb-3 fw-bold">
                        <i class="bi bi-people-fill me-2"></i>
                        Current Users
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $username => $userData): ?>
                                <tr data-username="<?php echo htmlspecialchars($username); ?>">
                                    <td>
                                        <i class="bi bi-person-circle me-2"></i>
                                        <?php echo htmlspecialchars($username); ?>
                                        <?php if (isset($userData['last_login'])): ?>
                                            <small class="text-muted d-block">
                                                Last login: <?php echo htmlspecialchars($userData['last_login'] ?? 'Never'); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline role-change-form">
                                            <input type="hidden" name="username"
                                                   value="<?php echo htmlspecialchars($username); ?>">
                                            <div class="input-group input-group-sm" style="min-width: 160px;">
                                                <span class="input-group-text">
                                                    <i class="bi bi-shield"></i>
                                                </span>
                                                <select class="form-select form-select-sm" name="new_role"
                                                        onchange="handleRoleChange(this.form)"
                                                    <?php echo $username === $currentUsername ? 'disabled' : ''; ?>>
                                                    <option value="viewer" <?php echo $userData['role'] === 'viewer' ? 'selected' : ''; ?>>
                                                        Viewer
                                                    </option>
                                                    <option value="admin" <?php echo $userData['role'] === 'admin' ? 'selected' : ''; ?>>
                                                        Admin
                                                    </option>
                                                </select>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column flex-md-row gap-2 action-buttons-container">
                                            <button type="button" class="btn btn-danger btn-sm icon-btn w-100 w-md-auto"
                                                    onclick="showDeleteUserModal('<?php echo htmlspecialchars($username); ?>')"
                                                <?php echo $username === $currentUsername ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash"></i>
                                                Delete
                                            </button>
                                            <button type="button"
                                                    class="btn btn-secondary btn-sm icon-btn w-100 w-md-auto change-password-btn"
                                                    onclick="showInlinePasswordForm('<?php echo htmlspecialchars($username); ?>')">
                                                <i class="bi bi-key"></i>
                                                Password
                                            </button>
                                        </div>
                                        <div class="password-change-container mt-2" style="display: none;">
                                            <form class="d-flex gap-2 align-items-start password-change-form">
                                                <input type="hidden" name="change_password_username"
                                                       value="<?php echo htmlspecialchars($username); ?>">
                                                <div class="flex-grow-1">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">
                                                            <i class="bi bi-key"></i>
                                                        </span>
                                                        <input type="password" class="form-control form-control-sm"
                                                               name="new_password" placeholder="New Password" required minlength="8">
                                                        <button class="btn btn-outline-secondary btn-sm toggle-password" type="button">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-primary btn-sm icon-btn"
                                                        onclick="handlePasswordChange(this.form)">
                                                    <i class="bi bi-check-lg"></i>
                                                    Update
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm icon-btn"
                                                        onclick="hideInlinePasswordForm(this)">
                                                    <i class="bi bi-x-lg"></i>
                                                    Cancel
                                                </button>
                                            </form>
                                            <div class="feedback-message mt-2" style="display: none;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"
                     style="position: absolute; bottom: 0; left: 0; right: 0; background-color: white; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-info icon-btn me-auto" data-bs-dismiss="modal" id="viewUsageGraphBtn">
                        <i class="bi bi-bar-chart-line"></i>
                        Usage Graph
                    </button>
                    <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete User Confirmation Modal - Secondary modal for confirming user deletion -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Confirm User Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <i class="bi bi-question-circle me-2"></i>
                    Are you sure you want to delete user "<span id="deleteUserName"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger icon-btn"
                            onclick="handleDeleteUser(document.getElementById('deleteUserName').textContent)">
                        <i class="bi bi-trash"></i>
                        Delete User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Graph Modal -->
    <div class="modal fade" id="usageGraphModal" tabindex="-1" aria-labelledby="usageGraphModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="usageGraphModalLabel">
                        <i class="bi bi-bar-chart-line me-2"></i>
                        User Activity Trends
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="min-height: 500px;">
                    <!-- Hidden field to store selected username -->
                    <input type="hidden" id="graphModalUsername" value="">
                    
                    <!-- React component will be rendered here -->
                    <div id="usageGraphContainer"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to display alert messages in the notification area
        function showAlert(message, type) {
            // Select the main notification area below the Add User form
            const notificationArea = document.getElementById('mainNotificationArea');
            if (!notificationArea) {
                console.error('Notification area not found');
                return;
            }

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            // Add the alert to the notification area
            notificationArea.appendChild(alertDiv);
            notificationArea.style.display = 'block';

            // Scroll to the notification
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Remove after a delay
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    // Fade out
                    alertDiv.style.transition = 'opacity 0.5s ease';
                    alertDiv.style.opacity = '0';

                    setTimeout(() => {
                        alertDiv.remove();
                        // Hide the container if empty
                        if (notificationArea.children.length === 0) {
                            notificationArea.style.display = 'none';
                        }
                    }, 500);
                }
            }, 4000);
        }

        // Show feedback messages in forms
        function showFormFeedback(form, message, type) {
            // For the main add user form, use the main notification area
            if (form.id === 'addUserForm') {
                showAlert(message, type);
                return;
            }

            // For other forms (like password change), use inline feedback
            const feedbackContainer = form.closest('form, div').querySelector('.feedback-message');
            if (feedbackContainer) {
                feedbackContainer.innerHTML = `<div class="alert alert-${type} py-2 px-3 mb-0">${message}</div>`;
                feedbackContainer.style.display = 'block';

                setTimeout(() => {
                    // Fade out
                    feedbackContainer.firstChild.style.transition = 'opacity 0.5s ease';
                    feedbackContainer.firstChild.style.opacity = '0';

                    setTimeout(() => {
                        feedbackContainer.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        }

        // Add event listener for the add user form submission
        document.addEventListener('DOMContentLoaded', function () {
            // Setup password toggle buttons
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.closest('.input-group').querySelector('input[type="password"], input[type="text"]');
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);

                    // Toggle icon
                    const icon = this.querySelector('i');
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                });
            });

            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const formData = new FormData(this);

                    // Client-side validation
                    const username = formData.get('new_username');
                    const password = formData.get('new_password');

                    if (!username.match(/^[a-zA-Z0-9_]+$/)) {
                        showFormFeedback(this, 'Username can only contain letters, numbers, and underscores', 'danger');
                        return;
                    }

                    if (password.length < 8) {
                        showFormFeedback(this, 'Password must be at least 8 characters', 'danger');
                        return;
                    }

                    try {
                        const response = await fetch('user_actions.php?action=add', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            const username = formData.get('new_username');
                            const role = formData.get('new_role');

                            // Get the table body
                            const tableBody = document.querySelector('#usersModal table tbody');
                            if (!tableBody) {
                                console.error('Table body not found');
                                return;
                            }

                            // Create the new row
                            const newRow = document.createElement('tr');
                            newRow.dataset.username = username;
                            newRow.innerHTML = `
                                <td>
                                    <i class="bi bi-person-circle me-2"></i> ${username}
                                    <small class="text-muted d-block">
                                        Last login: Never
                                    </small>
                                </td>
                                <td>
                                    <form method="post" class="d-inline role-change-form">
                                        <input type="hidden" name="username" value="${username}">
                                        <div class="input-group input-group-sm" style="min-width: 160px;">
                                            <span class="input-group-text">
                                                <i class="bi bi-shield"></i>
                                            </span>
                                            <select class="form-select form-select-sm" name="new_role" onchange="handleRoleChange(this.form)">
<option value="viewer" ${role === 'viewer' ? 'selected' : ''}>
                                                    Viewer
                                                </option>
                                                <option value="admin" ${role === 'admin' ? 'selected' : ''}>
                                                    Admin
                                                </option>
                                            </select>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <div class="d-flex flex-column flex-md-row gap-2 action-buttons-container">
                                        <button type="button" class="btn btn-danger btn-sm icon-btn w-100 w-md-auto" onclick="showDeleteUserModal('${username}')">
                                            <i class="bi bi-trash"></i>
                                            Delete
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm icon-btn w-100 w-md-auto change-password-btn" onclick="showInlinePasswordForm('${username}')">
                                            <i class="bi bi-key"></i>
                                            Password
                                        </button>
                                    </div>
                                    <div class="password-change-container mt-2" style="display: none;">
                                        <form class="d-flex gap-2 align-items-start password-change-form">
                                            <input type="hidden" name="change_password_username" value="${username}">
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">
                                                        <i class="bi bi-key"></i>
                                                    </span>
                                                    <input type="password" class="form-control form-control-sm" name="new_password" placeholder="New Password" required minlength="8">
                                                    <button class="btn btn-outline-secondary btn-sm toggle-password" type="button">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-primary btn-sm icon-btn"
                                                    onclick="handlePasswordChange(this.form)">
                                                <i class="bi bi-check-lg"></i>
                                                Update
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm icon-btn"
                                                    onclick="hideInlinePasswordForm(this)">
                                                <i class="bi bi-x-lg"></i>
                                                Cancel
                                            </button>
                                        </form>
                                        <div class="feedback-message mt-2" style="display: none;"></div>
                                    </div>
                                </td>
                            `;

                            // Find where to insert the new row alphabetically
                            const rows = Array.from(tableBody.getElementsByTagName('tr'));
                            const insertIndex = rows.findIndex(row =>
                                row.dataset.username.toLowerCase() > username.toLowerCase()
                            );

                            if (insertIndex === -1) {
                                // Add to the end if no insertion point found
                                tableBody.appendChild(newRow);
                            } else {
                                tableBody.insertBefore(newRow, rows[insertIndex]);
                            }

                            // Attach event listener to the new password toggle button
                            const toggleButton = newRow.querySelector('.toggle-password');
                            if (toggleButton) {
                                toggleButton.addEventListener('click', function() {
                                    const input = this.closest('.input-group').querySelector('input[type="password"], input[type="text"]');
                                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                                    input.setAttribute('type', type);

                                    // Toggle icon
                                    const icon = this.querySelector('i');
                                    icon.classList.toggle('bi-eye');
                                    icon.classList.toggle('bi-eye-slash');
                                });
                            }

                            // Reset the form and show success message
                            this.reset();
                            showAlert('User added successfully!', 'success');
                        } else {
                            showFormFeedback(this, result.message || 'Error adding user', 'danger');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showFormFeedback(this, 'Error adding user', 'danger');
                    }
                });
            }

            // Set up the button to view usage graphs
            const viewUsageGraphBtn = document.getElementById('viewUsageGraphBtn');
            
            if (viewUsageGraphBtn) {
                viewUsageGraphBtn.addEventListener('click', function() {
                    // Get the first user in the table or default to the current user
                    const userRows = document.querySelectorAll('#usersModal table tbody tr');
                    let username = '<?php echo htmlspecialchars($currentUsername); ?>';
                    
                    if (userRows.length > 0) {
                        username = userRows[0].dataset.username || username;
                    }
                    
                    // Set the username in the hidden field
                    document.getElementById('graphModalUsername').value = username;
                    
                    // Show the graph modal
                    const graphModal = new bootstrap.Modal(document.getElementById('usageGraphModal'));
                    graphModal.show();
                });
            }
            
            // When the graph modal is shown, render the React component
            const usageGraphModal = document.getElementById('usageGraphModal');
            if (usageGraphModal) {
                usageGraphModal.addEventListener('shown.bs.modal', function() {
                    // Prevent body scrolling
                    document.body.style.overflow = 'hidden';
                });
                
                // Restore body scrolling when modal is hidden
                usageGraphModal.addEventListener('hidden.bs.modal', function() {
                    document.body.style.overflow = '';
                });
            }
        });

        // Function to handle changing a user's role
        async function handleRoleChange(form) {
            const formData = new FormData(form);
            try {
                const response = await fetch('user_actions.php?action=change_role', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert('Role updated successfully!', 'success');
                } else {
                    showAlert(result.message || 'Error updating role', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error updating role', 'danger');
            }
        }

        // Function to handle user deletion
        async function handleDeleteUser(username) {
            try {
                const formData = new FormData();
                formData.append('username', username);

                const response = await fetch('user_actions.php?action=delete', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    const userRow = document.querySelector(`tr[data-username="${username}"]`);
                    if (userRow) {
                        // Add fade-out animation
                        userRow.style.transition = 'opacity 0.5s ease';
                        userRow.style.opacity = '0';

                        // Remove row after animation completes
                        setTimeout(() => {
                            userRow.remove();
                        }, 500);
                    }

                    const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteUserModal'));
                    if (deleteModal) {
                        deleteModal.hide();
                    }

                    showAlert('User deleted successfully!', 'success');
                } else {
                    showAlert(result.message || 'Error deleting user', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error deleting user', 'danger');
            }
        }

        // Function to handle password change
        async function handlePasswordChange(form) {
            const formData = new FormData(form);

            // Client-side validation
            const password = formData.get('new_password');
            if (password.length < 8) {
                // For password change forms, show feedback inline
                const feedbackContainer = form.closest('.password-change-container').querySelector('.feedback-message');
                if (feedbackContainer) {
                    feedbackContainer.innerHTML = `<div class="alert alert-danger py-2 px-3 mb-0">Password must be at least 8 characters</div>`;
                    feedbackContainer.style.display = 'block';

                    setTimeout(() => {
                        feedbackContainer.style.display = 'none';
                    }, 3000);
                }
                return;
            }

            try {
                const response = await fetch('user_actions.php?action=change_password', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Send notification to main area
                    showAlert('Password changed successfully!', 'success');

                    // Hide the form after a short delay
                    setTimeout(() => {
                        hideInlinePasswordForm(form.querySelector('button[type="button"]'));
                    }, 1500);
                } else {
                    showFormFeedback(form, result.message || 'Error changing password', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showFormFeedback(form, 'Error changing password', 'danger');
            }
        }

        // Function to show the delete user confirmation modal
        function showDeleteUserModal(username) {
            const modal = document.getElementById('deleteUserModal');
            const userNameSpan = document.getElementById('deleteUserName');
            userNameSpan.textContent = username;
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }

        // Function to show the inline password change form
        function showInlinePasswordForm(username) {
            // Hide all other password forms first
            document.querySelectorAll('.password-change-container').forEach(container => {
                container.style.display = 'none';
            });
            document.querySelectorAll('.action-buttons-container').forEach(container => {
                container.style.display = 'flex';
            });

            // Show the selected password form and hide its action buttons
            const row = document.querySelector(`tr[data-username="${username}"]`);
            const passwordContainer = row.querySelector('.password-change-container');
            const buttonsContainer = row.querySelector('.action-buttons-container');

            // Show with animation
            passwordContainer.style.display = 'block';
            passwordContainer.style.opacity = '0';
            setTimeout(() => {
                passwordContainer.style.transition = 'opacity 0.3s ease';
                passwordContainer.style.opacity = '1';
            }, 10);

            buttonsContainer.style.display = 'none';
        }

        // Function to hide the inline password change form
        function hideInlinePasswordForm(cancelButton) {
            const container = cancelButton.closest('.password-change-container');
            const buttonsContainer = container.previousElementSibling;
            const form = container.querySelector('form');

            // Hide with animation
            container.style.transition = 'opacity 0.3s ease';
            container.style.opacity = '0';

            setTimeout(() => {
                container.style.display = 'none';
                buttonsContainer.style.display = 'flex';
                form.reset();

                // Clear any feedback messages
                const feedback = container.querySelector('.feedback-message');
                if (feedback) {
                    feedback.style.display = 'none';
                }
            }, 300);
        }

        // Prevent body scrolling when modal is open
        document.getElementById('usersModal').addEventListener('shown.bs.modal', function () {
            document.body.style.overflow = 'hidden';
        });

        document.getElementById('usersModal').addEventListener('hidden.bs.modal', function () {
            document.body.style.overflow = '';
        });
    </script>
    <?php
    return ob_get_clean();
}

?>
