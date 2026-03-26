<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class ConnectedAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = ConnectedAccount::where('organization_id', $request->get('organization')->id)
            ->get()
            ->map(fn (ConnectedAccount $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'label' => $a->label,
                'is_active' => $a->is_active,
                'has_credentials' => !empty($a->credentials),
                'expires_at' => $a->expires_at,
                'created_at' => $a->created_at,
            ]);

        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:yandex,google,xmlriver',
            'label' => 'required|string|max:255',
            'credentials' => 'nullable|array',
        ]);

        $orgId = $request->get('organization')->id;

        // For Yandex with verification code
        if ($validated['type'] === 'yandex' && isset($validated['credentials']['code'])) {
            $token = $this->exchangeYandexCode($validated['credentials']['code']);
            if (!$token) {
                return response()->json(['message' => 'Не удалось получить токен. Проверьте код.'], 422);
            }
            $validated['credentials'] = ['token' => $token];
        }

        $account = ConnectedAccount::create([
            'organization_id' => $orgId,
            'type' => $validated['type'],
            'label' => $validated['label'],
            'credentials' => $validated['credentials'] ?? [],
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $account->id,
            'type' => $account->type,
            'label' => $account->label,
            'is_active' => $account->is_active,
            'has_credentials' => !empty($account->credentials),
        ], 201);
    }

    public function update(Request $request, ConnectedAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'credentials' => 'sometimes|array',
        ]);

        if (isset($validated['credentials']['code']) && $account->type === 'yandex') {
            $token = $this->exchangeYandexCode($validated['credentials']['code']);
            if ($token) {
                $validated['credentials'] = ['token' => $token];
            } else {
                return response()->json(['message' => 'Не удалось обновить токен'], 422);
            }
        }

        $account->update($validated);

        return response()->json([
            'id' => $account->id,
            'type' => $account->type,
            'label' => $account->label,
            'is_active' => $account->is_active,
        ]);
    }

    public function destroy(ConnectedAccount $account): JsonResponse
    {
        $account->delete();
        return response()->json(null, 204);
    }

    public function test(ConnectedAccount $account): JsonResponse
    {
        $ok = match ($account->type) {
            'yandex' => $this->testYandex($account),
            'xmlriver' => $this->testXmlRiver($account),
            default => false,
        };

        return response()->json(['ok' => $ok]);
    }

    private function exchangeYandexCode(string $code): ?string
    {
        try {
            $response = Http::asForm()->post('https://oauth.yandex.ru/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('yandex.client_id'),
                'client_secret' => config('yandex.client_secret'),
            ]);

            return $response->json('access_token');
        } catch (\Exception) {
            return null;
        }
    }

    private function testYandex(ConnectedAccount $account): bool
    {
        $token = $account->credentials['token'] ?? null;
        if (!$token) return false;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://login.yandex.ru/info');

            return $response->ok();
        } catch (\Exception) {
            return false;
        }
    }

    private function testXmlRiver(ConnectedAccount $account): bool
    {
        $creds = $account->credentials;
        $baseUrl = $creds['base_url'] ?? 'http://xmlriver.com/search/xml';
        $user = $creds['user'] ?? '';
        $key = $creds['key'] ?? '';

        try {
            $response = Http::timeout(10)->get($baseUrl, [
                'user' => $user,
                'key' => $key,
                'query' => 'test',
                'num' => 1,
            ]);

            return str_contains($response->body(), '<yandexsearch');
        } catch (\Exception) {
            return false;
        }
    }
}
