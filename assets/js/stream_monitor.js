/**
 * Enhanced Stream Monitor
 * Provides real-time monitoring of both primary and secondary stream URLs
 */
class StreamMonitor {
    constructor() {
        this.checkIntervalTime = 30000; // 30 seconds
        this.primaryIndicator = null;
        this.secondaryIndicator = null;
        this.checkInterval = null;
        this.lastPrimaryStatus = null;
        this.lastSecondaryStatus = null;
        this.retryCount = 0;
        this.maxRetries = 3;
        this.usingRedundant = false;
    }

    async init() {
        try {
            const response = await fetch('get_settings.php?key=use_redundant_recording');
            const data = await response.json();

            this.usingRedundant = data.value === true || data.value === "true" || data.value === "1";
            this.createStatusIndicators();

            // Immediate first check
            await this.checkStatus();

            // Setup interval for periodic checks
            this.checkInterval = setInterval(() => this.checkStatus(), this.checkIntervalTime);
        } catch (error) {
            console.error('Error checking redundant recording setting:', error);
            this.usingRedundant = false;
            this.createStatusIndicators();

            // Still setup monitoring
            this.checkStatus();
            this.checkInterval = setInterval(() => this.checkStatus(), this.checkIntervalTime);
        }

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
            }
        });
    }

    createStatusIndicators() {
        // Create primary indicator
        this.primaryIndicator = this.createIndicator('primary-stream-status', 'Primary Stream', 20);
        document.body.appendChild(this.primaryIndicator);

        // Create secondary indicator if using redundant
        if (this.usingRedundant) {
            this.secondaryIndicator = this.createIndicator('secondary-stream-status', 'Secondary Stream', 40);
            document.body.appendChild(this.secondaryIndicator);
        }

        // Create tooltip
        const tooltip = document.createElement('div');
        tooltip.id = 'stream-status-tooltip';
        tooltip.style.cssText = `
            position: fixed;
            bottom: 60px;
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
    }

    createIndicator(id, title, bottomPosition) {
        const indicator = document.createElement('div');
        indicator.id = id;
        indicator.title = title;
        indicator.className = 'stream-status-indicator';
        indicator.style.cssText = `
            position: fixed;
            bottom: ${bottomPosition}px;
            right: 20px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background-color: gray;
            z-index: 9999;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            cursor: help;
        `;

        // Add tooltip events
        indicator.addEventListener('mouseenter', () => this.showTooltip(id));
        indicator.addEventListener('mouseleave', () => this.hideTooltip());

        return indicator;
    }

    showTooltip(indicatorId) {
        const tooltip = document.getElementById('stream-status-tooltip');
        if (!tooltip) return;

        let content = '';
        if (indicatorId === 'primary-stream-status') {
            content = this.lastPrimaryStatus
                ? `Primary Stream: ${this.lastPrimaryStatus.active
                    ? (this.lastPrimaryStatus.message || 'Accessible')
                    : (this.lastPrimaryStatus.message || 'Not accessible')}`
                : 'Primary Stream: Checking...';
        } else if (indicatorId === 'secondary-stream-status') {
            content = this.lastSecondaryStatus
                ? `Secondary Stream: ${this.lastSecondaryStatus.active
                    ? (this.lastSecondaryStatus.message || 'Accessible')
                    : (this.lastSecondaryStatus.message || 'Not accessible')}`
                : 'Secondary Stream: Checking...';
        }

        tooltip.textContent = content;
        tooltip.style.display = 'block';
    }

    hideTooltip() {
        const tooltip = document.getElementById('stream-status-tooltip');
        if (tooltip) {
            tooltip.style.display = 'none';
        }
    }

    updateStatusIndicators(primaryStatus, secondaryStatus) {
        if (!this.primaryIndicator) return;

        // Update primary indicator
        if (primaryStatus) {
            if (primaryStatus.active) {
                this.primaryIndicator.style.backgroundColor = '#28a745';
                this.primaryIndicator.title = primaryStatus.message || 'Primary stream is accessible';
            } else {
                this.primaryIndicator.style.backgroundColor = '#dc3545';
                this.primaryIndicator.title = primaryStatus.message || 'Primary stream is not accessible';
            }
        } else {
            this.primaryIndicator.style.backgroundColor = '#6c757d';
            this.primaryIndicator.title = 'Checking primary stream...';
        }

        // Update secondary indicator if using redundant
        if (this.usingRedundant && this.secondaryIndicator) {
            if (secondaryStatus) {
                if (secondaryStatus.active) {
                    this.secondaryIndicator.style.backgroundColor = '#28a745';
                    this.secondaryIndicator.title = secondaryStatus.message || 'Secondary stream is accessible';
                } else {
                    this.secondaryIndicator.style.backgroundColor = '#dc3545';
                    this.secondaryIndicator.title = secondaryStatus.message || 'Secondary stream is not accessible';
                }
            } else {
                this.secondaryIndicator.style.backgroundColor = '#6c757d';
                this.secondaryIndicator.title = 'Checking secondary stream...';
            }
        }
    }

    async checkStatus() {
        // Update to checking state
        this.updateStatusIndicators(null, null);

        try {
            // Fetch primary status
            const primaryResponse = await fetch('check_stream_url.php?stream=primary', {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });

            // Validate primary response
            if (!primaryResponse.ok) {
                throw new Error('Failed to fetch primary stream status');
            }

            const primaryResult = await primaryResponse.json();
            this.lastPrimaryStatus = primaryResult;

            // Fetch secondary status if using redundant
            let secondaryResult = null;
            if (this.usingRedundant) {
                try {
                    const secondaryResponse = await fetch('check_stream_url.php?stream=secondary', {
                        method: 'GET',
                        headers: {
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache'
                        }
                    });

                    // Validate secondary response
                    if (!secondaryResponse.ok) {
                        throw new Error('Failed to fetch secondary stream status');
                    }

                    secondaryResult = await secondaryResponse.json();
                    this.lastSecondaryStatus = secondaryResult;
                } catch (secondaryError) {
                    console.error('Secondary stream check error:', secondaryError);
                    this.lastSecondaryStatus = {
                        active: false,
                        message: 'Unable to check secondary stream'
                    };
                }
            }

            // Update status indicators with independent results
            this.updateStatusIndicators(primaryResult, secondaryResult);

            return {
                primary: primaryResult,
                secondary: secondaryResult
            };

        } catch (error) {
            console.error('Stream status check error:', error);

            const errorResult = {
                active: false,
                message: 'Connection error checking stream'
            };

            // Update indicators with error state
            this.updateStatusIndicators(errorResult, this.usingRedundant ? errorResult : null);

            return null;
        }
    }
}

// Add styles for the indicators
const style = document.createElement('style');
style.textContent = `
    .stream-status-indicator {
        transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stream-status-indicator.pulse {
        animation: pulse-animation 2s infinite;
    }
    
    @keyframes pulse-animation {
        0% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
        }
        70% {
            box-shadow: 0 0 0 6px rgba(40, 167, 69, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
        }
    }
`;
document.head.appendChild(style);

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