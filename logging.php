<?php
class ActivityLogger {
    private $logFile = 'logs/user_activity.log';
    private $maxLogSize = 5242880; // 5MB
    private $retentionDays = 360;

    public function logActivity($username, $action, $filename = '', $timestamp = null) {
        // If no timestamp is provided, create one
        if ($timestamp === null) {
            date_default_timezone_set('America/Chicago');
            $timestamp = date('Y-m-d H:i:s');
        }

        // Get client IP if available (handles command-line execution)
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';

        $logEntry = json_encode([
                'timestamp' => $timestamp,
                'username' => $username,
                'action' => $action,
                'filename' => $filename,
                'ip' => $ip
            ]) . "\n";

        // Check file size and rotate if necessary
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $this->rotateLog();
        }

        file_put_contents($this->logFile, $logEntry, FILE_APPEND);

        // Clean old entries after adding new one
        $this->cleanOldEntries();
    }

    private function cleanOldEntries() {
        if (!file_exists($this->logFile)) {
            return;
        }

        $lines = file($this->logFile);
        $cutoffDate = strtotime("-{$this->retentionDays} days");
        $newLines = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['timestamp'])) {
                $entryDate = strtotime($entry['timestamp']);
                if ($entryDate >= $cutoffDate) {
                    $newLines[] = $line;
                }
            }
        }

        // Write back only the entries within retention period
        file_put_contents($this->logFile, implode('', $newLines));
    }

    private function rotateLog() {
        $backup = $this->logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename($this->logFile, $backup);
    }

    public function getActivities($limit = 1000) {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = array_reverse(file($this->logFile));
        $activities = [];
        $cutoffDate = strtotime("-{$this->retentionDays} days");

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['timestamp'])) {
                $entryDate = strtotime($entry['timestamp']);
                if ($entryDate >= $cutoffDate) {
                    $activities[] = $entry;
                }
            }
        }

        // Only apply limit if it's not null
        return $limit ? array_slice($activities, 0, $limit) : $activities;
    }
}
?>
