# logging.php

## Purpose
A PHP logging class to manage user activities and system logs in the BLIVE Replay system.

## Key Features
- Record user activities
- Manage log file storage
- Implement log rotation
- Provide activity tracking

## Main Components
- ActivityLogger class
- Comprehensive logging methods
- Log file management

## Logging Capabilities
- Record detailed user actions
- Track system events
- Maintain activity history
- Implement retention policies

## Log Entry Attributes
- Timestamp
- Username
- Action type
- Associated filename
- Client IP address

## Log Management
- Automatic log rotation
- Size-based log truncation
- Configurable retention period
- Prevent log file growth

## Retention Policies
- Default retention: 360 days
- Configurable log size limit
- Automatic old entry removal

## Supported Log Actions
- User authentication
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
- Flexible data extraction

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
