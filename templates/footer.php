<script>
    <?php if ($recordingActive && $recordingStart): ?>
    // Start the timer if recording is active
    const startTime = <?php echo $recordingStart; ?>;
    const timerInterval = updateTimer(startTime);
    <?php endif; ?>

    function updateTimer(startTime) {
        const timerElement = document.getElementById('recordingTimer');
        if (!timerElement) return;

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function updateDisplay() {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - startTime;
            timerElement.textContent = formatTime(elapsed);
        }

        updateDisplay();
        return setInterval(updateDisplay, 1000);
    }

    function logVLCPlay(event, filename) {
        // Prevent the default link behavior temporarily
        event.preventDefault();

        const now = new Date();
        const timestamp = now.toISOString().slice(0, 19).replace('T', ' ');
        const vlcLink = event.currentTarget.href;

        // Log the activity
        fetch('log_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=played_vlc&filename=${encodeURIComponent(filename)}&timestamp=${encodeURIComponent(timestamp)}`
        }).then(() => {
            // Check if activity log modal exists before trying to access its properties
            const activityLogModal = document.getElementById('activityLogModal');
            if (activityLogModal && activityLogModal.classList.contains('show')) {
                // Only try to refresh log entries if the function exists
                if (typeof refreshLogEntries === 'function') {
                    refreshLogEntries();
                }
            }

            // After logging, open VLC link
            window.location.href = vlcLink;

            // Open the specified webpage in a new tab after a 3-second delay
            if (window.vlcWebpageUrl) {
                setTimeout(() => {
                    window.open(window.vlcWebpageUrl, '_blank');
                }, 5000); // 5000 milliseconds = 5 seconds
            }
        }).catch(error => {
            console.error('Error logging VLC play:', error);
            // Continue with opening VLC even if logging fails
            window.location.href = vlcLink;

            // Still open the webpage after delay, even if logging failed
            if (window.vlcWebpageUrl) {
                setTimeout(() => {
                    window.open(window.vlcWebpageUrl, '_blank');
                }, 5000); // 5000 milliseconds = 5 seconds
            }
        });
    }

    function logLiveStreamClick(event, openWebpage) {
        const now = new Date();
        const timestamp = now.toISOString().slice(0, 19).replace('T', ' ');

        fetch('log_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=livestream_click&timestamp=${encodeURIComponent(timestamp)}`
        }).then(() => {
            if (document.getElementById('activityLogModal') &&
                document.getElementById('activityLogModal').classList.contains('show')) {
                refreshLogEntries();
            }

            // Open the webpage in a new tab after a delay if enabled
            if (openWebpage && window.vlcWebpageUrl) {
                setTimeout(() => {
                    window.open(window.vlcWebpageUrl, '_blank');
                }, 5000); // 5000 milliseconds = 5 seconds
            }
        });
    }

    function doStayAlive() {
        fetch('stayalive.php')
            .catch(error => console.error('Stay alive request failed:', error));
    }

    const timerStayAlive = setInterval(doStayAlive, 600000); // 10 minutes

    // Only set up FFmpeg status checking for non-admin users
    <?php if (!isAdmin()): ?>
    // Function to check FFmpeg status
    async function checkFFmpegStatus() {
        try {
            const response = await fetch('ffmpeg_status.php');
            const data = await response.json();

            const hasDetectedRecording = sessionStorage.getItem('recordingDetected') === 'true';

            // If recording is not in progress, reset the detection state
            if (!data.recording_in_progress) {
                sessionStorage.removeItem('recordingDetected');
                return;
            }

            // If recording is in progress and we haven't detected it yet
            if (data.recording_in_progress && !hasDetectedRecording) {
                // Mark that we've detected a recording in this session
                sessionStorage.setItem('recordingDetected', 'true');
                // Clear the interval
                if (window.ffmpegStatusInterval) {
                    clearInterval(window.ffmpegStatusInterval);
                }
                // Refresh the page
                window.location.replace(window.location.href);
            }
        } catch (error) {
            console.error('Error checking FFmpeg status:', error);
        }
    }

    // Initial check after a brief delay
    setTimeout(checkFFmpegStatus, 15000);

    // Set up periodic checking every 15 seconds
    window.ffmpegStatusInterval = setInterval(checkFFmpegStatus, 15000);

    // Clean up interval when page is unloaded
    window.addEventListener('beforeunload', () => {
        if (window.ffmpegStatusInterval) {
            clearInterval(window.ffmpegStatusInterval);
        }
    });
    <?php endif; ?>

</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/recordings_update.js"></script>

<?php if (isAdmin() && isset($settings['enable_scheduler']) && $settings['enable_scheduler']): ?>
<!-- This script inclusion should be handled by index.php instead of hardcoded here -->
<?php endif; ?>
<div class="container">
    <hr>
    <div class="text-center text-muted">
        <small>&copy; <?php echo date('Y'); ?> Bethany Church. All rights reserved.</small>
    </div>
</div>
</body>
</html>
