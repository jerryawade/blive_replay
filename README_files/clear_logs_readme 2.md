# clear_logs.php

## Purpose
This PHP script allows administrators to clear specific system log files while keeping the user activity logs intact.

## Key Functions
- Provides a secure way to clear different types of log files
- Supports clearing logs for:
  - FFmpeg recordings
  - Scheduler
  - Stream URL checks
  - Email communications
  - Debug logs

## Security Features
- Only accessible to authenticated admin users
- Prevents unauthorized log clearing
- Preserves user activity logs

## Usage
- Accessed through the activity log modal in the admin interface
- Allows selective clearing of different log types
- Adds a timestamp entry when a log is cleared to maintain an audit trail
