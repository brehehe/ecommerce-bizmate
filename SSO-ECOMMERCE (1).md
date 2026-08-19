# SSO Implementation Guide: E-commerce (OAuth Client)

## Overview

E-commerce (PadelShop) berfungsi sebagai **OAuth2 Client** yang menggunakan PadelGigs sebagai identity provider. User dapat login menggunakan "Login with PadelGigs" atau register secara lokal.

## Architecture Role

```
┌─────────────────────────────────────────────────────────────┐
│                      PadelShop.com                          │
│                    (OAuth2 Client)                          │
│                                                             │
│  ┌─────────────────┐    ┌─────────────────────────────────┐ │
│  │  OAuth Client   │    │  Local Authentication          │ │
│  │                 │    │                                 │ │
│  │  - Login with   │    │  - Register locally             │ │
│  │    PadelGigs    │    │  - Login with email/password   │ │
│  │  - Token mgmt   │    │  - Link PadelGigs account      │ │
│  └─────────────────┘    └─────────────────────────────────┘ │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Customers Table                                        ││
│  │  - id (ULID)                                            ││
│  │  - padelgigs_user_id (nullable, untuk linked accounts)  ││
│  │  - name, email, password (nullable untuk OAuth-only)    ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

## Database Setup

### Migration: Create Customers Table

```php
// database/migrations/xxxx_create_customers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Link ke PadelGigs user (nullable untuk local customers)
            $table->string('padelgigs_user_id', 26)->nullable()->unique();

            // Basic info
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();

            // Password nullable untuk OAuth-only customers
            $table->string('password')->nullable();

            // OAuth tokens (encrypted)
            $table->text('padelgigs_access_token')->nullable();
            $table->text('padelgigs_refresh_token')->nullable();
            $table->timestamp('padelgigs_token_expires_at')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

### Customer Model

```php
// app/Models/Customer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

class Customer extends Authenticatable
{
    use HasUlids, Notifiable;

    protected $fillable = [
        'padelgigs_user_id',
        'name',
        'email',
        'phone',
        'password',
        'padelgigs_access_token',
        'padelgigs_refresh_token',
        'padelgigs_token_expires_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'padelgigs_access_token',
        'padelgigs_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'padelgigs_token_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Encrypt tokens before storing
    public function setPadelgigsAccessTokenAttribute(?string $value): void
    {
        $this->attributes['padelgigs_access_token'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    public function getPadelgigsAccessTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setPadelgigsRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['padelgigs_refresh_token'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    public function getPadelgigsRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Helper methods
    public function isLinkedToPadelgigs(): bool
    {
        return $this->padelgigs_user_id !== null;
    }

    public function hasLocalPassword(): bool
    {
        return $this->password !== null;
    }

    public function isPadelgigsTokenExpired(): bool
    {
        if (!$this->padelgigs_token_expires_at) {
            return true;
        }

        // Consider expired 5 minutes before actual expiry
        return $this->padelgigs_token_expires_at->subMinutes(5)->isPast();
    }
}
```

## Environment Configuration

```env
# .env

# PadelGigs OAuth Configuration
PADELGIGS_OAUTH_URL=https://padelgigs.com
PADELGIGS_OAUTH_CLIENT_ID=9f4a2b3c-1234-5678-9abc-def012345678
PADELGIGS_OAUTH_CLIENT_SECRET=abc123def456...
PADELGIGS_OAUTH_REDIRECT_URI=https://padelshop.com/auth/padelgigs/callback
```

```php
// config/services.php

return [
    // ... other services

    'padelgigs' => [
        'url' => env('PADELGIGS_OAUTH_URL', 'https://padelgigs.com'),
        'client_id' => env('PADELGIGS_OAUTH_CLIENT_ID'),
        'client_secret' => env('PADELGIGS_OAUTH_CLIENT_SECRET'),
        'redirect' => env('PADELGIGS_OAUTH_REDIRECT_URI'),
    ],
];
```

## OAuth Service

