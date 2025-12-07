# Security Policy

## Overview

HugousERP takes security seriously. This document outlines the security measures implemented in the system and provides guidelines for secure development and deployment.

## Reporting Security Vulnerabilities

If you discover a security vulnerability, please report it by emailing the development team directly. Do not create a public GitHub issue for security vulnerabilities.

**Response Time**: We aim to respond to security reports within 48 hours and provide a fix within 7 days for critical vulnerabilities.

## Security Features

### 1. Authentication

#### Password Security
- **Hashing**: All passwords are hashed using bcrypt with a cost factor of 12 (configurable via `BCRYPT_ROUNDS`)
- **Minimum Length**: 6 characters minimum (configurable)
- **Password Reset**: Secure token-based password reset with expiration
- **Session Management**: Configurable session lifetime and max sessions per user

#### Two-Factor Authentication (2FA)
- **TOTP-based**: Time-based One-Time Password compatible with Google Authenticator
- **Recovery Codes**: Encrypted recovery codes stored securely
- **Enforcement**: Can be enforced per user or globally
- **Setup Flow**: Secure QR code generation and verification

#### Session Security
- **Session Fixation Protection**: Session regeneration on login
- **Session Tracking**: All active sessions tracked with device information
- **Multi-Session Control**: Configurable maximum sessions per user
- **Auto Logout**: Automatic logout after inactivity period

### 2. Authorization

#### Role-Based Access Control (RBAC)
- **Hierarchical Roles**: Super Admin > Admin > Manager > User
- **Fine-Grained Permissions**: 100+ permissions across all modules
- **Permission Middleware**: `perm:permission_name` middleware for route protection
- **Policy-Based**: Laravel policies for model-level authorization
- **Branch-Level Access**: Users restricted to assigned branches

#### Permission Enforcement Layers
1. **Route Level**: Middleware on routes
2. **Controller Level**: Authorization checks in controllers
3. **Service Level**: Business logic authorization
4. **UI Level**: Blade directives (@can, @cannot)
5. **API Level**: Token abilities

### 3. Input Validation & Sanitization

#### Validation Strategy
- **Form Requests**: Dedicated request classes for complex validation
- **Livewire Validation**: Real-time validation in Livewire components
- **Type Safety**: PHP 8.2 strict types enforced
- **Sanitization**: Input sanitization before database operations

#### Common Validation Rules
```php
// User input validation example
[
    'email' => ['required', 'email', 'max:255'],
    'phone' => ['nullable', 'string', 'max:20'],
    'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
    'date' => ['required', 'date', 'after:yesterday'],
]
```

### 4. SQL Injection Prevention

#### Measures Implemented
- **Eloquent ORM**: Primary database interaction method
- **Query Builder**: Parameterized queries with bindings
- **Raw Queries**: Only used when necessary with proper parameter binding
- **Prepared Statements**: All user inputs are escaped

#### Safe Query Example
```php
// Safe - using Eloquent
Product::where('branch_id', $branchId)->get();

// Safe - using bindings with raw queries
DB::table('sales')->whereRaw('amount > ?', [$amount])->get();

// UNSAFE - Never do this
DB::select("SELECT * FROM users WHERE id = $userId"); // ❌
```

### 5. Cross-Site Scripting (XSS) Prevention

#### Protection Layers
- **Blade Templating**: Automatic output escaping using `{{ }}` syntax
- **Content Security Policy**: CSP headers (can be enabled)
- **HTML Purifier**: Used for rich text content
- **Input Sanitization**: Special characters stripped or encoded

#### Blade Security
```blade
{{-- Safe - automatic escaping --}}
{{ $userInput }}

{{-- Unsafe - only use for trusted content --}}
{!! $trustedHtml !!}
```

### 6. Cross-Site Request Forgery (CSRF) Protection

#### Implementation
- **CSRF Tokens**: Automatically included in all forms
- **Livewire CSRF**: Built-in CSRF protection in Livewire components
- **SPA Exemption**: API routes use token authentication instead
- **Token Rotation**: Tokens rotated on each request

```blade
<form method="POST" action="/submit">
    @csrf  <!-- CSRF token automatically included -->
    <!-- form fields -->
</form>
```

### 7. HTTP Security Headers

The `SecurityHeaders` middleware adds the following headers to all responses:

```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Strict-Transport-Security: max-age=31536000; includeSubDomains (production only)
```

### 8. Rate Limiting

#### Implemented Limits
- **Login Attempts**: 5 attempts per minute per IP
- **API Requests**: 60 requests per minute per token
- **Password Reset**: 5 attempts per hour per email
- **OTP Verification**: 5 attempts per 5 minutes

#### Configuration
```php
// In route definitions
Route::middleware(['throttle:60,1'])->group(function () {
    // API routes
});
```

### 9. File Upload Security

#### Security Measures
- **File Type Validation**: Whitelist of allowed MIME types
- **File Size Limits**: Configurable maximum file size
- **Virus Scanning**: Can be integrated (ClamAV)
- **Secure Storage**: Files stored outside web root
- **Signed URLs**: Temporary access URLs for private files

#### Safe File Upload Example
```php
$request->validate([
    'file' => ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'], // 5MB
]);

$path = $request->file('file')->store('attachments', 'private');
```

### 10. API Security

#### Authentication
- **Laravel Sanctum**: Token-based authentication
- **Token Abilities**: Scoped permissions per token
- **Token Expiration**: Configurable token lifetime
- **Token Revocation**: Immediate token revocation capability

#### API Best Practices
```php
// Require authentication
Route::middleware(['auth:sanctum'])->group(function () {
    // Protected routes
});

// Add rate limiting
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Rate-limited routes
});

// Branch-level access control
Route::middleware(['auth:sanctum', 'api-branch'])->group(function () {
    // Branch-specific routes
});
```

