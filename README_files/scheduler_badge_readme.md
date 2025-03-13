# scheduler_badge.js

## Purpose
A JavaScript module to manage and display the next scheduled recording badge in the BLIVE Replay system.

## Key Features
- Fetch next scheduled recording
- Update badge dynamically
- Provide real-time scheduling information

## Functionality
- Retrieve next schedule details
- Update badge display
- Handle periodic updates
- Manage badge visibility

## Update Mechanisms
- Periodic checking (every 30 seconds)
- Force update on modal close
- Minimal server interaction

## Badge Information
- Schedule title
- Next run date
- Start and end times
- Conditional date formatting

## Performance Considerations
- Lightweight update process
- Minimal server resource usage
- Efficient background checking

## Admin-Only Features
- Only visible to admin users
- Automatic background updates
- Detailed scheduling information

## Technical Components
- Fetch API for schedule retrieval
- Dynamic content update
- Error handling
- Caching mechanism

## Update Triggers
- Periodic background checks
- Modal close event
- Force refresh capabilities

## Error Handling
- Graceful error management
- Fallback to previous state
- Prevent interface disruption

## Console Logging
- Debug information
- Track update processes
- Aid in troubleshooting

## Potential Integrations
- Scheduler management interface
- System status tracking
- Real-time update mechanisms

## User Experience
- Instant schedule visibility
- Non-intrusive updates
- Clear, concise information display

## Security Considerations
- Admin-only access
- Prevent unauthorized updates
- Secure information retrieval