```php
// app/Services/PadelGigsOAuthService.php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PadelGigsOAuthService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;

    public function __construct()
    {
        $this->baseUrl = config('services.padelgigs.url');
        $this->clientId = config('services.padelgigs.client_id');
        $this->clientSecret = config('services.padelgigs.client_secret');
        $this->redirectUri = config('services.padelgigs.redirect');
    }

    /**
     * Generate authorization URL with PKCE
     */
    public function getAuthorizationUrl(array $scopes = ['openid', 'profile', 'email']): array
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Store in session for verification
        session([
            'padelgigs_oauth_state' => $state,
            'padelgigs_oauth_code_verifier' => $codeVerifier,
        ]);

        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return [
            'url' => "{$this->baseUrl}/oauth/authorize?{$query}",
            'state' => $state,
        ];
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code, string $state): array
    {
        // Verify state
        $storedState = session('padelgigs_oauth_state');
        if ($state !== $storedState) {
            throw new \RuntimeException('Invalid OAuth state');
        }

        $codeVerifier = session('padelgigs_oauth_code_verifier');

        // Clear session data
        session()->forget(['padelgigs_oauth_state', 'padelgigs_oauth_code_verifier']);

        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token", [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to exchange code for tokens: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token", [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'scope' => 'openid profile email',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to refresh token: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get user info from PadelGigs
     */
    public function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/api/user");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to get user info: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get valid access token for a customer (refresh if needed)
     */
    public function getValidAccessToken(Customer $customer): ?string
    {
        if (!$customer->isLinkedToPadelgigs()) {
            return null;
        }

        if (!$customer->isPadelgigsTokenExpired()) {
            return $customer->padelgigs_access_token;
        }

        // Token expired, try to refresh
        if (!$customer->padelgigs_refresh_token) {
            return null;
        }

        try {
            $tokens = $this->refreshToken($customer->padelgigs_refresh_token);

            $customer->update([
                'padelgigs_access_token' => $tokens['access_token'],
                'padelgigs_refresh_token' => $tokens['refresh_token'] ?? $customer->padelgigs_refresh_token,
                'padelgigs_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            return $tokens['access_token'];
        } catch (\Throwable $e) {
            // Refresh failed, token is invalid
            $customer->update([
                'padelgigs_access_token' => null,
                'padelgigs_refresh_token' => null,
                'padelgigs_token_expires_at' => null,
            ]);

            return null;
        }
    }

    /**
     * Generate PKCE code challenge
     */
    protected function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }
}
```

## Authentication Controller

