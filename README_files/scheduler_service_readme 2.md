# scheduler_service.php

## Purpose
A PHP script that manages automated recording schedules for the BLIVE Replay system.

## Key Features
- Execute scheduled recordings
- Start and stop recordings automatically
- Support multiple schedule types
- Send email notifications

## Schedule Types Supported
- One-time recordings
- Daily recordings
- Weekly recordings
- Monthly recordings

## Main Functionality
- Check current schedules
- Determine if a recording should start/stop
- Handle recording state
- Manage system resources

## Scheduling Logic
- Evaluate current time against schedule
- Check schedule type-specific conditions
- Start recordings at scheduled times
- Stop recordings when schedule ends

## Notification System
- Send email alerts for recording events
- Notify about recording start/stop
- Configurable notification settings

## Logging
- Record scheduler activities
- Track recording start/stop events
- Maintain detailed log of operations

## Execution
- Designed to run via cron job
- Check schedules every minute
- Lightweight and efficient

## Configuration
- Timezone support
- Email notification settings
- Flexible schedule management

## Error Handling
- Graceful error management
- Log potential issues
- Prevent system disruptions

## Recommended Setup
- Run as a background service
- Use system cron for scheduling
- Ensure proper permissions
