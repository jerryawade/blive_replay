<?php
require_once 'logging.php';

class SettingsManager
{
    private $settingsFile = 'json/settings.json';
    private $defaultSettings = [
        'server_url' => 'http://yourdomain.com',
        'live_stream_url' => 'vlc://yourdomain.com',
        'open_webpage_for_livestream' => false,
        'srt_url' => '',
        'stream_check_interval' => 5,
        'show_recordings' => true,
        'show_livestream' => true,
        'allow_vlc' => true,
        'allow_m3u' => true,
        'allow_mp4' => true,
        'vlc_webpage_url' => '',
        'timezone' => 'America/Chicago',

        // Scheduler settings
        'enable_scheduler' => false,
        'scheduler_notification_email' => '',

        // Email notification settings
        'email_notifications_enabled' => false,
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_security' => 'tls', // tls, ssl, or none
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => 'RePlay System'
    ];

    public function __construct()
    {
        if (!file_exists($this->settingsFile)) {
            $this->saveSettings($this->defaultSettings);
        }
    }

    public function getSettings()
    {
        if (file_exists($this->settingsFile)) {
            $settings = json_decode(file_get_contents($this->settingsFile), true);
            return array_merge($this->defaultSettings, $settings); // Ensure all keys exist
        }
        return $this->defaultSettings;
    }

    public function saveSettings($settings)
    {
        file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    }

    public function updateSettings($newSettings, $username)
    {
        $currentSettings = $this->getSettings();
        $updatedSettings = array_merge($currentSettings, $newSettings);

        // Log changes for each setting that was modified
        $activityLogger = new ActivityLogger();
        foreach ($newSettings as $key => $value) {
            if (!isset($currentSettings[$key]) || $currentSettings[$key] !== $value) {
                $oldValue = isset($currentSettings[$key]) ? (is_bool($currentSettings[$key]) ? ($currentSettings[$key] ? 'enabled' : 'disabled') : $currentSettings[$key]) : 'not set';
                $newValue = is_bool($value) ? ($value ? 'enabled' : 'disabled') : $value;

                $activityLogger->logActivity(
                    $username,
                    'settings_changed',
                    "Changed {$key} from '{$oldValue}' to '{$newValue}'"
                );
            }
        }

        $this->saveSettings($updatedSettings);
    }
}

