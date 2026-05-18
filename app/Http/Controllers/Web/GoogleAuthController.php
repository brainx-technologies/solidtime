<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        /** @var RedirectResponse $response */
        $response = Socialite::driver('google')
            ->redirect();

        return $response;
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();
        $email = mb_strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return redirect()->route('login')->with('message', 'Google account has no email address.');
        }

        $allowedDomain = mb_strtolower((string) config('services.google.allowed_domain', 'brainxtech.com'));
        $atPosition = mb_strrpos($email, '@');
        if ($atPosition === false) {
            return redirect()->route('login')->with('message', 'Google account email is invalid.');
        }

        $emailDomain = mb_substr($email, $atPosition + 1);
        if ($emailDomain !== $allowedDomain) {
            return redirect()->route('login')->with('message', 'Only @'.$allowedDomain.' Google accounts are allowed.');
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $email)
            ->where('is_placeholder', '=', false)
            ->first();

        if ($user === null) {
            return redirect()->route('login')->with('message', 'Account not found. Please contact your administrator.');
        }

        Auth::login($user, true);

        return redirect()->intended('/dashboard');
    }
}
