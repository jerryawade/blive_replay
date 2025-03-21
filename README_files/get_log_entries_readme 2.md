# get_log_entries.php

## Purpose
A PHP script to retrieve user activity log entries in the BLIVE Replay system.

## Key Features
- Fetch user activity logs
- Provide recent user activities
- Support logging and tracking

## Functionality
- Read user activity log file
- Parse log entries
- Return activity data as JSON

## Log Entry Details
- Timestamp
- Username
- Action performed
- Associated filename (if applicable)

## Security Measures
- Admin authentication required
- Prevent unauthorized log access
- Limit log entry exposure

## Log Processing
- Read log file
- Parse JSON-formatted entries
- Sort entries (typically newest first)
- Apply optional filtering

## Response Format
- JSON object
- Array of activity entries
- Consistent data structure

## Use Cases
- Activity monitoring
- User action tracking
- Administrative oversight
- Audit trail retrieval

## Admin-Only Access
- Only accessible to users with admin privileges

## Performance Considerations
- Efficient log file reading
- Minimal server resource usage
- Quick response time

## Potential Integrations
- Admin dashboard
- User activity tracking
- System audit interfaces

## Log Entry Types
- User logins
- Settings changes
- Recording actions
- System modifications
