# stream_monitor.js

## Purpose
A JavaScript class for monitoring the status of the recording URL in the BLIVE RePlay system.

## Key Features
- Real-time stream URL status monitoring
- Dynamic status indicator
- Automatic status checks
- Retry mechanism for stream accessibility
- Visual feedback on stream status

## Main Functionality
- Check recording URL status
- Update status indicator color
- Handle different stream states:
  - Accessible
  - Temporarily unavailable
  - Not accessible
- Provide detailed status information

## Status Tracking
- Maintain recording URL accessibility status
- Track consecutive failures
- Stabilize status during temporary issues
- Provide visual and tooltip feedback

## Monitoring Mechanisms
- Periodic status checks
- Manual force check option
- Adaptive failure handling
- Configurable retry limits

## Status Indicator
- Color-coded status representation
- Green: URL accessible
- Yellow: Checking status
- Orange: Temporary issues
- Red: URL not accessible

## Admin-Only Access
- Only available to users with admin privileges
