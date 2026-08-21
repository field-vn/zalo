<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Auth;

use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Exceptions\TokenException;

/**
 * Luồng OAuth của Zalo App.
 *
 * Khác với chuẩn OAuth2 thông thường: app_secret đi ở header `secret_key`
 * chứ không phải trong body, và request phải là form-urlencoded.
 */
final class OAuthClient
{
    public function __construct(
        private readonly Transport $transport,
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $oauthBase = 'https://oauth.zaloapp.com/v4',
        private readonly string $consentUrl = 'https://oauth.zaloapp.com/v4/oa/permission',
    ) {}

    /** URL để admin OA bấm vào và cấp quyền. */
    public function consentUrl(string $redirectUri, string $state): string
    {
        return $this->consentUrl.'?'.http_build_query([
            'app_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /** Đổi `code` lấy token lần đầu. */
    public function exchangeCode(string $code, string $codeVerifier = ''): TokenPair
    {
        $form = [
            'code' => $code,
            'app_id' => $this->appId,
            'grant_type' => 'authorization_code',
        ];

        if ($codeVerifier !== '') {
            $form['code_verifier'] = $codeVerifier;
        }

        return $this->request($form, 'đổi code lấy token');
    }

    /**
     * Refresh token.
     *
     * Response chứa refresh_token MỚI — caller BẮT BUỘC phải lưu lại,
     * refresh_token cũ chết ngay sau lời gọi này.
     */
    public function refresh(string $refreshToken): TokenPair
    {
        return $this->request([
            'refresh_token' => $refreshToken,
            'app_id' => $this->appId,
            'grant_type' => 'refresh_token',
        ], 'refresh token');
    }

    /** @param array<string, string> $form */
    private function request(array $form, string $what): TokenPair
    {
        $response = $this->transport->postForm(
            $this->oauthBase.'/oa/access_token',
            $form,
            ['secret_key' => $this->appSecret],
        );

        $data = $response->all();

        if (empty($data['access_token'])) {
            throw new TokenException(sprintf(
                'Không %s được: %s',
                $what,
                $response->errorMessage() ?: ($data['error_description'] ?? 'Zalo không trả về access_token'),
            ));
        }

        return TokenPair::fromResponse($data);
    }
}
