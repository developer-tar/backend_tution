# Fix for SameSite Cookie Error

## Problem
You're getting: `Cookie "11_plus_magic_session" has been rejected because it is in a cross-site context and its "SameSite" is "Lax" or "Strict".`

This happens when your frontend and backend are on different domains/ports.

## Solution

### For Production (HTTPS)

Add these to your `.env` file:

```env
SESSION_SAME_SITE=none
SESSION_SECURE_COOKIE=true
```

**Important:** When `SameSite=None`, `Secure=true` is REQUIRED by browsers.

### For Local Development (HTTP)

You have 3 options:

#### Option 1: Use HTTPS Locally (Recommended)
1. Set up HTTPS for local development (using Laragon's SSL or similar)
2. Then use the production settings above

#### Option 2: Use Same-Site Setup
Configure your frontend to proxy API requests so they appear same-site:
- Frontend: `http://localhost:3000`
- Backend proxy: `http://localhost:3000/api` → `http://localhost:8000/api`
- Keep default settings: `SESSION_SAME_SITE=lax` (or don't set it)

#### Option 3: Temporary Dev Workaround (NOT for production)
For local development only, you can temporarily use:

```env
SESSION_SAME_SITE=null
SESSION_SECURE_COOKIE=false
```

⚠️ **Warning:** This is less secure and should ONLY be used in development.

## After Making Changes

1. Add the environment variables to your `.env` file
2. Clear config cache:
   ```bash
   php artisan config:clear
   ```
3. Restart your server
4. Clear browser cookies (or use incognito/private mode)
5. Test again

## Verify It's Working

After making changes, check your browser's developer tools:
1. Open DevTools → Application/Storage → Cookies
2. Find your session cookie
3. Check that `SameSite` is set correctly
4. If `SameSite=None`, verify `Secure` is `true`

## Notes

- The cart merge functionality is working (items are being saved and merged on login)
- CORS is already configured with `supports_credentials: true` which is correct
- Make sure your frontend is sending requests with `withCredentials: true` (if using axios/fetch)

