<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Registers the phone for push notifications. The device is tied to the API
 * token in use, so it is forgotten when that token is revoked or expires.
 */
class DeviceController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);

        // A token moves to whoever signed in on that phone most recently.
        DeviceToken::query()->updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id,
            'personal_access_token_id' => $request->user()->currentAccessToken()->getKey(),
            'platform' => $data['platform'],
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        $request->user()->deviceTokens()->where('token', $data['token'])->delete();

        return response()->noContent();
    }
}
