<!-- Activity & Logs Modal -->
<div class="modal fade" id="activityLogModal" tabindex="-1" aria-labelledby="activityLogModalLabel" role="dialog"
     aria-modal="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activityLogModalLabel">System Logs</h5>
                <div class="auto-refresh-indicator">
                    &nbsp;<span class="small text-muted">Auto-refreshing</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="height: calc(100vh - 250px); padding: 0;">
                <!-- Main content wrapper -->
                <div style="display: flex; flex-direction: column; height: 100%;">
                    <!-- Log Type Selection Tabs (Styled like scheduler tabs) -->
                    <ul class="nav nav-tabs mb-0 p-2 bg-white d-print-none" id="logTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="userLogTab" data-bs-toggle="tab"
                                    data-bs-target="#userLogContent" type="button" role="tab"
                                    aria-controls="userLogContent" aria-selected="true">
                                <i class="bi bi-person-lines-fill me-1"></i> User
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ffmpegLogTab" data-bs-toggle="tab"
                                    data-bs-target="#ffmpegLogContent" type="button" role="tab"
                                    aria-controls="ffmpegLogContent" aria-selected="false">
                                <i class="bi bi-camera-video me-1"></i> FFmpeg
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="schedulerLogTab" data-bs-toggle="tab"
                                    data-bs-target="#schedulerLogContent" type="button" role="tab"
                                    aria-controls="schedulerLogContent" aria-selected="false">
                                <i class="bi bi-calendar-check me-1"></i> Scheduler
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="schedulerLogFileTab" data-bs-toggle="tab"
                                    data-bs-target="#schedulerLogFileContent" type="button" role="tab"
                                    aria-controls="schedulerLogFileContent" aria-selected="false">
                                <i class="bi bi-file-text me-1"></i> Scheduler Log
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="streamLogTab" data-bs-toggle="tab"
                                    data-bs-target="#streamLogContent" type="button" role="tab"
                                    aria-controls="streamLogContent" aria-selected="false">
                                <i class="bi bi-reception-4 me-1"></i> Stream
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="emailLogTab" data-bs-toggle="tab"
                                    data-bs-target="#emailLogContent" type="button" role="tab"
                                    aria-controls="emailLogContent" aria-selected="false">
                                <i class="bi bi-envelope-paper me-1"></i> Email
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="debugLogTab" data-bs-toggle="tab"
                                    data-bs-target="#debugLogContent" type="button" role="tab"
                                    aria-controls="debugLogContent" aria-selected="false">
                                <i class="bi bi-bug me-1"></i> Debug
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" style="flex: 1; min-height: 0;">
                        <!-- User Activity Log Content -->
                        <div class="tab-pane fade show active" id="userLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <table class="table table-striped table-hover">
                                <thead>
                                <tr style="position: sticky; top: 0; background: white; z-index: 1; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                    <th>Timestamp</th>
                                    <th>Username</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                </tr>
                                </thead>
                                <tbody id="activityLogTableBody">
                                <!-- Activity log content will be loaded here -->
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <div class="log-loading-spinner">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading user activity logs...</span>
                                            </div>
                                            <p class="mt-2">Loading user activity logs...</p>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- FFmpeg Log Content -->
                        <div class="tab-pane fade" id="ffmpegLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="ffmpeg-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1;">
<div class="log-loading-spinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading FFmpeg logs...</span>
    </div>
    <p class="mt-2">Loading FFmpeg logs...</p>
</div>
                                </pre>
                            </div>
                        </div>

                        <!-- Scheduler Log Content -->
                        <div class="tab-pane fade" id="schedulerLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="scheduler-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1;">
<div class="log-loading-spinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading scheduler logs...</span>
    </div>
    <p class="mt-2">Loading scheduler logs...</p>
</div>
                                </pre>
                            </div>
                        </div>

                        <!-- Scheduler Log File Content -->
                        <div class="tab-pane fade" id="schedulerLogFileContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="scheduler-log-file-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1;">
<div class="log-loading-spinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading scheduler log file...</span>
    </div>
    <p class="mt-2">Loading scheduler log file...</p>
</div>
                                </pre>
                            </div>
                        </div>

                        <!-- Stream URL Check Log Content -->
                        <div class="tab-pane fade" id="streamLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="stream-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1; font-family: monospace;">
<div class="log-loading-spinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading stream check logs...</span>
    </div>
    <p class="mt-2">Loading stream check logs...</p>
