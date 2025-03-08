/**
 * Enhanced Stream Monitor
 * Updates indicator color and status text without refreshing the page
 * 
 * Improved stability for handling connection fluctuations
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

        let tooltipContent = '';

        if (this.checkInProgress) {
            tooltipContent = 'Checking recording URL...<br>Click to check again';
        } else if (this.lastStatus) {
            tooltipContent = this.lastStatus.active
                ? `Recording URL is accessible<br>Click to check again`
                : `Recording URL is not accessible<br>Click to check again`;

            // Add time info
            if (this.lastStatus.last_check) {
                const lastCheckTime = new Date(this.lastStatus.last_check * 1000).toLocaleTimeString();
                tooltipContent += `<br>Last checked: ${lastCheckTime}`;
                
                // Add last success info if available
                if (this.lastStatus.last_success) {
                    const lastSuccessTime = new Date(this.lastStatus.last_success * 1000).toLocaleTimeString();
                    tooltipContent += `<br>Last confirmed working: ${lastSuccessTime}`;
                }
                
                // Add retry info if available
                if (this.lastStatus.retries) {
                    tooltipContent += `<br>Retries: ${this.lastStatus.retries}`;
                }
                
                // Add stability info if we're in a stabilized state
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

        // Check if recording is in progress
        const recordingStatus = document.getElementById('recordingStatus');
        const isRecordingActive = recordingStatus && recordingStatus.classList.contains('recording-active');

        this.debug(`Updating status text. Recording active: ${isRecordingActive}, Status: ${JSON.stringify(status)}`);

        if (isRecordingActive) {
            // Always show recording in progress during an active recording
            this.statusTextElement.textContent = 'Recording in Progress (DO NOT refresh your browser!)';
        } else if (this.checkInProgress) {
            // Show checking status when check is in progress
            this.statusTextElement.textContent = 'Checking Stream URL...';
        } else {
            // When not recording, show stream status
            if (status) {
                // Use the stable success state if we're actively stabilizing after failure
                if (this.stabilityTimeout && this.stableSuccessState === true) {
                    this.statusTextElement.textContent = 'Recording Stopped';
                } else {
                    this.statusTextElement.textContent = status.active === true
                        ? 'Recording Stopped'
                        : 'Recording URL Not Accessible';
                }
            } else {
                // Fallback to generic message if no status
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

        // Clear existing styles
        this.statusIndicator.style.animation = 'none';
        this.statusIndicator.classList.remove('status-check', 'status-active', 'status-inactive');

        // Checking state
        if (this.checkInProgress) {
            this.statusIndicator.style.backgroundColor = '#FFC107'; // Yellow
            this.statusIndicator.style.animation = 'pulse 2s infinite';
            this.statusIndicator.title = 'Checking recording URL...';
            this.statusIndicator.classList.add('status-check');
            this.forceRedraw(this.statusIndicator);
            return;
        }

        // Determine color and status
        let newColor = '#6c757d'; // Default gray
        let className = '';
        let title = 'Recording URL status unknown';

        // Explicit handling of status
        if (status) {
            // Enhanced stability handling for indicator color
            if (status.active === true) {
                // Reset failure count on success
                this.failureCount = 0;
                this.stableSuccessState = true;
                
                // Clear any pending stability timeout
                if (this.stabilityTimeout) {
                    clearTimeout(this.stabilityTimeout);
                    this.stabilityTimeout = null;
                }
                
                newColor = '#28a745'; // Green
                className = 'status-active';
                title = 'Recording URL is accessible';
            } else {
                // Status is false - handle consecutive failures
                this.failureCount++;
                this.debug(`Failure count incremented to ${this.failureCount}`);
                
                // If we're in stability period, maintain green
                if (this.stableSuccessState === true) {
                    // If we have a recent success and this is the first failure, start stability timer
                    if (this.failureCount === 1 && status.last_success) {
                        const timeSinceLastSuccess = (Date.now() / 1000) - status.last_success;
                        
                        // If the last success was within the last 10 minutes, maintain green
                        if (timeSinceLastSuccess < 600) { // 10 minutes in seconds
                            this.debug("Recent success detected, stabilizing state as green");
                            newColor = '#28a745'; // Green
                            className = 'status-active';
                            title = 'Recording URL is likely accessible (recent success)';
                            
                            // Set up stability timeout to reset after some time if failures continue
                            if (this.stabilityTimeout) {
                                clearTimeout(this.stabilityTimeout);
                            }
                            
                            this.stabilityTimeout = setTimeout(() => {
                                this.debug("Stability timeout expired, resetting state");
                                this.stableSuccessState = null;
                                this.stabilityTimeout = null;
                                
                                // Only update UI if failures have continued
                                if (this.failureCount >= this.maxFailureCount) {
                                    this.updateStatusIndicator(this.lastStatus);
                                    this.updateStatusText(this.lastStatus);
                                }
                            }, 60000); // 1 minute stability period
                            
                            // Early return to maintain green state
                            this.debug(`Setting stabilized indicator: Color=${newColor}, Class=${className}, Title=${title}`);
                            this.statusIndicator.style.backgroundColor = newColor;
                            this.statusIndicator.title = title;
                            this.statusIndicator.classList.add(className);
                            this.forceRedraw(this.statusIndicator);
                            return;
                        }
                    }
                }
                
                // If we've reached the failure threshold, show red
                if (this.failureCount >= this.maxFailureCount) {
                    this.stableSuccessState = false;
                    newColor = '#dc3545'; // Red
                    className = 'status-inactive';
                    title = 'Recording URL is not accessible';
                } else {
                    // For early failures, show orange as a warning
                    newColor = '#fd7e14'; // Orange
                    className = 'status-warning';
                    title = 'Recording URL may be temporarily unavailable';
                }
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
        this.debug('Forcing stream status check');

        // Set checking state even if a check is already in progress
        this.checkInProgress = true;
        this.updatesPending = true;

        // Update UI to show checking state
        this.updateStatusIndicator();
        this.updateStatusText();

        // Start a fresh check
        this.checkStatus(true);
    }

    /**
     * Poll for status updates when a check is in progress
     */
    pollForUpdates() {
        // Only poll if a check is in progress or updates are pending
        if (!this.checkInProgress && !this.updatesPending) return;

        // Don't poll too frequently
        const now = Date.now();
        if (now - this.lastCheckTime < 1000) return;

        this.lastCheckTime = now;
        this.debug("Polling for status updates");

        // Add a timestamp to prevent caching
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

                // Validate the data object
                if (!data || typeof data.active === 'undefined') {
                    throw new Error('Invalid response from server');
                }

                // If data indicates checking is still in progress, maintain checking state
                if (data.checking) {
                    this.debug("Still checking according to server");
                    this.checkInProgress = true;
                    return;
                }

                // Check is complete, update status
                this.lastStatus = data;
                this.checkInProgress = false;
                this.updatesPending = false;
                this.updateStatusIndicator(data);
                this.updateStatusText(data);
            })
            .catch(error => {
                this.debug(`Error polling for updates: ${error.message}`);
                // Don't change state on error to avoid flickering
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

        // Update UI immediately to show checking state
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

            // Validate the result object
            if (!result || typeof result.active === 'undefined') {
                throw new Error('Invalid response from server');
            }

            // If checking is in progress, leave UI in checking state
            if (result.checking) {
                this.debug('Stream check in progress, will continue polling');
                return;
            }

            // Check is complete, update status
            this.lastStatus = result;
            this.firstCheckDone = true;
            this.checkInProgress = false;
            this.updatesPending = false;

            // Update UI with fresh status
            this.updateStatusIndicator(result);
            this.updateStatusText(result);

            return result;

        } catch (error) {
            this.debug(`Error checking stream status: ${error.message}`);

            // For connection errors to the status endpoint, don't count as a stream error
            // Instead maintain the previous status
            if (this.lastStatus) {
                this.debug('Maintaining previous status due to fetch error');
                this.checkInProgress = false;
                this.updatesPending = false;
                return this.lastStatus;
            }

            // Only set to "not accessible" if this is the first check ever
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
            
            #stream-status-indicator.status-warning {
                background-color: #fd7e14 !important;
            }
        `;
        document.head.appendChild(style);

        // Initialize the stream monitor
        setTimeout(() => {
            // Clean up any existing monitor first
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