```php
// app/Http/Controllers/Auth/PadelGigsAuthController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PadelGigsOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PadelGigsAuthController extends Controller
{
    public function __construct(
        protected PadelGigsOAuthService $oauthService
    ) {}

    /**
     * Redirect to PadelGigs for authorization
     */
    public function redirect(): RedirectResponse
    {
        $auth = $this->oauthService->getAuthorizationUrl();

        return redirect($auth['url']);
    }

    /**
     * Handle callback from PadelGigs
     */
    public function callback(Request $request): RedirectResponse
    {
        // Check for errors
        if ($request->has('error')) {
            return redirect()->route('login')
                ->withErrors(['padelgigs' => $request->input('error_description', 'Authorization failed')]);
        }

        try {
            // Exchange code for tokens
            $tokens = $this->oauthService->exchangeCodeForTokens(
                $request->input('code'),
                $request->input('state')
            );

            // Get user info
            $userInfo = $this->oauthService->getUserInfo($tokens['access_token']);

            // Find or create customer
            $customer = $this->findOrCreateCustomer($userInfo, $tokens);

            // Login customer
            Auth::guard('customer')->login($customer, true);

            return redirect()->intended(route('dashboard'));

        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->withErrors(['padelgigs' => 'Failed to authenticate with PadelGigs']);
        }
    }

    /**
     * Link existing customer to PadelGigs account
     */
    public function link(): RedirectResponse
    {
        // Store current customer ID to link after callback
        session(['padelgigs_link_customer_id' => Auth::guard('customer')->id()]);

        $auth = $this->oauthService->getAuthorizationUrl();

        return redirect($auth['url']);
    }

    /**
     * Handle link callback
     */
    public function linkCallback(Request $request): RedirectResponse
    {
        $customerId = session('padelgigs_link_customer_id');
        session()->forget('padelgigs_link_customer_id');

        if (!$customerId) {
            return redirect()->route('profile')
                ->withErrors(['padelgigs' => 'Invalid linking session']);
        }

        try {
            $tokens = $this->oauthService->exchangeCodeForTokens(
                $request->input('code'),
                $request->input('state')
            );

            $userInfo = $this->oauthService->getUserInfo($tokens['access_token']);

            // Check if PadelGigs account already linked to another customer
            $existingCustomer = Customer::where('padelgigs_user_id', $userInfo['id'])->first();

            if ($existingCustomer && $existingCustomer->id !== $customerId) {
                return redirect()->route('profile')
                    ->withErrors(['padelgigs' => 'This PadelGigs account is already linked to another customer']);
            }

            // Update current customer with PadelGigs link
            $customer = Customer::findOrFail($customerId);
            $customer->update([
                'padelgigs_user_id' => $userInfo['id'],
                'padelgigs_access_token' => $tokens['access_token'],
                'padelgigs_refresh_token' => $tokens['refresh_token'],
                'padelgigs_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            return redirect()->route('profile')
                ->with('success', 'PadelGigs account linked successfully');

        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile')
                ->withErrors(['padelgigs' => 'Failed to link PadelGigs account']);
        }
    }

    /**
     * Unlink PadelGigs account
     */
    public function unlink(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        // Must have local password to unlink
        if (!$customer->hasLocalPassword()) {
            return redirect()->route('profile')
                ->withErrors(['padelgigs' => 'Please set a password before unlinking PadelGigs']);
        }

        $customer->update([
            'padelgigs_user_id' => null,
            'padelgigs_access_token' => null,
            'padelgigs_refresh_token' => null,
            'padelgigs_token_expires_at' => null,
        ]);

        return redirect()->route('profile')
            ->with('success', 'PadelGigs account unlinked');
    }

    /**
     * Find existing customer or create new one
     */
    protected function findOrCreateCustomer(array $userInfo, array $tokens): Customer
    {
        // First, try to find by PadelGigs ID
        $customer = Customer::where('padelgigs_user_id', $userInfo['id'])->first();

        if ($customer) {
            // Update tokens
            $customer->update([
                'padelgigs_access_token' => $tokens['access_token'],
                'padelgigs_refresh_token' => $tokens['refresh_token'],
                'padelgigs_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            return $customer;
        }

        // Try to find by email
        $customer = Customer::where('email', $userInfo['email'])->first();

        if ($customer) {
            // Link existing customer to PadelGigs
            $customer->update([
                'padelgigs_user_id' => $userInfo['id'],
                'padelgigs_access_token' => $tokens['access_token'],
                'padelgigs_refresh_token' => $tokens['refresh_token'],
                'padelgigs_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            return $customer;
        }

        // Create new customer
        return Customer::create([
            'padelgigs_user_id' => $userInfo['id'],
            'name' => $userInfo['name'],
            'email' => $userInfo['email'],
            'phone' => $userInfo['phone'] ?? null,
            'email_verified_at' => isset($userInfo['email_verified_at']) ? now() : null,
            'padelgigs_access_token' => $tokens['access_token'],
            'padelgigs_refresh_token' => $tokens['refresh_token'],
            'padelgigs_token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);
    }
}
```

## Routes

```php
// routes/web.php

use App\Http\Controllers\Auth\PadelGigsAuthController;

Route::middleware('guest:customer')->group(function () {
    // OAuth routes
    Route::get('/auth/padelgigs', [PadelGigsAuthController::class, 'redirect'])
        ->name('auth.padelgigs');
    Route::get('/auth/padelgigs/callback', [PadelGigsAuthController::class, 'callback'])
        ->name('auth.padelgigs.callback');
});

Route::middleware('auth:customer')->group(function () {
    // Account linking
    Route::get('/auth/padelgigs/link', [PadelGigsAuthController::class, 'link'])
        ->name('auth.padelgigs.link');
    Route::get('/auth/padelgigs/link/callback', [PadelGigsAuthController::class, 'linkCallback'])
        ->name('auth.padelgigs.link.callback');
    Route::delete('/auth/padelgigs/unlink', [PadelGigsAuthController::class, 'unlink'])
        ->name('auth.padelgigs.unlink');
});
```

