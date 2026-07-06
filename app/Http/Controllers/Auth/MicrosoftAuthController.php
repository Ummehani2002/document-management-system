<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;

class MicrosoftAuthController extends Controller
{
    /** @var list<string> */
    protected array $loginScopes = [
        'openid',
        'profile',
        'email',
        'offline_access',
        'User.Read',
    ];

    /** @var list<string> */
    protected array $mailScopes = [
        'openid',
        'offline_access',
        'User.Read',
        'Mail.Send',
    ];

    public function redirect(): RedirectResponse
    {
        if (! $this->azureConfigured()) {
            return redirect()->route('login')->withErrors([
                'microsoft' => 'Microsoft sign-in is not configured yet. Ask your administrator to set AZURE_CLIENT_ID and related settings.',
            ]);
        }

        session(['microsoft_auth_intent' => 'login']);

        return $this->azureDriver($this->loginScopes)
            ->with(['prompt' => 'login'])
            ->redirect();
    }

    public function redirectForMail(): RedirectResponse
    {
        if (! $this->azureConfigured()) {
            return redirect()->back()->withErrors([
                'microsoft' => 'Microsoft mail permission is not configured yet.',
            ]);
        }

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        session([
            'microsoft_auth_intent' => 'mail',
            'microsoft_mail_return_url' => url()->previous() ?: route('documents.search'),
        ]);

        return $this->azureDriver($this->mailScopes)
            ->with(['prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->azureConfigured()) {
            return redirect()->route('login')->withErrors([
                'microsoft' => 'Microsoft sign-in is not configured yet.',
            ]);
        }

        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors([
                'microsoft' => (string) ($request->input('error_description') ?: $request->input('error')),
            ]);
        }

        if (! $request->filled('code')) {
            return redirect()->route('login')->withErrors([
                'microsoft' => 'Microsoft sign-in was cancelled or did not complete. Please try again.',
            ]);
        }

        try {
            $azureUser = $this->azureDriver($this->loginScopes)->user();
        } catch (InvalidStateException $e) {
            Log::warning('Microsoft sign-in state mismatch', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'microsoft' => 'Your sign-in session expired. Enter your company email and click Sign in with Microsoft again.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Microsoft sign-in callback failed', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            $detail = trim($e->getMessage());
            $message = config('app.debug') && $detail !== ''
                ? 'Microsoft sign-in failed: '.$detail
                : 'Microsoft sign-in failed. Please try again.';

            return redirect()->route('login')->withErrors([
                'microsoft' => $message,
            ]);
        }

        $intent = (string) session()->pull('microsoft_auth_intent', 'login');

        if ($intent === 'mail') {
            return $this->handleMailCallback($azureUser);
        }

        return $this->handleLoginCallback($azureUser);
    }

    protected function handleLoginCallback(SocialiteUser $azureUser): RedirectResponse
    {
        $email = strtolower(trim((string) $azureUser->getEmail()));
        if ($email === '' && method_exists($azureUser, 'getRaw')) {
            $raw = $azureUser->getRaw();
            if (is_array($raw)) {
                $email = strtolower(trim((string) ($raw['mail'] ?? $raw['userPrincipalName'] ?? '')));
            }
        }
        if ($email === '') {
            return redirect()->route('login')->withErrors([
                'microsoft' => 'Your Microsoft account did not return an email address.',
            ]);
        }

        $user = User::query()
            ->where('azure_id', $azureUser->getId())
            ->orWhere('email', $email)
            ->first();

        if ($user === null) {
            $user = User::create([
                'name' => $azureUser->getName() ?: $email,
                'username' => $email,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        $this->storeAzureTokens($user, $azureUser);
        $user->save();

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    protected function handleMailCallback(SocialiteUser $azureUser): RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if ((string) $user->azure_id !== (string) $azureUser->getId()) {
            return redirect()->route('login')->withErrors([
                'microsoft' => 'Microsoft account mismatch. Sign in with the same work account you use in this app.',
            ]);
        }

        $this->storeAzureTokens($user, $azureUser);
        $user->azure_mail_consent_at = now();
        $user->save();

        $returnUrl = session()->pull('microsoft_mail_return_url', route('documents.search'));

        return redirect()->to($returnUrl)->with('success', 'Microsoft mail permission granted. You can share files from your email now.');
    }

    protected function storeAzureTokens(User $user, SocialiteUser $azureUser): void
    {
        $user->fill([
            'azure_id' => (string) $azureUser->getId(),
            'name' => $azureUser->getName() ?: $user->name,
            'email' => strtolower(trim((string) ($azureUser->getEmail() ?: $user->email))),
            'username' => $user->username ?: strtolower(trim((string) ($azureUser->getEmail() ?: $user->email))),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'azure_access_token' => (string) $azureUser->token,
            'azure_refresh_token' => $azureUser->refreshToken ?: $user->azure_refresh_token,
            'azure_token_expires_at' => now()->addSeconds((int) ($azureUser->expiresIn ?? 3600)),
        ]);
    }

  protected function azureDriver(array $scopes = []): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('azure')
            ->redirectUrl((string) config('services.azure.redirect'))
            ->with([
                'response_mode' => 'query',
                'response_type' => 'code',
            ]);

        if ($scopes !== []) {
            $driver->scopes($scopes);
        }

        return $driver;
    }

    protected function azureConfigured(): bool
    {
        return (string) config('services.azure.client_id') !== ''
            && (string) config('services.azure.client_secret') !== ''
            && (string) config('services.azure.redirect') !== '';
    }
}
