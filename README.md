# IMS-Sirigampola-V2
Inventory Management System - Secured System

Live: https://ssd2.42web.io/

## Overview

This is an enhanced and secured version of the Sirigampola Inventory Management System (IMS). The system has undergone significant security improvements to address various vulnerabilities, particularly focusing on cookie security and CSRF protection.

## Security Enhancements

### 1. Secure Session Handling

- Implemented in `init.php`
- Session cookies now use HttpOnly, Secure, and SameSite flags
- SameSite attribute set to 'Lax' for better security-functionality balance

### 2. CSRF Protection

- Generated CSRF tokens for forms
- Implemented token verification on form submissions

### 3. Secure Cookie Handling

- Created `setSecureCookie()` function for setting cookies with proper security attributes
- All cookies now set with HttpOnly flag and proper SameSite configuration

### 4. Input Sanitization

- Implemented thorough input validation and sanitization to prevent XSS attacks

### 5. Secure Redirections

- Implemented secure redirections after login and other sensitive operations

## Security Best Practices

- Always use the `setSecureCookie()` function when setting cookies
- Regularly audit the codebase to ensure all cookie operations are secure
- Validate and sanitize all user inputs before processing or storing
- Keep the system and its dependencies up to date

## Future Improvements

- Implement two-factor authentication
- Regular security audits and penetration testing
- Enhance logging for security events

## Installation and Setup

[Add installation instructions here]

## Usage

[Add usage instructions here]

## Contributing

[Add contribution guidelines here]

## License

[Add license information here]

## Acknowledgements

- Original system developed by [Original Author]
- Security enhancements by Mihisara and the development team

## Contact

For any queries or support, please contact [Your Contact Information]

