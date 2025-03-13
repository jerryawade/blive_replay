# log_activity.php

## Purpose
A PHP script to log user activities in the BLIVE Replay system.

## Key Features
- Record user actions
- Track system interactions
- Maintain activity audit trail

## Functionality
- Accept activity details
- Log user-initiated actions
- Store activity information

## Logged Activity Types
- User interactions
- Recording actions
- Livestream views
- Video plays
- System events

## Input Parameters
- Username
- Action type
- Associated filename (optional)
- Timestamp

## Security Measures
- Authentication check
- Prevent unauthorized logging
- Secure activity recording

## Logging Mechanism
- JSON-based log file
- Timestamp each entry
- Include user and action details

## Use Cases
- User activity tracking
- System audit trail
- Usage analytics
- Diagnostic information

## Supported Actions
- Login/logout
- File downloads
- Video plays
- Livestream access
- System modifications

## Performance Considerations
- Lightweight logging process
- Minimal server resource usage
- Quick log entry creation

## Error Handling
- Graceful error management
- Prevent logging failures
- Maintain system stability

## Timezone Support
- Use system-configured timezone
- Consistent timestamp recording

## Authentication Requirements
- Must be authenticated user
- Prevent anonymous logging

## Log Storage
- Persistent JSON file storage
- Configurable log retention
- Support long-term activity tracking
