# stream_monitor_service.php

## Purpose
A PHP background service for monitoring the recording URL status in the BLIVE Replay system.

## Key Features
- Check recording URL accessibility
- Implement multiple verification methods
- Manage stream status persistently
- Handle concurrent checks

## Check Methods
1. FFprobe Stream Information Analysis
2. Frame Capture Verification
3. Basic Connection Test

## Status Tracking
- Generate detailed stream status report
- Store status in JSON file
- Track successful and failed connection attempts
- Manage retry mechanism

## Verification Process
- Use multiple techniques to confirm stream accessibility
- Provide robust and reliable status checking
- Handle temporary connectivity issues
- Prevent false negative/positive reports

## Logging
- Comprehensive logging of check attempts
- Record detailed check information
- Maintain log file with rotation
- Track check performance and results

## Concurrency Management
- Prevent multiple simultaneous checks
- Use lock files for process control
- Implement timeout mechanisms

## Configuration
- Configurable check interval
- Supports manual force checks
- Adaptable to different network environments

## Status Information Provided
- Accessibility status
- Last successful check timestamp
- Retry count
- Detailed error messages

## Security Considerations
- Secure file handling
- Prevent information exposure
- Minimal system resource usage

## Recommended Deployment
- Run as a background service
- Integrate with system monitoring
- Use with client-side stream monitoring
