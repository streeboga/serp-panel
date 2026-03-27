<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

#[Group(name: 'Яндекс OAuth', description: 'Авторизация через Яндекс OAuth для доступа к Wordstat API', weight: 40)]
final class YandexOAuthController extends Controller
{
    /**
     * Получение URL авторизации Яндекс
     *
     * Возвращает URL для перенаправления пользователя на страницу авторизации Яндекс OAuth.
     */
    #[Response(200, description: 'URL авторизации и флаг ручного ввода кода')]
    public function redirect(): JsonResponse
    {
        $url = 'https://oauth.yandex.ru/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('yandex.client_id'),
            'redirect_uri' => config('yandex.redirect_uri'),
        ]);

        return response()->json([
            'url' => $url,
            'manual_code' => config('yandex.manual_code', true),
        ]);
    }

    /**
     * Обработка callback от Яндекс
     *
     * Принимает код авторизации от Яндекс, обменивает на токен и перенаправляет на фронтенд.
     */
    #[Response(302, description: 'Перенаправление на фронтенд')]
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        if (! $code) {
            return redirect(config('app.frontend_url', 'http://localhost:5174').'/settings?error=no_code');
        }

        // Exchange code for token
        $response = Http::asForm()->post('https://oauth.yandex.ru/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => config('yandex.client_id'),
            'client_secret' => config('yandex.client_secret'),
        ]);

        if (! $response->ok()) {
            return redirect(config('app.frontend_url', 'http://localhost:5174').'/settings?error=token_failed');
        }

        $token = $response->json('access_token');

        // Since this is a redirect callback, store token in session
        // and let the frontend call saveToken to persist it
        session(['yandex_token' => $token]);

        return redirect(config('app.frontend_url', 'http://localhost:5174').'/settings?yandex=connected');
    }

    /**
     * Сохранение токена Яндекс
     *
     * Сохраняет полученный OAuth-токен в организацию. Токен берётся из сессии или тела запроса.
     */
    #[Response(200, description: 'Токен сохранён')]
    #[Response(400, description: 'Токен отсутствует')]
    public function saveToken(Request $request): JsonResponse
    {
        $org = $request->get('organization');
        $token = session('yandex_token');

        if (! $token) {
            // Try from request body (alternative flow)
            $token = $request->input('token');
        }

        if (! $token) {
            return response()->json(['error' => 'No token available'], 400);
        }

        $org->update(['yandex_token' => $token]);
        session()->forget('yandex_token');

        return response()->json(['status' => 'connected']);
    }

    /**
     * Получение статуса подключения Яндекс
     *
     * Проверяет, подключён ли OAuth-токен Яндекс для текущей организации.
     */
    #[Response(200, description: 'Статус подключения')]
    public function status(Request $request): JsonResponse
    {
        $org = $request->get('organization');

        return response()->json([
            'connected' => ! empty($org->yandex_token),
        ]);
    }

    /**
     * Отключение Яндекс аккаунта
     *
     * Удаляет OAuth-токен Яндекс из организации, отключая доступ к Wordstat API.
     */
    #[Response(200, description: 'Аккаунт отключён')]
    public function disconnect(Request $request): JsonResponse
    {
        $org = $request->get('organization');
        $org->update(['yandex_token' => null]);

        return response()->json(['status' => 'disconnected']);
    }
}
