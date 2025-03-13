# scheduler_status.php

## Purpose
A PHP script to provide comprehensive status information about the recording scheduler in the BLIVE Replay system.

## Key Features
- Report current scheduler status
- Provide detailed recording information
- Track upcoming and current schedules

## Status Information
- Scheduler enabled/disabled state
- Current recording status
- Next scheduled recording
- Last scheduler action
- Server time details

## Returned Details
- Recording active flag
- Recording start time
- Current schedule details
- Next scheduled recording
- Timezone information

## Schedule Types Supported
- One-time recordings
- Daily recordings
- Weekly recordings
- Monthly recordings

## Calculation Logic
- Determine next schedule
- Consider current system time
- Handle different recurrence patterns
- Prevent past schedule selection

## Security Measures
- Admin authentication required
- Prevent unauthorized access
- Secure schedule information retrieval

## Use Cases
- Update user interface
- Provide scheduling insights
- Monitor recording system status
- Display upcoming recordings

## Performance Considerations
- Efficient schedule processing
- Minimal server resource usage
- Quick status retrieval

## Timezone Handling
- Respect system-configured timezone
- Accurate time calculations
- Handle daylight saving transitions

## Error Handling
- Graceful error management
- Provide meaningful status messages
- Handle various scheduling scenarios

## Admin-Only Access
- Only accessible to users with admin privileges

## Potential Integrations
- Scheduler badge
- Recording status display
- System monitoring interfaces

## Diagnostic Information
- Scheduler configuration
- Current recording state
- Upcoming schedule details
- System time tracking
