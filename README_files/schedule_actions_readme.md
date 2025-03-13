# schedule_actions.php

## Purpose
A PHP script that handles all server-side operations for managing recording schedules in the BLIVE Replay system.

## Key Features
- Create new recording schedules
- Update existing schedules
- Delete schedules
- Retrieve schedule lists
- Validate schedule inputs

## Supported Schedule Types
- One-time recordings
- Daily recordings
- Weekly recordings
- Monthly recordings

## Main Actions
- `list`: Retrieve all schedules
- `get`: Fetch details of a specific schedule
- `add`: Create a new schedule
- `update`: Modify an existing schedule
- `delete`: Remove a schedule

## Input Validation
- Check required fields
- Validate schedule type-specific parameters
- Ensure time ranges are correct
- Prevent invalid schedule creation

## Security Measures
- Admin authentication required
- Sanitize and validate all inputs
- Prevent unauthorized schedule modifications

## Error Handling
- Provide detailed error messages
- Return JSON responses
- Handle various potential error scenarios

## Logging
- Log schedule-related activities
- Track user actions
- Maintain audit trail of schedule changes

## Admin-Only Access
- Only accessible to users with admin privileges