## Auth Configuration

```php
// config/auth.php

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'customer' => [
        'driver' => 'session',
        'provider' => 'customers',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],

    'customers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Customer::class,
    ],
],
```

## Frontend Components (Vue)

### Login Page

```vue
<!-- resources/js/pages/auth/Login.vue -->

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import authRoutes from '@/routes/auth';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(authRoutes.login.url(), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login" />

    <div class="mx-auto max-w-md py-12">
        <div class="rounded-lg border bg-card p-6 shadow-sm">
            <h1 class="text-2xl font-bold">Login</h1>

            <!-- PadelGigs OAuth Button -->
            <a
                :href="authRoutes.padelgigs.url()"
                class="mt-6 flex w-full items-center justify-center gap-2 rounded-md border bg-background px-4 py-2 font-medium transition-colors hover:bg-muted"
            >
                <img
                    src="/images/padelgigs-icon.svg"
                    alt="PadelGigs"
                    class="h-5 w-5"
                />
                Login with PadelGigs
            </a>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-card px-2 text-muted-foreground">
                        Or continue with email
                    </span>
                </div>
            </div>

            <!-- Local Login Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                    />
                </div>

                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                    />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                >
                    Login
                </Button>
            </form>

            <p class="mt-4 text-center text-sm text-muted-foreground">
                Don't have an account?
                <Link
                    :href="authRoutes.register.url()"
                    class="text-primary hover:underline"
                >
                    Register
                </Link>
            </p>
        </div>
    </div>
</template>
```

### Profile - Account Linking Section

```vue
<!-- resources/js/components/profile/PadelGigsLink.vue -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link2, Unlink } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import authRoutes from '@/routes/auth';

defineProps<{
    isLinked: boolean;
    hasPassword: boolean;
}>();

const unlink = () => {
    if (confirm('Are you sure you want to unlink your PadelGigs account?')) {
        router.delete(authRoutes.padelgigs.unlink.url());
    }
};
</script>

<template>
    <div class="rounded-lg border p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img
                    src="/images/padelgigs-icon.svg"
                    alt="PadelGigs"
                    class="h-8 w-8"
                />
                <div>
                    <p class="font-medium">PadelGigs Account</p>
                    <p class="text-sm text-muted-foreground">
                        {{ isLinked ? 'Connected' : 'Not connected' }}
                    </p>
                </div>
            </div>

            <div v-if="isLinked">
                <Button
                    v-if="hasPassword"
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    @click="unlink"
                >
                    <Unlink class="h-4 w-4" />
                    Unlink
                </Button>
                <p v-else class="text-xs text-muted-foreground">
                    Set a password to unlink
                </p>
            </div>
            <a v-else :href="authRoutes.padelgigs.link.url()">
                <Button variant="outline" size="sm" class="gap-1.5">
                    <Link2 class="h-4 w-4" />
                    Connect
                </Button>
            </a>
        </div>
    </div>
</template>
```

## Local Authentication

### Registration Controller

```php
// app/Http/Controllers/Auth/RegisterController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        event(new Registered($customer));

        Auth::guard('customer')->login($customer);

        return redirect()->route('dashboard');
    }
}
```

### Login Controller

```php
// app/Http/Controllers/Auth/LoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check if customer exists and has a password
        $customer = Customer::where('email', $credentials['email'])->first();

        if ($customer && !$customer->hasLocalPassword()) {
            throw ValidationException::withMessages([
                'email' => 'This account uses PadelGigs login. Please use "Login with PadelGigs".',
            ]);
        }

        if (!Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

## Middleware

### Ensure Customer Has Password

```php
// app/Http/Middleware/EnsureCustomerHasPassword.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerHasPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if ($customer && !$customer->hasLocalPassword()) {
            return redirect()->route('profile.password.create')
                ->with('warning', 'Please set a password to continue');
        }

        return $next($request);
    }
}
```

## Testing

### Feature Tests

```php
// tests/Feature/PadelGigsAuthTest.php

use App\Models\Customer;
use App\Services\PadelGigsOAuthService;

