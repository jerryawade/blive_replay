# recording_status.php

## Purpose
A PHP script to check and report the current recording status in the BLIVE Replay system.

## Key Features
- Determine if a recording is in progress
- Retrieve recording start timestamp
- Provide current recording state

## Functionality
- Check for active recording
- Verify recording process
- Return recording status details

## Status Information
- Recording active/inactive state
- Recording start timestamp
- Precise recording status reporting

## Detection Mechanism
- Check for FFmpeg PID file
- Verify recording start time
- Minimal file system interaction

## Response Format
- JSON object
- Clear, concise status information
- Easy to consume by client-side scripts

## Security Measures
- Admin authentication required
- Prevent unauthorized status access
- Secure information retrieval

## Use Cases
- Update user interface
- Synchronize recording state
- Provide real-time recording information
- Support recording control interfaces

## Admin-Only Access
- Only accessible to users with admin privileges

## Performance Considerations
- Lightweight status check
- Minimal server resource usage
- Quick response time

## Error Handling
- Graceful error management
- Provide meaningful status messages
- Handle various recording scenarios

## Potential Integrations
- Recording control interfaces
- User interface status updates
- System monitoring components

## Status Details
- Recording active flag
- Precise start time tracking
- Quick state determination
