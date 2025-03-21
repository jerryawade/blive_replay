<?php
// VLC control functions
function getVLCStatus($host, $port, $password) {
    $url = "http://{$host}:{$port}/requests/status.json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, ":" . $password);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Add this to settings.php default settings
$defaultVLCSettings = [
    'vlc_host' => 'localhost',
    'vlc_port' => '8080',
    'vlc_password' => '',
    'enable_vlc_controls' => false
];

// VLC Controls HTML
function renderVLCControls($fileName) {
    global $settings;
    if (!isset($settings['enable_vlc_controls']) || !$settings['enable_vlc_controls']) {
        return '';
    }

    ob_start();
    ?>
    <div class="vlc-controls" id="vlc-controls-<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $fileName); ?>" style="display: none;">
        <div class="progress mb-2" style="height: 10px;">
            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>
        <div class="btn-group">
            <button class="btn btn-sm btn-primary vlc-play">Play</button>
            <button class="btn btn-sm btn-primary vlc-pause">Pause</button>
        </div>
        <span class="ms-2 time-display">00:00 / 00:00</span>
    </div>
    <?php
    return ob_get_clean();
}
?>

<script>
    class VLCController {
        constructor(host, port, password) {
            this.host = host;
            this.port = port;
            this.password = password;
            this.updateInterval = null;
        }

        async sendCommand(command, params = {}) {
            const queryString = new URLSearchParams({ command, ...params }).toString();
            // Use relative path and get current directory
            const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            const url = `${currentPath}vlc_proxy.php?${queryString}`;

            try {
                console.log('Sending request to:', url); // Debug log
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error('VLC command error:', error);
                return null;
            }
        }

        async play() {
            return await this.sendCommand('pl_play');
        }

        async pause() {
            return await this.sendCommand('pl_pause');
        }

        async seek(percent) {
            return await this.sendCommand('seek', { val: `${percent}%` });
        }

        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        startUpdating(controlsId) {
            const controls = document.getElementById(controlsId);
            if (!controls) return;

            const progressBar = controls.querySelector('.progress-bar');
            const timeDisplay = controls.querySelector('.time-display');

            this.updateInterval = setInterval(async () => {
                const status = await this.sendCommand('status');
                if (status) {
                    const position = (status.position * 100) || 0;
                    progressBar.style.width = `${position}%`;

                    const current = this.formatTime(status.time || 0);
                    const total = this.formatTime(status.length || 0);
                    timeDisplay.textContent = `${current} / ${total}`;
                }
            }, 1000);
        }

        stopUpdating() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
        }
    }

    // Initialize VLC controls when Play in VLC is clicked
    function initVLCControls(fileName, host, port, password) {
        const controlsId = `vlc-controls-${fileName.replace(/[^a-zA-Z0-9]/g, '_')}`;
        const controls = document.getElementById(controlsId);
        if (!controls) return;

        const vlc = new VLCController(host, port, password);
        controls.style.display = 'block';

        controls.querySelector('.vlc-play').addEventListener('click', () => vlc.play());
        controls.querySelector('.vlc-pause').addEventListener('click', () => vlc.pause());
        controls.querySelector('.progress').addEventListener('click', (e) => {
            const rect = e.target.getBoundingClientRect();
            const percent = ((e.clientX - rect.left) / rect.width) * 100;
            vlc.seek(percent);
        });

        vlc.startUpdating(controlsId);

        // Clean up when navigating away
        window.addEventListener('beforeunload', () => vlc.stopUpdating());
    }
</script>