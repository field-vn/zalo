<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Http\Middleware\Authorize;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * $next PHẢI trả về Response thật — trong pipeline của Laravel không bao giờ
 * có chuyện middleware nhận lại string. Trả string chỉ làm test dễ viết hơn
 * nhưng lại kiểm tra sai thứ đang chạy trên production.
 */
function runMiddleware(Request $request): Response
{
    /** @var Response */
    return (new Authorize)->handle(
        $request,
        static fn (): Response => new Response('passed'),
    );
}

function basicRequest(string $user = '', string $password = ''): Request
{
    $request = Request::create('/zalo', 'GET');

    if ($user !== '') {
        $request->headers->set('PHP_AUTH_USER', $user);
        $request->headers->set('PHP_AUTH_PW', $password);
    }

    return $request;
}

function withBasicAuth(): void
{
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
}

it('cho qua khi basic auth đúng', function (): void {
    withBasicAuth();

    expect(runMiddleware(basicRequest('admin', 'secret'))->getContent())->toBe('passed');
});

it('trả 401 kèm WWW-Authenticate khi sai mật khẩu', function (): void {
    withBasicAuth();

    $response = runMiddleware(basicRequest('admin', 'sai'));

    expect($response->getStatusCode())->toBe(401)
        ->and($response->headers->get('WWW-Authenticate'))->toContain('Basic');
});

it('trả 401 khi sai username', function (): void {
    withBasicAuth();

    expect(runMiddleware(basicRequest('khac', 'secret'))->getStatusCode())->toBe(401);
});

it('FAIL-CLOSED: chặn ngoài local khi chưa cấu hình credential', function (): void {
    config()->set('zalo.ui.user', null);
    config()->set('zalo.ui.password', null);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => runMiddleware(basicRequest()))->toThrow(HttpException::class);
});

it('cho qua ở local khi chưa cấu hình credential', function (): void {
    config()->set('zalo.ui.user', null);
    config()->set('zalo.ui.password', null);
    app()->detectEnvironment(fn (): string => 'local');

    expect(runMiddleware(basicRequest())->getContent())->toBe('passed');
});

it('gate của ứng dụng thắng basic auth', function (): void {
    withBasicAuth();
    ZaloManager::auth(fn (): bool => true);

    // Không gửi basic auth mà vẫn qua được, vì gate đã cho phép.
    expect(runMiddleware(basicRequest())->getContent())->toBe('passed');
});

it('gate từ chối thì chặn kể cả basic auth đúng', function (): void {
    withBasicAuth();
    ZaloManager::auth(fn (): bool => false);

    expect(fn () => runMiddleware(basicRequest('admin', 'secret')))
        ->toThrow(HttpException::class);
});

it('chặn IP ngoài allowlist TRƯỚC khi xét auth', function (): void {
    config()->set('zalo.ui.allowed_ips', ['203.0.113.0/24']);
    withBasicAuth();

    $request = basicRequest('admin', 'secret');
    $request->server->set('REMOTE_ADDR', '198.51.100.7');

    expect(fn () => runMiddleware($request))->toThrow(HttpException::class);
});

it('cho qua IP nằm trong allowlist', function (): void {
    config()->set('zalo.ui.allowed_ips', ['203.0.113.0/24']);
    withBasicAuth();

    $request = basicRequest('admin', 'secret');
    $request->server->set('REMOTE_ADDR', '203.0.113.7');

    expect(runMiddleware($request)->getContent())->toBe('passed');
});
