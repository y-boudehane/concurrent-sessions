# Integration Guide - Concurrent Session Management

This guide provides step-by-step instructions for integrating the concurrent session management system into your Laravel + Vue.js project.

## 📋 Prerequisites

- Laravel 9+ with Inertia.js
- Vue.js 3+ with TypeScript
- Laravel Echo + Pusher for WebSocket
- Database session driver
- Tailwind CSS (for styling)

---

## 🚀 Step 1: Backend Setup

### 1.1 Copy Backend Files

Copy the following files to your Laravel project:

```bash
# Controllers
cp backend/Controllers/ConfirmDeviceController.php app/Http/Controllers/
cp backend/Controllers/Api/SessionDataController.php app/Http/Controllers/Api/

# Middleware
cp backend/Middleware/ConfirmSessionMiddleware.php app/Http/Middleware/

# Services
cp backend/Services/ConcurrentSessionManager.php app/Services/

# Events
cp backend/Events/NewLoginDetected.php app/Events/
cp backend/Events/SessionConfirmed.php app/Events/
cp backend/Events/SessionDisconnected.php app/Events/
```

### 1.2 Register Middleware

Add the middleware to your HTTP kernel:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        
        // Add this line
        \App\Http\Middleware\ConfirmSessionMiddleware::class,
    ],
];
```

### 1.3 Add Routes

Add routes to your route files:

```php
// routes/web.php
use App\Http\Controllers\ConfirmDeviceController;

Route::middleware(['auth'])->group(function () {
    Route::get('/confirm-device', [ConfirmDeviceController::class, 'show'])->name('confirm-device');
    Route::post('/confirm-device', [ConfirmDeviceController::class, 'confirm']);
});
```

```php
// routes/api.php
use App\Http\Controllers\Api\SessionDataController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/session-data', [SessionDataController::class, 'index']);
});
```

### 1.4 Integrate with Login Controller

Modify your login controller to use the concurrent session manager:

```php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php
use App\Services\ConcurrentSessionManager;

public function store(LoginRequest $request, ConcurrentSessionManager $sessionManager)
{
    $request->authenticate();
    $request->session()->regenerate();

    $user = Auth::user();
    
    // Check for concurrent sessions
    $hasConcurrentSessions = $sessionManager->detectAndNotifyConcurrentSessions(
        $user->id,
        $request->session()->getId(),
        [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]
    );

    if ($hasConcurrentSessions) {
        // Flag this session as awaiting confirmation
        session([
            'awaiting_confirmation' => true,
            'new_login_at' => now()->toDateTimeString(),
        ]);

        // Redirect to confirmation page
        return redirect()->route('confirm-device');
    }

    // Normal login flow
    return redirect()->intended($this->redirectPath($user));
}

private function redirectPath($user): string
{
    if ($user->hasRole('teacher')) {
        return route('teacher.dashboard');
    } elseif ($user->hasRole('student')) {
        return route('student.dashboard');
    }
    
    return '/dashboard';
}
```

---

## 🎨 Step 2: Frontend Setup

### 2.1 Copy Frontend Files

Copy the following files to your Vue.js project:

```bash
# Components
cp frontend/components/ConcurrentSessionDialog.vue resources/js/components/
cp frontend/components/SessionMonitor.vue resources/js/components/
cp frontend/components/SessionDisconnectedAlert.vue resources/js/components/

# Composables
cp frontend/composables/useSessionMonitor.ts resources/js/composables/

# Pages
cp frontend/pages/auth/ConfirmDevice.vue resources/js/pages/auth/

# Types
cp frontend/types/session.d.ts resources/js/types/
```

### 2.2 Install Dependencies

Install required npm packages:

```bash
npm install laravel-echo pusher-js
npm install lucide-vue-next  # For icons
npm install @inertiajs/vue3  # If not already installed
npm install vue-i18n         # If using translations
```

### 2.3 Configure Laravel Echo

Set up Laravel Echo in your main JavaScript file:

```typescript
// resources/js/bootstrap.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        },
    },
});
```

### 2.4 Add to Layout

Add the SessionMonitor component to your main layout:

```vue
<!-- resources/js/layouts/AppLayout.vue -->
<template>
    <div>
        <!-- Your existing layout content -->
        <nav>...</nav>
        <main>
            <slot />
        </main>
        
        <!-- Add this component -->
        <SessionMonitor />
    </div>
</template>

