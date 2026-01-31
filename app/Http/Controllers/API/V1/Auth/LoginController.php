<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /**
     * Logs in the user.
     *
     * @bodyParam email string required The email of the user.
     * @bodyParam password string required The password of the user.
     * @bodyParam role string required The role of the user. Can be either customer or contractor.
     */
    public function login(Request $request)
    {

        // return $next($request);
        $validateData = $request->validate([
            'email' => 'required_without:user_name|string',
            'password' => 'required|string',
            // 'user_name' => 'required_without:email|string',
            // 'role' => 'required|in:customer,contractor',
        ]);
        try {
            if (filter_var(isset($validateData['email']) ? $validateData['email'] : null, FILTER_VALIDATE_EMAIL) !== false) {
                $user = User::withTrashed()->where('email', $validateData['email'])->first();
                if (empty($user)) {
                    return Helper::jsonErrorResponse('No account found with this email.', 422, ['email' => 'No account found with this email.']);
                }
                if ($user->trashed()) {
                    return Helper::jsonErrorResponse(
                        'Your account has been deactivated. Please contact support.',
                        403,
                        ['email' => 'Your account has been deactivated by the administrator.']
                    );
                }
            }
            if (isset($validateData['user_name'])) {
                $user = User::withTrashed()->where('user_name', $validateData['user_name'])->first();
                if (empty($user)) {
                    return Helper::jsonErrorResponse('No account found with this user name.', 422, ['user_name' => 'No account found with this user name.']);
                }
                if ($user->trashed()) {
                    return Helper::jsonErrorResponse(
                        'Your account has been deactivated. Please contact support.',
                        403,
                        ['user_name' => 'Your account has been deactivated by the administrator.']
                    );
                }
            }
            if ($user && empty($user->email_verified_at)) {
                return Helper::jsonErrorResponse('Your email address has not been verified yet. Please check your inbox for the verification email and verify your account.', 403);
            }
            // if(($user->role==='admin')){
            //     return Helper::jsonErrorResponse('Admin cannot login in user panel', 422, ['login' => 'Admin cannot login in user panel.']);
            // }

            // ! Check the password
            if (! Hash::check($request->password, $user->password)) {
                return Helper::jsonErrorResponse('Invalid password', 401);
            }
            
            // * Track login information
            $ipAddress = $request->ip();
            $user->last_login_at = now();
            $user->login_count = ($user->login_count ?? 0) + 1;
            $user->last_login_ip = $ipAddress;

            $geoData = $this->getGeoFromIp($ipAddress);
            $user->location = $geoData['location'];
            $user->timezone = $geoData['timezone'];
            $user->save();
            
            // * Generate token if email is verified
            $token = auth('api')->login($user);

            return response()->json([
                'status' => true,
                'message' => 'User logged in successfully.',
                'code' => 200,
                'token_type' => 'bearer',
                'token' => $token,
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'data' => auth('api')->user(),
            ], 200);
        } catch (Exception $e) {
            Log::error('LoginController::Login'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Refreshes the JWT access token for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception If an error occurs during token refresh.
     */
    public function refreshToken()
    {
        try {
            $refreshToken = auth('api')->refresh();

            return response()->json([
                'status' => true,
                'message' => 'Access token refreshed successfully.',
                'code' => 200,
                'token_type' => 'bearer',
                'token' => $refreshToken,
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'data' => auth('api')->user(),
            ]);
        } catch (Exception $e) {
            Log::error('LoginController::refreshToken'.$e->getMessage());

            return Helper::jsonErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get location and timezone from IP address using ip-api.com
     *
     * @param  string  $ip
     * @return array{location: string|null, timezone: string|null}
     */
    private function getGeoFromIp(string $ip): array
    {
        $result = ['location' => null, 'timezone' => null];

        try {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $result['location'] = 'Local';

                return $result;
            }

            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,timezone");

            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    $locationParts = array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null,
                    ]);
                    $result['location'] = ! empty($locationParts) ? implode(', ', $locationParts) : null;
                    $result['timezone'] = $data['timezone'] ?? null;
                }
            }
        } catch (Exception $e) {
            Log::error('LoginController::getGeoFromIp - '.$e->getMessage());
        }

        return $result;
    }
}
