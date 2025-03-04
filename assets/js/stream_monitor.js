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
        console.log("Status text element found:", this.statusTextElement);
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

        if (status && status.active) {
            this.statusTextElement.textContent = 'Recording Stopped';
        } else if (status) {
            this.statusTextElement.textContent = 'Stream URL Not Accessible';
        }
    }
    
    /**
     * Show a notification message
     */
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show fixed-top mx-auto mt-3`;
        notification.style.maxWidth = '500px';
        notification.style.zIndex = '9999';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Add to body
        document.body.appendChild(notification);
        
        // Auto-remove after delay
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    /**
     * Force DOM redraw for an element
     */
    forceRedraw(element) {
        // Some ways to force a repaint
        const originalDisplay = element.style.display;
        element.style.display = 'none';
        void element.offsetHeight; // Trigger reflow
        element.style.display = originalDisplay;
        
        // Also try changing a non-visible property
        element.style.opacity = '0.99';
        setTimeout(() => {
            element.style.opacity = '1';
        }, 20);
    }

    /**
     * Update the status indicator with guaranteed visual update
     */
    updateStatusIndicator(status) {
        if (!this.statusIndicator) return;
        console.log("Updating status indicator with:", status);

        // Clear any existing styles and classes
        this.statusIndicator.style.animation = 'none';
        this.statusIndicator.classList.remove('status-check', 'status-active', 'status-inactive');

        // Temporarily update to the checking state
        if (status && status.checking) {
            // Method 1: Direct style
            this.statusIndicator.style.backgroundColor = '#FFC107'; // Yellow
            this.statusIndicator.style.animation = 'pulse 2s infinite';
            this.statusIndicator.title = 'Checking stream URL...';
            
            // Method 2: Class-based
            this.statusIndicator.classList.add('status-check');
            
            // Force a redraw
            this.forceRedraw(this.statusIndicator);
            return;
        }

        // Update status text if needed
        this.updateStatusText(status);

        // Determine the color based on status
        let newColor, className;
        if (status && status.active) {
            newColor = '#28a745'; // Green
            className = 'status-active';
            this.statusIndicator.title = 'Stream URL is accessible';
        } else if (status) {
            newColor = '#dc3545'; // Red
            className = 'status-inactive';
            this.statusIndicator.title = 'Stream URL is not accessible';
        } else {
            newColor = '#6c757d'; // Gray
            this.statusIndicator.title = 'Stream URL status unknown';
        }

        // Method 1: Use multiple approaches to ensure color change is visible
        // Direct style change
        this.statusIndicator.style.backgroundColor = newColor;
        
        // Method 2: Class-based approach
        this.statusIndicator.classList.add(className);
        
        // Method 3: Force a DOM reflow
        this.forceRedraw(this.statusIndicator);
        
        // Method 4: Brief animation to ensure visibility change is noticeable
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
        // Only allow if not already checking
        if (this.checkInProgress) {
            this.showNotification('Check already in progress, please wait', 'info');
            return;
        }

        // Show checking state
        this.updateStatusIndicator({
            checking: true,
            message: 'Checking stream URL...'
        });

        // Show notification
        this.showNotification('Checking stream URL...', 'info');

        // Start the check
        this.checkStatus(true);
    }

    /**
     * Check stream URL status
     * @param {boolean} forceCheck - Whether to force a fresh check
     */
    async checkStatus(forceCheck = false) {
        // Prevent multiple simultaneous checks
        if (this.checkInProgress) {
            return;
        }

        this.checkInProgress = true;

        try {
            // Show checking state
            this.updateStatusIndicator({
                checking: true,
                message: 'Checking stream URL...'
            });

            // Build URL with cache buster
            const timestamp = Date.now();
            const queryParams = [];
            
            if (forceCheck) {
                queryParams.push('force_check=1');
            }
            
            // Always add cache buster
            queryParams.push(`t=${timestamp}`);
            
            const url = `check_stream_url.php?${queryParams.join('&')}`;
            
            console.log("Checking stream status...");
            
            // Make the request with explicit cache control
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            });

            // Check response status
            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }

            // Parse JSON response
            const result = await response.json();
            console.log("Stream status response:", result);

            // Check if result has a "checking" status
            if (result.checking) {
                console.log("Stream is being checked, will retry shortly");
                
                // Wait 2 seconds and try again
                setTimeout(() => {
                    this.checkInProgress = false;
                    this.checkStatus(forceCheck);
                }, 2000);
                
                return;
            }

            // Check if status changed
            const statusChanged = !this.lastStatus || 
                (this.lastStatus.active !== result.active);
                
            if (statusChanged && this.firstCheckDone) {
                // Show notification if status changed (but not on first check)
                this.showNotification(
                    `Stream status changed: Stream URL is now ${result.active ? 'accessible' : 'not accessible'}`,
                    result.active ? 'success' : 'danger'
                );
            }
            
            // Update last status
            this.lastStatus = result;
            this.firstCheckDone = true;

            // Update status indicator
            this.updateStatusIndicator(result);
            
            // Show notification on forced check
            if (forceCheck) {
                this.showNotification(
                    `Stream check complete: Stream URL is ${result.active ? 'accessible' : 'not accessible'}`,
                    'info'
                );
            }

            this.checkInProgress = false;
            return result;

        } catch (error) {
            console.error('Error checking stream status:', error);
            
            // Show error notification on forced check
            if (forceCheck) {
                this.showNotification(
                    `Error checking stream: ${error.message}`,
                    'danger'
                );
            }
            
            this.checkInProgress = false;
        }
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
