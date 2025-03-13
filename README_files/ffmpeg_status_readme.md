# ffmpeg_status.php

## Purpose
A lightweight PHP script to check the current status of FFmpeg recording in the BLIVE Replay system.

## Key Features
- Check if a recording is in progress
- Provide simple recording status
- Minimal server resource usage

## Functionality
- Verify existence of FFmpeg PID file
- Return JSON response with recording status
- Quick status check mechanism

## Status Determination
- Check for presence of `ffmpeg_pid.txt`
- Simple boolean status reporting
- Instant response

## Response Format
- JSON object
- `recording_in_progress`: true/false
- Lightweight and easily consumable

## Use Cases
- Client-side recording status verification
- Synchronize UI with current recording state
- Quick system status check

## Security Considerations
- No sensitive information exposed
- Minimal processing
- Simple file existence check

## Performance
- Extremely fast execution
- Negligible server resource consumption
- Instant status reporting

## Typical Usage
- Used by client-side JavaScript
- Support recording state management
- Enable responsive user interface updates

## Implementation
- Single-purpose script
- Direct file system check
- No complex logic or processing

## Potential Integrations
- Recording control interfaces
- System status displays
- Automatic recording state tracking
