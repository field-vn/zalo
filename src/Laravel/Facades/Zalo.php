<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Facades;

use Closure;
use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Auth\OAuthClient;
use FieldVn\Zalo\Core\Auth\RefreshingTokenProvider;
use FieldVn\Zalo\Core\Channels\Bot\BotChannel;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Core\Channels\OA\Resources\MessageResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\TagResource;
use FieldVn\Zalo\Core\Channels\OA\Resources\UserResource;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Testing\RecordedRequest;
use FieldVn\Zalo\Laravel\Testing\ZaloFake;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Docblock này không phải trang trí — thiếu nó thì IDE mù hoàn toàn và
 * người dùng sẽ đánh giá package là cẩu thả ngay từ dòng code đầu tiên.
 *
 * @method static OAChannel oa(string|int|null $key = null)
 * @method static BotChannel bot(string|int|null $key = null)
 * @method static Collection<int,OAChannel> oas(?callable $filter = null)
 * @method static Collection<int,ZaloOa> availableOas()
 * @method static Collection<int,object> availableBots()
 * @method static OAuthClient oauth(?string $appKey = null)
 * @method static RefreshingTokenProvider tokenProviderFor(ZaloOa $oa)
 * @method static void forgetResolved()
 *
 * Proxy về OA mặc định:
 * @method static MessageResource messages()
 * @method static UserResource users()
 * @method static TagResource tags()
 *
 * Chỉ có sau khi gọi Zalo::fake():
 * @method static void assertSent(?callable $callback = null)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $expected)
 * @method static void assertSentTo(string $userId, ?string $text = null)
 * @method static void assertNotSentTo(string $userId)
 * @method static void assertSentVia(string $slug)
 * @method static Collection<int,RecordedRequest> sent()
 *
 * @see ZaloManager
 * @see ZaloFake
 */
class Zalo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Factory::class;
    }

    /**
     * Chặn mọi lời gọi tới Zalo trong test và cho phép assert những gì đã gửi.
     *
     *     Zalo::fake();
     *     $this->post('/don-hang', [...]);
     *     Zalo::assertSentTo('user-1', 'Đơn hàng đã xác nhận');
     *
     * Chỉ thay tầng mạng — message builder và resource vẫn chạy code thật,
     * nên test bắt được cả lỗi ở những tầng đó.
     */
    public static function fake(): ZaloFake
    {
        $fake = new ZaloFake(
            app()->bound(OaRepository::class) ? app(OaRepository::class) : null,
            app()->bound(BotRepository::class) ? app(BotRepository::class) : null,
        );

        static::swap($fake);

        return $fake;
    }

    /**
     * Đăng ký gate bảo vệ UI. Có gate thì basic auth bị bỏ qua.
     *
     *     Zalo::auth(fn ($request) => $request->user()?->is_admin === true);
     */
    public static function auth(Closure $callback): void
    {
        ZaloManager::auth($callback);
    }

    public static function hasAuthGate(): bool
    {
        return ZaloManager::hasAuthGate();
    }

    public static function checkAuthGate(Request $request): bool
    {
        return ZaloManager::checkAuthGate($request);
    }
}
