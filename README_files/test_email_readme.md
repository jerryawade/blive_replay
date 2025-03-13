# test_email.php

## Purpose
A PHP script to test and validate email notification configurations in the BLIVE Replay system.

## Key Features
- Verify email notification settings
- Test SMTP server connectivity
- Send test email to configured address
- Validate email configuration inputs

## Validation Checks
- Verify notification email address
- Check SMTP server settings
- Validate SMTP credentials
- Ensure all required fields are present

## Input Validation
- Email address format check
- SMTP host validation
- Username and password verification
- Connection security settings review

## Test Email Process
- Generate test email content
- Use PHPMailer library for sending
- Provide detailed success/failure response
- Offer meaningful error messages

## Email Content
- Includes system information
- Verifies email delivery capability
- Provides timestamp and server details
- Simple, informative test message

## Automated PHPMailer Download
- Attempt to download PHPMailer library
- Fallback to manual library installation
- Support different library installation methods

## Security Measures
- Admin authentication required
- Prevent unauthorized email configuration testing
- Secure handling of credentials

## Response Handling
- Return JSON response
- Indicate test success or failure
- Provide detailed diagnostic information

## Admin-Only Access
- Only accessible to users with admin privileges

## Potential Use Cases
- Verify email notification settings
- Troubleshoot email configuration issues
- Confirm SMTP server connectivity
