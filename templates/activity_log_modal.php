<!-- Activity & Logs Modal -->
<div class="modal fade" id="activityLogModal" tabindex="-1" aria-labelledby="activityLogModalLabel" role="dialog"
     aria-modal="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activityLogModalLabel">System Logs</h5>
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
                                <i class="bi bi-reception-4 me-1"></i> Stream Check
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
                                </tbody>
                            </table>
                        </div>

                        <!-- FFmpeg Log Content -->
                        <div class="tab-pane fade" id="ffmpegLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="ffmpeg-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1;"></pre>
                            </div>
                        </div>

                        <!-- Scheduler Log Content -->
                        <div class="tab-pane fade" id="schedulerLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="scheduler-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1;"></pre>
                            </div>
                        </div>

                        <!-- Scheduler Log File Content -->
                        <div class="tab-pane fade" id="schedulerLogFileContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="scheduler-log-file-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1;"></pre>
                            </div>
                        </div>

                        <!-- Stream URL Check Log Content -->
                        <div class="tab-pane fade" id="streamLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="stream-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1; font-family: monospace;"></pre>
                            </div>
                        </div>
                        <!-- Debug Log Content -->
                        <div class="tab-pane fade" id="debugLogContent" role="tabpanel"
                             style="height: 100%; overflow-y: auto; overflow-x: hidden;">
                            <div style="display: flex; flex-direction: column; min-height: 100%;">
                                <pre class="debug-log-pre"
                                     style="margin: 0; padding: 1rem; white-space: pre-wrap; word-wrap: break-word; flex-grow: 1; font-family: monospace;"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-print-none">
                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Close
                </button>
                <button type="button" class="btn btn-success icon-btn" onclick="printLog()">
                    <i class="bi bi-printer"></i>
                    Print Log
                </button>
                <button type="button" class="btn btn-primary icon-btn" onclick="refreshCurrentLog()">
                    <i class="bi bi-arrow-clockwise"></i>
                    Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let fullUserLogData = [];
    let fullFFmpegLogData = '';
    let fullSchedulerLogData = '';
    let fullSchedulerLogFileData = '';
    let fullStreamLogData = '';
    let fullDebugLogData = '';
    let logRefreshInterval = null;

    function switchToUserLog() {
        currentLogType = 'user';
        document.getElementById('userLogContent').style.display = 'block';
        document.getElementById('ffmpegLogContent').style.display = 'none';
        document.getElementById('schedulerLogContent').style.display = 'none';
        document.getElementById('debugLogContent').style.display = 'none';

        document.getElementById('userLogBtn').classList.add('active');
        document.getElementById('ffmpegLogBtn').classList.remove('active');
        document.getElementById('schedulerLogBtn').classList.remove('active');
        document.getElementById('debugLogBtn').classList.remove('active');

        refreshCurrentLog();
    }

    function switchToFFmpegLog() {
        currentLogType = 'ffmpeg';
        document.getElementById('userLogContent').style.display = 'none';
        document.getElementById('ffmpegLogContent').style.display = 'block';
        document.getElementById('schedulerLogContent').style.display = 'none';
        document.getElementById('debugLogContent').style.display = 'none';

        document.getElementById('userLogBtn').classList.remove('active');
        document.getElementById('ffmpegLogBtn').classList.add('active');
        document.getElementById('schedulerLogBtn').classList.remove('active');
        document.getElementById('debugLogBtn').classList.remove('active');

        refreshCurrentLog();
    }

    function switchToSchedulerLog() {
        currentLogType = 'scheduler';
        document.getElementById('userLogContent').style.display = 'none';
        document.getElementById('ffmpegLogContent').style.display = 'none';
        document.getElementById('schedulerLogContent').style.display = 'block';
        document.getElementById('debugLogContent').style.display = 'none';

        document.getElementById('userLogBtn').classList.remove('active');
        document.getElementById('ffmpegLogBtn').classList.remove('active');
        document.getElementById('schedulerLogBtn').classList.add('active');
        document.getElementById('debugLogBtn').classList.remove('active');

        refreshCurrentLog();
    }

    function switchToDebugLog() {
        currentLogType = 'debug';
        document.getElementById('userLogContent').style.display = 'none';
        document.getElementById('ffmpegLogContent').style.display = 'none';
        document.getElementById('schedulerLogContent').style.display = 'none';
        // Add this line
        document.getElementById('streamLogContent').style.display = 'none';
        document.getElementById('debugLogContent').style.display = 'block';

        document.getElementById('userLogBtn').classList.remove('active');
        document.getElementById('ffmpegLogBtn').classList.remove('active');
        document.getElementById('schedulerLogBtn').classList.remove('active');
        // Add this line
        document.getElementById('streamLogBtn').classList.remove('active');
        document.getElementById('debugLogBtn').classList.add('active');

        refreshCurrentLog();
    }

    function switchToDebugLog() {
        currentLogType = 'debug';
        document.getElementById('userLogContent').style.display = 'none';
        document.getElementById('ffmpegLogContent').style.display = 'none';
        document.getElementById('schedulerLogContent').style.display = 'none';
        document.getElementById('debugLogContent').style.display = 'block';

        document.getElementById('userLogBtn').classList.remove('active');
        document.getElementById('ffmpegLogBtn').classList.remove('active');
        document.getElementById('schedulerLogBtn').classList.remove('active');
        document.getElementById('debugLogBtn').classList.add('active');

        refreshCurrentLog();
    }

    // Function to refresh the current active log tab
    function refreshCurrentLog() {
        // Find which tab is active
        const activeTab = document.querySelector('#logTabs .nav-link.active');
        if (!activeTab) return;

        const tabId = activeTab.id;

        if (tabId === 'userLogTab') {
            refreshUserLog();
        } else if (tabId === 'ffmpegLogTab') {
            refreshFFmpegLog();
        } else if (tabId === 'schedulerLogTab') {
            refreshSchedulerLog();
        } else if (tabId === 'schedulerLogFileTab') {
            refreshSchedulerLogFile();
        } else if (tabId === 'streamLogTab') {
            refreshStreamLog();
        } else if (tabId === 'debugLogTab') {
            refreshDebugLog();
        }
    }

    function refreshUserLog() {
        fetch('get_log_entries.php')
            .then(response => response.json())
            .then(data => {
                fullUserLogData = data.activities;
                updateUserLogDisplay();
            })
            .catch(error => console.error('Error:', error));
    }

    function refreshFFmpegLog() {
        fetch('get_ffmpeg_log.php')
            .then(response => response.text())
            .then(data => {
                fullFFmpegLogData = data;
                document.querySelector('.ffmpeg-log-pre').textContent = data;
            })
            .catch(error => console.error('Error:', error));
    }

    function refreshSchedulerLog() {
        fetch('get_scheduler_log.php')
            .then(response => response.text())
            .then(data => {
                fullSchedulerLogData = data;
                document.querySelector('.scheduler-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                document.querySelector('.scheduler-log-pre').textContent = 'Error loading scheduler log: ' + error.message;
            });
    }

    function refreshSchedulerLogFile() {
        fetch('get_scheduler_log_file.php')
            .then(response => response.text())
            .then(data => {
                fullSchedulerLogFileData = data;
                document.querySelector('.scheduler-log-file-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                document.querySelector('.scheduler-log-file-pre').textContent = 'Error loading scheduler log file: ' + error.message;
            });
    }

    function refreshStreamLog() {
        fetch('get_stream_url_check_log.php')
            .then(response => response.text())
            .then(data => {
                fullStreamLogData = data;
                document.querySelector('.stream-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                document.querySelector('.stream-log-pre').textContent = 'Error loading stream URL check log: ' + error.message;
            });
    }

    function refreshDebugLog() {
        fetch('get_debug_log.php')
            .then(response => response.text())
            .then(data => {
                fullDebugLogData = data;
                document.querySelector('.debug-log-pre').textContent = data;
            })
            .catch(error => {
                console.error('Error:', error);
                document.querySelector('.debug-log-pre').textContent = 'Error loading debug log: ' + error.message;
            });
    }

    function updateUserLogDisplay() {
        const tbody = document.getElementById('activityLogTableBody');
        tbody.innerHTML = '';
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

    function printLog() {
        // Find which tab is active
        const activeTab = document.querySelector('#logTabs .nav-link.active');
        if (!activeTab) return;

        const tabId = activeTab.id;

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

                // Optionally, focus the first log tab for keyboard users
                const firstTab = document.querySelector('#logTabs .nav-link.active');
                if (firstTab) {
                    firstTab.focus();
                }

                // Start auto-refresh
                refreshCurrentLog();
                logRefreshInterval = setInterval(refreshCurrentLog, 5000);
            });

            // Clear auto-refresh when modal is hidden
            activityLogModal.addEventListener('hidden.bs.modal', function () {
                // Restore body scrolling
                document.body.style.overflow = '';

                if (logRefreshInterval) {
                    clearInterval(logRefreshInterval);
                }

                // Optional: Return focus to the triggering element
                const triggerElement = document.querySelector('[data-bs-target="#activityLogModal"]');
                if (triggerElement) {
                    triggerElement.focus();
                }
            });
        }
    });
</script>