// Settings Modal HTML Template
function renderSettingsModal($settings)
{
    ob_start();
    ?>
    <!-- Settings Modal with Tabs -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">
                        <i class="bi bi-gear me-2"></i>
                        System Settings
                    </h5>
                    <div class="d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-link text-dark me-2" id="aboutButton"
                                title="About RePlay">
                            <i class="bi bi-info-circle-fill"></i>
                        </button>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="height: calc(100vh - 250px); overflow-y: auto;">
                    <form id="settingsForm">
                        <!-- Settings Navigation Tabs -->
                        <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="server-tab" data-bs-toggle="tab"
                                        data-bs-target="#server-config" type="button" role="tab"
                                        aria-controls="server-config" aria-selected="true">
                                    <i class="bi bi-server me-1"></i> Server Configuration
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="permissions-tab" data-bs-toggle="tab"
                                        data-bs-target="#permissions-config" type="button" role="tab"
                                        aria-controls="permissions-config" aria-selected="false">
                                    <i class="bi bi-shield-lock me-1"></i> Permissions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="scheduler-tab" data-bs-toggle="tab"
                                        data-bs-target="#scheduler-config" type="button" role="tab"
                                        aria-controls="scheduler-config" aria-selected="false">
                                    <i class="bi bi-calendar-event me-1"></i> Scheduler
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="downloads-tab" data-bs-toggle="tab"
                                        data-bs-target="#downloads-config" type="button" role="tab"
                                        aria-controls="downloads-config" aria-selected="false">
                                    <i class="bi bi-download me-1"></i> Downloads
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="settingsTabContent">
                            <!-- Server Configuration Tab -->
                            <div class="tab-pane fade show active" id="server-config" role="tabpanel"
                                 aria-labelledby="server-tab">
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">System Timezone</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-clock"></i>
                                        </span>
                                        <select class="form-select" id="timezone" name="timezone" required>
                                            <?php
                                            $timezones = DateTimeZone::listIdentifiers();
                                            $current_timezone = $settings['timezone'] ?? 'America/Chicago';
                                            foreach ($timezones as $tz) {
                                                $selected = ($tz === $current_timezone) ? 'selected' : '';
                                                echo "<option value=\"$tz\" $selected>$tz</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <small class="text-muted">Select the timezone for the system (affects logs and
                                        recording timestamps)</small>
                                </div>
                                <div class="mb-3">
                                    <label for="server_url" class="form-label">Server URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-link-45deg"></i>
                                        </span>
                                        <input type="url" class="form-control" id="server_url" name="server_url"
                                               value="<?php echo htmlspecialchars($settings['server_url']); ?>"
                                               required>
                                    </div>
                                    <small class="text-muted">Example: http://yourdomain.com</small>
                                </div>
                                <div class="mb-3">
                                    <label for="live_stream_url" class="form-label">Live Stream URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-broadcast"></i>
                                        </span>
                                        <input type="text" class="form-control" id="live_stream_url"
                                               name="live_stream_url"
                                               value="<?php echo htmlspecialchars($settings['live_stream_url']); ?>"
                                               required>
                                    </div>
                                    <small class="text-muted">Example: vlc://yourdomain.com:port if needed</small>
                                </div>
                                <div class="mb-3">
                                    <label for="srt_url" class="form-label">Recording URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-record-circle"></i>
                                        </span>
                                        <input type="text" class="form-control" id="srt_url" name="srt_url"
                                               value="<?php echo htmlspecialchars($settings['srt_url'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <small class="text-muted">Example: srt://yourdomain.com:port if needed</small>
                                </div>
                                <div class="mb-3">
                                    <label for="stream_check_interval" class="form-label">Recording URL Check Interval
                                        (minutes)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-clock-history"></i>
                                        </span>
                                        <input type="number" class="form-control" id="stream_check_interval"
                                               name="stream_check_interval"
                                               value="<?php echo htmlspecialchars($settings['stream_check_interval'] ?? '5'); ?>"
                                               min="1" max="60" required>
                                    </div>
                                    <small class="text-muted">How frequently the system should check if the Recording
                                        URL is accessible (1-60 minutes)</small>
                                </div>
                                <div class="mb-3">
                                    <label for="vlc_webpage_url" class="form-label">VLC Webpage URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-window"></i>
                                        </span>
                                        <input type="url" class="form-control" id="vlc_webpage_url"
                                               name="vlc_webpage_url"
                                               value="<?php echo htmlspecialchars($settings['vlc_webpage_url'] ?? ''); ?>">
                                    </div>
                                    <small class="text-muted">URL to open in a new tab when playing videos
                                        (optional)</small>
                                </div>
                            </div>

                            <!-- Permissions Tab -->
                            <div class="tab-pane fade" id="permissions-config" role="tabpanel"
                                 aria-labelledby="permissions-tab">
                                <!-- Landing Page Options Section -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="bi bi-house-door me-2"></i>
                                        Landing Page Options
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="show_recordings"
                                                       name="show_recordings"
                                                    <?php echo isset($settings['show_recordings']) && $settings['show_recordings'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="show_recordings">
                                                    <i class="bi bi-collection-play me-2"></i>
                                                    Show Recordings link
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="show_livestream"
                                                       name="show_livestream"
                                                    <?php echo isset($settings['show_livestream']) && $settings['show_livestream'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="show_livestream">
                                                    <i class="bi bi-broadcast me-2"></i>
                                                    Show Live Stream link
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="open_webpage_for_livestream"
                                                       name="open_webpage_for_livestream"
                                                    <?php echo isset($settings['open_webpage_for_livestream']) && $settings['open_webpage_for_livestream'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="open_webpage_for_livestream">
                                                    <i class="bi bi-window-plus me-2"></i>
                                                    Open VLC webpage when clicking Live Stream
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- RePlay Permissions Section -->
                                <div class="card">
                                    <div class="card-header">
                                        <i class="bi bi-shield-lock me-2"></i>
                                        RePlay Permissions
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="allow_vlc"
                                                       name="allow_vlc"
                                                    <?php echo isset($settings['allow_vlc']) && $settings['allow_vlc'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allow_vlc">
                                                    <i class="bi bi-play-circle me-2"></i>
                                                    Allow viewers to play recordings in VLC
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="allow_m3u"
                                                       name="allow_m3u"
                                                    <?php echo isset($settings['allow_m3u']) && $settings['allow_m3u'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allow_m3u">
                                                    <i class="bi bi-file-earmark-play me-2"></i>
                                                    Allow viewers to download M3U playlists
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="allow_mp4"
                                                       name="allow_mp4"
                                                    <?php echo isset($settings['allow_mp4']) && $settings['allow_mp4'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allow_mp4">
                                                    <i class="bi bi-download me-2"></i>
                                                    Allow viewers to download MP4 files
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduler Tab -->
                            <div class="tab-pane fade" id="scheduler-config" role="tabpanel"
                                 aria-labelledby="scheduler-tab">
                                <!-- Scheduler Settings Section -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="bi bi-calendar-check me-2"></i>
                                        Recording Scheduler
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="enable_scheduler"
                                                       name="enable_scheduler"
                                                    <?php echo isset($settings['enable_scheduler']) && $settings['enable_scheduler'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_scheduler">
                                                    <i class="bi bi-stopwatch me-2"></i>
                                                    Enable automatic scheduled recordings
                                                </label>
                                            </div>
                                            <small class="text-muted">When enabled, recordings will automatically start
                                                and stop
                                                based on your defined schedules.</small>
                                        </div>
                                        <div class="mb-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm icon-btn"
                                                    id="manageSchedulesBtn"
                                                    data-bs-dismiss="modal" onclick="showSchedulesModal()">
                                                <i class="bi bi-calendar-plus"></i>
                                                Manage Recording Schedules
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Notification Settings Section -->
                                <div class="card">
                                    <div class="card-header">
                                        <i class="bi bi-envelope me-2"></i>
                                        Email Notification Settings
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input"
                                                       id="email_notifications_enabled"
                                                       name="email_notifications_enabled"
                                                    <?php echo isset($settings['email_notifications_enabled']) && $settings['email_notifications_enabled'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="email_notifications_enabled">
                                                    <i class="bi bi-bell me-2"></i>
                                                    Enable email notifications
                                                </label>
                                            </div>
                                            <small class="text-muted">When enabled, notifications will be sent for
                                                scheduled
                                                recording events.</small>
                                        </div>

                                        <div id="smtp_settings_container" class="border rounded p-3 mt-3"
                                             style="display: <?php echo isset($settings['email_notifications_enabled']) && $settings['email_notifications_enabled'] ? 'block' : 'none'; ?>;">
                                            <div class="mb-3">
                                                <label for="scheduler_notification_email" class="form-label">Notification
                                                    Email(s)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="bi bi-envelope-at"></i>
                                                    </span>
                                                    <input type="text" class="form-control"
                                                           id="scheduler_notification_email"
                                                           name="scheduler_notification_email"
                                                           value="<?php echo htmlspecialchars($settings['scheduler_notification_email'] ?? ''); ?>"
                                                           placeholder="notifications@example.com, secondary@example.com">
                                                </div>
                                                <small class="text-muted">Email address(es) to receive notifications
                                                    about scheduled recordings. Separate multiple addresses with
                                                    commas.</small>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="smtp_host" class="form-label">SMTP Server</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="bi bi-server"></i>
                                                        </span>
                                                        <input type="text" class="form-control" id="smtp_host"
                                                               name="smtp_host"
                                                               value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                                               placeholder="smtp.gmail.com">
                                                    </div>
                                                    <small class="text-muted">e.g., smtp.gmail.com,
                                                        smtp.office365.com</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="smtp_port" class="form-label">SMTP Port</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="bi bi-hdd-network"></i>
                                                        </span>
                                                        <input type="text" class="form-control" id="smtp_port"
                                                               name="smtp_port"
                                                               value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                                                               placeholder="587">
                                                    </div>
                                                    <small class="text-muted">587 (TLS), 465 (SSL), or 25
                                                        (non-secure)</small>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="smtp_security" class="form-label">Connection
                                                    Security</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="bi bi-shield-lock"></i>
                                                    </span>
                                                    <select class="form-select" id="smtp_security" name="smtp_security">
                                                        <option value="tls" <?php echo (isset($settings['smtp_security']) && $settings['smtp_security'] === 'tls') ? 'selected' : ''; ?>>
                                                            TLS
                                                        </option>
                                                        <option value="ssl" <?php echo (isset($settings['smtp_security']) && $settings['smtp_security'] === 'ssl') ? 'selected' : ''; ?>>
                                                            SSL
                                                        </option>
                                                        <option value="none" <?php echo (isset($settings['smtp_security']) && $settings['smtp_security'] === 'none') ? 'selected' : ''; ?>>
                                                            None
                                                        </option>
                                                    </select>
                                                </div>
                                                <small class="text-muted">TLS is recommended for most providers</small>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="smtp_username" class="form-label">SMTP Username</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="bi bi-person"></i>
                                                        </span>
                                                        <input type="text" class="form-control" id="smtp_username"
                                                               name="smtp_username"
                                                               value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                                               placeholder="user@example.com">
                                                    </div>
                                                    <small class="text-muted">Usually your email address</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="smtp_password" class="form-label">SMTP Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="bi bi-key"></i>
                                                        </span>
                                                        <input type="password" class="form-control" id="smtp_password"
                                                               name="smtp_password"
                                                               value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                                                               placeholder="Password or App Password">
                                                        <button class="btn btn-outline-secondary toggle-password"
                                                                type="button">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted">Use app password for Gmail or services
                                                        with
                                                        2FA</small>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="smtp_from_email" class="form-label">From Email</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="bi bi-at"></i>
                                                        </span>
                                                        <input type="email" class="form-control" id="smtp_from_email"
                                                               name="smtp_from_email"
                                                               value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>"
                                                               placeholder="replay@yourdomain.com">
                                                    </div>
                                                    <small class="text-muted">Must be authorized by your SMTP
                                                        provider</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="smtp_from_name" class="form-label">From Name</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="bi bi-person-badge"></i>
                                                        </span>
                                                        <input type="text" class="form-control" id="smtp_from_name"
                                                               name="smtp_from_name"
                                                               value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'RePlay System'); ?>"
                                                               placeholder="RePlay System">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-2"></i>
                                                <strong>Gmail Users:</strong> You'll need to use an "App Password" if
                                                you have
                                                2-factor authentication enabled.
                                                <a href="https://support.google.com/accounts/answer/185833"
                                                   target="_blank"
                                                   class="alert-link">
                                                    Learn how to create an App Password
                                                </a>
                                            </div>

                                            <!-- Test email section with inline notification -->
                                            <div class="mt-3 border-top pt-3">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <h6 class="mb-0">Test Email Configuration</h6>
                                                        <p class="text-muted small mb-0">Verify your email settings by
                                                            sending a
                                                            test email</p>
                                                    </div>
                                                    <button type="button"
                                                            class="btn btn-outline-primary btn-sm icon-btn"
                                                            id="testEmailButton">
                                                        <i class="bi bi-envelope-check"></i>
                                                        Send Test Email
                                                    </button>
                                                </div>

                                                <!-- Test email notification area (hidden by default) -->
                                                <div id="emailTestNotification" class="mt-2" style="display: none;">
                                                    <!-- Content will be dynamically generated -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="downloads-config" role="tabpanel">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <i class="bi bi-download me-2"></i>
                                        VLC Handlers
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5><i class="bi bi-apple me-2"></i>Mac VLC Handler</h5>
                                                <a href="vlc_handlers/mac_vlc_handler.zip"
                                                   class="btn btn-outline-primary icon-btn mb-3">
                                                    <i class="bi bi-cloud-download me-2"></i>
                                                    Download VLC Handler
                                                </a>
                                                <p class="text-muted">
                                                    This VLC handler enables seamless integration with the BLIVE RePlay
                                                    system on macOS.
                                                    After downloading, unzip the file and run <b>./build.sh
                                                        --install</b> from a terminal.
                                                    If it doesn't work, try running chmod +x build.sh first.
                                                </p>
                                                <ul class="small text-muted">
                                                    <li>Compatible with macOS 10.14+ (Mojave and later)</li>
                                                    <li>Requires VLC media player 3.0 or newer</li>
                                                    <li>Supports live stream and one-click playback from BLIVE RePlay
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h5><i class="bi bi-windows me-2"></i>Windows VLC Handler</h5>
                                                <a href="vlc_handlers/windows_vlc_handler.zip"
                                                   class="btn btn-outline-primary icon-btn mb-3">
                                                    <i class="bi bi-cloud-download me-2"></i>
                                                    Download VLC Handler
                                                </a>
                                                <p class="text-muted">
                                                    This VLC handler enables seamless integration with the BLIVE RePlay
                                                    system on Windows.
                                                    Download and unzip the file within the <b>C:\Program
                                                        Files\VideoLAN\VLC\ folder.
                                                        Run the install.bat file.</b>
                                                </p>
                                                <ul class="small text-muted">
                                                    <li>Compatible with Windows 10 and 11</li>
                                                    <li>Requires VLC media player 3.0 or newer</li>
                                                    <li>Supports live stream and one-click playback from BLIVE RePlay
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- New Auto-Close Handlers section -->
                                <div class="card">
                                    <div class="card-header">
                                        <i class="bi bi-power me-2"></i>
                                        Auto-Close Chrome/VLC Handlers
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5><i class="bi bi-apple me-2"></i>Mac Auto-Close Handler</h5>
                                                <a href="vlc_handlers/mac_close_chrome_vlc.zip"
                                                   class="btn btn-outline-primary icon-btn mb-3">
                                                    <i class="bi bi-cloud-download me-2"></i>
                                                    Download Auto-Close Handler
                                                </a>
                                                <p class="text-muted">
                                                    This handler automatically closes Chrome and VLC and is intended to
                                                    be scheduled
                                                    to run at night or the in the morning to close the two apps if left
                                                    open.
                                                    After downloading, unzip the file and and copy to your Applications
                                                    folder.
                                                    To schedule an app to run via the Mac Calendar (iCal), follow these
                                                    steps:
                                                    <br>
                                                    1. Open Calendar app<br>
                                                    2. Select or create the existing event<br>
                                                    3. Click "Edit" or double-click the event<br>
                                                    4. Click "Add Alarm"<br>
                                                    5. Choose "Open file" action<br>
                                                    6. Select the script/app to run<br>
                                                    7. Set alarm time before event start<br>
                                                    8. Save event
                                                </p>
                                                <ul class="small text-muted">
                                                    <li>Compatible with macOS 10.14+ (Mojave and later)</li>
                                                    <li>Automatically closes applications after stream</li>
                                                    <li>Reduces system resource usage</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h5><i class="bi bi-windows me-2"></i>Windows Auto-Close Handler</h5>
                                                <a href="vlc_handlers/windows_close_chrome_vlc.zip"
                                                   class="btn btn-outline-primary icon-btn mb-3">
                                                    <i class="bi bi-cloud-download me-2"></i>
                                                    Download Auto-Close Handler
                                                </a>
                                                <p class="text-muted">
                                                    This handler automatically closes Chrome and VLC and is intended to
                                                    be scheduled
                                                    to run at night or the in the morning to close the two apps if left
                                                    open. Create a Scripts folder on your C:\ drive.
                                                    After downloading, unzip the file and place the files in your
                                                    Scripts folder.
                                                    <br>You may double-click on the CreateTask.ps1 file to create the
                                                    needed scheduled task in task scheduler.
                                                    <br>By default, the task will run every night at 1:00 AM.
                                                </p>
                                                <ul class="small text-muted">
                                                    <li>Compatible with Windows 10 and 11</li>
                                                    <li>Automatically terminates streaming applications</li>
                                                    <li>Helps manage system resources efficiently</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary icon-btn" id="saveSettingsBtn">
                        <i class="bi bi-save"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prevent body scrolling when modal is open
        document.getElementById('settingsModal').addEventListener('shown.bs.modal', function () {
            document.body.style.overflow = 'hidden';
        });

        document.getElementById('settingsModal').addEventListener('hidden.bs.modal', function () {
            document.body.style.overflow = '';
        });

        // Function to show schedules modal after settings modal is closed
        function showSchedulesModal() {
            // Close settings modal first (to prevent backdrop issues)
            setTimeout(() => {
                // Show the schedules modal
                const schedulesModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
                schedulesModal.show();

                // Ensure the list tab is active
                const listTab = document.getElementById('schedule-list-tab');
                if (listTab) {
                    const tab = new bootstrap.Tab(listTab);
                    tab.show();
                }
            }, 500);
        }

        // Function to show about modal
        document.getElementById('aboutButton').addEventListener('click', function (e) {
            e.preventDefault();
            // Get the settings modal instance
            const settingsModal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));

            // Hide the settings modal
            settingsModal.hide();

            // Wait a bit for the settings modal to close, then show the about modal
            setTimeout(() => {
                const aboutModal = new bootstrap.Modal(document.getElementById('aboutModal'));
                aboutModal.show();

                // When about modal is hidden, show settings modal again
                document.getElementById('aboutModal').addEventListener('hidden.bs.modal', function () {
                    settingsModal.show();
                }, {once: true});
            }, 400);
        });

        // Toggle SMTP settings visibility based on checkbox
        document.addEventListener('DOMContentLoaded', function () {
            const emailNotificationsCheckbox = document.getElementById('email_notifications_enabled');
            const smtpContainer = document.getElementById('smtp_settings_container');
            const notificationEmailField = document.getElementById('scheduler_notification_email');

            if (emailNotificationsCheckbox && smtpContainer) {
                // Initial required state
                notificationEmailField.required = emailNotificationsCheckbox.checked;

                // Toggle on change
                emailNotificationsCheckbox.addEventListener('change', function () {
                    smtpContainer.style.display = this.checked ? 'block' : 'none';
                    notificationEmailField.required = this.checked;

                    // If turning off notifications, validate the form to clear any existing errors
                    if (!this.checked) {
                        const form = document.getElementById('settingsForm');
                        if (form) form.checkValidity();
                    }
                });

                // Handle test email button
                const testEmailButton = document.getElementById('testEmailButton');
                const emailTestNotification = document.getElementById('emailTestNotification');

                if (testEmailButton && emailTestNotification) {
                    testEmailButton.addEventListener('click', function () {
                        // Basic validation before sending
                        if (!notificationEmailField.value) {
                            showEmailTestResult(false, 'Please enter a notification email address');
                            notificationEmailField.focus();
                            return;
                        }

                        if (!document.getElementById('smtp_host').value ||
                            !document.getElementById('smtp_username').value ||
                            !document.getElementById('smtp_password').value) {
                            showEmailTestResult(false, 'Please complete all required SMTP fields');
                            return;
                        }

                        // Get the current form values
                        const formData = new FormData(document.getElementById('settingsForm'));

                        // Show loading state
                        testEmailButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
                        testEmailButton.disabled = true;
                        showEmailTestResult('loading', 'Sending test email...');

                        // Send test email
                        fetch('test_email.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showEmailTestResult(true, 'Test email sent successfully! Please check your inbox.');
                                } else {
                                    showEmailTestResult(false, data.message || 'Error sending test email');
                                }
                            })
                            .catch(error => {
                                showEmailTestResult(false, error.message || 'Error communicating with the server');
                            }).finally(() => {
                            // Reset button state
                            testEmailButton.innerHTML = '<i class="bi bi-envelope-check"></i> Send Test Email';
                            testEmailButton.disabled = false;
                        });
                    });
                }

                // Function to show test email results
                function showEmailTestResult(success, message) {
                    if (emailTestNotification) {
                        if (success === 'loading') {
                            emailTestNotification.innerHTML = `
                                <div class="alert alert-info">
                                    <div class="d-flex align-items-center">
                                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                        <span>${message}</span>
                                    </div>
                                </div>
                            `;
                        } else {
                            const alertClass = success ? 'alert-success' : 'alert-danger';
                            const icon = success ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';

                            emailTestNotification.innerHTML = `
                                <div class="alert ${alertClass}">
                                    <div class="d-flex align-items-center">
                                        <i class="bi ${icon} me-2"></i>
                                        <span>${message}</span>
                                    </div>
                                </div>
                            `;
                        }

                        emailTestNotification.style.display = 'block';

                        // Auto-hide success messages after 10 seconds
                        if (success === true) {
                            setTimeout(() => {
                                emailTestNotification.style.display = 'none';
                            }, 10000);
                        }
                    }
                }
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

// This function is no longer used with the AJAX approach, but is kept for backward compatibility
function handleSettingsUpdate($settingsManager)
{
    if (isset($_POST['update_settings']) && isset($_SESSION['username'])) {
        $newSettings = [
            'server_url' => rtrim($_POST['server_url'], '/'),
            'live_stream_url' => $_POST['live_stream_url'],
            'srt_url' => $_POST['srt_url'],
            'stream_check_interval' => max(1, min(60, (int)($_POST['stream_check_interval'] ?? 5))),
            'show_recordings' => isset($_POST['show_recordings']),
            'show_livestream' => isset($_POST['show_livestream']),
            'allow_vlc' => isset($_POST['allow_vlc']),
            'allow_m3u' => isset($_POST['allow_m3u']),
            'allow_mp4' => isset($_POST['allow_mp4']),
            'vlc_webpage_url' => $_POST['vlc_webpage_url'] ?? '',
            'timezone' => $_POST['timezone'] ?? 'America/Chicago',
            'enable_scheduler' => isset($_POST['enable_scheduler']),
            'scheduler_notification_email' => $_POST['scheduler_notification_email'] ?? '',

            // Email notification settings
            'email_notifications_enabled' => isset($_POST['email_notifications_enabled']),
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_security' => $_POST['smtp_security'] ?? 'tls',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name' => $_POST['smtp_from_name'] ?? 'RePlay System'
        ];
        $settingsManager->updateSettings($newSettings, $_SESSION['username']);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

function renderAboutModal()
{
    $version = "1.5.0"; // Update this version number as needed
    $buildDate = "March 2025";

    ob_start();
    ?>
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="aboutModalLabel">
                        <i class="bi bi-info-circle me-2"></i>
                        About
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="assets/imgs/Bethany Live Replay-04.png" alt="BLIVE RePlay" class="img-fluid mb-3"
                         style="max-width: 250px;">
                    <h4>BLIVE RePlay</h4>
                    <p>Version <?php echo $version; ?></p>
                    <p class="text-muted">Build Date: <?php echo $buildDate; ?></p>
                    <hr>
                    <div class="mb-3">
                        <p>A service recording system developed for Bethany Church.</p>
                        <p>Features include live streaming, recording management, and automated scheduling.</p>
                    </div>
                    <div class="text-start mt-4">
                        <h6 class="mb-2"><i class="bi bi-check-circle me-2"></i>System Information</h6>
                        <ul class="list-unstyled ms-4">
                            <li><small>PHP Version: <?php echo phpversion(); ?></small></li>
                            <li><small>FFmpeg
                                    Version: <?php echo htmlspecialchars(trim(shell_exec('ffmpeg -version | head -n 1') ?: 'Not installed')); ?></small>
                            </li>
                            <li><small>Server OS:
                                    <?php
                                    $os = htmlspecialchars(php_uname('s') . ' ' . php_uname('r'));
                                    $distro = trim(shell_exec('lsb_release -d'));
                                    echo $os . ' on ' . $distro;
                                    ?>
                                </small></li>
                        </ul>
                    </div>
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
    <?php
    return ob_get_clean();
}

?>