</div>
                                </pre>
                            </div>
                        </div>

                        <!-- Email Log Content -->
                        <div class="tab-pane fade" id="emailLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
        <pre class="email-log-pre"
             style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1; font-family: monospace;">
<div class="log-loading-spinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading email logs...</span>
    </div>
    <p class="mt-2">Loading email logs...</p>
</div>
        </pre>
                            </div>
                        </div>

                        <!-- Debug Log Content -->
                        <div class="tab-pane fade" id="debugLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="debug-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1; font-family: monospace;">
<div class="log-loading-spinner">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading debug logs...</span>
    </div>
    <p class="mt-2">Loading debug logs...</p>
</div>
                                </pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-print-none">
                <button type="button" class="btn btn-danger icon-btn" id="clearCurrentLogBtn" style="display: none;">
                    <i class="bi bi-trash"></i>
                    Clear Log
                </button>
                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Close
                </button>
                <button type="button" class="btn btn-success icon-btn" onclick="printLog()">
                    <i class="bi bi-printer"></i>
                    Print Log
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add spinner styles -->
<style>
    /* Log Loading Spinner Styles */
    .log-loading-spinner {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 200px;
        width: 100%;
    }

    .log-loading-spinner .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.25rem;
    }

    .log-loading-spinner p {
        margin-top: 1rem;
        font-size: 1rem;
        color: #6c757d;
    }

    /* Animation for the loading spinner */
    @keyframes pulse-opacity {
        0% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
        100% {
            opacity: 1;
        }
    }

    .log-loading-spinner .spinner-border {
        animation: spinner-border 0.75s linear infinite, pulse-opacity 2s ease-in-out infinite;
    }

    /* Auto-refresh indicator styles */
    .auto-refresh-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-right: 1rem;
        font-size: 0.75rem;
    }

    .auto-refresh-indicator .spinner-border {
        width: 0.8rem;
        height: 0.8rem;
        border-width: 0.1rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .log-loading-spinner {
            min-height: 150px;
        }

        .log-loading-spinner .spinner-border {
            width: 2rem;
            height: 2rem;
        }
    }
</style>

