<?php

namespace App\Tenants\Modules\IAM\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OTPService
{
    /**
     * Generate a 6-digit OTP and store it in cache for 5 minutes.
     */
    public function generateOTP(string $identifier): string
    {
        $otp = (string) rand(100000, 999999);
        
        Cache::put("otp_{$identifier}", $otp, now()->addMinutes(5));
        
        // In a real system, this would be sent via Email/SMS
        Log::info("MFA OTP for {$identifier}: {$otp}");
        
        return $otp;
    }

    /**
     * Verify the provided OTP.
     */
    public function verifyOTP(string $identifier, string $otp): bool
    {
        $cachedOtp = Cache::get("otp_{$identifier}");

        if ($cachedOtp && $cachedOtp === $otp) {
            Cache::forget("otp_{$identifier}");
            return true;
        }

        return false;
    }
}
