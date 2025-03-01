class RecordingController {
    constructor() {
        this.isRecording = false;
        this.recordingTimer = null;
        this.startTime = 0;
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
            const response = await fetch('recording_status.php');
            const data = await response.json();
            
            // Update UI based on recording status
            this.isRecording = data.recording_active;
            this.updateUI(data);
            
            if (data.recording_active && data.recording_start) {
                this.startTime = parseInt(data.recording_start);
                this.startTimer();
            }
        } catch (error) {
            console.error('Error checking recording status:', error);
        }
    }

    updateUI(data) {
        const startButton = document.getElementById('startRecordingBtn');
        const stopButton = document.getElementById('stopRecordingBtn');
        const recordingStatus = document.getElementById('recordingStatus');

        if (startButton && stopButton) {
            startButton.disabled = data.recording_active;
            stopButton.disabled = !data.recording_active;
        }

        if (recordingStatus) {
            if (data.recording_active) {
                recordingStatus.classList.remove('recording-inactive');
                recordingStatus.classList.add('recording-active');

                // Add null check before updating text content
                const statusText = recordingStatus.querySelector('.status-text');
                if (statusText) {
                    statusText.textContent = 'Recording in Progress (DO NOT refresh your browser!)';
                }
            } else {
                recordingStatus.classList.remove('recording-active');
                recordingStatus.classList.add('recording-inactive');

                // Add null check before updating text content
                const statusText = recordingStatus.querySelector('.status-text');
                if (statusText) {
                    statusText.textContent = 'Recording Stopped';
                }
            }
        }
    }
    async startRecording() {
        try {
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
                this.startTimer();
                this.updateUI({ recording_active: true, recording_start: result.start_time });
                
                // Show success message
                this.showNotification('Recording started successfully', 'success');
                
                // Trigger update for all connected clients
                this.triggerPageUpdate();
            } else {
                this.showNotification('Failed to start recording: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error starting recording:', error);
            this.showNotification('Error starting recording. Check console for details.', 'error');
        }
    }

    async stopRecording() {
        if (!confirm('Are you sure you want to stop the current recording?')) {
            return;
        }
        
        try {
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
                this.updateUI({ recording_active: false, recording_start: 0 });
                
                // Show success message
                this.showNotification('Recording stopped successfully', 'success');
                
                // Trigger update for all connected clients
                this.triggerPageUpdate();
            } else {
                this.showNotification('Failed to stop recording: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error stopping recording:', error);
            this.showNotification('Error stopping recording. Check console for details.', 'error');
        }
    }

    startTimer() {
        const timerElement = document.getElementById('recordingTimer');
        if (!timerElement) return;
        
        // Clear existing timer if any
        this.stopTimer();
        
        // Update timer display function
        const updateDisplay = () => {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - this.startTime;
            timerElement.textContent = this.formatTime(elapsed);
        };
        
        // Update immediately and then every second
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
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show fixed-top mx-auto mt-3`;
        notification.style.maxWidth = '500px';
        notification.style.zIndex = '9999';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Add to document
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    triggerPageUpdate() {
        // Update the last_change.txt file timestamp via AJAX
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
    }
});
