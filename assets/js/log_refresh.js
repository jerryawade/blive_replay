document.addEventListener("DOMContentLoaded", function() {
    let lastReadLine = 0;
    const logContainer = document.getElementById("logContainer");

    function fetchNewLogEntries(url) {
        fetch(`${url}?lastReadLine=${lastReadLine}`)
            .then(response => response.json())
            .then(data => {
                data.newLines.forEach(line => {
                    const logLine = document.createElement("div");
                    logLine.textContent = line;
                    logContainer.prepend(logLine);
                });
                lastReadLine = data.totalLines;
            })
            .catch(error => console.error("Error fetching log entries:", error));
    }

    setInterval(() => fetchNewLogEntries('get_debug_log.php'), 30000); // Refresh every 30 seconds for debug log
    setInterval(() => fetchNewLogEntries('get_stream_url_check_log.php'), 30000); // Refresh every 30 seconds for stream URL check log
    setInterval(() => fetchNewLogEntries('get_scheduler_log_file.php'), 30000); // Refresh every 30 seconds for scheduler log
    setInterval(() => fetchNewLogEntries('get_ffmpeg_log.php'), 30000); // Refresh every 30 seconds for FFmpeg log
    setInterval(() => fetchNewLogEntries('get_log_entries.php'), 30000); // Refresh every 30 seconds for log entries
    setInterval(() => fetchNewLogEntries('get_scheduler_log.php'), 30000); // Refresh every 30 seconds for scheduler log
});