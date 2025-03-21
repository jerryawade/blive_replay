# user_management.php

## Purpose
A PHP class to manage user accounts in the BLIVE Replay system.

## Key Features
- User authentication
- Account management
- Role-based access control
- Secure credential handling

## User Management Functions
- Add new users
- Delete user accounts
- Change user passwords
- Modify user roles
- Manage remember-me tokens

## User Roles
- Admin
- Viewer

## Authentication Mechanisms
- Password verification
- Secure password hashing
- Remember-me token support
- Session management

## Security Measures
- Secure password storage
- Input validation
- Prevent username duplicates
- Protect against common vulnerabilities

## Password Requirements
- Minimum length: 8 characters
- Secure hashing (password_hash)
- Rehash support for updated algorithms

## Token Management
- Generate secure tokens
- Manage token expiration
- Prevent token reuse
- Secure token storage

## Logging
- Track user activities
- Record account modifications
- Maintain audit trail

## Access Control
- Role-based permissions
- Separate admin and viewer capabilities
- Prevent unauthorized actions

## File Handling
- Secure JSON-based user storage
- Atomic write operations
- Prevent file corruption

## Error Handling
- Detailed error messages
- Prevent information disclosure
- Graceful error management

## Remember-Me Functionality
- Persistent login support
- Secure token generation
- Token cleanup and rotation

## Performance Considerations
- Efficient user management
- Minimal resource usage
- Quick authentication processes

## Supported Operations
- User creation
- User deletion
- Password changes
- Role modifications
- Token management
