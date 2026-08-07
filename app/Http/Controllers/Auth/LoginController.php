<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Google\Client as GoogleClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Throwable;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        app()->setLocale(request()->se_lang);
        $this->middleware('guest')->except('logout');
    }

    /**
     * Send the response after the user was authenticated.
     */
    protected function sendLoginResponse(Request $request): RedirectResponse
    {
        $user = $this->guard()->user();

        if ((int) ($user->status ?? User::INACTIVE) !== User::ACTIVE) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account is inactive.',
            ]);
        }

        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($request->se_lang) {
            $request->user()->update(['language' => $request->se_lang]);
            \Session::put('languageName', $request->se_lang);
        }

        $this->redirectTo = redirectToDashboard();

        if (! isset($request->remember)) {
            return $this->authenticated($request, $this->guard()->user())
                ?: redirect()->intended($this->redirectPath())
                    ->withCookie(\Cookie::forget('email'))
                    ->withCookie(\Cookie::forget('password'))
                    ->withCookie(\Cookie::forget('remember'));
        }

        return $this->authenticated($request, $this->guard()->user())
            ?: redirect()->intended($this->redirectPath())
                ->withCookie(\Cookie::make('email', $request->email, 3600))
                ->withCookie(\Cookie::make('remember', 1, 3600));
    }

    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($response = $this->loggedOut($request)) {
            return $response;
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/login');
    }

    /**
     * Redirect the guest to Google's OAuth consent screen.
     */
    public function redirectToGoogle(Request $request): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google login is not configured yet.',
            ]);
        }

        $client = $this->googleClient();
        $state = bin2hex(random_bytes(32));

        $request->session()->put('google_oauth_state', $state);
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    /**
     * Authenticate an existing HMS user using their verified Google email.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google login session expired. Please try again.',
            ]);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google login was cancelled or denied.',
            ]);
        }

        try {
            $client = $this->googleClient();
            $token = $client->fetchAccessTokenWithAuthCode((string) $request->query('code'));

            if (isset($token['error'])) {
                throw new \RuntimeException((string) ($token['error_description'] ?? $token['error']));
            }

            if (empty($token['id_token'])) {
                throw new \RuntimeException('Google did not return an ID token.');
            }

            $payload = $client->verifyIdToken($token['id_token']);
            $email = is_array($payload) ? ($payload['email'] ?? null) : null;
            $googleSub = (string) (is_array($payload) ? ($payload['sub'] ?? '') : '');
            $googleAud = is_array($payload) ? ($payload['aud'] ?? null) : null;
            $googleIss = is_array($payload) ? ($payload['iss'] ?? null) : null;

            if (empty($googleAud)
                || ! hash_equals((string) config('services.google.client_id'), (string) $googleAud)
                || ! in_array((string) $googleIss, ['https://accounts.google.com', 'accounts.google.com'], true)) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Google login token is invalid.',
                ]);
            }

            if (! $email || empty($payload['email_verified']) || empty($googleSub)) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Google did not return a verified email address.',
                ]);
            }

            $user = User::query()
                ->where('google_id', $googleSub)
                ->first();

            if (! $user) {
                return redirect()->route('login')->withErrors([
                    'email' => 'No HMS account is linked to this Google identity.',
                ]);
            }

            if (! $user->email) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Google login is not linked to this HMS account.',
                ]);
            }

            if ($user->google_issuer === null || $user->google_issuer === '') {
                $user->forceFill(['google_issuer' => $googleIss]);
                $user->save();
            } elseif (! hash_equals((string) $user->google_issuer, (string) $googleIss)) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Google account mismatch.',
                ]);
            }

            if (! hash_equals((string) $user->email, (string) $email)) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Google account email mismatch.',
                ]);
            }

            if ((int) $user->status !== User::ACTIVE) {
                return redirect()->route('login')->withErrors([
                    'email' => 'This account is inactive.',
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(redirectToDashboard());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'email' => 'Google login failed. Please try again.',
            ]);
        }
    }

    private function googleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));
        $client->setScopes(['openid', 'email', 'profile']);
        $client->setAccessType('online');
        $client->setPrompt('select_account');

        return $client;
    }
}
