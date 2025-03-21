# recordings_update.js

## Purpose
A JavaScript module that manages real-time updates for the recordings list in the BLIVE RePlay system.

## Key Features
- Use Server-Sent Events (SSE) for live updates
- Fallback to polling if SSE connection fails
- Check for changes in recordings list
- Automatically reload page when recordings change

## Main Functionality
- Establish SSE connection to server
- Monitor for recording-related changes
- Implement automatic page refresh mechanism
- Handle connection errors and retries

## Update Mechanisms
- Primary: Server-Sent Events (real-time updates)
- Fallback: Polling every 30 seconds
- Detect changes using server-side hash comparison

## Connection Management
- Automatic reconnection attempts
- Exponential backoff for connection failures
- Maximum retry limit
- Fallback to standard polling

## Browser Compatibility
- Works with modern browsers supporting SSE
- Provides fallback for browsers without SSE support
