# message-system.js

## Purpose
Client-side JavaScript for the BLIVE RePlay messaging system.

## Key Features
- User interface for reading and sending messages
- Periodic checking for new messages
- Message display in modal windows
- Administrative message composition
- Message read tracking

## User Interface Components
- Message display modal for recipients
- Message composition form for administrators
- User-specific message buttons
- Broadcast message capability

## Core Functions
- `init`: Initialize messaging system
- `checkMessagesFile`: Poll for new messages
- `displayMessage`: Show message modal
- `markMessageAsRead`: Update read status
- `showSendMessageForm`: Display message composition UI

## Admin-Specific Features
- "Broadcast Message" button in users modal
- Per-user message buttons in user list
- Message composition form with recipient selection
- Success/error feedback for message operations

## User-Specific Features
- Automatic message checking (30-second interval)
- Unread message notification
- Message display in modal window
- Local storage tracking of displayed messages

## Security Considerations
- Role-based UI elements
- Authentication for all operations
- Proper message targeting

## Recommended Usage
- Automatically initialized on page load
- Works with check_messages.php and message_actions.php