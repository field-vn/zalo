<?php

declare(strict_types=1);

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Laravel\Events\ZaloOaConnected;
use FieldVn\Zalo\Laravel\Models\ZaloOa;
use FieldVn\Zalo\Laravel\Support\Authorizer;
use FieldVn\Zalo\Laravel\Support\OAuthState;
use FieldVn\Zalo\Tests\Support\FakeTransport;
use Illuminate\Support\Facades\Event;

/** @return array<string, string> */
function basicAuthHeader(string $user = 'admin', string $password = 'secret'): array
{
    // Không dùng $this->withBasicAuth(): method đó không có ở mọi phiên bản
    // Laravel mà package hỗ trợ. Header thì luôn đúng.
    return ['Authorization' => 'Basic '.base64_encode("{$user}:{$password}")];
}

function withUiCredentials(): void
{
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
}

function fakeTransport(): FakeTransport
{
    $fake = new FakeTransport;
    app()->instance(Transport::class, $fake);

    return $fake;
}

function oaRecord(array $attributes = []): ZaloOa
{
    return ZaloOa::create(array_merge([
        'name' => 'CSKH Shop',
        'slug' => 'cskh',
        'oa_id' => '111',
        'is_active' => false,
    ], $attributes));
}

/** Response chuẩn của oauth.zaloapp.com khi đổi code. */
function tokenResponse(string $access = 'access-1', string $refresh = 'refresh-1'): array
{
    return [
        'access_token' => $access,
        'refresh_token' => $refresh,
        'expires_in' => '3600',
    ];
}

it('consent URL mang đủ app_id, redirect_uri và state', function (): void {
    fakeTransport();
    $oa = oaRecord();

    $url = app(Authorizer::class)->consentUrl($oa);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://oauth.zaloapp.com/v4/oa/permission')
        ->and($query['app_id'])->toBe('test-app-id')
        ->and($query['redirect_uri'])->toContain('/zalo/oauth/callback')
        ->and($query['state'])->toHaveLength(40);
});

it('đổi code lấy token và lưu lại', function (): void {
    fakeTransport()
        ->push(tokenResponse())
        ->push(['data' => ['name' => 'CSKH Shop ABC', 'oa_id' => '999']]);

    $oa = oaRecord();

    app(Authorizer::class)->completeWithCode($oa, 'ma-cap-quyen');
    $oa->refresh();

    expect($oa->token)->not->toBeNull()
        ->and($oa->token->access_token)->toBe('access-1')
        ->and($oa->token->refresh_token)->toBe('refresh-1')
        ->and($oa->is_active)->toBeTrue();
});

it('gửi app_secret ở header secret_key chứ không phải trong body', function (): void {
    // Zalo khác chuẩn OAuth2 thông thường ở đúng chỗ này — sai là hỏng cả luồng.
    $transport = fakeTransport()->push(tokenResponse())->push(['data' => []]);

    app(Authorizer::class)->completeWithCode(oaRecord(), 'ma-cap-quyen');

    $request = $transport->firstRequest();

    expect($request['method'])->toBe('FORM')
        ->and($request['url'])->toContain('/oa/access_token')
        ->and($request['headers']['secret_key'])->toBe('test-app-secret')
        ->and($request['data'])->not->toHaveKey('app_secret')
        ->and($request['data']['grant_type'])->toBe('authorization_code');
});

it('tự điền tên và avatar OA từ Zalo', function (): void {
    fakeTransport()
        ->push(tokenResponse())
        ->push(['data' => [
            'name' => 'CSKH Shop ABC',
            'avatar' => 'https://cdn.zalo.me/a.png',
            'oa_id' => '999',
        ]]);

    $oa = oaRecord(['name' => 'Tên tạm']);

    app(Authorizer::class)->completeWithCode($oa, 'ma-cap-quyen');
    $oa->refresh();

    expect($oa->name)->toBe('CSKH Shop ABC')
        ->and($oa->avatar_url)->toBe('https://cdn.zalo.me/a.png')
        ->and($oa->oa_id)->toBe('999');
});

it('vẫn giữ token khi lấy thông tin OA thất bại', function (): void {
    // Token đã lưu xong; không lấy được profile chỉ là bất tiện, không phải
    // lý do để coi cả luồng cấp quyền là thất bại.
    fakeTransport()
        ->push(tokenResponse())
        ->push(['error' => -216, 'message' => 'lỗi gì đó']);

    $oa = oaRecord();

    app(Authorizer::class)->completeWithCode($oa, 'ma-cap-quyen');
    $oa->refresh();

    expect($oa->token)->not->toBeNull()
        ->and($oa->is_active)->toBeTrue();
});

it('bắn event ZaloOaConnected', function (): void {
    Event::fake([ZaloOaConnected::class]);
    fakeTransport()->push(tokenResponse())->push(['data' => []]);

    app(Authorizer::class)->completeWithCode(oaRecord(), 'ma-cap-quyen');

    Event::assertDispatched(ZaloOaConnected::class);
});

it('callback với state hợp lệ thì lưu token', function (): void {
    withUiCredentials();
    fakeTransport()->push(tokenResponse())->push(['data' => []]);

    $oa = oaRecord();
    $state = OAuthState::issue((int) $oa->getKey());

    $this->withHeaders(basicAuthHeader())
        ->get("/zalo/oauth/callback?code=abc&state={$state}")
        ->assertRedirect()
        ->assertSessionHas('zalo.success');

    expect($oa->fresh()->token)->not->toBeNull();
});

it('callback với state sai thì KHÔNG lưu gì', function (): void {
    withUiCredentials();
    fakeTransport()->push(tokenResponse());
    $oa = oaRecord();

    $this->withHeaders(basicAuthHeader())
        ->get('/zalo/oauth/callback?code=abc&state=state-gia-mao')
        ->assertRedirect()
        ->assertSessionHas('zalo.error');

    expect($oa->fresh()->token)->toBeNull();
});

it('callback khi admin từ chối thì báo nhẹ nhàng, không phải lỗi hệ thống', function (): void {
    withUiCredentials();

    $this->withHeaders(basicAuthHeader())
        ->get('/zalo/oauth/callback?error=access_denied')
        ->assertRedirect()
        ->assertSessionHas('zalo.error');
});

it('route callback được middleware Authorize bảo vệ', function (): void {
    withUiCredentials();

    $this->get('/zalo/oauth/callback?code=abc&state=x')->assertStatus(401);
});
