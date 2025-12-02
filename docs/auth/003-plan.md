# Module 3: Auth & Customers - Implementation Plan

## Overview

Module 3 provides API-only authentication and customer profile management for X1 with JWT/Bearer auth, rotating refresh tokens, email verification, password reset, customer profiles, and address management. The implementation follows GDPR-safe practices and maintains backward compatibility with Modules 1-2.

## Token Model

### Access Tokens
- **Type**: Bearer JWT (via Laravel Sanctum personal access tokens)
- **TTL**: 900 seconds (15 minutes), configurable via `JWT_ACCESS_TTL`
- **Storage**: `personal_access_tokens` table (existing Sanctum table)
- **Usage**: Required for all protected endpoints via `Authorization: Bearer {token}` header

### Refresh Tokens
- **Type**: Opaque token with server-side hash storage
- **TTL**: 1,209,600 seconds (14 days), configurable via `JWT_REFRESH_TTL`
- **Storage**: `refresh_tokens` table with hash, expiry, and revocation tracking
- **Rotation**: Each refresh generates new access + refresh token pair; old refresh token is revoked
- **Reuse Detection**: If a revoked refresh token is used, entire token family is revoked (security measure)

### Token Lifecycle
1. **Registration**: Creates user, returns access token + refresh token
2. **Login**: Validates credentials, returns access token + refresh token
3. **Refresh**: Validates refresh token, rotates to new pair, revokes old refresh token
4. **Logout**: Revokes current access token and associated refresh token

## Database Schema

### Modified Tables

#### users (update existing)
Add `status` column for account state management:
```sql
ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active';
-- Values: 'active', 'suspended', 'pending_verification'
```

### New Tables

