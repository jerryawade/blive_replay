/**
 * Enhanced Stream Monitor
 * Updates indicator color and status text without refreshing the page
 *
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
        this.lastCheckTime = 0;
        this.pollInterval = null;
        this.updatesPending = false;
        this.failureCount = 0;          // Track consecutive failures
        this.maxFailureCount = 3;       // Number of consecutive failures before showing red
        this.stableSuccessState = null; // Track last stable state
        this.stabilityTimeout = null;   // Timer for stabilizing state

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

        // Setup polling for status updates when a check is in progress
        this.pollInterval = setInterval(() => this.pollForUpdates(), 1000); // Poll every second

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
            }
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
            if (this.stabilityTimeout) {
                clearTimeout(this.stabilityTimeout);
            }
        });
    }

    /**
     * Find the status text element on the page
     */
    findStatusTextElement() {
        this.statusTextElement = document.querySelector('.status-text');
        this.debug(`Status text element ${this.statusTextElement ? 'found' : 'not found'}`);
    }

    /**
     * Create status indicator
     */
    createStatusIndicator() {
        if (document.getElementById('stream-status-indicator')) {
            this.statusIndicator = document.getElementById('stream-status-indicator');
            return;
        }

        this.statusIndicator = document.createElement('div');
        this.statusIndicator.id = 'stream-status-indicator';
        this.statusIndicator.title = 'Recording URL Status';
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

        this.statusIndicator.addEventListener('mouseenter', () => this.showTooltip());
        this.statusIndicator.addEventListener('mouseleave', () => this.hideTooltip());
        this.statusIndicator.addEventListener('click', () => this.forceCheck());

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

        let tooltipContent = '';

        if (this.checkInProgress) {
            tooltipContent = 'Checking recording URL...<br>Click to check again';
        } else if (this.lastStatus) {
            tooltipContent = this.lastStatus.active
                ? `Recording URL is accessible<br>Click to check again`
                : `Recording URL is not accessible<br>Click to check again`;

            if (this.lastStatus.last_check) {
                const lastCheckTime = new Date(this.lastStatus.last_check * 1000).toLocaleTimeString();
                tooltipContent += `<br>Last checked: ${lastCheckTime}`;

                if (this.lastStatus.last_success) {
                    const lastSuccessTime = new Date(this.lastStatus.last_success * 1000).toLocaleTimeString();
                    tooltipContent += `<br>Last confirmed working: ${lastSuccessTime}`;
                }

                if (this.lastStatus.retries) {
                    tooltipContent += `<br>Retries: ${this.lastStatus.retries}`;
                }

                if (this.failureCount > 0 && this.lastStatus.active) {
                    tooltipContent += `<br><small>(Status stabilized after temporary issue)</small>`;
                }
            }
        } else {
            tooltipContent = 'Stream status unknown<br>Click to check now';
        }

        tooltip.innerHTML = tooltipContent;
        tooltip.style.display = 'block';
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

        const recordingStatus = document.getElementById('recordingStatus');
        const isRecordingActive = recordingStatus && recordingStatus.classList.contains('recording-active');

        this.debug(`Updating status text. Recording active: ${isRecordingActive}, Status: ${JSON.stringify(status)}`);

        if (isRecordingActive) {
            this.statusTextElement.textContent = 'Recording in Progress (DO NOT refresh your browser!)';
        } else if (this.checkInProgress) {
            this.statusTextElement.textContent = 'Checking Stream URL...';
        } else {
            if (status) {
                if (this.stabilityTimeout && this.stableSuccessState === true) {
                    this.statusTextElement.textContent = 'Recording Stopped';
                } else {
                    this.statusTextElement.textContent = status.active === true
                        ? 'Recording Stopped'
                        : 'Recording URL Not Accessible';
                }
            } else {
                this.statusTextElement.textContent = 'Stream Status Unknown';
            }
        }
    }

    /**
     * Update the status indicator
     */
    updateStatusIndicator(status) {
        if (!this.statusIndicator) return;

        this.debug(`Updating status indicator. Status: ${JSON.stringify(status)}, CheckInProgress: ${this.checkInProgress}`);

        this.statusIndicator.style.animation = 'none';
        this.statusIndicator.classList.remove('status-check', 'status-active', 'status-inactive');

        if (this.checkInProgress) {
            this.statusIndicator.style.backgroundColor = '#FFC107'; // Yellow
            this.statusIndicator.style.animation = 'pulse 2s infinite';
            this.statusIndicator.title = 'Checking recording URL...';
            this.statusIndicator.classList.add('status-check');
            this.forceRedraw(this.statusIndicator);
            return;
        }

        let newColor = '#6c757d'; // Default gray
        let className = '';
        let title = 'Recording URL status unknown';

        if (status) {
            if (status.active === true) {
                this.failureCount = 0;
                this.stableSuccessState = true;

                if (this.stabilityTimeout) {
                    clearTimeout(this.stabilityTimeout);
                    this.stabilityTimeout = null;
                }

                newColor = '#28a745'; // Green
                className = 'status-active';
                title = 'Recording URL is accessible';
            } else {
                this.failureCount++;
                this.debug(`Failure count incremented to ${this.failureCount}`);

                if (this.stableSuccessState === true) {
                    if (this.failureCount === 1 && status.last_success) {
                        const timeSinceLastSuccess = (Date.now() / 1000) - status.last_success;

                        if (timeSinceLastSuccess < 600) { // 10 minutes in seconds
                            this.debug("Recent success detected, stabilizing state as green");
                            newColor = '#28a745'; // Green
                            className = 'status-active';
                            title = 'Recording URL is likely accessible (recent success)';

                            if (this.stabilityTimeout) {
                                clearTimeout(this.stabilityTimeout);
                            }

                            this.stabilityTimeout = setTimeout(() => {
                                this.debug("Stability timeout expired, resetting state");
                                this.stableSuccessState = null;
                                this.stabilityTimeout = null;
                                if (this.failureCount >= this.maxFailureCount) {
                                    this.updateStatusIndicator(this.lastStatus);
                                    this.updateStatusText(this.lastStatus);
                                }
                            }, 300000); // 5 minutes stability period

                            this.debug(`Setting stabilized indicator: Color=${newColor}, Class=${className}, Title=${title}`);
                            this.statusIndicator.style.backgroundColor = newColor;
                            this.statusIndicator.title = title;
                            this.statusIndicator.classList.add(className);
                            this.forceRedraw(this.statusIndicator);
                            return;
                        }
                    }
                }

                if (this.failureCount >= this.maxFailureCount) {
                    this.stableSuccessState = false;
                    newColor = '#dc3545'; // Red
                    className = 'status-inactive';
                    title = 'Recording URL is not accessible';
                } else {
                    newColor = '#fd7e14'; // Orange
                    className = 'status-warning';
                    title = 'Recording URL may be temporarily unavailable';
                }
            }
        }

        this.debug(`Setting indicator: Color=${newColor}, Class=${className}, Title=${title}`);

        this.statusIndicator.style.backgroundColor = newColor;
        this.statusIndicator.title = title;
        if (className) {
            this.statusIndicator.classList.add(className);
        }

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
        this.debug('Forcing stream status check');
        this.checkInProgress = true;
        this.updatesPending = true;
        this.updateStatusIndicator();
        this.updateStatusText();
        this.checkStatus(true);
    }

    /**
     * Poll for status updates when a check is in progress
     */
    pollForUpdates() {
        if (!this.checkInProgress && !this.updatesPending) return;
        const now = Date.now();
        if (now - this.lastCheckTime < 1000) return;
        this.lastCheckTime = now;
        this.debug("Polling for status updates");
        fetch(`check_stream_url.php?t=${now}`, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error(`Server responded with ${response.status}`);
                return response.json();
            })
            .then(data => {
                this.debug(`Poll received data: ${JSON.stringify(data)}`);
                if (!data || typeof data.active === 'undefined') {
                    throw new Error('Invalid response from server');
                }
                if (data.checking) {
                    this.debug("Still checking, maintaining current state");
                    this.checkInProgress = true;
                    return;
                }
                this.lastStatus = data;
                this.checkInProgress = false;
                this.updatesPending = false;
                if (data.active === false) {
                    this.failureCount++;
                } else {
                    this.failureCount = 0;
                }
                this.updateStatusIndicator(data);
                this.updateStatusText(data);
            })
            .catch(error => {
                this.debug(`Error polling for updates: ${error.message}`);
            });
    }

    /**
     * Check stream URL status
     */
    async checkStatus(forceCheck = false) {
        if (this.checkInProgress && !forceCheck) return;
        this.checkInProgress = true;
        this.updatesPending = true;
        this.debug(`Starting status check. Force check: ${forceCheck}`);
        this.updateStatusIndicator();
        this.updateStatusText();

        try {
            const timestamp = Date.now();
            this.lastCheckTime = timestamp;
            const queryParams = forceCheck ? 'force_check=1&' : '';
            const url = `check_stream_url.php?${queryParams}t=${timestamp}`;

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

            if (!result || typeof result.active === 'undefined') {
                throw new Error('Invalid response from server');
            }

            if (result.checking) {
                this.debug('Stream check in progress, will continue polling');
                return;
            }

            this.lastStatus = result;
            this.firstCheckDone = true;
            this.checkInProgress = false;
            this.updatesPending = false;
            if (result.active === false) {
                this.failureCount++;
            } else {
                this.failureCount = 0;
            }
            this.updateStatusIndicator(result);
            this.updateStatusText(result);

            return result;

        } catch (error) {
            this.debug(`Error checking stream status: ${error.message}`);
            if (this.lastStatus) {
                this.debug('Maintaining previous status due to fetch error');
                this.checkInProgress = false;
                this.updatesPending = false;
                return this.lastStatus;
            }

            const errorStatus = {
                active: false,
                message: 'Error checking recording URL',
                error: true
            };
            this.lastStatus = errorStatus;
            this.checkInProgress = false;
            this.updatesPending = false;
            this.updateStatusIndicator(errorStatus);
            this.updateStatusText(errorStatus);
        }
    }

    /**
     * Force DOM redraw for an element
     */
    forceRedraw(element) {
        const originalDisplay = element.style.display;
        element.style.display = 'none';
        void element.offsetHeight;
        element.style.display = originalDisplay;

        element.style.opacity = '0.99';
        setTimeout(() => {
            element.style.opacity = '1';
        }, 20);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const isAdmin = document.body.dataset.isAdmin === 'true';
    if (isAdmin) {
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
            
            #stream-status-indicator.status-warning {
                background-color: #fd7e14 !important;
            }
        `;
        document.head.appendChild(style);

        setTimeout(() => {
            if (window.streamMonitor) {
                if (window.streamMonitor.checkInterval) {
                    clearInterval(window.streamMonitor.checkInterval);
                }
                if (window.streamMonitor.pollInterval) {
                    clearInterval(window.streamMonitor.pollInterval);
                }
                if (window.streamMonitor.stabilityTimeout) {
                    clearTimeout(window.streamMonitor.stabilityTimeout);
                }
            }

            window.streamMonitor = new StreamMonitor();
            window.streamMonitor.init();
        }, 500);
    }
});