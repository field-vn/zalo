<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Facades;

use Closure;
use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Core\Auth\OAuthClient;
use FieldVn\Zalo\Core\Auth\RefreshingTokenProvider;
use FieldVn\Zalo\Core\Channels\Bot\BotChannel;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Core\Channels\OA\Resources\MessageResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\TagResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\UserResource;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Docblock này không phải trang trí — thiếu nó thì IDE mù hoàn toàn và
 * người dùng sẽ đánh giá package là cẩu thả ngay từ dòng code đầu tiên.
 *
 * @method static OAChannel                 oa(string|int|null $key = null)
 * @method static BotChannel                bot(string|int|null $key = null)
 * @method static Collection<int,OAChannel> oas(?callable $filter = null)
 * @method static Collection<int,ZaloOa>    availableOas()
 * @method static Collection<int,object>    availableBots()
 * @method static OAuthClient               oauth(string $appKey = 'default')
 * @method static RefreshingTokenProvider   tokenProviderFor(ZaloOa $oa)
 * @method static void                      forgetResolved()
 *
 * Proxy về OA mặc định:
 * @method static MessageResource messages()
 * @method static UserResource    users()
 * @method static TagResource     tags()
 *
 * @see \FieldVn\Zalo\Laravel\Managers\ZaloManager
 */
class Zalo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Factory::class;
    }

    /**
     * Đăng ký gate bảo vệ UI. Có gate thì basic auth bị bỏ qua.
     *
     *     Zalo::auth(fn ($request) => $request->user()?->is_admin === true);
     */
    public static function auth(Closure $callback): void
    {
        \FieldVn\Zalo\Laravel\Managers\ZaloManager::auth($callback);
    }

    public static function hasAuthGate(): bool
    {
        return \FieldVn\Zalo\Laravel\Managers\ZaloManager::hasAuthGate();
    }

    public static function checkAuthGate(\Illuminate\Http\Request $request): bool
    {
        return \FieldVn\Zalo\Laravel\Managers\ZaloManager::checkAuthGate($request);
    }
}
