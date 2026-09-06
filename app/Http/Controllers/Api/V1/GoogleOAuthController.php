<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Integrations\GoogleOAuth;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

#[Group(name: 'Google OAuth', description: 'Авторизация Google для доступа к Search Console', weight: 41)]
final class GoogleOAuthController extends Controller
{
    public function __construct(
        private readonly GoogleOAuth $oauth,
    ) {}

    /**
     * URL авторизации Google
     *
     * Возвращает ссылку на согласие Google для доступа к Search Console.
     */
    #[Response(200, description: 'URL авторизации')]
    #[Response(422, description: 'Приложение Google не настроено')]
    public function redirect(Request $request): JsonResponse
    {
        if (! $this->oauth->configured()) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'Google не настроен',
                    'detail' => 'Заполните GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET и GOOGLE_REDIRECT_URI.']],
            ], 422);
        }

        // state защищает от подделки ответа: в callback сверяем то же значение.
        $state = Str::random(40);
        $request->session()->put('google_state', $state);
        $request->session()->put('google_org_id', $request->get('organization')?->id);

        return response()->json(['url' => $this->oauth->authorizeUrl($state)]);
    }

    /**
     * Callback от Google
     *
     * Меняет код на токены, сохраняет их в организацию и возвращает на фронтенд.
     */
    #[Response(302, description: 'Перенаправление на фронтенд')]
    public function callback(Request $request): RedirectResponse
    {
        $front = (string) config('app.frontend_url', 'http://localhost:5174');
        $expected = $request->session()->pull('google_state');
        $orgId = $request->session()->pull('google_org_id');

        if (! $expected || $request->input('state') !== $expected) {
            return redirect($front.'/settings?error=google_state');
        }

        $code = $request->input('code');
        $org = $orgId ? Organization::find($orgId) : null;

        if (! is_string($code) || $code === '' || $org === null) {
            return redirect($front.'/settings?error=google_no_code');
        }

        if (! $this->oauth->exchange($org, $code)) {
            return redirect($front.'/settings?error=google_token_failed');
        }

        return redirect($front.'/settings?google=connected');
    }

    /**
     * Статус подключения Google
     */
    #[Response(200, description: 'Статус подключения')]
    public function status(Request $request): JsonResponse
    {
        $org = $request->get('organization');

        return response()->json([
            'configured' => $this->oauth->configured(),
            'connected' => ! empty($org->google_refresh_token),
        ]);
    }

    /**
     * Отключение Google
     */
    #[Response(200, description: 'Аккаунт отключён')]
    public function disconnect(Request $request): JsonResponse
    {
        $request->get('organization')->update([
            'google_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);

        return response()->json(['status' => 'disconnected']);
    }
}
