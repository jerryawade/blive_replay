# logging.php

## Purpose
A PHP class to manage activity logging in the BLIVE Replay system.

## Key Features
- Comprehensive user activity logging
- Log file management
- Activity tracking and retention

## Main Components
- ActivityLogger class
- Log file management
- Activity tracking methods

## Logging Functionality
- Record user activities
- Store log entries
- Manage log file size
- Implement log rotation

## Log Entry Attributes
- Timestamp
- Username
- Action type
- Associated filename
- Client IP address

## Log File Management
- Automatic log rotation
- Size-based log truncation
- Retention period control

## Retention Policies
- Default retention: 360 days
- Configurable log size limit
- Automatic old entry removal

## Supported Log Actions
- User login/logout
- File interactions
- System modifications
- Recording actions
- User management events

## Security Considerations
- Secure log file handling
- Prevent log file tampering
- Mask sensitive information

## Performance Optimization
- Efficient log writing
- Minimal resource consumption
- Quick log entry creation

## Retrieval Methods
- Get recent activities
- Filter log entries
- Support various retrieval options

## Error Handling
- Graceful log writing
- Prevent logging failures
- Maintain system stability

## Timezone Support
- Configurable timestamp generation
- Consistent log entry formatting

## Log File Structure
- JSON-based log format
- Human-readable entries
- Easy parsing and analysis

## Use Cases
- System audit trail
- User activity tracking
- Diagnostic information
- Security monitoring