<script setup lang="ts">
import SessionMonitor from '@/components/SessionMonitor.vue';
</script>
```

### 2.5 Pass Session ID to Frontend

Make sure your Inertia middleware passes the session ID:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user(),
        ],
        'sessionId' => $request->session()->getId(), // Add this line
    ]);
}
```

---

## 🗄️ Step 3: Database Setup

### 3.1 Ensure Session Driver

Make sure you're using the database session driver:

```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'database'),
```

### 3.2 Create Sessions Table

If you don't have a sessions table, create it:

```bash
php artisan session:table
php artisan migrate
```

### 3.3 Add Session Tracking (Optional)

If you want additional session tracking, create a migration:

```php
// database/migrations/add_session_tracking_columns.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
            $table->string('device_name')->nullable();
            $table->boolean('is_trusted')->default(false);
        });
    }

    public function down()
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'device_name', 'is_trusted']);
        });
    }
};
```

---

## ⚙️ Step 4: Configuration

### 4.1 Environment Variables

Add these to your `.env` file:

```env
# Broadcasting
BROADCAST_DRIVER=pusher

# Pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster

# Vite (for frontend)
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### 4.2 Broadcasting Configuration

Configure broadcasting in `config/broadcasting.php`:

```php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ],
    ],
],
```

### 4.3 Queue Configuration

For better performance, use queues for broadcasting:

```env
QUEUE_CONNECTION=database
```

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

---

## 🎨 Step 5: UI Components Setup

### 5.1 Shadcn/UI Components

If you're using Shadcn/UI, make sure you have these components:

```bash
# Install required UI components
npx shadcn-vue@latest add dialog
npx shadcn-vue@latest add button
npx shadcn-vue@latest add card
npx shadcn-vue@latest add alert
```

### 5.2 Alternative UI Libraries

If you're not using Shadcn/UI, you can adapt the components to use:

- **Headless UI**: Replace Dialog components
- **Element Plus**: Use ElDialog, ElButton, ElCard
- **Vuetify**: Use VDialog, VBtn, VCard
- **Custom**: Create your own modal components

Example with Headless UI:

```vue
<!-- Replace Shadcn Dialog with Headless UI -->
<template>
    <TransitionRoot :show="open" as="template">
        <Dialog as="div" class="relative z-10">
            <DialogPanel class="...">
                <!-- Your content -->
            </DialogPanel>
        </Dialog>
    </TransitionRoot>
</template>

<script setup lang="ts">
import { Dialog, DialogPanel, TransitionRoot } from '@headlessui/vue';
</script>
```

---

## 🌐 Step 6: Translations (Optional)

### 6.1 Add Translation Keys

If using vue-i18n, add translation keys:

```json
// resources/js/i18n/en.json
{
    "auth": {
        "concurrentSession": {
            "title": "New Login Detected",
            "description": "Someone signed in to your account from another device.",
            "currentDevice": "Current Device",
            "otherDevices": "Other Active Sessions",
            "keepCurrent": "Disconnect Other Sessions",
            "keepOther": "Stay on Other Device",
            "warning": "Choose which device you want to keep active."
        }
    }
}
```

### 6.2 Configure i18n

Set up vue-i18n in your app:

```typescript
// resources/js/app.ts
import { createI18n } from 'vue-i18n';
import en from './i18n/en.json';

const i18n = createI18n({
    locale: 'en',
    messages: { en }
});

createInertiaApp({
    // ... other config
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n) // Add this
            .mount(el);
    },
});
```

---

## 🧪 Step 7: Testing

### 7.1 Manual Testing

1. **Basic Flow Test:**
   ```bash
   # Terminal 1: Start Laravel
   php artisan serve
   
   # Terminal 2: Start queue worker
   php artisan queue:work
   
   # Terminal 3: Start Vite
   npm run dev
   ```

2. **Test Concurrent Sessions:**
   - Open browser A, login to your app
   - Open browser B (or incognito), login with same account
   - Browser A should show the dialog
   - Test both "Keep Current" and "Keep Other" options

### 7.2 Automated Testing

Create feature tests:

```php
// tests/Feature/ConcurrentSessionTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrentSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_session_detection()
    {
        $user = User::factory()->create();
        
        // First login
        $this->actingAs($user)->get('/dashboard');
        $firstSessionId = session()->getId();
        
        // Second login (simulate different device)
        $this->actingAs($user)->post('/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);
        
        // Should be flagged for confirmation
        $this->assertTrue(session('awaiting_confirmation'));
    }

    public function test_session_data_api()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/api/session-data');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'currentDevice' => ['ip_address', 'user_agent'],
                    'otherDevices'
                ]);
    }
}
```

---

## 🔧 Step 8: Customization

### 8.1 Styling Customization

Customize the dialog appearance:

```vue
<!-- In ConcurrentSessionDialog.vue -->
<DialogContent class="sm:max-w-2xl custom-dialog-style">
    <!-- Add your custom classes -->