test('can redirect to padelgigs for authorization', function () {
    $response = $this->get(route('auth.padelgigs'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('padelgigs.com/oauth/authorize');
});

test('can handle oauth callback and create customer', function () {
    $this->mock(PadelGigsOAuthService::class, function ($mock) {
        $mock->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andReturn([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'expires_in' => 3600,
            ]);

        $mock->shouldReceive('getUserInfo')
            ->once()
            ->andReturn([
                'id' => 'padelgigs-user-123',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '08123456789',
            ]);
    });

    session([
        'padelgigs_oauth_state' => 'test-state',
        'padelgigs_oauth_code_verifier' => 'test-verifier',
    ]);

    $response = $this->get(route('auth.padelgigs.callback', [
        'code' => 'test-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('customers', [
        'padelgigs_user_id' => 'padelgigs-user-123',
        'email' => 'john@example.com',
    ]);

    $this->assertAuthenticated('customer');
});

test('can link padelgigs account to existing customer', function () {
    $customer = Customer::factory()->create([
        'padelgigs_user_id' => null,
        'password' => bcrypt('password'),
    ]);

    $this->mock(PadelGigsOAuthService::class, function ($mock) {
        $mock->shouldReceive('exchangeCodeForTokens')
            ->andReturn([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'expires_in' => 3600,
            ]);

        $mock->shouldReceive('getUserInfo')
            ->andReturn([
                'id' => 'padelgigs-user-123',
                'name' => 'John Doe',
                'email' => $customer->email,
            ]);
    });

    session([
        'padelgigs_link_customer_id' => $customer->id,
        'padelgigs_oauth_state' => 'test-state',
        'padelgigs_oauth_code_verifier' => 'test-verifier',
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->get(route('auth.padelgigs.link.callback', [
            'code' => 'test-code',
            'state' => 'test-state',
        ]));

    $response->assertRedirect(route('profile'));

    $customer->refresh();
    expect($customer->padelgigs_user_id)->toBe('padelgigs-user-123');
});

test('cannot unlink padelgigs without local password', function () {
    $customer = Customer::factory()->create([
        'padelgigs_user_id' => 'padelgigs-user-123',
        'password' => null,
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->delete(route('auth.padelgigs.unlink'));

    $response->assertRedirect(route('profile'));
    $response->assertSessionHasErrors('padelgigs');

    $customer->refresh();
    expect($customer->padelgigs_user_id)->not->toBeNull();
});
```

## Error Handling

### Common OAuth Errors

```php
// app/Exceptions/Handler.php atau exception handling

// Di callback, handle berbagai error scenarios:

// 1. User denied authorization
if ($request->input('error') === 'access_denied') {
    return redirect()->route('login')
        ->with('info', 'You cancelled the PadelGigs authorization');
}

// 2. Invalid state (CSRF)
// Already handled in service

// 3. Network errors
// Wrapped in try-catch

// 4. Token refresh failed
// Service returns null, customer needs to re-authorize
```

## Security Checklist

- [x] PKCE implemented for authorization flow
- [x] State parameter untuk CSRF protection
- [x] Tokens encrypted sebelum disimpan ke database
- [x] Refresh token rotation handled
- [x] Session regenerated setelah login
- [x] Cannot unlink tanpa local password
- [x] Existing email linking dengan konfirmasi
- [x] Rate limiting di auth routes
- [ ] Add OAuth error logging
- [ ] Add suspicious activity detection

## Troubleshooting

### "Invalid OAuth state" error

Session mungkin expired. Coba login ulang.

### Customer tidak bisa login setelah linking

Check apakah customer punya password. OAuth-only customers harus pakai "Login with PadelGigs".

### Token refresh terus failing

PadelGigs mungkin revoke tokens. Customer perlu re-authorize.

## Checklist Deployment

- [ ] Set environment variables untuk PadelGigs OAuth
- [ ] Test OAuth flow di staging
- [ ] Verify HTTPS di redirect URIs
- [ ] Setup customer auth guard
- [ ] Add PadelGigs icon/branding assets
- [ ] Test account linking flow
- [ ] Test token refresh mechanism
- [ ] Add monitoring untuk OAuth failures
- [ ] Document customer support procedures untuk OAuth issues
