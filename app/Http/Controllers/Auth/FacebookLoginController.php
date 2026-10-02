<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class FacebookLoginController
{
    public function redirectToFacebook(): RedirectResponse
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback(Request $request): RedirectResponse
    {
        $facebookUser = Socialite::driver('facebook')->user();

        $user = User::firstOrCreate(
            ['email' => $facebookUser->getEmail()],
            [
                'name' => $facebookUser->getName() ?: 'Facebook User',
                'password' => bcrypt(Str::random(16)),
                'provider' => 'facebook',
                'provider_id' => $facebookUser->getId(),
                'avatar' => $facebookUser->getAvatar(),
                'email_verified_at' => now(),
            ],
        );

        $user->update([
            'provider' => 'facebook',
            'provider_id' => $facebookUser->getId(),
            'avatar' => $facebookUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        Auth::login($user, true);

        return redirect()->intended($user->is_admin ? '/bookings' : '/book-a-service');
    }
}
