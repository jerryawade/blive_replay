# next_schedule.php

## Purpose
A PHP script to determine and return the next scheduled recording in the BLIVE Replay system.

## Key Features
- Identify upcoming scheduled recordings
- Calculate next recording time
- Support multiple schedule types

## Schedule Types Handled
- One-time recordings
- Daily recordings
- Weekly recordings
- Monthly recordings

## Functionality
- Read recording schedules
- Calculate next schedule
- Consider current system time
- Support timezone configurations

## Calculation Logic
- Evaluate current date and time
- Determine next possible recording
- Handle different recurrence patterns
- Prevent past schedule selection

## Response Format
- JSON object
- Next schedule details
- Timestamp information

## Returned Information
- Schedule title
- Next run date/time
- Start and end times
- Schedule type details

## Security Measures
- Admin authentication required
- Prevent unauthorized access
- Secure schedule information

## Use Cases
- Display upcoming recordings
- Update user interface
- Provide scheduling insights
- System status tracking

## Performance Considerations
- Efficient schedule calculation
- Minimal processing overhead
- Quick response time

## Timezone Support
- Respect system-configured timezone
- Accurate time calculations
- Handle daylight saving transitions

## Admin-Only Access
- Only accessible to users with admin privileges

## Error Handling
- Graceful schedule processing
- Handle edge cases
- Provide meaningful responses

## Potential Integrations
- Scheduler badge
- Recording status display
- User notification systems
