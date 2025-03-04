/**
 * Enhanced Stream Monitor (No Page Refresh)
 * Updates indicator color and status text without refreshing the page
 */
class StreamMonitor {
    constructor() {
        // Tracking properties
        this.statusIndicator = null;
        this.checkInterval = null;
        this.lastStatus = null;
        this.checkInProgress = false;
        this.firstCheckDone = false;
        this.statusTextElement = null;

        // Debugging flag
        this.debugMode = true;
    }

    /**
     * Debug logging method
     */
    debug(message) {
        if (this.debugMode) {
            console.log(`[StreamMonitor] ${message}`);
        }
    }

    /**
     * Initialize the stream monitor
     */
    init() {
        this.createStatusIndicator();
        this.findStatusTextElement();

        // Immediate first check with a slight delay
        setTimeout(() => {
            this.checkStatus();
        }, 1000);

        // Setup interval for periodic checks
        this.checkInterval = setInterval(() => this.checkStatus(), 30000); // Every 30 seconds

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
            }
        });
    }

    /**
     * Find the status text element on the page
     */
    findStatusTextElement() {
        // The status text is inside the element with class "status-text"
        this.statusTextElement = document.querySelector('.status-text');
        this.debug(`Status text element ${this.statusTextElement ? 'found' : 'not found'}`);
    }

    /**
     * Create status indicator
     */
    createStatusIndicator() {
        // Reuse existing indicator if present
        if (document.getElementById('stream-status-indicator')) {
            this.statusIndicator = document.getElementById('stream-status-indicator');
            return;
        }

        this.statusIndicator = document.createElement('div');
        this.statusIndicator.id = 'stream-status-indicator';
        this.statusIndicator.title = 'Stream URL Status';
        this.statusIndicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background-color: gray;
            z-index: 9999;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: background-color 0.2s ease;
        `;

        // Add event listeners
        this.statusIndicator.addEventListener('mouseenter', () => this.showTooltip());
        this.statusIndicator.addEventListener('mouseleave', () => this.hideTooltip());
        this.statusIndicator.addEventListener('click', () => this.forceCheck());

        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.id = 'stream-status-tooltip';
        tooltip.style.cssText = `
            position: fixed;
            bottom: 40px;
            right: 20px;
            background-color: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            display: none;
            z-index: 10000;
            max-width: 250px;
            word-wrap: break-word;
            pointer-events: none;
        `;
        document.body.appendChild(tooltip);

        document.body.appendChild(this.statusIndicator);
    }

    /**
     * Show tooltip with status details
     */
    showTooltip() {
        const tooltip = document.getElementById('stream-status-tooltip');
        if (!tooltip) return;

        const status = this.lastStatus;

        if (status) {
            let tooltipContent = status.active
                ? `Stream URL is accessible<br>Click to recheck`
                : `Stream URL is not accessible<br>Click to recheck`;

            // Add time info
            if (status.last_check) {
                const lastCheckTime = new Date(status.last_check * 1000).toLocaleTimeString();
                tooltipContent += `<br>Last checked: ${lastCheckTime}`;
            }

            tooltip.innerHTML = tooltipContent;
            tooltip.style.display = 'block';
        }
    }

    /**
     * Hide tooltip
     */
    hideTooltip() {
        const tooltip = document.getElementById('stream-status-tooltip');
        if (tooltip) {
            tooltip.style.display = 'none';
        }
    }

    /**
     * Update the status text on the page
     */
    updateStatusText(status) {
        if (!this.statusTextElement) {
            this.findStatusTextElement();
            if (!this.statusTextElement) return;
        }

        // Check if recording is in progress
        const recordingStatus = document.getElementById('recordingStatus');
        const isRecordingActive = recordingStatus && recordingStatus.classList.contains('recording-active');

        this.debug(`Updating status text. Recording active: ${isRecordingActive}, Status: ${JSON.stringify(status)}`);

        if (isRecordingActive) {
            // Always show recording in progress during an active recording
            this.statusTextElement.textContent = 'Recording in Progress (DO NOT refresh your browser!)';
        } else {
            // When not recording, show stream status
            if (status) {
                this.statusTextElement.textContent = status.active === true
                    ? 'Recording Stopped'
                    : 'Stream URL Not Accessible';
            } else {
                // Fallback to generic message if no status
                this.statusTextElement.textContent = 'Stream URL Not Accessible';
            }
        }
    }

    /**
     * Update the status indicator
     */
    updateStatusIndicator(status) {
        if (!this.statusIndicator) return;

        this.debug(`Updating status indicator. Raw status: ${JSON.stringify(status)}`);

        // Clear existing styles
        this.statusIndicator.style.animation = 'none';
        this.statusIndicator.classList.remove('status-check', 'status-active', 'status-inactive');

        // Checking state
        if (status && status.checking) {
            this.statusIndicator.style.backgroundColor = '#FFC107'; // Yellow
            this.statusIndicator.style.animation = 'pulse 2s infinite';
            this.statusIndicator.title = 'Checking stream URL...';
            this.statusIndicator.classList.add('status-check');
            this.forceRedraw(this.statusIndicator);
            return;
        }

        // Determine color and status
        let newColor = '#6c757d'; // Default gray
        let className = '';
        let title = 'Stream URL status unknown';

        // Explicit handling of status
        if (status) {
            // Increased strictness for accessibility
            if (status.active === true) {
                newColor = '#28a745'; // Green
                className = 'status-active';
                title = 'Stream URL is accessible';
            } else {
                newColor = '#dc3545'; // Red
                className = 'status-inactive';
                title = 'Stream URL is not accessible';
            }
        }

        this.debug(`Setting indicator: Color=${newColor}, Class=${className}, Title=${title}`);

        // Update indicator
        this.statusIndicator.style.backgroundColor = newColor;
        this.statusIndicator.title = title;
        if (className) {
            this.statusIndicator.classList.add(className);
        }

        // Force visual update
        this.forceRedraw(this.statusIndicator);
        this.statusIndicator.animate([
            { transform: 'scale(1.2)' },
            { transform: 'scale(1.0)' }
        ], {
            duration: 300,
            easing: 'ease-out'
        });
    }

    /**
     * Force a new stream check
     */
    forceCheck() {
        if (this.checkInProgress) {
            this.debug('Check already in progress, cannot force new check');
            return;
        }

        this.debug('Forcing stream status check');
        this.updateStatusIndicator({ checking: true });
        this.checkStatus(true);
    }

    /**
     * Check stream URL status
     */
    async checkStatus(forceCheck = false) {
        if (this.checkInProgress) return;

        this.checkInProgress = true;
        this.debug(`Starting status check. Force check: ${forceCheck}`);

        try {
            this.updateStatusIndicator({ checking: true });

            const timestamp = Date.now();
            const queryParams = forceCheck ? ['force_check=1'] : [];
            queryParams.push(`t=${timestamp}`);

            const url = `check_stream_url.php?${queryParams.join('&')}`;

            this.debug(`Checking stream status at: ${url}`);

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            });

            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            this.debug(`Stream status response: ${JSON.stringify(result)}`);

            // Handling checking status
            if (result.checking) {
                this.debug('Stream is being checked, will retry shortly');
                setTimeout(() => {
                    this.checkInProgress = false;
                    this.checkStatus(forceCheck);
                }, 2000);
                return;
            }

            // Update last status
            this.lastStatus = result;
            this.firstCheckDone = true;

            // Update indicator and status text
            this.updateStatusIndicator(result);
            this.updateStatusText(result);

            this.checkInProgress = false;
            return result;

        } catch (error) {
            this.debug(`Error checking stream status: ${error.message}`);

            // Reset to a default "not accessible" state on error
            const errorStatus = { active: false, message: 'Error checking stream URL' };
            this.lastStatus = errorStatus;
            this.updateStatusIndicator(errorStatus);
            this.updateStatusText(errorStatus);

            this.checkInProgress = false;
        }
    }

    /**
     * Force DOM redraw for an element
     */
    forceRedraw(element) {
        const originalDisplay = element.style.display;
        element.style.display = 'none';
        void element.offsetHeight; // Trigger reflow
        element.style.display = originalDisplay;

        element.style.opacity = '0.99';
        setTimeout(() => {
            element.style.opacity = '1';
        }, 20);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is admin
    const isAdmin = document.body.dataset.isAdmin === 'true';
    if (isAdmin) {
        // Add CSS for styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.7; }
                100% { transform: scale(1); opacity: 1; }
            }
            
            #stream-status-indicator.status-check {
                background-color: #FFC107 !important;
                animation: pulse 2s infinite;
            }
            
            #stream-status-indicator.status-active {
                background-color: #28a745 !important;
            }
            
            #stream-status-indicator.status-inactive {
                background-color: #dc3545 !important;
            }
        `;
        document.head.appendChild(style);

        // Initialize the stream monitor
        setTimeout(() => {
            window.streamMonitor = new StreamMonitor();
            window.streamMonitor.init();
        }, 500);
    }
});