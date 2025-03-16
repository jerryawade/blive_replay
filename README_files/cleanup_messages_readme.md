# cleanup_messages.php

## Purpose
A PHP script that purges all messages in the BLIVE RePlay messaging system.

## Key Features
- Removes all stored messages
- Prevents message accumulation
- Resets the messaging system
- Keeps logs of cleanup operations

## Implementation
- Calls cleanupOldMessages() from message_actions.php
- Simple, single-purpose maintenance script
- Creates debug log entries for tracking

## File Management
- Completely clears messages.json
- Maintains file structure
- Creates empty array as default state

## Logging
- Records cleanup operations in logs/cleanup_debug.log
- Logs timestamp of each cleanup
- Tracks success/failure of operations

## Security Considerations
- No direct user interface
- Intended for system maintenance only
- Should be restricted from unauthorized access

## Recommended Usage
- Schedule via cron job for periodic cleaning
- Run daily or weekly depending on message volume
- Example cron configuration:
  ```
  0 0 * * 0 php /path/to/cleanup_messages.php > /dev/null 2>&1
  ```