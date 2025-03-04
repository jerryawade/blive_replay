class RecordingController {
    constructor() {
        this.isRecording = false;
        this.recordingTimer = null;
        this.startTime = 0;
        this.usingRedundant = false;
        this.primaryActive = false;
        this.secondaryActive = false;
        this.setupEventListeners();
        this.checkRecordingStatus();
    }

    setupEventListeners() {
        // Find start and stop buttons
        const startButton = document.getElementById('startRecordingBtn');
        const stopButton = document.getElementById('stopRecordingBtn');

        if (startButton) {
            startButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.startRecording();
            });
        }

        if (stopButton) {
            stopButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.stopRecording();
            });
        }
    }

    async checkRecordingStatus() {
        try {
            const [recordingResponse, streamResponse, redundantResponse] = await Promise.all([
                fetch('recording_status.php'),
                fetch('check_stream_url.php'),
                fetch('check_redundant_recording.php').catch(() => ({ json: () => Promise.resolve({ redundant: false }) }))
            ]);

            const recordingData = await recordingResponse.json();
            const streamData = await streamResponse.json();

            // Try to get redundant recording status, but don't fail if endpoint doesn't exist
            let redundantData = { redundant: false };
            try {
                redundantData = await redundantResponse.json();
            } catch (error) {
                console.log('Redundant recording status check not available');
            }

            // Combine recording and stream data for UI update
            const combinedData = {
                ...recordingData,
                stream_accessible: streamData.active,
                stream_message: streamData.message,
                using_redundant: redundantData.using_redundant || false,
                primary_active: redundantData.primary || false,
                secondary_active: redundantData.secondary || false
            };

            // Update UI with combined data
            this.isRecording = recordingData.recording_active;
            this.usingRedundant = combinedData.using_redundant;
            this.primaryActive = combinedData.primary_active;
            this.secondaryActive = combinedData.secondary_active;

            this.updateUI(combinedData);

            if (recordingData.recording_active && recordingData.recording_start) {
                this.startTime = parseInt(recordingData.recording_start);
                this.startTimer();
            }
        } catch (error) {
            console.error('Error checking recording and stream status:', error);

            // Disable start button if there's an error checking the stream
            const startButton = document.getElementById('startRecordingBtn');
            if (startButton) {
                startButton.disabled = true;
                startButton.setAttribute('title', 'Unable to verify stream URL');
            }
        }
    }

    updateUI(data) {
        const startButton = document.getElementById('startRecordingBtn');
        const stopButton = document.getElementById('stopRecordingBtn');
        const recordingStatus = document.getElementById('recordingStatus');

        if (startButton && stopButton) {
            // Disable start button if:
            // 1. Recording is already in progress, OR
            // 2. Stream is not accessible
            const shouldDisableStart = data.recording_active || !data.stream_accessible;
            startButton.disabled = shouldDisableStart;

            // Set appropriate tooltip
            if (data.recording_active) {
                startButton.setAttribute('title', 'Recording is already in progress');
            } else if (!data.stream_accessible) {
                startButton.setAttribute('title', data.stream_message || 'Stream URL is not accessible');
            } else {
                startButton.removeAttribute('title');
            }

            stopButton.disabled = !data.recording_active;
        }

        if (recordingStatus) {
            if (data.recording_active) {
                recordingStatus.classList.remove('recording-inactive');
                recordingStatus.classList.add('recording-active');

                const statusText = recordingStatus.querySelector('.status-text');
                if (statusText) {
                    // If using redundant recording, show status of both streams
                    if (data.using_redundant) {
                        const primaryStatus = data.primary_active ? 'Active' : 'Inactive';
                        const secondaryStatus = data.secondary_active ? 'Active' : 'Inactive';
                        statusText.innerHTML = `Recording in Progress (DO NOT refresh your browser!)<br>
                            <small>Primary: ${primaryStatus}, Secondary: ${secondaryStatus}</small>`;
                    } else {
                        statusText.textContent = 'Recording in Progress (DO NOT refresh your browser!)';
                    }
                }
            } else {
                recordingStatus.classList.remove('recording-active');
                recordingStatus.classList.add('recording-inactive');

                const statusText = recordingStatus.querySelector('.status-text');
                if (statusText) {
                    statusText.textContent = data.stream_accessible
                        ? 'Recording Stopped'
                        : 'Stream URL Not Accessible';
                }
            }
        }
    }

    async startRecording() {
        try {
            // Show loading state on button
            const startButton = document.getElementById('startRecordingBtn');
            if (startButton) {
                const originalContent = startButton.innerHTML;
                startButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Starting...';
                startButton.disabled = true;
            }

            const response = await fetch('recording_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=start'
            });

            const result = await response.json();

            if (result.success) {
                this.isRecording = true;
                this.startTime = result.start_time;
                this.usingRedundant = result.redundant || false;
                this.primaryActive = result.primary_success || false;
                this.secondaryActive = result.secondary_success || false;

                this.startTimer();

                // Update UI with the new recording state
                this.updateUI({
                    recording_active: true,
                    recording_start: result.start_time,
                    stream_accessible: true,
                    using_redundant: this.usingRedundant,
                    primary_active: this.primaryActive,
                    secondary_active: this.secondaryActive
                });

                this.showNotification('Recording started successfully', 'success');
                this.triggerPageUpdate();
            } else {
                // Reset start button
                if (startButton) {
                    startButton.innerHTML = '<i class="bi bi-record-circle"></i> Start Recording';
                    startButton.disabled = false;
                }

                this.showNotification('Failed to start recording: ' + result.message, 'error');

                // Recheck status to update UI
                this.checkRecordingStatus();
            }
        } catch (error) {
            console.error('Error starting recording:', error);
            this.showNotification('Error starting recording. Check console for details.', 'error');

            // Reset start button
            const startButton = document.getElementById('startRecordingBtn');
            if (startButton) {
                startButton.innerHTML = '<i class="bi bi-record-circle"></i> Start Recording';
                startButton.disabled = false;
            }

            // Recheck status to update UI
            this.checkRecordingStatus();
        }
    }

    async stopRecording() {
        if (!confirm('Are you sure you want to stop the current recording?')) {
            return;
        }

        try {
            // Show loading state on button
            const stopButton = document.getElementById('stopRecordingBtn');
            if (stopButton) {
                const originalContent = stopButton.innerHTML;
                stopButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Stopping...';
                stopButton.disabled = true;
            }

            const response = await fetch('recording_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=stop'
            });

            const result = await response.json();

            if (result.success) {
                this.isRecording = false;
                this.stopTimer();

                // Reset redundant status
                this.usingRedundant = false;
                this.primaryActive = false;
                this.secondaryActive = false;

                // Recheck status to update UI
                this.checkRecordingStatus();

                this.showNotification('Recording stopped successfully', 'success');
                this.triggerPageUpdate();
            } else {
                // Reset stop button
                if (stopButton) {
                    stopButton.innerHTML = '<i class="bi bi-stop-circle"></i> Stop Recording';
                    stopButton.disabled = false;
                }

                this.showNotification('Failed to stop recording: ' + result.message, 'error');

                // Recheck status to update UI
                this.checkRecordingStatus();
            }
        } catch (error) {
            console.error('Error stopping recording:', error);
            this.showNotification('Error stopping recording. Check console for details.', 'error');

            // Reset stop button
            const stopButton = document.getElementById('stopRecordingBtn');
            if (stopButton) {
                stopButton.innerHTML = '<i class="bi bi-stop-circle"></i> Stop Recording';
                stopButton.disabled = false;
            }

            // Recheck status to update UI
            this.checkRecordingStatus();
        }
    }

    startTimer() {
        const timerElement = document.getElementById('recordingTimer');
        if (!timerElement) return;

        this.stopTimer();

        const updateDisplay = () => {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - this.startTime;
            timerElement.textContent = this.formatTime(elapsed);
        };

        updateDisplay();
        this.recordingTimer = setInterval(updateDisplay, 1000);
    }

    stopTimer() {
        if (this.recordingTimer) {
            clearInterval(this.recordingTimer);
            this.recordingTimer = null;
        }
    }

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show fixed-top mx-auto mt-3`;
        notification.style.maxWidth = '500px';
        notification.style.zIndex = '9999';

        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    triggerPageUpdate() {
        fetch('update_change_timestamp.php', { method: 'POST' })
            .catch(error => console.error('Error updating timestamp:', error));
    }
}

// Initialize recording controller when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize for admin users
    const isAdmin = document.body.dataset.isAdmin === 'true';
    if (isAdmin) {
        window.recordingController = new RecordingController();

        // Set up a periodic recording status check
        setInterval(() => {
            if (window.recordingController) {
                window.recordingController.checkRecordingStatus();
            }
        }, 30000); // Check every 30 seconds
    }
});