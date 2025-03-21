# activity_log_archiver.php

## Purpose
A PHP script for processing and archiving user activity logs in the BLIVE Replay system.

## Key Features
- Extract and archive specific user activities
- Manage log file sizes
- Preserve long-term analytics data
- Automatically clean up old log entries

## Log Types Archived
- Livestream views
- Video plays

## Archiving Process
- Read main activity log
- Extract specific actions
- Store in compact archive files
- Remove processed entries from main log
- Maintain log files within defined retention periods

## Archive Management
- Main activity log: 30 days retention
- Analytics archives: 1 year retention
- Automatic pruning of old entries
- Prevent log file from becoming too large

## Retention Periods
- Main Log: 30 days
- Stream Views Archive: 1 year
- Video Plays Archive: 1 year

## Analytics Support
- Provides methods to retrieve user activity data
- Supports filtering by user and time range
- Enables usage statistics generation

## Recommended Usage
- Run daily via cron job
- Can be manually triggered through web interface
