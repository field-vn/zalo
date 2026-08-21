<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Managers;

use Closure;
use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Auth\OAuthClient;
use FieldVn\Zalo\Core\Auth\RefreshingTokenProvider;
use FieldVn\Zalo\Core\Channels\Bot\BotChannel;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Core\Exceptions\ConfigurationException;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Stores\EloquentTokenStore;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Điểm vào của package.
 *
 * OA và Bot là *connection có tên* (kiểu Storage::disk), không phải driver —
 * chúng khác nhau từ tầng xác thực nên không hoán đổi được cho nhau. Xem ADR-0001.
 *
 * Nguồn sự thật của connection là DB; App credentials lấy từ config/env.
 */
class ZaloManager implements Factory
{
    /** @var array<string, OAChannel|BotChannel> */
    protected array $resolved = [];

    /** Gate tuỳ chọn cho UI — có gate thì gate thắng basic auth. */
    protected static ?Closure $authGate = null;

    public function __construct(
        protected readonly Container $container,
        protected readonly OaRepository $oas,
        protected readonly BotRepository $bots,
    ) {}

    public function oa(string|int|null $key = null): OAChannel
    {
        $record = $key === null ? $this->oas->default() : $this->oas->find($key);

        if (! $record instanceof ZaloOa) {
            throw ConfigurationException::oaNotFound($key);
        }

        if (! $record->is_active) {
            throw ConfigurationException::oaInactive($record->slug);
        }

        $cacheKey = 'oa.'.$record->getKey();

        if (! isset($this->resolved[$cacheKey])) {
            $this->resolved[$cacheKey] = $this->buildOa($record);
        }

        /** @var OAChannel */
        return $this->resolved[$cacheKey];
    }

    public function bot(string|int|null $key = null): BotChannel
    {
        $record = $key === null ? $this->bots->default() : $this->bots->find($key);

        if (! $record instanceof ZaloBot) {
            throw ConfigurationException::botNotFound($key);
        }

        $cacheKey = 'bot.'.$record->getKey();

        if (! isset($this->resolved[$cacheKey])) {
            $this->resolved[$cacheKey] = new BotChannel(
                slug: $record->slug,
                transport: $this->transport(),
                token: $record->token,
                baseUrl: (string) config('zalo.endpoints.bot'),
            );
        }

        /** @var BotChannel */
        return $this->resolved[$cacheKey];
    }

    /**
     * @param  (callable(ZaloOa): bool)|null  $filter
     * @return Collection<int, OAChannel>
     */
    public function oas(?callable $filter = null): Collection
    {
        // Không dùng ->when(): nó nuốt mất generic của Collection và khiến
        // PHPStan không suy ra được kiểu phần tử ở bước ->map() phía sau.
        $records = $this->oas->active();

        if ($filter !== null) {
            $records = $records->filter($filter);
        }

        return $records
            ->map(fn (ZaloOa $oa): OAChannel => $this->oa($oa->slug))
            ->values();
    }

    /** @return Collection<int, ZaloOa> */
    public function availableOas(): Collection
    {
        return $this->oas->active();
    }

    /** @return Collection<int, ZaloBot> */
    public function availableBots(): Collection
    {
        return $this->bots->active();
    }

    /** Client OAuth cho một App — dùng bởi luồng authorize và refresh. */
    public function oauth(string $appKey = 'default'): OAuthClient
    {
        $app = $this->appConfig($appKey);

        return new OAuthClient(
            transport: $this->transport(),
            appId: (string) $app['app_id'],
            appSecret: (string) $app['app_secret'],
            oauthBase: (string) config('zalo.endpoints.oauth'),
            consentUrl: (string) config('zalo.endpoints.oauth_consent'),
        );
    }

    public function tokenProviderFor(ZaloOa $oa): RefreshingTokenProvider
    {
        return new RefreshingTokenProvider(
            oaSlug: $oa->slug,
            oauth: $this->oauth($oa->app_key),
            store: new EloquentTokenStore($oa),
            refreshBeforeMinutes: (int) config('zalo.scheduler.refresh_before', 15),
            rotateBeforeDays: (int) config('zalo.scheduler.rotate_before', 14),
        );
    }

    /** Đăng ký gate cho UI. Có gate thì basic auth bị bỏ qua. */
    public static function auth(Closure $callback): void
    {
        static::$authGate = $callback;
    }

    public static function hasAuthGate(): bool
    {
        return static::$authGate !== null;
    }

    public static function checkAuthGate(Request $request): bool
    {
        return static::$authGate !== null && (bool) call_user_func(static::$authGate, $request);
    }

    /** Chỉ dùng trong test. */
    public static function clearAuthGate(): void
    {
        static::$authGate = null;
    }

    public function forgetResolved(): void
    {
        $this->resolved = [];
    }

    protected function buildOa(ZaloOa $oa): OAChannel
    {
        // Ném lỗi sớm và rõ ràng nếu App chưa cấu hình, thay vì để hỏng ở tầng HTTP.
        $this->appConfig($oa->app_key);

        return new OAChannel(
            slug: $oa->slug,
            transport: $this->transport(),
            tokens: $this->tokenProviderFor($oa),
            baseUrl: (string) config('zalo.endpoints.oa'),
        );
    }

    /** @return array{app_id: string, app_secret: string, redirect: string} */
    protected function appConfig(string $appKey): array
    {
        /** @var array<string, string>|null $app */
        $app = config('zalo.apps.'.$appKey);

        if ($app === null) {
            throw ConfigurationException::appNotConfigured($appKey);
        }

        if (empty($app['app_id']) || empty($app['app_secret'])) {
            throw ConfigurationException::appCredentialsMissing();
        }

        /** @var array{app_id: string, app_secret: string, redirect: string} */
        return $app;
    }

    protected function transport(): Transport
    {
        return $this->container->make(Transport::class);
    }

    /**
     * Proxy về OA mặc định: Zalo::messages() === Zalo::oa()->messages()
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->oa()->{$method}(...$parameters);
    }
}
