# Authentication Fix for Result Storage / ការជួសជុលការផ្ទៀងផ្ទាត់សម្រាប់ការរក្សាទុកលទ្ធផល

## Problem / បញ្ហា

The spin result storage endpoint was not properly storing `user_id` even when users were authenticated with valid tokens. This happened because:

1. The route was not protected by authentication middleware
2. `Auth::id()` returned `null` for unauthenticated requests
3. The token was not being used to identify the user

## Root Cause / មូលហេតុដើម

```php
// In routes/api.php (BEFORE)
Route::post('result', [ResultController::class, 'store']); // No auth middleware!
```

The route was accessible to both authenticated and guest users, but the authentication context was not properly set.

## Solution / ដំណោះស្រាយ

### 1. Created Optional Authentication Middleware / បង្កើត Optional Authentication Middleware

Created `app/Http/Middleware/OptionalAuth.php` that:

-   Allows both authenticated and guest users to access routes
-   Properly sets the user context when a valid token is provided
-   Doesn't fail if no authentication is provided

### 2. Updated Route Configuration / ធ្វើបច្ចុប្បន្នភាពការកំណត់ផ្លូវ

```php
// In routes/api.php (AFTER)
Route::middleware('auth.optional')->group(function () {
    Route::post('result', [ResultController::class, 'store']); // Save spin result
    Route::get('results', [ResultController::class, 'index']); // Get spin history
    Route::get('statistics', [ResultController::class, 'statistics']); // Get statistics
});
```

### 3. Enhanced ResultController / បង្កើតជាមួយ ResultController

Updated the `store` method to:

-   Get user ID from authentication or request
-   Add debugging logs to track authentication status
-   Handle both authenticated and guest users properly

```php
// Get user ID from authentication or request
$userId = Auth::id() ?? $request->input('user_id');

// Log authentication status for debugging
\Log::info('Result creation - Auth status', [
    'auth_check' => Auth::check(),
    'auth_id' => Auth::id(),
    'request_user_id' => $request->input('user_id'),
    'final_user_id' => $userId,
    'bearer_token' => $request->bearerToken() ? 'present' : 'absent',
]);
```

## How It Works / របៀបដែលវាដំណើរការ

### For Authenticated Users / សម្រាប់អ្នកប្រើប្រាស់ដែលបានផ្ទៀងផ្ទាត់

1. User provides Bearer token in Authorization header
2. `OptionalAuth` middleware validates the token and sets the user context
3. `Auth::id()` returns the authenticated user's ID
4. Result is stored with the correct `user_id`

### For Guest Users / សម្រាប់អ្នកប្រើប្រាស់ភ្ញៀវ

1. No Bearer token provided
2. `OptionalAuth` middleware allows the request to proceed
3. `Auth::id()` returns `null`
4. Result is stored with `user_id` as `null`

## Testing / ការធ្វើតេស្ត

Created comprehensive tests in `tests/Feature/ResultAuthenticationTest.php`:

-   ✅ Authenticated user can store result with user_id
-   ✅ Guest user can store result without user_id
-   ✅ Authenticated user with token can store result

## Usage Examples / ឧទាហរណ៍នៃការប្រើប្រាស់

### Authenticated Request / សំណើដែលបានផ្ទៀងផ្ទាត់

```bash
curl -X POST /api/v1/spin/result \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "segment_index": 5,
    "result_english": "Test Prize",
    "result_khmer": "រង្វាន់តេស្ត",
    "result_color": "#FF0000",
    "spin_angle": 180.5
  }'
```

### Guest Request / សំណើភ្ញៀវ

```bash
curl -X POST /api/v1/spin/result \
  -H "Content-Type: application/json" \
  -d '{
    "segment_index": 3,
    "result_english": "Guest Prize",
    "result_khmer": "រង្វាន់ភ្ញៀវ",
    "result_color": "#00FF00",
    "spin_angle": 90.0
  }'
```

## Database Impact / ផលប៉ះពាល់លើមូលដ្ឋានទិន្នន័យ

-   Results table now properly stores `user_id` for authenticated users
-   Guest users still have `user_id` as `null` (as intended)
-   No breaking changes to existing functionality

## Monitoring / ការតាមដាន

Added logging to track authentication status:

```php
\Log::info('Result creation - Auth status', [
    'auth_check' => Auth::check(),
    'auth_id' => Auth::id(),
    'request_user_id' => $request->input('user_id'),
    'final_user_id' => $userId,
    'bearer_token' => $request->bearerToken() ? 'present' : 'absent',
]);
```

Check logs at `storage/logs/laravel.log` to monitor authentication behavior.
