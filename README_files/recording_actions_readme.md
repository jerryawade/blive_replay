# recording_actions.php

## Purpose
A PHP script to manage recording schedule actions in the BLIVE Replay system.

## Key Features
- Create recording schedules
- Update existing schedules
- Delete schedules
- Retrieve schedule information

## Supported Actions
- `list`: Retrieve all schedules
- `get`: Fetch specific schedule details
- `add`: Create new schedule
- `update`: Modify existing schedule
- `delete`: Remove a schedule

## Schedule Types
- One-time recordings
- Daily recordings
- Weekly recordings
- Monthly recordings

## Input Validation
- Check required fields
- Validate schedule parameters
- Ensure time range consistency
- Prevent invalid schedule creation

## Security Measures
- Admin authentication required
- Prevent unauthorized modifications
- Secure input handling

## Validation Checks
- Schedule type verification
- Time range validation
- Day selection validation
- Prevent past schedule creation

## Logging
- Record schedule-related activities
- Track user actions
- Maintain audit trail

## Response Handling
- JSON response format
- Detailed success/error messages
- Consistent API interface

## Admin-Only Access
- Only accessible to users with admin privileges

## Error Handling
- Provide meaningful error messages
- Handle various potential failures
- Prevent system disruption

## Performance Considerations
- Efficient schedule processing
- Minimal server resource usage
- Quick action completion

## Timezone Support
- Respect system-configure