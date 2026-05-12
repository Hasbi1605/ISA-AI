<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class UserFacingError
{
    public static function message(\Throwable $throwable, string $fallback = 'Maaf, terjadi kendala. Silakan coba lagi.'): string
    {
        if ($throwable instanceof ValidationException) {
            return $throwable->validator->errors()->first() ?: $fallback;
        }

        $message = trim($throwable->getMessage());

        if ($message === '') {
            return $fallback;
        }

        $technicalPatterns = [
            '/SQLSTATE\[/i',
            '/cURL error/i',
            '/Connection refused/i',
            '/Stack trace/i',
            '/\/Users\//i',
            '/vendor\/laravel/i',
            '/client_secret|access_token|refresh_token|api[_ -]?key/i',
            '/exception|runtimeexception|invalidargumentexception/i',
        ];

        foreach ($technicalPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return $fallback;
            }
        }

        return mb_strlen($message) > 180 ? $fallback : $message;
    }
}
