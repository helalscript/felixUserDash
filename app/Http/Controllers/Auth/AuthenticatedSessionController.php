<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        if ($user->role !== 'admin') {
            return redirect('/')->with('error', 'You are not authorized to access the admin panel.');
        }
        
        // Track login information
        $ipAddress = $request->ip();
        $user->last_login_at = now();
        $user->login_count = ($user->login_count ?? 0) + 1;
        $user->last_login_ip = $ipAddress;
        $user->location = $this->getLocationFromIp($ipAddress);
        $user->save();
        
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'You are logged in successfully.');
        // return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Get location from IP address
     * 
     * @param string $ip
     * @return string|null
     */
    private function getLocationFromIp(string $ip): ?string
    {
        try {
            // Skip local/private IPs
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'Local';
            }

            // Use a free IP geolocation service (ip-api.com)
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city");
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    $locationParts = array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null,
                    ]);
                    return !empty($locationParts) ? implode(', ', $locationParts) : null;
                }
            }
        } catch (\Exception $e) {
            // Silently fail - location is optional
        }

        return null;
    }
}
