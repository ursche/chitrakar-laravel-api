# Chitrakar AI — Laravel API Specification

All endpoints are prefixed with `/api/v1`.  
Authentication uses Laravel Sanctum token-based auth (SPA or mobile).  
All requests/responses use `Content-Type: application/json` unless noted.  
All authenticated endpoints require the header: `Authorization: Bearer {token}`

---

## Table of Contents

1. [Auth](#1-auth)
2. [User](#2-user)
3. [Packages](#3-packages)
4. [Payments — Khalti](#4-payments--khalti)
5. [Payments — eSewa](#5-payments--esewa)
6. [Transactions](#6-transactions)
7. [Image Upload](#7-image-upload)
8. [Jobs (AI Generation)](#8-jobs-ai-generation)
9. [Webhook (AI Service Callback)](#9-webhook-ai-service-callback)
10. [Config / Lookup Data](#10-config--lookup-data)
11. [Error Format](#11-error-format)

---

## 1. Auth

### POST `/api/v1/auth/register`
Register a new user. Awards 5 free starter credits.

**Request:**
```json
{
  "name": "Aarav Sharma",
  "email": "aarav@example.com",
  "password": "minimum8chars",
  "password_confirmation": "minimum8chars"
}
```

**Response `201`:**
```json
{
  "user": {
    "id": 1,
    "name": "Aarav Sharma",
    "email": "aarav@example.com",
    "credits": 5,
    "created_at": "2025-01-15T10:00:00Z"
  },
  "token": "1|abcdefghijklmnop..."
}
```

**Errors:** `422` validation failed (email taken, password too short, etc.)

---

### POST `/api/v1/auth/login`
Login and get a Sanctum token.

**Request:**
```json
{
  "email": "aarav@example.com",
  "password": "minimum8chars"
}
```

**Response `200`:**
```json
{
  "user": {
    "id": 1,
    "name": "Aarav Sharma",
    "email": "aarav@example.com",
    "credits": 25,
    "created_at": "2025-01-15T10:00:00Z"
  },
  "token": "2|abcdefghijklmnop..."
}
```

**Errors:** `401` invalid credentials

---

### POST `/api/v1/auth/logout`
Revoke the current token.  
**Auth required.**

**Request:** _(empty body)_

**Response `200`:**
```json
{
  "message": "Logged out successfully"
}
```

---

### POST `/api/v1/auth/forgot-password`
Send password reset email.

**Request:**
```json
{
  "email": "aarav@example.com"
}
```

**Response `200`:**
```json
{
  "message": "Password reset link sent to your email"
}
```

**Errors:** `422` email not found (or return same 200 to avoid enumeration)

---

### POST `/api/v1/auth/reset-password`
Reset password using the token from email.

**Request:**
```json
{
  "token": "reset-token-from-email",
  "email": "aarav@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response `200`:**
```json
{
  "message": "Password reset successfully"
}
```

**Errors:** `422` invalid/expired token

---

## 2. User

### GET `/api/v1/user/me`
Get the authenticated user's profile and current credit balance.  
**Auth required.**

**Response `200`:**
```json
{
  "id": 1,
  "name": "Aarav Sharma",
  "email": "aarav@example.com",
  "credits": 25,
  "created_at": "2025-01-15T10:00:00Z"
}
```

---

### PUT `/api/v1/user/me`
Update user profile (name or email).  
**Auth required.**

**Request:**
```json
{
  "name": "Aarav B Sharma",
  "email": "newemail@example.com"
}
```

**Response `200`:**
```json
{
  "id": 1,
  "name": "Aarav B Sharma",
  "email": "newemail@example.com",
  "credits": 25,
  "created_at": "2025-01-15T10:00:00Z"
}
```

**Errors:** `422` email already taken

---

## 3. Packages

### GET `/api/v1/packages`
List all available credit packages. No auth required (public).

**Response `200`:**
```json
{
  "data": [
    {
      "id": "starter",
      "name": "Starter Pack",
      "price_npr": 999,
      "credits": 10,
      "popular": false
    },
    {
      "id": "growth",
      "name": "Growth Pack",
      "price_npr": 2499,
      "credits": 30,
      "popular": true
    },
    {
      "id": "pro",
      "name": "Pro Pack",
      "price_npr": 4999,
      "credits": 75,
      "popular": false
    },
    {
      "id": "enterprise",
      "name": "Enterprise Pack",
      "price_npr": 9999,
      "credits": 200,
      "popular": false
    }
  ]
}
```

---

## 4. Payments — Khalti

Khalti uses a two-step flow: **initiate** → redirect user to Khalti → **verify** on return.

### POST `/api/v1/payments/khalti/initiate`
Create a Khalti payment order. Backend calls Khalti's `/epayment/initiate/` API and returns the `payment_url` to redirect the user to.  
**Auth required.**

**Request:**
```json
{
  "package_id": "growth"
}
```

**Response `200`:**
```json
{
  "pidx": "KHL-bZIPsNpX4WiNp2zd",
  "payment_url": "https://pay.khalti.com/?pidx=KHL-bZIPsNpX4WiNp2zd",
  "transaction_id": 42,
  "expires_at": "2025-01-15T10:30:00Z"
}
```

- The frontend redirects the user to `payment_url`.
- Khalti will redirect back to your configured return URL with `?pidx=...&status=Completed` (or `&status=User canceled`).

**Errors:** `404` package not found, `502` Khalti API error

---

### POST `/api/v1/payments/khalti/verify`
Called by the frontend after Khalti redirects back. Backend verifies with Khalti and awards credits.  
**Auth required.**

**Request:**
```json
{
  "pidx": "KHL-bZIPsNpX4WiNp2zd"
}
```

**Response `200`:**
```json
{
  "success": true,
  "credits_awarded": 30,
  "new_credit_balance": 55,
  "transaction": {
    "id": 42,
    "amount_npr": 2499,
    "payment_gateway": "khalti",
    "status": "complete",
    "credits_awarded": 30,
    "gateway_tx_id": "KHL-bZIPsNpX4WiNp2zd",
    "created_at": "2025-01-15T10:00:00Z"
  }
}
```

**Errors:** `400` payment not completed / already verified, `502` Khalti verification failed

---

## 5. Payments — eSewa

eSewa uses a form POST flow: frontend POSTs a form directly to eSewa's gateway, then eSewa redirects back.

### POST `/api/v1/payments/esewa/initiate`
Generate the signed parameters the frontend needs to submit to eSewa's form endpoint.  
**Auth required.**

**Request:**
```json
{
  "package_id": "starter"
}
```

**Response `200`:**
```json
{
  "transaction_id": 43,
  "esewa_form_url": "https://rc-epay.esewa.com.np/api/epay/main/v2/form",
  "form_fields": {
    "amount": "999",
    "tax_amount": "0",
    "total_amount": "999",
    "transaction_uuid": "chitrakar-tx-43-1705312800",
    "product_code": "EPAYTEST",
    "product_service_charge": "0",
    "product_delivery_charge": "0",
    "success_url": "https://yourapp.com/payment/esewa/success",
    "failure_url": "https://yourapp.com/payment/esewa/failure",
    "signed_field_names": "total_amount,transaction_uuid,product_code",
    "signature": "base64-hmac-signature-here"
  }
}
```

The frontend renders a hidden form with these fields and submits it to `esewa_form_url`.

**Errors:** `404` package not found

---

### POST `/api/v1/payments/esewa/verify`
Called by the frontend after eSewa redirects to success URL. The `data` param is a base64-encoded JSON from eSewa. Backend decodes, verifies signature, and awards credits.  
**Auth required.**

**Request:**
```json
{
  "data": "eyJ0cmFuc2FjdGlvbl9jb2RlIjoiMDAwQUFBIiwic3RhdHVzIjoiQ09NUExFVEUiLCJ0b3RhbF9hbW91bnQiOiI5OTkuMCIsInRyYW5zYWN0aW9uX3V1aWQiOiJjaGl0cmFrYXItdHgtNDMtMTcwNTMxMjgwMCIsInByb2R1Y3RfY29kZSI6IkVQQVlURVNUIiwic2lnbmVkX2ZpZWxkX25hbWVzIjoidHJhbnNhY3Rpb25fY29kZSxzdGF0dXMsdG90YWxfYW1vdW50LHRyYW5zYWN0aW9uX3V1aWQscHJvZHVjdF9jb2RlLHNpZ25lZF9maWVsZF9uYW1lcyIsInNpZ25hdHVyZSI6ImFiY2RlZiJ9"
}
```

**Response `200`:**
```json
{
  "success": true,
  "credits_awarded": 10,
  "new_credit_balance": 35,
  "transaction": {
    "id": 43,
    "amount_npr": 999,
    "payment_gateway": "esewa",
    "status": "complete",
    "credits_awarded": 10,
    "gateway_tx_id": "000AAA",
    "created_at": "2025-01-15T10:05:00Z"
  }
}
```

**Errors:** `400` invalid signature / already verified, `502` verification failed

---

## 6. Transactions

### GET `/api/v1/transactions`
List the authenticated user's payment history.  
**Auth required.**

**Query params:**
- `page` (optional, default 1)
- `per_page` (optional, default 15)

**Response `200`:**
```json
{
  "data": [
    {
      "id": 42,
      "amount_npr": 2499,
      "payment_gateway": "khalti",
      "status": "complete",
      "credits_awarded": 30,
      "gateway_tx_id": "KHL-bZIPsNpX4WiNp2zd",
      "created_at": "2025-01-15T10:00:00Z"
    },
    {
      "id": 41,
      "amount_npr": 999,
      "payment_gateway": "esewa",
      "status": "complete",
      "credits_awarded": 10,
      "gateway_tx_id": "ESW-789012",
      "created_at": "2025-01-10T09:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 2,
    "last_page": 1
  }
}
```

---

## 7. Image Upload

Images must be uploaded before creating a job. The response URL is then passed to `POST /api/v1/jobs`.

### POST `/api/v1/upload`
Upload a product image to storage (S3, Cloudflare R2, or local disk).  
**Auth required.**  
**Content-Type: `multipart/form-data`**

**Request (form data):**
```
image: <file>   (JPEG/PNG/WEBP, max 10MB)
```

**Response `201`:**
```json
{
  "url": "https://cdn.chitrakar.ai/uploads/user-1/abc123.jpg",
  "key": "uploads/user-1/abc123.jpg",
  "size_bytes": 204800,
  "mime_type": "image/jpeg"
}
```

**Errors:** `422` invalid file type or size too large, `507` storage error

---

## 8. Jobs (AI Generation)

### POST `/api/v1/jobs`
Create a new AI generation job. Deducts credits immediately. Job starts as `processing` and is updated to `complete` or `failed` asynchronously by the AI service.  
**Auth required.**

**Credit cost per service type:**
| Service | Credits |
|---|---|
| `virtual-mannequin` | 4 |
| `product-staging` | 3 |
| `promotional-banner` | 2 |

**Request:**
```json
{
  "service_type": "virtual-mannequin",
  "input_image_url": "https://cdn.chitrakar.ai/uploads/user-1/abc123.jpg",
  "prompt_payload": {
    "service_type": "virtual-mannequin",
    "model_style": "Traditional Nepali",
    "setting": "Thamel cafe"
  }
}
```

For `product-staging`:
```json
{
  "service_type": "product-staging",
  "input_image_url": "https://cdn.chitrakar.ai/uploads/user-1/xyz456.jpg",
  "prompt_payload": {
    "service_type": "product-staging",
    "aesthetic": "Dark, luxury display with velvet"
  }
}
```

For `promotional-banner`:
```json
{
  "service_type": "promotional-banner",
  "input_image_url": "https://cdn.chitrakar.ai/uploads/user-1/def789.jpg",
  "prompt_payload": {
    "service_type": "promotional-banner",
    "promo_text": "Rs 199 Special Offer",
    "theme": "Tihar Festival",
    "product_description": "Delicious steamed momos"
  }
}
```

**Response `201`:**
```json
{
  "id": 101,
  "user_id": 1,
  "service_type": "virtual-mannequin",
  "input_image_url": "https://cdn.chitrakar.ai/uploads/user-1/abc123.jpg",
  "prompt_payload": {
    "service_type": "virtual-mannequin",
    "model_style": "Traditional Nepali",
    "setting": "Thamel cafe"
  },
  "status": "processing",
  "output_urls": [],
  "credits_used": 4,
  "created_at": "2025-01-20T10:00:00Z",
  "completed_at": null
}
```

**Errors:** `402` insufficient credits, `422` invalid service_type or missing payload fields

---

### GET `/api/v1/jobs`
List all jobs for the authenticated user. Used to populate the Gallery page.  
**Auth required.**

**Query params:**
- `page` (optional, default 1)
- `per_page` (optional, default 20)
- `service_type` (optional: `virtual-mannequin` | `product-staging` | `promotional-banner`)
- `status` (optional: `pending` | `processing` | `complete` | `failed`)

**Response `200`:**
```json
{
  "data": [
    {
      "id": 101,
      "user_id": 1,
      "service_type": "virtual-mannequin",
      "input_image_url": "https://cdn.chitrakar.ai/uploads/user-1/abc123.jpg",
      "prompt_payload": {
        "service_type": "virtual-mannequin",
        "model_style": "Traditional Nepali",
        "setting": "Thamel cafe"
      },
      "status": "complete",
      "output_urls": [
        "https://cdn.chitrakar.ai/outputs/job-101/result.jpg"
      ],
      "credits_used": 4,
      "created_at": "2025-01-20T10:00:00Z",
      "completed_at": "2025-01-20T10:00:45Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 4,
    "last_page": 1
  }
}
```

---

### GET `/api/v1/jobs/{id}`
Get a single job by ID. Used for polling job status after submission.  
**Auth required.**

**Response `200`:**
```json
{
  "id": 101,
  "user_id": 1,
  "service_type": "virtual-mannequin",
  "input_image_url": "https://cdn.chitrakar.ai/uploads/user-1/abc123.jpg",
  "prompt_payload": {
    "service_type": "virtual-mannequin",
    "model_style": "Traditional Nepali",
    "setting": "Thamel cafe"
  },
  "status": "complete",
  "output_urls": [
    "https://cdn.chitrakar.ai/outputs/job-101/result.jpg"
  ],
  "credits_used": 4,
  "created_at": "2025-01-20T10:00:00Z",
  "completed_at": "2025-01-20T10:00:45Z"
}
```

**Errors:** `403` job belongs to another user, `404` job not found

---

### GET `/api/v1/jobs/stats`
Summary counts for the Dashboard stats cards.  
**Auth required.**

**Response `200`:**
```json
{
  "total_jobs": 4,
  "completed_jobs": 3,
  "processing_jobs": 1,
  "failed_jobs": 0,
  "total_credits_used": 13
}
```

---

## 9. Webhook (AI Service Callback)

When your AI generation service finishes processing a job, it calls this endpoint to deliver results. Secure with a shared secret header.

### POST `/api/v1/webhooks/job-complete`
**Not authenticated with Bearer token.**  
**Secured by:** `X-Webhook-Secret: {shared-secret-env-var}`

**Request:**
```json
{
  "job_id": 101,
  "status": "complete",
  "output_urls": [
    "https://cdn.chitrakar.ai/outputs/job-101/result.jpg"
  ]
}
```

On failure:
```json
{
  "job_id": 101,
  "status": "failed",
  "output_urls": [],
  "error_message": "AI service timeout"
}
```

**Response `200`:**
```json
{
  "received": true
}
```

> **Note:** If you are not building a separate AI service yet and want to use a third-party AI API (like Replicate, Stability AI, or similar) directly from Laravel, you would call that API synchronously or via a queued job from `POST /api/v1/jobs`, without needing this webhook. Add this endpoint later when you have an async AI pipeline.

---

## 10. Config / Lookup Data

Static option lists that the frontend dropdowns use. These match the hardcoded arrays in `lib/mock-data.ts`.

### GET `/api/v1/config/options`
No auth required.

**Response `200`:**
```json
{
  "model_styles": [
    "Traditional Nepali",
    "Elegant Modern",
    "Casual Street",
    "Professional Business",
    "Festive Traditional"
  ],
  "settings": [
    "Thamel cafe",
    "City street",
    "Mountain backdrop",
    "Studio white background",
    "Heritage palace",
    "Modern office",
    "Garden setting"
  ],
  "aesthetics": [
    "Rustic, marble table with soft lighting",
    "Dark, luxury display with velvet",
    "Minimalist white background",
    "Natural wood and greenery",
    "Traditional Nepali craft setting",
    "Modern glass and metal"
  ],
  "themes": [
    "Tihar Festival",
    "Dashain Special",
    "New Year Sale",
    "Summer Collection",
    "Winter Warmth",
    "Flash Sale",
    "Grand Opening",
    "Anniversary Celebration"
  ],
  "service_credits": {
    "virtual-mannequin": 4,
    "product-staging": 3,
    "promotional-banner": 2
  }
}
```

---

## 11. Error Format

All error responses follow this structure:

```json
{
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

`errors` is only present on `422` validation failures. For other errors (401, 403, 404, 402, 500), only `message` is returned.

**HTTP status codes used:**

| Code | Meaning |
|---|---|
| `200` | OK |
| `201` | Created |
| `401` | Unauthenticated (missing/invalid token) |
| `402` | Insufficient credits |
| `403` | Forbidden (resource belongs to another user) |
| `404` | Not found |
| `422` | Validation error |
| `500` | Server error |
| `502` | Upstream payment gateway error |

---

## Laravel Route Summary

```php
// Public
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/packages', [PackageController::class, 'index']);
Route::get('/config/options', [ConfigController::class, 'options']);

// Webhook (secret header auth, not Sanctum)
Route::post('/webhooks/job-complete', [WebhookController::class, 'jobComplete']);

// Authenticated (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/user/me', [UserController::class, 'me']);
    Route::put('/user/me', [UserController::class, 'update']);

    Route::post('/upload', [UploadController::class, 'store']);

    Route::post('/payments/khalti/initiate', [KhaltiController::class, 'initiate']);
    Route::post('/payments/khalti/verify', [KhaltiController::class, 'verify']);
    Route::post('/payments/esewa/initiate', [EsewaController::class, 'initiate']);
    Route::post('/payments/esewa/verify', [EsewaController::class, 'verify']);

    Route::get('/transactions', [TransactionController::class, 'index']);

    Route::get('/jobs/stats', [JobController::class, 'stats']);
    Route::get('/jobs', [JobController::class, 'index']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);
});
```
