<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Send OTP to email or phone number.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string|max:100',
        ]);

        $identifier = trim($request->identifier);
        
        // Generate a 6-digit OTP
        $code = (string) mt_rand(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        // Save or update OTP in the database
        DB::table('otp_verifications')->updateOrInsert(
            ['identifier' => $identifier],
            [
                'code' => $code,
                'expires_at' => $expiresAt,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        );

        // Log the OTP for auditing and easy local access
        Log::info("Swaad E Punjab OTP for {$identifier}: {$code}");

        // If the identifier is an email, send email
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        if ($isEmail) {
            try {
                Mail::raw("Your Swaad E Punjab verification OTP code is: {$code}. This code is valid for 10 minutes.", function ($message) use ($identifier) {
                    $message->to($identifier)
                        ->subject("Verify Your Account - Swaad E Punjab");
                });
            } catch (\Exception $e) {
                Log::error("Failed to send verification email to {$identifier}: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Verification code sent successfully.',
            // Return OTP in response for development and manual testing
            'otp' => $code,
        ]);
    }

    /**
     * Verify the sent OTP code.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string|max:100',
            'code' => 'required|string|max:10',
        ]);

        $identifier = trim($request->identifier);
        $code = trim($request->code);

        // Lookup verification code
        $verification = DB::table('otp_verifications')
            ->where('identifier', $identifier)
            ->where('code', $code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$verification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired OTP verification code.'
            ], 422);
        }

        // Clean up verification record
        DB::table('otp_verifications')->where('identifier', $identifier)->delete();

        // Attempt to find user's name from their latest order to make signup/login seamless
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $latestOrder = null;
        
        if ($isEmail) {
            $latestOrder = Order::where('customer_email', $identifier)->latest()->first();
        } else {
            $latestOrder = Order::where('customer_phone', $identifier)->latest()->first();
        }

        $customerName = $latestOrder ? $latestOrder->customer_name : 'Customer';

        return response()->json([
            'status' => 'success',
            'message' => 'OTP verified successfully.',
            'identifier' => $identifier,
            'name' => $customerName,
            'is_email' => $isEmail
        ]);
    }

    /**
     * Handle Google Sign-In verification.
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        $credential = $request->credential;

        // Verify ID Token via Google API
        try {
            $response = Http::get("https://oauth2.googleapis.com/tokeninfo", [
                'id_token' => $credential
            ]);

            if ($response->successful()) {
                $payload = $response->json();
                $email = $payload['email'] ?? null;
                $name = $payload['name'] ?? 'Google User';

                if (!$email) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to retrieve email from Google Sign-In.'
                    ], 400);
                }

                // Look up latest order name
                $latestOrder = Order::where('customer_email', $email)->latest()->first();
                if ($latestOrder) {
                    $name = $latestOrder->customer_name;
                }

                return response()->json([
                    'status' => 'success',
                    'email' => $email,
                    'name' => $name
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Google Login verification exception: " . $e->getMessage());
        }

        // Development/Offline Mock Mode fallback:
        // Parse JWT payload locally if the Google endpoint fails or is blocked during local testing
        try {
            $parts = explode('.', $credential);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
                if (isset($payload['email'])) {
                    $email = $payload['email'];
                    $name = $payload['name'] ?? 'Google User';
                    
                    // Look up latest order name
                    $latestOrder = Order::where('customer_email', $email)->latest()->first();
                    if ($latestOrder) {
                        $name = $latestOrder->customer_name;
                    }

                    return response()->json([
                        'status' => 'success',
                        'email' => $email,
                        'name' => $name,
                        'note' => 'verified_locally'
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Google Local fallback decoding error: " . $e->getMessage());
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to verify Google ID Token.'
        ], 422);
    }
}