#### customer_profiles
```sql
CREATE TABLE customer_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    marketing_opt_in BOOLEAN DEFAULT FALSE,
    birth_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### customer_addresses
```sql
CREATE TABLE customer_addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(50) NOT NULL,
    country CHAR(2) NOT NULL,
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    street_line1 VARCHAR(255) NOT NULL,
    street_line2 VARCHAR(255) NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_default (user_id, is_default)
);
```

#### email_verifications
```sql
CREATE TABLE email_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_expires (user_id, expires_at)
);
```

#### password_resets (custom, separate from Laravel's default)
```sql
CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_token (token)
);
```

#### refresh_tokens
```sql
CREATE TABLE refresh_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    replaced_by BIGINT UNSIGNED NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (replaced_by) REFERENCES refresh_tokens(id) ON DELETE SET NULL,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_revoked (user_id, revoked)
);
```

## API Endpoints

### Authentication Endpoints (Public)

#### POST /api/v1/auth/register
Register a new customer account.

**Request:**
```json
{
    "email": "customer@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "first_name": "John",
    "last_name": "Doe"
}
```

**Response (201):**
```json
{
    "data": {
        "user": {
            "id": 1,
            "email": "customer@example.com"
        },
        "access_token": "1|abc123...",
        "refresh_token": "rt_xyz789...",
        "token_type": "Bearer",
        "expires_in": 900
    },
    "meta": {
        "email_verification_required": true
    }
}
```

#### POST /api/v1/auth/login
Authenticate and obtain tokens.

**Request:**
```json
{
    "email": "customer@example.com",
    "password": "SecurePass123!"
}
```

**Response (200):**
```json
{
    "data": {
        "user": {
            "id": 1,
            "email": "customer@example.com"
        },
        "access_token": "2|def456...",
        "refresh_token": "rt_abc123...",
        "token_type": "Bearer",
        "expires_in": 900
    },
    "meta": {}
}
```

**Error Response (401):**
```json
{
    "title": "Authentication Failed",
    "detail": "The provided credentials are incorrect.",
    "status": 401,
    "errors": {},
    "trace_id": "abc123"
}
```

#### POST /api/v1/auth/token/refresh
Rotate tokens using refresh token.

**Request:**
```json
{
    "refresh_token": "rt_xyz789..."
}
```

**Response (200):**
```json
{
    "data": {
        "access_token": "3|ghi789...",
        "refresh_token": "rt_new123...",
        "token_type": "Bearer",
        "expires_in": 900
    },
    "meta": {}
}
```

#### POST /api/v1/auth/logout (Protected)
Revoke current tokens.

**Headers:** `Authorization: Bearer {access_token}`

**Response (204):** No content

### Email Verification Endpoints

#### POST /api/v1/auth/email/verify/request (Protected)
Request a new verification email.

**Response (200):**
```json
{
    "data": {
        "message": "Verification email sent."
    },
    "meta": {}
}
```

#### POST /api/v1/auth/email/verify/confirm
Confirm email with token.

**Request:**
```json
{
    "token": "verification_token_here"
}
```

**Response (200):**
```json
{
    "data": {
        "message": "Email verified successfully."
    },
    "meta": {}
}
```

### Password Reset Endpoints

#### POST /api/v1/auth/password/forgot
Request password reset email.

**Request:**
```json
{
    "email": "customer@example.com"
}
```

**Response (200):**
```json
{
    "data": {
        "message": "If the email exists, a reset link has been sent."
    },
    "meta": {}
}
```

#### POST /api/v1/auth/password/reset
Reset password with token.

**Request:**
```json
{
    "token": "reset_token_here",
    "email": "customer@example.com",
    "password": "NewSecurePass456!",
    "password_confirmation": "NewSecurePass456!"
}
```

**Response (200):**
```json
{
    "data": {
        "message": "Password reset successfully."
    },
    "meta": {}
}
```

### Profile Endpoints (Protected)

#### GET /api/v1/me
Get current user profile.

**Response (200):**
```json
{
    "data": {
        "id": 1,
        "email": "customer@example.com",
        "email_verified": true,
        "profile": {
            "first_name": "John",
            "last_name": "Doe",
            "phone": "+1234567890",
            "marketing_opt_in": false,
            "birth_date": "1990-01-15"
        }
    },
    "meta": {}
}
```

#### PATCH /api/v1/me
Update current user profile.

**Request:**
```json
{
    "first_name": "Johnny",
    "phone": "+1987654321",
    "marketing_opt_in": true
}
```

**Response (200):** Same as GET /api/v1/me

### Address Endpoints (Protected)

#### GET /api/v1/me/addresses
List user addresses.

**Response (200):**
```json
{
    "data": [
        {
            "id": 1,
            "label": "Home",
            "country": "US",
            "city": "New York",
            "postal_code": "10001",
            "street_line1": "123 Main St",
            "street_line2": "Apt 4B",
            "is_default": true
        }
    ],
    "meta": {
        "total": 1
    }
}
```

#### POST /api/v1/me/addresses
Create new address.

**Request:**
```json
{
    "label": "Work",
    "country": "US",
    "city": "New York",
    "postal_code": "10002",
    "street_line1": "456 Office Blvd",
    "is_default": false
}
```

**Response (201):** Address object

#### PATCH /api/v1/me/addresses/{id}
Update address.

#### DELETE /api/v1/me/addresses/{id}
Delete address.

**Response (204):** No content

#### POST /api/v1/me/addresses/{id}/make-default
Set address as default.

**Response (200):** Address object with `is_default: true`

## GDPR Compliance Notes

### Data Minimization
- Only collect necessary fields for service operation
- Profile fields (phone, birth_date) are optional
- Marketing opt-in is explicit and defaults to false

### GDPR-Safe Fields Exposed via API
- `id`, `email`, `email_verified` (user)
- `first_name`, `last_name`, `phone`, `marketing_opt_in`, `birth_date` (profile)
- Full address fields (addresses)

### Fields NOT Exposed
- `password` / `password_hash`
- `remember_token`
- Internal timestamps (created_at, updated_at) - only exposed where useful
- Token hashes and internal IDs for security tokens

### Data Retention
- Refresh tokens: Auto-expire after TTL
- Email verification tokens: 24-hour expiry
- Password reset tokens: 1-hour expiry (configurable via `PASSWORD_RESET_TTL`)

## Error Model (RFC7807)

All errors follow this structure:

```json
{
    "title": "Human-readable error title",
    "detail": "Detailed explanation of the error",
    "status": 422,
    "errors": {
        "field_name": ["Validation error message"]
    },
    "trace_id": "unique-request-identifier"
}
```

### HTTP Status Codes
- **200**: Success
- **201**: Created
- **204**: No Content (successful deletion/logout)
- **400**: Bad Request (malformed request)
- **401**: Unauthorized (invalid/missing token, invalid credentials)
- **403**: Forbidden (valid token but insufficient permissions)
- **404**: Not Found
- **409**: Conflict (e.g., email already registered)
- **422**: Validation Error
- **429**: Too Many Requests (rate limited)
- **500**: Server Error

## Rate Limiting

### Sensitive Endpoints
| Endpoint | Limit | Window |
|----------|-------|--------|
| POST /auth/register | 5 requests | per minute |
| POST /auth/login | 5 requests | per minute |
| POST /auth/password/forgot | 3 requests | per hour |
| POST /auth/email/verify/request | 3 requests | per hour |
| POST /auth/token/refresh | 30 requests | per minute |

### Configuration
```env
LOGIN_RATE_LIMIT=5
REGISTER_RATE_LIMIT=5
FORGOT_RATE_LIMIT=3
VERIFY_RATE_LIMIT=3
```

## Service Layer Architecture

### AuthService
- `register(array $data): array` - Create user, profile, tokens
- `login(string $email, string $password): array` - Validate credentials, issue tokens
- `refresh(string $refreshToken): array` - Rotate tokens with reuse detection
- `logout(User $user, string $accessToken): void` - Revoke tokens
- `revokeTokenFamily(RefreshToken $token): void` - Security: revoke all related tokens

### EmailVerificationService
- `sendVerificationEmail(User $user): void` - Generate token, queue email
- `verify(string $token): bool` - Validate and consume token
- `isVerified(User $user): bool` - Check verification status

### PasswordResetService
- `sendResetEmail(string $email): void` - Generate token, queue email (no user leak)
- `reset(string $token, string $email, string $password): bool` - Validate and reset

### ProfileService
- `getProfile(User $user): CustomerProfile` - Get or create profile
- `updateProfile(User $user, array $data): CustomerProfile` - Update GDPR-safe fields

### AddressService
- `listAddresses(User $user): Collection` - Get all user addresses
- `createAddress(User $user, array $data): CustomerAddress` - Create with default handling
- `updateAddress(int $id, User $user, array $data): CustomerAddress` - Update owned address
- `deleteAddress(int $id, User $user): void` - Delete owned address
- `setDefault(int $id, User $user): CustomerAddress` - Set as default, unset others

## Validation Rules

### Registration
- `email`: required, valid email format, unique in users table
- `password`: required, min 8 chars, confirmed, must contain uppercase, lowercase, number
- `first_name`: optional, string, max 100
- `last_name`: optional, string, max 100

### Login
- `email`: required, valid email format
- `password`: required, string
- Error messages are generic to prevent user enumeration

### Profile Update
- `first_name`: optional, string, max 100
- `last_name`: optional, string, max 100
- `phone`: optional, string, max 20, regex for phone format
- `marketing_opt_in`: optional, boolean
- `birth_date`: optional, date, before today

### Address
- `label`: required, string, max 50
- `country`: required, string, size 2 (ISO country code)
- `city`: required, string, max 100
- `postal_code`: required, string, max 20
- `street_line1`: required, string, max 255
- `street_line2`: optional, string, max 255
- `is_default`: optional, boolean

## Security Considerations

### Password Security
- Passwords hashed using bcrypt (Laravel default)
- Minimum 8 characters with complexity requirements
- Password not logged or exposed in responses

### Token Security
- Refresh tokens stored as SHA-256 hashes
- Access tokens use Sanctum's secure token generation
- Token rotation prevents replay attacks
- Reuse detection revokes entire token family

### Auth Event Logging
Log the following events (without sensitive data):
- Registration attempts (success/failure)
- Login attempts (success/failure, IP, user agent)
- Token refresh (success/failure)
- Password reset requests
- Email verification attempts

### Generic Error Messages
- Login failures return generic "credentials incorrect" message
- Password reset always returns success message (prevents email enumeration)

## Configuration (.env.example additions)

```env
# Auth Module Configuration
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=1209600
PASSWORD_RESET_TTL=3600
EMAIL_VERIFICATION_TTL=86400

