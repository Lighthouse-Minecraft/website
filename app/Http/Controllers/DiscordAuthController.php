<?php

namespace App\Http\Controllers;

use App\Actions\LinkDiscordAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class DiscordAuthController extends Controller
{
    public function redirect()
    {
        Gate::authorize('link-discord');

        return Socialite::driver('discord')
            ->scopes(['identify', 'guilds.join'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        Gate::authorize('link-discord');

        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Exception $e) {
            Log::warning('Discord OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('settings.discord-account')
                ->with('error', 'Failed to authenticate with Discord. Please try again.');
        }

        $result = LinkDiscordAccount::run($request->user(), [
            'id' => $discordUser->getId(),
            'username' => $discordUser->getNickname() ?? $discordUser->getName(),
            'global_name' => $discordUser->user['global_name'] ?? null,
            'avatar' => $discordUser->user['avatar'] ?? null,
            'access_token' => $discordUser->token,
            'refresh_token' => $discordUser->refreshToken,
            'expires_in' => $discordUser->expiresIn,
        ]);

        if ($result['success']) {
            return redirect()->route('settings.discord-account')
                ->with('success', $result['message']);
        }

        return redirect()->route('settings.discord-account')
            ->with('error', $result['message']);
    }
}
