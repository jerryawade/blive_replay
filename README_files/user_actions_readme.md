# user_actions.php

## Purpose
A PHP script to manage user account actions in the BLIVE Replay system.

## Key Features
- Add new users
- Delete existing users
- Change user passwords
- Modify user roles
- Centralized user management

## Supported Actions
- `add`: Create new user account
- `delete`: Remove user account
- `change_password`: Update user password
- `change_role`: Modify user privileges

## User Management Validation
- Validate username format
- Check password complexity
- Ensure unique usernames
- Validate role assignments

## Security Measures
- Admin authentication required
- Prevent unauthorized user modifications
- Secure password handling
- Input sanitization

## Password Requirements
- Minimum length: 8 characters
- Alphanumeric username
- Secure password hashing

## User Roles
- Admin
- Viewer

## Logging
- Track user management activities
- Log user addition/deletion
- Record role changes
- Maintain administrative audit trail

## Error Handling
- Provide detailed error messages
- Return JSON responses
- Handle various potential failure scenarios

## Admin-Only Access
- Only accessible to users with admin privileges

## Input Validation
- Username format check
- Password strength verification
- Role validity confirmation

## Potential Scenarios
- User onboarding
- Access management
- Security maintenance
- Account lifecycle management

## Response Handling
- Successful action confirmation
- Clear error reporting
- Consistent JSON response format
