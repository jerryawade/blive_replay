/**
 * Clear log button initialization and event handlers
 */
document.addEventListener('DOMContentLoaded', function() {
    // Set up tab change events to manage the clear log button visibility
    const logTabs = document.querySelectorAll('#logTabs .nav-link');
    const clearLogButton = document.getElementById('clearCurrentLogBtn');

    if (logTabs && clearLogButton) {
        // Map of tab IDs to log types
        const tabToLogType = {
            'ffmpegLogTab': 'ffmpeg',
            'schedulerLogTab': 'scheduler',
            'schedulerLogFileTab': 'schedulerFile',
            'streamLogTab': 'stream',
            'emailLogTab': 'email',
            'debugLogTab': 'debug'
        };

        // Initial state - hide button when user activity log is shown
        if (document.querySelector('#logTabs .nav-link.active').id === 'userLogTab') {
            clearLogButton.style.display = 'none';
        } else {
            clearLogButton.style.display = 'inline-flex';
        }

        // Listen for tab changes
        logTabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(event) {
                // Show/hide clear button based on active tab
                if (event.target.id === 'userLogTab') {
                    clearLogButton.style.display = 'none';
                } else {
                    clearLogButton.style.display = 'inline-flex';
                }
            });
        });

        // Handle clear log button click
        clearLogButton.addEventListener('click', function() {
            // Get the currently active tab
            const activeTab = document.querySelector('#logTabs .nav-link.active');
            if (!activeTab) return;

            // Get the log type based on active tab
            const logType = tabToLogType[activeTab.id];
            if (!logType) return;

            // Get a friendly name for the log type
            const logName = activeTab.textContent.trim();

            // Show the confirmation dialog (matching other system confirmation dialogs)
            showDeleteConfirmationModal(logName, logType);
        });
    }

    // Ensure this runs when the modal is shown
    const activityLogModal = document.getElementById('activityLogModal');
    if (activityLogModal) {
        activityLogModal.addEventListener('shown.bs.modal', function() {
            // Initial state - hide button when user activity log is shown
            const activeTab = document.querySelector('#logTabs .nav-link.active');
            if (activeTab && clearLogButton) {
                if (activeTab.id === 'userLogTab') {
                    clearLogButton.style.display = 'none';
                } else {
                    clearLogButton.style.display = 'inline-flex';
                }
            }
        });
    }
});

/**
 * Clear the specified log via AJAX
 *
 * @param {string} logType The log type to clear (ffmpeg, scheduler, etc.)
 */
async function clearLog(logType) {
    // Show a loading overlay
    showLoadingOverlay();

    try {
        // Prepare form data
        const formData = new FormData();
        formData.append('log', logType);

        // Send the clear request
        const response = await fetch('clear_logs.php', {
            method: 'POST',
            body: formData
        });

        // Parse the response
        const result = await response.json();

        if (result.success) {
            // Show success message
            showClearLogResult(true, result.message);

            // Refresh the current log content
            refreshCurrentLog();
        } else {
            // Show error message
            showClearLogResult(false, result.message);
        }
    } catch (error) {
        console.error('Error clearing log:', error);
        showClearLogResult(false, 'Error communicating with the server');
    } finally {
        // Hide loading overlay
        hideLoadingOverlay();
    }
}

/**
 * Show loading overlay while clearing logs
 */
function showLoadingOverlay() {
    // Create a loading overlay if it doesn't exist
    let overlay = document.getElementById('log-clear-overlay');

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'log-clear-overlay';
        overlay.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center h-100">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Clearing log...</span>
                </div>
                <p class="text-white">Clearing log, please wait...</p>
            </div>
        `;

        // Style the overlay
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        `;

        // Add to modal body
        const modalBody = document.querySelector('#activityLogModal .modal-body');
        if (modalBody) {
            modalBody.style.position = 'relative';
            modalBody.appendChild(overlay);
        }
    } else {
        overlay.style.display = 'flex';
    }
}

/**
 * Hide loading overlay
 */
