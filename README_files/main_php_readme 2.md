# main.php

## Purpose
The primary content template for the BLIVE Replay system, managing recordings display and interactions.

## Key Features
- List and manage recordings
- Provide recording interactions
- Support admin-specific controls
- Handle file-level operations

## Recording Display
- Group recordings by date
- Show recording details
- Display thumbnails
- Provide metadata

## Admin Control Capabilities
- Start/stop recordings
- Add/edit recording notes
- Delete recordings
- Manage recording system

## Recording Interactions
- Play in VLC
- Download M3U playlist
- Download MP4 file
- Add/edit notes

## Thumbnail Generation
- Create video thumbnails
- Support default recording image
- Handle current recording state

## Note Management
- Add contextual notes
- Limited to 50 characters
- Associate notes with specific recordings

## Security Considerations
- Admin-only modifications
- Prevent note changes during recording
- Secure file interactions

## Performance Optimizations
- Efficient recording display
- Grouped by date
- Minimal server resource usage

## User Experience Features
- Responsive design
- Intuitive recording management
- Clear interaction options

## Supported Actions
- Play recordings
- Download recordings
- Add notes
- Delete recordings (admin only)

## Technical Components
- Date-based grouping
- Thumbnail generation
- File metadata extraction
- Conditional rendering

## Potential Integrations
- VLC player support
- File download mechanisms
- Note management system

## Error Handling
- Graceful file access
- Prevent unauthorized actions
- Clear user feedback

## Responsive Design
- Adaptive layout
- Mobile-friendly display
- Cross-device compatibility
