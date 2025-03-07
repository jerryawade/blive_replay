// Enhanced Recordings List Updater
(function() {
    // Function to fetch and update recordings
    async function updateRecordingsList() {
        try {
            const response = await fetch('check_updates.php', {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            const data = await response.json();
            
            // Compare with last known hash
            const currentHash = localStorage.getItem('recordingsListHash');
            
            if (data.hash !== currentHash) {
                // Update stored hash
                localStorage.setItem('recordingsListHash', data.hash);
                
                // Hash changed, full page reload
                window.location.reload();
            }
        } catch (error) {
            console.error('Error checking recordings updates:', error);
        }
    }

    // Set up Server-Sent Events (SSE) connection
    function setupSSE() {
        let retryCount = 0;
        const MAX_RETRIES = 3;
        let evtSource = null;

        function connect() {
            if (evtSource) {
                evtSource.close();
            }

            evtSource = new EventSource('?sse=listen');

            evtSource.onopen = function() {
                console.log('SSE connection established');
                retryCount = 0;
            };

            evtSource.onerror = function(err) {
                console.error('SSE connection error:', err);
                evtSource.close();
                evtSource = null;

                if (retryCount < MAX_RETRIES) {
                    const backoffTime = Math.min(1000 * Math.pow(2, retryCount), 10000);
                    retryCount++;
                    console.log(`Attempting reconnection in ${backoffTime / 1000} seconds...`);
                    setTimeout(connect, backoffTime);
                } else {
                    console.log('Max retry attempts reached. Falling back to polling.');
                    // Fallback to polling if SSE fails
                    startPolling();
                }
            };

            evtSource.addEventListener('recordingChange', function(e) {
                console.log('Recording change detected');
                updateRecordingsList();
            });

            evtSource.addEventListener('heartbeat', function(e) {
                console.log('Heartbeat received at:', new Date().toISOString());
            });
        }

        // Start polling as a fallback
        function startPolling() {
            const pollInterval = setInterval(updateRecordingsList, 30000); // Check every 30 seconds
            
            // Clean up on page unload
            window.addEventListener('beforeunload', () => {
                clearInterval(pollInterval);
            });
        }

        // Initial connection
        connect();

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (evtSource) {
                evtSource.close();
            }
        });
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', setupSSE);
})();