</DialogContent>

<style scoped>
.custom-dialog-style {
    @apply bg-gradient-to-br from-white to-gray-50;
    @apply border-2 border-blue-200;
    @apply shadow-2xl;
}
</style>
```

### 8.2 Device Detection Customization

Enhance device detection in `ConcurrentSessionManager`:

```php
public function parseUserAgent(?string $userAgent): string
{
    if (!$userAgent) {
        return 'Unknown device';
    }

    // Add more sophisticated detection
    if (str_contains($userAgent, 'Mobile')) {
        return $this->parseMobileDevice($userAgent);
    }
    
    if (str_contains($userAgent, 'Tablet')) {
        return $this->parseTabletDevice($userAgent);
    }
    
    return $this->parseDesktopDevice($userAgent);
}
```

### 8.3 Event Customization

Add custom events for analytics:

```php
// In ConcurrentSessionManager
public function terminateOtherSessions(int $userId, string $currentSessionId, string $reason = 'user_choice'): void
{
    // ... existing logic
    
    // Add custom event
    event(new SessionsTerminated($userId, $terminatedCount, $reason));
}
```

---

## 🚨 Step 9: Troubleshooting

### 9.1 Common Issues

**Dialog doesn't appear:**
```bash
# Check WebSocket connection
# In browser console:
window.Echo.connector.pusher.connection.state
# Should be "connected"

# Check Laravel logs
tail -f storage/logs/laravel.log

# Test Pusher connection
php artisan tinker
>>> broadcast(new App\Events\NewLoginDetected(1, 'session-123', []));
```

**API returns 401:**
```php
// Check middleware order in Kernel.php
// Make sure 'web' middleware comes before 'auth'
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/session-data', [SessionDataController::class, 'index']);
});
```

**Sessions not detected:**
```bash
# Check session driver
php artisan config:show session.driver
# Should be "database"

# Check sessions table
php artisan tinker
>>> DB::table('sessions')->count()
```

### 9.2 Debug Mode

Enable debug logging:

```php
// In ConcurrentSessionManager
use Illuminate\Support\Facades\Log;

public function detectAndNotifyConcurrentSessions(int $userId, string $newSessionId, array $deviceInfo): bool
{
    Log::info('Checking concurrent sessions', [
        'user_id' => $userId,
        'new_session_id' => $newSessionId,
        'device_info' => $deviceInfo
    ]);
    
    // ... rest of method
}
```

---

## ✅ Step 10: Deployment

### 10.1 Production Checklist

- [ ] Environment variables configured
- [ ] Queue worker running (`php artisan queue:work`)
- [ ] WebSocket service running (Pusher/Soketi)
- [ ] Session table exists and is accessible
- [ ] Middleware registered correctly
- [ ] Routes added to both web.php and api.php
- [ ] Frontend assets built (`npm run build`)
- [ ] SessionMonitor added to layout

### 10.2 Performance Optimization

```php
// Cache session queries
// In ConcurrentSessionManager
public function getOtherSessions(int $userId, string $currentSessionId): Collection
{
    return Cache::remember(
        "user_sessions_{$userId}_{$currentSessionId}",
        60, // 1 minute
        fn() => DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->get()
    );
}
```

### 10.3 Monitoring

Add monitoring for production:

```php
// Monitor concurrent session events
Log::channel('concurrent_sessions')->info('Session conflict resolved', [
    'user_id' => $userId,
    'choice' => $choice,
    'sessions_terminated' => $count
]);
```

---

## 🎉 Completion

After following this guide, you should have:

✅ **Backend**: Controllers, middleware, services, and events  
✅ **Frontend**: Components, composables, and pages  
✅ **Database**: Session tracking and storage  
✅ **WebSocket**: Real-time communication  
✅ **UI**: Responsive dialog and alerts  
✅ **Testing**: Manual and automated tests  

Your concurrent session management system is now ready for production! 🚀

---

## 📞 Support

If you encounter issues:

1. Check this integration guide step by step
2. Review the main README.md for architecture details
3. Check Laravel and browser console logs
4. Test individual components (API, WebSocket, UI)
5. Verify all dependencies are installed correctly

The system is designed to be robust and handle edge cases gracefully. Most issues are related to configuration or missing dependencies.