function hideLoadingOverlay() {
    const overlay = document.getElementById('log-clear-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

/**
 * Show confirmation modal before clearing logs
 *
 * @param {string} logName The name of the log to clear
 * @param {string} logType The type identifier of the log to clear
 */
function showDeleteConfirmationModal(logName, logType) {
    // Get or create the confirmation modal
    let modal = document.getElementById('clearLogConfirmModal');

    // If modal doesn't exist, create it
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'clearLogConfirmModal';
        modal.className = 'modal fade';
        modal.tabIndex = '-1';
        modal.setAttribute('aria-labelledby', 'clearLogConfirmModalLabel');
        modal.setAttribute('aria-hidden', 'true');

        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="clearLogConfirmModalLabel">
                            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                            Confirm Clear Log
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="d-flex align-items-center">
                            <div class="text-danger fs-3 me-3" aria-hidden="true">
                                <i class="bi bi-question-circle-fill"></i>
                            </div>
                            <div>
                                <p class="mb-0 fs-5" id="clearLogConfirmMessage">
                                    Are you sure you want to clear this log?
                                </p>
                                <p class="text-muted small mb-0 mt-2">
                                    This action cannot be undone.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                            Cancel
                        </button>
                        <button type="button" class="btn btn-danger icon-btn" id="confirmClearLogBtn">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                            Clear Log
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    }

    // Set the message
    const messageElement = modal.querySelector('#clearLogConfirmMessage');
    if (messageElement) {
        messageElement.textContent = `Are you sure you want to clear the ${logName} log?`;
    }

    // Initialize Bootstrap modal if needed
    let bsModal = bootstrap.Modal.getInstance(modal);
    if (!bsModal) {
        bsModal = new bootstrap.Modal(modal);
    }

    // Set up the confirm button action
    const confirmButton = modal.querySelector('#confirmClearLogBtn');
    if (confirmButton) {
        // Remove existing event listeners
        const newConfirmButton = confirmButton.cloneNode(true);
        confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);

        // Add event listener for the specific logType
        newConfirmButton.addEventListener('click', function() {
            bsModal.hide();
            clearLog(logType);
        });
    }

    // Show the modal
    bsModal.show();
}

/**
 * Show result notification after clearing logs
 *
 * @param {boolean} success Whether the operation was successful
 * @param {string} message Message to display
 */
function showClearLogResult(success, message) {
    // Get the active tab to place the notification in the appropriate content area
    const activeTab = document.querySelector('#logTabs .nav-link.active');
    if (!activeTab) return;

    // Get the content container based on active tab
    let contentArea = null;

    switch (activeTab.id) {
        case 'ffmpegLogTab':
            contentArea = document.querySelector('#ffmpegLogContent');
            break;
        case 'schedulerLogTab':
            contentArea = document.querySelector('#schedulerLogContent');
            break;
        case 'schedulerLogFileTab':
            contentArea = document.querySelector('#schedulerLogFileContent');
            break;
        case 'streamLogTab':
            contentArea = document.querySelector('#streamLogContent');
            break;
        case 'emailLogTab':
            contentArea = document.querySelector('#emailLogContent');
            break;
        case 'debugLogTab':
            contentArea = document.querySelector('#debugLogContent');
            break;
        default:
            return; // No container found
    }

    if (!contentArea) return;

    // Create the notification alert
    const notification = document.createElement('div');
    notification.className = `alert alert-${success ? 'success' : 'danger'} m-3`;
    notification.style.cssText = "font-family: Arial, sans-serif; font-size: 14px;";
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${success ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i>
            <span>${message}</span>
        </div>
    `;

    // Insert the notification at the top of the content area
    // First, make sure we clear any existing notifications
    const existingNotifications = contentArea.querySelectorAll('.alert');
    existingNotifications.forEach(item => item.remove());

    // Insert at the beginning of the content area
    if (contentArea.firstChild) {
        contentArea.insertBefore(notification, contentArea.firstChild);
    } else {
        contentArea.appendChild(notification);
    }

    // Scroll to the top to ensure notification is visible
    contentArea.scrollTop = 0;

    // Auto-remove after delay
    setTimeout(() => {
        // Fade out effect
        notification.style.transition = 'opacity 0.5s ease';
        notification.style.opacity = '0';

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 500);
    }, 5000);
}