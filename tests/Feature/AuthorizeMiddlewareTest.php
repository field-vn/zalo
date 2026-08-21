<?php

declare(strict_types=1);

use FieldVn\Zalo\Laravel\Http\Middleware\Authorize;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

function runMiddleware(Request $request): mixed
{
    return (new Authorize())->handle($request, fn (): string => 'passed');
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

it('cho qua khi basic auth đúng', function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');

    expect(runMiddleware(basicRequest('admin', 'secret')))->toBe('passed');
});

it('trả 401 kèm WWW-Authenticate khi sai mật khẩu', function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');

    $response = runMiddleware(basicRequest('admin', 'sai'));

    expect($response->getStatusCode())->toBe(401)
        ->and($response->headers->get('WWW-Authenticate'))->toContain('Basic');
});

it('trả 401 khi sai username', function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');

    expect(runMiddleware(basicRequest('khac', 'secret'))->getStatusCode())->toBe(401);
});

it('FAIL-CLOSED: chặn ngoài local khi chưa cấu hình credential', function (): void {
    config()->set('zalo.ui.user', null);
    config()->set('zalo.ui.password', null);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => runMiddleware(basicRequest()))
        ->toThrow(HttpException::class);
});

it('cho qua ở local khi chưa cấu hình credential', function (): void {
    config()->set('zalo.ui.user', null);
    config()->set('zalo.ui.password', null);
    app()->detectEnvironment(fn (): string => 'local');

    expect(runMiddleware(basicRequest()))->toBe('passed');
});

it('gate của ứng dụng thắng basic auth', function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
    ZaloManager::auth(fn (): bool => true);

    // Không gửi basic auth mà vẫn qua được, vì gate đã cho phép.
    expect(runMiddleware(basicRequest()))->toBe('passed');
});

it('gate từ chối thì chặn kể cả basic auth đúng', function (): void {
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');
    ZaloManager::auth(fn (): bool => false);

    expect(fn () => runMiddleware(basicRequest('admin', 'secret')))
        ->toThrow(HttpException::class);
});

it('chặn IP ngoài allowlist TRƯỚC khi xét auth', function (): void {
    config()->set('zalo.ui.allowed_ips', ['203.0.113.0/24']);
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');

    $request = basicRequest('admin', 'secret');
    $request->server->set('REMOTE_ADDR', '198.51.100.7');

    expect(fn () => runMiddleware($request))->toThrow(HttpException::class);
});

it('cho qua IP nằm trong allowlist', function (): void {
    config()->set('zalo.ui.allowed_ips', ['203.0.113.0/24']);
    config()->set('zalo.ui.user', 'admin');
    config()->set('zalo.ui.password', 'secret');

    $request = basicRequest('admin', 'secret');
    $request->server->set('REMOTE_ADDR', '203.0.113.7');

    expect(runMiddleware($request))->toBe('passed');
});
