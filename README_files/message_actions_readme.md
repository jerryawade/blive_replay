# message_actions.php

## Purpose
PHP backend for managing user messages in the BLIVE RePlay system.

## Key Features
- Handles message creation, reading, and management
- Provides message cleanup functionality
- Maintains user messaging JSON data store
- Tracks message read status

## Core Functions
- `cleanupOldMessages`: Removes all messages
- `getMessages`: Retrieves all stored messages
- `saveMessages`: Updates message storage
- `generateUniqueId`: Creates message identifiers

## Actions Supported
- `send`: Create new messages (admin only)
- `mark_read`: Update read status for a message
- `list`: Retrieve all messages (admin only)

## Message Structure
- id: Unique message identifier
- subject: Message title
- body: Message content
- sender: Username of sender
- timestamp: When message was sent
- recipient: Target user (null for broadcasts)
- read_by: Array of users who have read the message

## Security Considerations
- Authentication check for all operations
- Role verification for admin-only functions
- Session validation throughout

## File Management
- Creates JSON directory if needed
- Ensures proper file permissions
- Maintains atomic write operations

## Recommended Usage
- Called by client-side JavaScript for message operations
- Integrate with cleanup_messages.php for maintenance