### 11. Database Security

#### Measures
- **Connection Encryption**: SSL/TLS for database connections (production)
- **Least Privilege**: Database users with minimal required permissions
- **Backup Encryption**: Encrypted database backups
- **Audit Logging**: All data modifications logged
- **Soft Deletes**: Recoverable deletion of records

#### Database Configuration (Production)
```env
DB_SSL_MODE=require
DB_SSL_CERT=/path/to/cert.pem
DB_SSL_KEY=/path/to/key.pem
DB_SSL_CA=/path/to/ca.pem
```

### 12. Audit Logging

All critical operations are logged in the `audit_logs` table:

**Logged Events:**
- User login/logout
- Permission changes
- Data modifications
- Failed authentication attempts
- Security-related configuration changes

**Audit Log Fields:**
- User ID and name
- Action performed
- Target model and ID
- Before and after states (for updates)
- IP address
- User agent
- Timestamp

### 13. Sensitive Data Protection

#### Encrypted Fields
- Two-factor authentication secrets
- Password reset tokens
- API tokens
- Sensitive system settings

#### Hidden Fields (not exposed in JSON)
```php
protected $hidden = [
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
];
```

### 14. Dependency Security

#### Management
- **Regular Updates**: Dependencies updated monthly
- **Security Advisories**: Monitor GitHub security advisories
- **Composer Audit**: Run `composer audit` regularly
- **Vulnerability Scanning**: Automated scanning in CI/CD

```bash
# Check for vulnerabilities
composer audit

# Update dependencies
composer update --with-all-dependencies
```

## Secure Development Guidelines

### For Developers

1. **Never commit secrets** to version control
   - Use `.env` for sensitive configuration
   - Add `.env` to `.gitignore`
   - Use environment variables in production

2. **Validate all user input**
   - Use Form Requests for validation
   - Never trust client-side validation alone
   - Sanitize inputs before processing

3. **Use parameterized queries**
   - Always use Eloquent or Query Builder
   - Never concatenate user input into SQL
   - Use bindings for raw queries

4. **Implement proper authorization**
   - Check permissions at multiple layers
   - Use policies for model authorization
   - Don't rely on UI hiding alone

5. **Hash sensitive data**
   - Never store passwords in plain text
   - Use Laravel's Hash facade
   - Use encryption for reversible secrets

6. **Avoid exposing sensitive information**
   - Don't include stack traces in production errors
   - Sanitize error messages
   - Use generic error messages for authentication

7. **Follow least privilege principle**
   - Grant minimal required permissions
   - Use role-based access control
   - Regularly review permissions

## Deployment Security Checklist

### Pre-Deployment

- [ ] Environment set to `production`
- [ ] `APP_DEBUG` set to `false`
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secured
- [ ] HTTPS configured and enforced
- [ ] Security headers enabled
- [ ] Rate limiting configured
- [ ] File upload limits set
- [ ] CORS policies configured
- [ ] Error reporting to logging only

### Infrastructure

- [ ] Firewall configured
- [ ] Database not publicly accessible
- [ ] SSH key-based authentication
- [ ] Regular security patches applied
- [ ] Intrusion detection system enabled
- [ ] DDoS protection enabled
- [ ] Backup encryption enabled
- [ ] SSL/TLS certificates valid

### Application

- [ ] All dependencies updated
- [ ] Composer audit passed
- [ ] Security headers verified
- [ ] CSRF protection tested
- [ ] Authentication flows tested
- [ ] Authorization rules verified
- [ ] Audit logging enabled
- [ ] Error monitoring configured

## Security Testing

### Manual Testing

1. **Authentication Testing**
   - Test login with invalid credentials
   - Test rate limiting on login
   - Test password reset flow
   - Test 2FA setup and verification
   - Test session management

2. **Authorization Testing**
   - Test access control for each role
   - Test branch-level isolation
   - Test API token permissions
   - Test privilege escalation scenarios

3. **Input Validation Testing**
   - Test SQL injection attempts
   - Test XSS injection attempts
   - Test file upload vulnerabilities
   - Test parameter tampering

### Automated Testing

```bash
# Run security-focused tests
php artisan test --group=security

# Static analysis
./vendor/bin/phpstan analyse

# Code quality
./vendor/bin/pint --test
```

## Incident Response

### If a Security Breach Occurs

1. **Immediate Actions**
   - Isolate affected systems
   - Revoke compromised credentials
   - Block malicious IP addresses
   - Enable maintenance mode if necessary

2. **Investigation**
   - Review audit logs
   - Identify breach vector
   - Assess data exposure
   - Document findings

3. **Remediation**
   - Apply security patches
   - Reset compromised credentials
   - Notify affected users
   - Update security measures

4. **Post-Incident**
   - Conduct security review
   - Update security policies
   - Improve detection systems
   - Train team on prevention

## Security Contacts

For security concerns, contact:
- Security Team: [security@example.com]
- Development Lead: [development@example.com]

## Compliance

This system is designed to comply with:
- GDPR (General Data Protection Regulation)
- PCI DSS (for payment processing)
- SOC 2 Type II (audit-ready)

## Regular Security Reviews

- **Monthly**: Dependency updates and vulnerability scanning
- **Quarterly**: Security audit and penetration testing
- **Annually**: Comprehensive security review and policy updates

## Version History

- **v1.0.0** (2025-12-07): Initial security policy
- Security headers middleware implemented
- Performance indexes added
- Comprehensive documentation created

---

**Last Updated**: December 7, 2025  
**Next Review**: March 7, 2026