# Rate Limits
LOGIN_RATE_LIMIT=5
REGISTER_RATE_LIMIT=5
FORGOT_RATE_LIMIT=3
VERIFY_RATE_LIMIT=3
```

## Testing Strategy

### Unit Tests
- Token generation and validation
- Password hashing verification
- Rate limit logic

### Feature Tests
1. **Registration Flow**
   - Successful registration with valid data
   - Validation errors (invalid email, weak password, duplicate email)
   - Rate limiting enforcement

2. **Login Flow**
   - Successful login with valid credentials
   - Failed login with invalid credentials (generic error)
   - Rate limiting enforcement

3. **Token Refresh Flow**
   - Successful token rotation
   - Invalid refresh token rejection
   - Expired refresh token rejection
   - Reuse detection and family revocation

4. **Logout Flow**
   - Successful token revocation
   - Subsequent requests fail with 401

5. **Email Verification Flow**
   - Request verification email
   - Confirm with valid token
   - Reject expired/invalid token

6. **Password Reset Flow**
   - Request reset (success message regardless of email existence)
   - Reset with valid token
   - Reject expired/invalid token

7. **Profile Management**
   - Get profile (authenticated)
   - Update profile with valid data
   - Validation errors
   - 401 for unauthenticated requests

8. **Address Management**
   - CRUD operations
   - Default address exclusivity
   - Cannot access other user's addresses (403)
   - Validation errors

## Completion Checklist

- [ ] Design doc created at /docs/auth/003-plan.md
- [ ] Migrations for all new tables
- [ ] User model updated with status and relationships
- [ ] New models: CustomerProfile, CustomerAddress, EmailVerification, PasswordReset, RefreshToken
- [ ] Services: AuthService, EmailVerificationService, PasswordResetService, ProfileService, AddressService
- [ ] Controllers: AuthController, EmailVerificationController, PasswordResetController, MeController, AddressController
- [ ] Form Requests for all endpoints
- [ ] API Resources for serialization
- [ ] Routes registered with rate limiting
- [ ] .env.example updated
- [ ] OpenAPI documentation extended
- [ ] Tests passing for all flows
- [ ] Lint passing (Laravel Pint)
- [ ] Modules 1-2 unaffected
