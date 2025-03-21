# check_stream_url.php

## Purpose
A PHP script to check and monitor the accessibility of the recording URL in the BLIVE RePlay system.

## Key Features
- Verify recording URL status
- Implement multiple check methods
- Maintain persistent status information
- Handle concurrent check prevention

## Check Methods
1. FFprobe Stream Information Check
2. Frame Capture Test
3. Basic Connection Test

## Status Tracking
- Store status in JSON file
- Track last successful check
- Manage check intervals
- Handle temporary accessibility issues

## Concurrency Management
- Use lock files to prevent multiple simultaneous checks
- Implement timeout mechanisms
- Provide fallback for failed checks

## Status Information
- Accessibility status (active/inactive)
- Last check timestamp
- Number of retry attempts
- Detailed error messages

## Security Considerations
- Admin authentication required
- Prevent unauthorized access
- Secure file handling

## Configuration
- Configurable check interval
- Supports manual force checks
- Maintains status file for quick reference

## Usage
- Automatically triggered by client-side monitoring
- Can be manually initiated
- Provides JSON response with stream status
