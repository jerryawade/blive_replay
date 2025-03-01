<?php
require_once 'logging.php';

class SettingsManager
{
    private $settingsFile = 'settings.json';
    private $defaultSettings = [
        'server_url' => 'http://yourdomain.com',
        'live_stream_url' => 'vlc://yourdomain.com',
        'srt_url' => '',
        'show_recordings' => true,
        'show_livestream' => true,
        'allow_vlc' => true,
        'allow_m3u' => true,
        'allow_mp4' => true,
        'vlc_webpage_url' => '',
        'timezone' => 'America/Chicago',

        // Scheduler settings
        'enable_scheduler' => false,
        'scheduler_notification_email' => ''
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
                        <div class="container-fluid px-0">
                            <!-- Server Configuration Section -->
                            <div class="mb-4">
                                <h6 class="mb-3 fw-bold">
                                    <i class="bi bi-hdd-network me-2"></i>
                                    Server Configuration
                                </h6>
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

                            <!-- Landing Page Options Section -->
                            <div class="mb-4">
                                <h6 class="mb-3 fw-bold">
                                    <i class="bi bi-house-door me-2"></i>
                                    Landing Page Options
                                </h6>
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
                            </div>

                            <!-- RePlay Permissions Section -->
                            <div class="mb-4">
                                <h6 class="mb-3 fw-bold">
                                    <i class="bi bi-shield-lock me-2"></i>
                                    RePlay Permissions
                                </h6>
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="allow_vlc" name="allow_vlc"
                                            <?php echo isset($settings['allow_vlc']) && $settings['allow_vlc'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_vlc">
                                            <i class="bi bi-play-circle me-2"></i>
                                            Allow viewers to play recordings in VLC
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="allow_m3u" name="allow_m3u"
                                            <?php echo isset($settings['allow_m3u']) && $settings['allow_m3u'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_m3u">
                                            <i class="bi bi-file-earmark-play me-2"></i>
                                            Allow viewers to download M3U playlists
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="allow_mp4" name="allow_mp4"
                                            <?php echo isset($settings['allow_mp4']) && $settings['allow_mp4'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_mp4">
                                            <i class="bi bi-download me-2"></i>
                                            Allow viewers to download MP4 files
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduler Settings Section -->
                            <div class="mb-4">
                                <h6 class="mb-3 fw-bold">
                                    <i class="bi bi-calendar-check me-2"></i>
                                    Recording Scheduler
                                </h6>
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
                                    <small class="text-muted">When enabled, recordings will automatically start and stop
                                        based on your defined schedules.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="scheduler_notification_email" class="form-label">Notification Email
                                        (Optional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="scheduler_notification_email"
                                               name="scheduler_notification_email"
                                               value="<?php echo htmlspecialchars($settings['scheduler_notification_email'] ?? ''); ?>">
                                    </div>
                                    <small class="text-muted">Receive notifications about scheduled recordings (requires
                                        server mail configuration)</small>
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
            'show_recordings' => isset($_POST['show_recordings']),
            'show_livestream' => isset($_POST['show_livestream']),
            'allow_vlc' => isset($_POST['allow_vlc']),
            'allow_m3u' => isset($_POST['allow_m3u']),
            'allow_mp4' => isset($_POST['allow_mp4']),
            'vlc_webpage_url' => $_POST['vlc_webpage_url'] ?? '',
            'timezone' => $_POST['timezone'] ?? 'America/Chicago',
            'enable_scheduler' => isset($_POST['enable_scheduler']),
            'scheduler_notification_email' => $_POST['scheduler_notification_email'] ?? ''
        ];
        $settingsManager->updateSettings($newSettings, $_SESSION['username']);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

function renderAboutModal()
{
    $version = "1.2.0"; // Update this version number as needed
    $buildDate = "February 2025";

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
