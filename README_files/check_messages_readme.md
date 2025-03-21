# check_messages.php

## Purpose
A PHP script that checks for and retrieves user messages in the BLIVE RePlay system.

## Key Features
- Retrieves relevant messages for the authenticated user
- Filters messages for the specific user and broadcasts
- Returns sorted messages (newest first)
- Prevents browser caching of results

## User Authentication
- Verifies valid user session
- Returns only messages intended for the current user
- Protects message privacy between users

## Message Filtering
- Returns broadcast messages (recipient is null)
- Returns targeted messages (recipient matches username)
- Ignores messages intended for other users

## Response Format
- JSON response with success status
- Array of message objects
- Sorted by timestamp in descending order

## File Management
- Creates messages.json file if it doesn't exist
- Sets appropriate file permissions (0644)
- Maintains data integrity

## Security Considerations
- Requires authenticated session
- Returns 403 Unauthorized for invalid sessions
- Applies caching prevention headers

## Recommended Usage
- Called periodically by client-side JavaScript
- Used with message_actions.php for complete messaging functionality