<script>
    let fullUserLogData = [];
    let fullFFmpegLogData = '';
    let fullSchedulerLogData = '';
    let fullSchedulerLogFileData = '';
    let fullStreamLogData = '';
    let fullDebugLogData = '';
    let logRefreshInterval = null;

    // Function to refresh the current active log tab
    function refreshCurrentLog() {
        // Find which tab is active
        const activeTab = document.querySelector('#logTabs .nav-link.active');
        if (!activeTab) return;

        const tabId = activeTab.id;

        // Show loading spinner for the active tab
        if (tabId === 'userLogTab') {
            const tableBody = document.getElementById('activityLogTableBody');
            if (tableBody) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center">
                            <div class="log-loading-spinner">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading user activity logs...</span>
                                </div>
                                <p class="mt-2">Loading user activity logs...</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
            refreshUserLog();
        } else if (tabId === 'ffmpegLogTab') {
            const preElement = document.querySelector('.ffmpeg-log-pre');
            if (preElement) {
                preElement.innerHTML = `
                    <div class="log-loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading FFmpeg logs...</span>
                        </div>
                        <p class="mt-2">Loading FFmpeg logs...</p>
                    </div>
                `;
            }
            refreshFFmpegLog();
        } else if (tabId === 'schedulerLogTab') {
            const preElement = document.querySelector('.scheduler-log-pre');
            if (preElement) {
                preElement.innerHTML = `
                    <div class="log-loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading scheduler logs...</span>
                        </div>
                        <p class="mt-2">Loading scheduler logs...</p>
                    </div>
                `;
            }
            refreshSchedulerLog();
        } else if (tabId === 'schedulerLogFileTab') {
            const preElement = document.querySelector('.scheduler-log-file-pre');
            if (preElement) {
                preElement.innerHTML = `
                    <div class="log-loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading scheduler log file...</span>
                        </div>
                        <p class="mt-2">Loading scheduler log file...</p>
                    </div>
                `;
            }
            refreshSchedulerLogFile();
        } else if (tabId === 'streamLogTab') {
            const preElement = document.querySelector('.stream-log-pre');
            if (preElement) {
                preElement.innerHTML = `
                    <div class="log-loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading stream check logs...</span>
                        </div>
                        <p class="mt-2">Loading stream check logs...</p>
                    </div>
                `;
            }
            refreshStreamLog();
        } else if (tabId === 'emailLogTab') {
            const preElement = document.querySelector('.email-log-pre');
            if (preElement) {
                preElement.innerHTML = `
                <div class="log-loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading email logs...</span>
                    </div>
                    <p class="mt-2">Loading email logs...</p>
                </div>
            `;
            }
            refreshEmailLog();
        } else if (tabId === 'debugLogTab') {
            const preElement = document.querySelector('.debug-log-pre');
            if (preElement) {
                preElement.innerHTML = `
                    <div class="log-loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading debug logs...</span>
                        </div>
                        <p class="mt-2">Loading debug logs...</p>
                    </div>
                `;
            }
            refreshDebugLog();
        }
    }

    function updateUserLogDisplay() {
        const tbody = document.getElementById('activityLogTableBody');
        tbody.innerHTML = '';

        if (!fullUserLogData || fullUserLogData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center">
                        <i class="bi bi-info-circle me-2"></i>
                        No activity log entries found.
                    </td>
                </tr>
            `;
            return;
        }

        fullUserLogData.forEach(activity => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${activity.timestamp}</td>
                <td>${activity.username}</td>
                <td>${activity.action}</td>
                <td>${activity.filename || ''}</td>
            `;
            tbody.appendChild(row);
        });
    }

    function refreshUserLog() {
        fetch('get_log_entries.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                fullUserLogData = data.activities;
                updateUserLogDisplay();
            })
            .catch(error => {
                console.error('Error:', error);
                const tableBody = document.getElementById('activityLogTableBody');
                if (tableBody) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Error loading log: ${error.message}
                                <button class="btn btn-sm btn-outline-primary ms-3" onclick="refreshUserLog()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Retry
                                </button>
                            </td>
                        </tr>
                    `;
                }
            });
    }

    function refreshFFmpegLog() {
        fetch('get_ffmpeg_log.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                fullFFmpegLogData = data;
                document.querySelector('.ffmpeg-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                const preElement = document.querySelector('.ffmpeg-log-pre');
                if (preElement) {
                    preElement.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Loading Log</h5>
                            <p>${error.message || 'The server did not respond.'}</p>
                            <button type="button" class="btn btn-danger btn-sm" onclick="refreshFFmpegLog()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry
                            </button>
                        </div>
                    `;
                }
            });
    }

    function refreshSchedulerLog() {
        fetch('get_scheduler_log.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                fullSchedulerLogData = data;
                document.querySelector('.scheduler-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                const preElement = document.querySelector('.scheduler-log-pre');
                if (preElement) {
                    preElement.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Loading Log</h5>
                            <p>${error.message || 'The server did not respond.'}</p>
                            <button type="button" class="btn btn-danger btn-sm" onclick="refreshSchedulerLog()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry
                            </button>
                        </div>
                    `;
                }
            });
    }

    function refreshSchedulerLogFile() {
        fetch('get_scheduler_log_file.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                fullSchedulerLogFileData = data;
                document.querySelector('.scheduler-log-file-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                const preElement = document.querySelector('.scheduler-log-file-pre');
                if (preElement) {
                    preElement.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Loading Log</h5>
                            <p>${error.message || 'The server did not respond.'}</p>
                            <button type="button" class="btn btn-danger btn-sm" onclick="refreshSchedulerLogFile()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry
                            </button>
                        </div>
                    `;
                }
            });
    }

    function refreshStreamLog() {
        fetch('get_stream_url_check_log.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                fullStreamLogData = data;
                document.querySelector('.stream-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                const preElement = document.querySelector('.stream-log-pre');
                if (preElement) {
                    preElement.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Loading Log</h5>
                            <p>${error.message || 'The server did not respond.'}</p>
                            <button type="button" class="btn btn-danger btn-sm" onclick="refreshStreamLog()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry
                            </button>
                        </div>
                    `;
                }
            });
    }

    let fullEmailLogData = '';

    function refreshEmailLog() {
        fetch('get_email_log.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                fullEmailLogData = data;
                document.querySelector('.email-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                const preElement = document.querySelector('.email-log-pre');
                if (preElement) {
                    preElement.innerHTML = `
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Loading Log</h5>
                        <p>${error.message || 'The server did not respond.'}</p>
                        <button type="button" class="btn btn-danger btn-sm" onclick="refreshEmailLog()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                        </button>
                    </div>
                `;
                }
            });
    }

    function refreshDebugLog() {
        fetch('get_debug_log.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                fullDebugLogData = data;
                document.querySelector('.debug-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                const preElement = document.querySelector('.debug-log-pre');
                if (preElement) {
                    preElement.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Loading Log</h5>
                            <p>${error.message || 'The server did not respond.'}</p>
                            <button type="button" class="btn btn-danger btn-sm" onclick="refreshDebugLog()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry
                            </button>
                        </div>
                    `;
                }
            });
    }

    function printLog() {
        // Find which tab is active
        const activeTab = document.querySelector('#logTabs .nav-link.active');
        if (!activeTab) return;

        const tabId = activeTab.id;

        // Show loading state on the print button
        const printButton = document.querySelector('button[onclick="printLog()"]');
        if (printButton) {
            const originalContent = printButton.innerHTML;
            printButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Preparing...';
            printButton.disabled = true;

            setTimeout(() => {
                printButton.innerHTML = originalContent;
                printButton.disabled = false;
            }, 1500);
        }

        try {
            switch (tabId) {
                case 'userLogTab':
                    fetch('get_log_entries.php')
                        .then(response => response.json())
                        .then(data => {
                            fullUserLogData = data.activities;
                            updateUserLogDisplay();
                            window.print();
                        })
                        .catch(error => console.error('Error:', error));
                    break;

                case 'ffmpegLogTab':
                    fetch('get_ffmpeg_log.php')
                        .then(response => response.text())
                        .then(data => {
                            fullFFmpegLogData = data;
                            document.querySelector('.ffmpeg-log-pre').textContent = data;
                            window.print();
                        })
                        .catch(error => console.error('Error:', error));
                    break;

                case 'schedulerLogTab':
                    fetch('get_scheduler_log.php')
                        .then(response => response.text())
                        .then(data => {
                            fullSchedulerLogData = data;
                            document.querySelector('.scheduler-log-pre').textContent = data;
                            window.print();
                        })
                        .catch(error => console.error('Error:', error));
                    break;

                case 'schedulerLogFileTab':
                    fetch('get_scheduler_log_file.php')
                        .then(response => response.text())
                        .then(data => {
                            fullSchedulerLogFileData = data;
                            document.querySelector('.scheduler-log-file-pre').textContent = data;
                            window.print();
                        })
                        .catch(error => console.error('Error:', error));
                    break;

                case 'streamLogTab':
                    fetch('get_stream_url_check_log.php')
                        .then(response => response.text())
                        .then(data => {
                            fullStreamLogData = data;
                            document.querySelector('.stream-log-pre').textContent = data;
                            window.print();
                        })
                        .catch(error => console.error('Error:', error));
                    break;

                case 'emailLogTab':
                    fetch('get_email_log.php')
                        .then(response => response.text())
                        .then(data => {
                            fullEmailLogData = data;
                            document.querySelector('.email-log-pre').textContent = data;
                            window.print();
                        })
                        .catch(error => console.error('Error:', error));
                    break;

                case 'debugLogTab':
                    fetch('get_debug_log.php')
                        .then(response => response.text())
                        .then(data => {
                            fullDebugLogData = data;
                            document.querySelector('.debug-log-pre').textContent = data;
                            window.print();
                        })
                        .catch(error => console.error('Error:', error));
                    break;
            }
        } catch (error) {
            console.error('Error preparing log for print:', error);
        }
    }

    // Initialize tab events once DOM is loaded
    document.addEventListener('DOMContentLoaded', function () {
        // Set up tab change events to refresh content when a tab is shown
        const logTabs = document.querySelectorAll('#logTabs .nav-link');
        logTabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (event) {
                refreshCurrentLog();
            });
        });

        // Set up auto-refresh when modal is shown
        const activityLogModal = document.getElementById('activityLogModal');
        if (activityLogModal) {
            activityLogModal.addEventListener('shown.bs.modal', function () {
                // Prevent body scrolling
                document.body.style.overflow = 'hidden';

                // Start auto-refresh
                refreshCurrentLog();
                logRefreshInterval = setInterval(refreshCurrentLog, 60000); // Refresh every 60 seconds
            });

            // Clear auto-refresh when modal is hidden
            activityLogModal.addEventListener('hidden.bs.modal', function () {
                // Restore body scrolling
                document.body.style.overflow = '';

                if (logRefreshInterval) {
                    clearInterval(logRefreshInterval);
                }
            });
        }
    });
</script>
<script src="./assets/js/clear_logs.js"></script>
