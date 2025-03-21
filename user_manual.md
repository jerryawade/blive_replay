# BLIVE RePlay System User Manual
Version 1.6.0 | Last Updated: March 2025

## Table of Contents
- [Introduction](#introduction)
- [Admin Guide](#admin-guide)
  - [Logging In](#logging-in)
  - [Dashboard Overview](#dashboard-overview)
  - [Recording Management](#recording-management)
  - [Scheduled Recordings](#scheduled-recordings)
  - [Stream Monitoring](#stream-monitoring)
  - [System Settings](#system-settings)
  - [User Management](#user-management)
  - [System Logs](#system-logs)
  - [Messaging System](#messaging-system)
  - [Thumbnail Management](#thumbnail-management)
  - [System Stats](#system-stats)
- [Viewer Guide](#viewer-guide)
  - [Logging In](#viewer-logging-in)
  - [Landing Page](#landing-page)
  - [Viewing Recordings](#viewing-recordings)
  - [Playing Videos](#playing-videos)
  - [Accessing Live Stream](#accessing-live-stream)
- [Troubleshooting](#troubleshooting)
  - [Common Issues](#common-issues)
  - [VLC Handlers](#vlc-handlers)
  - [Auto-Close Utilities](#auto-close-utilities)
- [Support](#support)
- [System Intervals & Timers](#system-intervals--timers)

## Introduction

BLIVE RePlay is a comprehensive video recording and streaming system designed for Bethany Church. It facilitates live streaming, video recording management, intelligent scheduling, and now includes support for GPU monitoring.

## Admin Guide

### Logging In

1. Navigate to the BLIVE RePlay system URL in your web browser.
2. Enter your administrator username and password in the login form.
3. Optional: Check "Remember my username" to save your username for future logins.
4. Click "Sign In" to access the admin dashboard.

### Dashboard Overview

After logging in, you'll see:
- Recording Status: Indicates if a recording is in progress with a timer
- Recording Controls: Buttons to start and stop recording
- Recordings List: All previously recorded videos
- Navigation Menu: Access to settings, user management, logs, and system controls

### Recording Management

#### Starting a Recording
1. Ensure your stream source is active.
2. Check the stream status indicator (green dot in the corner) to confirm the stream URL is accessible.
3. Click the "Start Recording" button on the dashboard.
4. The status will change to "Recording in Progress" with a red background.
5. A timer will display the recording duration.
6. **Important:** Do NOT refresh your browser during recording.

#### Stopping a Recording
1. Click the "Stop Recording" button on the dashboard.
2. Confirm the action in the dialog box.
3. The recording will be processed and added to the recordings list.

#### Managing Recordings
For each recording, you can:
- Add/Edit Notes: Click "Add Note" or "Edit Note" to add a description (max 50 characters)
- Play Video: Play the recording using VLC media player
- Download M3U: Download a playlist file for the recording
- Download MP4: Download the video file directly
- Delete: Remove the recording from the system (cannot be undone)

### Scheduled Recordings

#### Accessing the Scheduler
1. Click the "Schedules" button on the dashboard or in the settings modal.
2. The Schedule Management modal will open.

#### Creating a Schedule
1. Click the "Add New" tab.
2. Enter a name for the schedule.
3. Select a recurrence type:
   - One-time: Records once on a specific date
   - Daily: Records every day at the specified time
   - Weekly: Records on selected days of the week
   - Monthly: Records on selected days of the month
4. Set the start and end times.
5. Check "Schedule Enabled" to activate the schedule.
6. Click "Save Schedule" to create the schedule.

#### Managing Schedules
From the "Schedules" tab, you can:
- Edit: Modify schedule details
- Delete: Remove the schedule
- Enable/Disable: Toggle the schedule status

### Stream Monitoring

#### Stream Status Indicator
- A small colored dot appears in the bottom-right corner of the screen
- Color meanings:
  - Green: Stream URL is accessible
  - Red: Stream URL is not accessible
  - Yellow: Currently checking stream status
  - Orange: Recording URL may be temporarily unavailable
- Hover over the indicator to see detailed status messages
- Click on the indicator to force a new stream check

### System Settings

#### Accessing Settings
Click the "Settings" button in the top navigation menu.

#### Key Configuration Areas
1. **Server Configuration**
   - Set system timezone
   - Configure server and stream URLs
   - Set recording URL check interval

2. **Landing Page Options**
   - Control visibility of recordings and live stream links
   - Configure VLC webpage opening behavior

3. **Permissions**
   - Manage viewer access to VLC, M3U, and MP4 downloads

4. **Scheduler**
   - Enable/disable automatic recordings
   - Configure email notifications

5. **Email Notification Settings**
   - Configure SMTP server details
   - Set notification email recipients
   - Test email configuration

### User Management

#### Adding a User
1. Click "Manage Users" in the top navigation menu
2. Fill in the username field (letters, numbers, and underscores only)
3. Create a password (minimum 8 characters)
4. Select a role (Admin or Viewer)
5. Click "Add" to create the user

#### Managing Existing Users
For each user, you can:
- Change Role: Switch between Admin and Viewer
- Change Password
- Delete User
- View Activity Insights with the usage graph

### Messaging System

#### Sending Broadcast Messages
1. Open the Users modal
2. Click the "Broadcast Message" button
3. Enter a subject and message
4. Click "Send" to broadcast to all users

#### Sending Direct Messages
1. In the Users modal, find the user you want to message
2. Click the "Message" button next to the user
3. Enter a subject and message
4. Click "Send" to send a direct message

#### Receiving Messages
- Unread messages will automatically pop up
- Click "Close" to mark as read
- Messages are stored locally to prevent repeated displays

### System Logs

#### Accessing Logs
Click the "Log" button in the top navigation menu.

#### Available Log Types
- User Activity: User actions in the system
- FFmpeg: Video recording technical logs
- Scheduler: Scheduled recording logs
- Stream Check: Stream URL validation logs
- Email: Email notification logs
- Debug: System-level debug information

### Thumbnail Management

#### Generating Thumbnails
1. Navigate to the recording you want to generate a thumbnail for.
2. Click the "Generate Thumbnail" button next to the recording.
3. The system will process the request and display the thumbnail once generated.

#### Viewing Thumbnails
- Thumbnails will be displayed in the recordings list next to each recording.
- Click on the thumbnail to view a larger version.

#### Using Thumbnails
- Thumbnails can be used to quickly identify recordings.
- Thumbnails are also used in the viewer's recordings list for easier navigation.

### System Stats

#### Overview
The System Stats feature provides administrators with real-time data on the system's performance and usage. This information is crucial for maintaining the health and efficiency of the BLIVE RePlay system.

#### Accessing System Stats
1. Click the "System Stats" button in the top navigation menu.
2. The System Stats dashboard will display various metrics related to the system's performance.

#### Key Metrics
The System Stats dashboard includes the following metrics:
- **CPU Usage**: Displays the current CPU usage percentage.
- **Memory Usage**: Shows the amount of RAM being used.
- **Network Activity**: Monitors the network input and output rates.
- **GPU Usage**: Displays GPU utilization, memory usage, temperature, and running processes.

#### Selecting Network Interface
Administrators can select the network interface to monitor network activity. To select the network interface:
1. Click the "Network Interface" dropdown on the System Stats dashboard.
2. Select the desired network interface from the list.

By using the System Stats feature, administrators can ensure the BLIVE RePlay system operates smoothly and efficiently.

## Viewer Guide

### Viewer Logging In
1. Navigate to the BLIVE RePlay system URL
2. Enter your viewer username and password
3. Optional: Check "Remember my username"
4. Click "Sign In"

### Landing Page
Depending on admin settings, you'll see:
- RePlay/Recordings: Access recorded videos
- Live Stream: Access the live stream

### Viewing Recordings
1. Click "RePlay/Recordings" on the landing page
2. Browse recordings with thumbnails, dates, and durations
3. View admin notes for additional context

### Playing Videos

#### Using VLC (if enabled by admin)
1. Click "Play Video" on the desired recording
2. Video opens in VLC media player
3. Optional webpage may open after a 5-second delay

#### Downloading Options (if enabled)
- Download M3U: Playlist file for various media players
- Download MP4: Full video file download

### Accessing Live Stream
1. Click "Live Stream" on the landing page
2. Stream opens in VLC media player

## Troubleshooting

### Common Issues
- **Cannot start recording:**
  - Verify Record URL in settings
  - Check stream accessibility via status indicator
  - Force a stream check if indicator is red

- **Stream status indicator shows red:**
  - Verify streaming source configuration
  - Check network connectivity
  - Review Stream logs for detailed errors

- **Scheduler not working:**
  - Confirm scheduler is enabled
  - Check scheduler logs
  - Verify timezone settings

- **Email notifications not received:**
  - Validate SMTP settings
  - Verify notification email
  - Review Email logs
  - Use "Send Test Email" function

- **VLC integration issues:**
  - Install appropriate VLC handler
  - Configure browser protocol handler
  - Verify VLC installation

## Support
For persistent issues, contact your system administrator.

## System Intervals & Timers

### Browser-Based Timers
- VLC Webpage Open Delay: 5 seconds after video start
- Recording Status Check (non-admin): Every 15 seconds
- Stream Status Poll During Check: Every 1 second
- Auto-hide Success Notifications: 3-5 seconds
- Message Check Interval: Every 30 seconds

### Automatic Intervals
| Feature                  | Interval                  | Description                              |
|--------------------------|---------------------------|------------------------------------------|
| Stream URL Check         | 5 minutes (default, 1-60 min configurable) | Checks recording URL accessibility |
| Force Stream Check Limit | 5 seconds                 | Minimum time between manual force checks |
| Stream Check Timeout     | 10 seconds                | Maximum time waiting for stream response |
| Log Auto-Refresh         | 60 seconds                | Automatically updates logs               |
| Recording Timer          | 1 second                  | Updates elapsed recording time           |
| Session Keep-Alive       | 10 minutes                | Maintains login session                  |
| Page Updates Check       | 250 milliseconds          | Checks recording state changes           |
| SSE Heartbeat            | 5 seconds                 | Keeps server-sent event connection alive |
| User Login Rate Limit    | 5 attempts / 5 minutes    | Protects against brute force attacks     |
| Session Regeneration     | 1 hour                    | Regenerates session ID for security      |
| Scheduler Service        | 1 minute                  | Checks for scheduled recordings          |
| Message Check            | 30 seconds                | Checks for new messages                  |