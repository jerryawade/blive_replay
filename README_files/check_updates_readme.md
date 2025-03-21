# check_updates.php

## Purpose
A PHP script to check for updates in recordings for the BLIVE Replay system.

## Key Features
- Generate consistent hash of recordings
- Detect changes in recording list
- Support client-side synchronization

## Update Detection Mechanism
- Create hash based on:
  - Existing recordings
  - Recording timestamps
  - Current recording status
  - Recording notes

## Tracked Changes
- New recordings added
- Recordings deleted
- Recording file modifications
- Active recording state

## Hash Generation Components
- Recording filenames
- File modification times
- Recording process status
- Notes file changes

## Response Format
- JSON object
- Unique content hash
- Current timestamp

## Use Cases
- Trigger client-side updates
- Synchronize recording list
- Detect system changes
- Support real-time UI updates

## Performance Considerations
- Lightweight hash generation
- Minimal server resource usage
- Quick update detection

## Security Measures
- Prevent unnecessary page reloads
- Efficient change tracking
- Minimal data exposure

## Potential Integrations
- Client-side update mechanisms
- Real-time recording list synchronization
- Automated content refresh

## Technical Details
- Consistent hash generation
- Supports multiple change detection scenarios
- Minimal processing overhead

## Timestamp Handling
- Provide current server timestamp
- Support client-side synchronization
- Accurate change tracking
