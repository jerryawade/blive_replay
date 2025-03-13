# update_note.php

## Purpose
A PHP script to manage notes for individual recordings in the BLIVE Replay system.

## Key Features
- Add notes to recorded files
- Edit existing recording notes
- Store notes in JSON file
- Provide context for recordings

## Functionality
- Accept note text for a specific recording
- Validate note input
- Save note to persistent storage
- Update change timestamp

## Input Handling
- Trim note text
- Limit note length
- Prevent notes during active recording
- Sanitize input

## Storage Mechanism
- Use JSON file for note storage
- Map notes to specific recording files
- Lightweight and efficient storage

## Note Characteristics
- Maximum length: 50 characters
- Associated with specific recording
- Provides additional context

## Security Measures
- Admin authentication required
- Prevent note updates during recording
- Validate file existence
- Secure file handling

## Logging
- Track note addition/modification
- Log user who made the change
- Maintain activity audit trail

## Error Handling
- Provide clear error messages
- Handle various potential failure scenarios
- Return JSON response with status

## Admin-Only Access
- Only accessible to users with admin privileges

## Use Cases
- Add recording descriptions
- Mark important recordings
- Provide context for archival
- Enhance recording management

## Performance
- Quick note update process
- Minimal server resource usage
- Efficient file storage mechanism
