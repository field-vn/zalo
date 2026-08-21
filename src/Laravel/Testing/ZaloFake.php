<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Testing;

use DateTimeImmutable;
use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Core\Auth\ArrayTokenStore;
use FieldVn\Zalo\Core\Auth\OAuthClient;
use FieldVn\Zalo\Core\Auth\RefreshingTokenProvider;
use FieldVn\Zalo\Core\Auth\TokenPair;
use FieldVn\Zalo\Core\Channels\Bot\BotChannel;
use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Laravel\Models\ZaloBot;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Bản giả của Zalo dùng trong test của DỰ ÁN, không phải của package.
 *
 *     Zalo::fake();
 *     // ... code cần test
 *     Zalo::assertSentTo('user-1', 'Đơn hàng đã xác nhận');
 *
 * Chỉ chặn TẦNG MẠNG, không giả lập cả package: message builder, resource,
 * cách dựng payload vẫn chạy code thật. Nhờ vậy test bắt được cả lỗi ở những
 * tầng đó, thay vì chỉ khẳng định "có ai đó gọi một method nào đó".
 *
 * Không cần OA trong DB và không cần token — channel giả tự có sẵn.
 */
class ZaloFake implements Factory
{
    private readonly RequestRecorder $recorder;

    /** @var array<string, OAChannel|BotChannel> */
    private array $channels = [];

    public function __construct(
        private readonly ?OaRepository $oas = null,
        private readonly ?BotRepository $bots = null,
        ?RequestRecorder $recorder = null,
    ) {
        $this->recorder = $recorder ?? new RequestRecorder;
    }

    public function recorder(): RequestRecorder
    {
        return $this->recorder;
    }

    /** @param array<string, mixed> $data */
    public function push(array $data, int $status = 200): self
    {
        $this->recorder->push($data, $status);

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function respondWith(array $data): self
    {
        $this->recorder->respondWith($data);

        return $this;
    }

    // ── Factory ──────────────────────────────────────────────────────────

    public function oa(string|int|null $key = null): OAChannel
    {
        $slug = $key === null ? 'default' : (string) $key;

        if (! isset($this->channels['oa.'.$slug])) {
            $this->channels['oa.'.$slug] = new OAChannel(
                slug: $slug,
                transport: new RecordingTransport($this->recorder, 'oa:'.$slug),
                tokens: $this->tokenProvider($slug),
                baseUrl: (string) config('zalo.endpoints.oa', 'https://openapi.zalo.me'),
            );
        }

        /** @var OAChannel */
        return $this->channels['oa.'.$slug];
    }

    public function bot(string|int|null $key = null): BotChannel
    {
        $slug = $key === null ? 'default' : (string) $key;

        if (! isset($this->channels['bot.'.$slug])) {
            $this->channels['bot.'.$slug] = new BotChannel(
                slug: $slug,
                transport: new RecordingTransport($this->recorder, 'bot:'.$slug),
                token: 'fake-bot-token',
                baseUrl: (string) config('zalo.endpoints.bot', 'https://bot-api.zapps.me/bot'),
            );
        }

        /** @var BotChannel */
        return $this->channels['bot.'.$slug];
    }

    /**
     * Đọc từ DB thật — fake chỉ thay tầng MẠNG, không thay database.
     *
     * @param  (callable(ZaloOa): bool)|null  $filter
     * @return Collection<int, OAChannel>
     */
    public function oas(?callable $filter = null): Collection
    {
        $records = $this->availableOas();

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
        /** @var Collection<int, ZaloOa> */
        return $this->oas?->active() ?? collect();
    }

    /** @return Collection<int, ZaloBot> */
    public function availableBots(): Collection
    {
        /** @var Collection<int, ZaloBot> */
        return $this->bots?->active() ?? collect();
    }

    // ── Assertions ───────────────────────────────────────────────────────

    /** @param (callable(RecordedRequest): bool)|null $callback */
    public function assertSent(?callable $callback = null): void
    {
        $matches = $this->matching($callback);

        PHPUnit::assertTrue(
            $matches->isNotEmpty(),
            'Không có request Zalo nào khớp điều kiện.'.$this->summary(),
        );
    }

    /** @param callable(RecordedRequest): bool $callback */
    public function assertNotSent(callable $callback): void
    {
        PHPUnit::assertTrue(
            $this->matching($callback)->isEmpty(),
            'Có request Zalo khớp điều kiện, nhưng lẽ ra không được có.'.$this->summary(),
        );
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertSame(
            0,
            $this->recorder->count(),
            'Lẽ ra không gửi gì tới Zalo.'.$this->summary(),
        );
    }

    public function assertSentCount(int $expected): void
    {
        PHPUnit::assertSame(
            $expected,
            $this->recorder->count(),
            "Mong đợi {$expected} request tới Zalo.".$this->summary(),
        );
    }

    /** Ca dùng nhiều nhất: đã gửi tin nhắn tới người này chưa (và nội dung gì). */
    public function assertSentTo(string $userId, ?string $text = null): void
    {
        $this->assertSent(
            fn (RecordedRequest $r): bool => $r->isMessage()
                && $r->userId() === $userId
                && ($text === null || $r->text() === $text)
        );
    }

    public function assertNotSentTo(string $userId): void
    {
        $this->assertNotSent(
            fn (RecordedRequest $r): bool => $r->isMessage() && $r->userId() === $userId
        );
    }

    /** Đã gửi qua đúng OA đó chưa — quan trọng với dự án nhiều OA. */
    public function assertSentVia(string $slug): void
    {
        $this->assertSent(fn (RecordedRequest $r): bool => $r->isOa() && $r->slug() === $slug);
    }

    /** @return Collection<int, RecordedRequest> */
    public function sent(): Collection
    {
        return $this->recorder->requests();
    }

    // ── Nội bộ ───────────────────────────────────────────────────────────

    /**
     * @param  (callable(RecordedRequest): bool)|null  $callback
     * @return Collection<int, RecordedRequest>
     */
    private function matching(?callable $callback): Collection
    {
        $requests = $this->recorder->requests();

        return $callback === null ? $requests : $requests->filter($callback);
    }

    /** Liệt kê những gì ĐÃ gửi — thiếu cái này thì assert fail rất khó lần ra. */
    private function summary(): string
    {
        $requests = $this->recorder->requests();

        if ($requests->isEmpty()) {
            return "\n\nKhông có request nào được ghi lại.";
        }

        $lines = $requests->map(function (RecordedRequest $r): string {
            $to = $r->userId() ?? '—';
            $text = $r->text();
            $text = $text === null ? '' : ' · "'.mb_strimwidth($text, 0, 40, '…').'"';

            return "  - {$r->channel} → {$to}{$text}  [{$r->method} {$r->url}]";
        })->implode("\n");

        return "\n\nĐã gửi ".$requests->count()." request:\n".$lines;
    }

    private function tokenProvider(string $slug): RefreshingTokenProvider
    {
        $tokens = new TokenPair(
            accessToken: 'fake-access-token',
            refreshToken: 'fake-refresh-token',
            expiresAt: (new DateTimeImmutable)->modify('+1 hour'),
            refreshExpiresAt: (new DateTimeImmutable)->modify('+90 days'),
        );

        return new RefreshingTokenProvider(
            oaSlug: $slug,
            oauth: new OAuthClient(
                new RecordingTransport($this->recorder, 'oa:'.$slug),
                'fake-app-id',
                'fake-app-secret',
            ),
            store: new ArrayTokenStore($tokens),
        );
    }
}
