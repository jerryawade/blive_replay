/**
 * Asynchronous Stream Monitor
 * Provides non-blocking stream URL status checking
 */
class StreamMonitor {
    constructor() {
        // Tracking properties
        this.statusIndicator = null;
        this.checkInterval = null;
        this.lastStatus = null;
        this.retryCount = 0;
        this.consecutiveFailures = 0;
        this.maxConsecutiveFailures = 3;
    }

    /**
     * Initialize the stream monitor
     */
    init() {
        this.createStatusIndicator();

        // Immediate first check
        this.checkStatus();

        // Setup interval for periodic checks
        this.checkInterval = setInterval(() => this.checkStatus(), 30000); // 30 seconds

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
            }
        });
    }

    /**
     * Create compact status indicator
     */
    createStatusIndicator() {
        // Reuse existing indicator if present
        if (document.getElementById('stream-status-indicator')) {
            this.statusIndicator = document.getElementById('stream-status-indicator');
            return;
        }

        // Create status indicator
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
            cursor: help;
        `;

        // Add event listeners for tooltip
        this.statusIndicator.addEventListener('mouseenter', () => this.showTooltip());
        this.statusIndicator.addEventListener('mouseleave', () => this.hideTooltip());

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
            tooltip.textContent = status.active
                ? (status.message || 'Stream URL is accessible')
                : (status.message || 'Stream URL is not accessible');
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
     * Update the status indicator
     * @param {Object} status - Status information
     */
    updateStatusIndicator(status) {
        if (!this.statusIndicator) return;

        // Update indicator color and title
        if (status) {
            if (status.active) {
                // Green for active
                this.statusIndicator.style.backgroundColor = '#28a745';
                this.statusIndicator.title = status.message || 'Stream URL is accessible';
                // Reset consecutive failures when active
                this.consecutiveFailures = 0;
            } else {
                // Red for inactive
                this.statusIndicator.style.backgroundColor = '#dc3545';
                this.statusIndicator.title = status.message || 'Stream URL is not accessible';
                // Track consecutive failures
                this.consecutiveFailures++;
            }
        } else {
            // Gray for checking
            this.statusIndicator.style.backgroundColor = '#6c757d';
            this.statusIndicator.title = 'Checking stream URL...';
        }
    }

    /**
     * Check stream URL status
     * @param {boolean} verbose - Whether to provide verbose logging
     */
    async checkStatus(verbose = false) {
        try {
            // Update to checking state
            this.updateStatusIndicator(null);

            // Fetch with optional cache bypass
            const response = await fetch(`check_stream_url.php${verbose ? '?bypass_cache=1' : ''}`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });

            // Check response status
            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }

            // Parse JSON response
            const result = await response.json();

            // Update last status and reset retry count
            this.lastStatus = result;
            this.retryCount = 0;

            // Update status indicator
            this.updateStatusIndicator(result);

            // Special handling for repeated failures
            if (this.consecutiveFailures >= this.maxConsecutiveFailures) {
                console.warn(`Stream has failed ${this.consecutiveFailures} consecutive checks`);
                // Potentially trigger an alert or take additional action
            }

            return result;

        } catch (error) {
            // Retry logic
            this.retryCount++;
            if (this.retryCount <= 3) {
                const retryDelay = Math.min(1000 * Math.pow(2, this.retryCount), 30000);

                // Update status to show retry
                this.updateStatusIndicator({
                    active: false,
                    message: `Connection error. Retrying (${this.retryCount}/3)...`
                });

                // Schedule retry
                await new Promise(resolve => setTimeout(resolve, retryDelay));
                return this.checkStatus();
            } else {
                // Max retries reached
                this.updateStatusIndicator({
                    active: false,
                    message: 'Unable to check stream URL status'
                });

                return null;
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is admin
    const isAdmin = document.body.dataset.isAdmin === 'true';
    if (isAdmin) {
        // Delay initialization slightly to avoid potential conflicts
        setTimeout(() => {
            window.streamMonitor = new StreamMonitor();
            window.streamMonitor.init();
        }, 500);
    }
});