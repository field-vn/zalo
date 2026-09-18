<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

use FieldVn\Zalo\Core\Channels\OA\OAChannel;
use FieldVn\Zalo\Core\Exceptions\ApiException;
use FieldVn\Zalo\Core\Exceptions\TransportException;
use FieldVn\Zalo\Core\Http\Response;

/**
 * Ảnh chụp live (lười): getoa một lần; quota OA / ZBS chỉ khi checker hỏi.
 *
 * Lỗi quyền ZBS (-105/-120/-135/-138) không nổ cả report — checker đọc errorCode.
 */
final class OaSnapshot
{
    /** @var list<int> */
    private const ZBS_DENIED = [-105, -120, -135, -138];

    /** @var array<string, mixed>|null */
    private ?array $oaInfo = null;

    private ?Response $oaInfoResponse = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $oaQuotaAssets = null;

    private ?int $oaQuotaError = null;

    /** @var array<string, array<string, mixed>> */
    private array $userQuotas = [];

    private mixed $zbsQuotaPayload = false;

    private ?int $zbsQuotaError = null;

    private mixed $zbsTemplatesPayload = false;

    private ?int $zbsTemplatesError = null;

    public function __construct(private readonly OAChannel $oa) {}

    public function channel(): OAChannel
    {
        return $this->oa;
    }

    /** @return array<string, mixed> */
    public function oaInfo(): array
    {
        $this->loadOaInfo();

        return $this->oaInfo ?? [];
    }

    public function oaInfoResponse(): Response
    {
        $this->loadOaInfo();

        return $this->oaInfoResponse ?? new Response(200, ['error' => 0, 'data' => []]);
    }

    public function packageName(): ?string
    {
        $name = $this->oaInfo()['package_name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function oaQuotaAssets(): array
    {
        $this->loadOaQuota();

        return $this->oaQuotaAssets ?? [];
    }

    public function oaQuotaError(): ?int
    {
        $this->loadOaQuota();

        return $this->oaQuotaError;
    }

    /**
     * @return array<string, mixed>
     */
    public function userQuota(string $userId): array
    {
        if (! isset($this->userQuotas[$userId])) {
            $response = $this->oa->request()->post('/v3.0/oa/quota/message', ['user_id' => $userId]);
            $this->throwIfTokenError($response);
            $this->userQuotas[$userId] = $response->successful()
                ? $this->asArray($response->payload())
                : ['error' => $response->errorCode(), 'message' => $response->errorMessage()];
        }

        return $this->userQuotas[$userId];
    }

    /** @return array<string, mixed> */
    public function zbsQuota(): array
    {
        $this->loadZbsQuota();

        return is_array($this->zbsQuotaPayload) ? $this->zbsQuotaPayload : [];
    }

    public function zbsQuotaError(): ?int
    {
        $this->loadZbsQuota();

        return $this->zbsQuotaError;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function zbsTemplates(): array
    {
        $this->loadZbsTemplates();

        return is_array($this->zbsTemplatesPayload) ? array_values($this->zbsTemplatesPayload) : [];
    }

    public function zbsTemplatesError(): ?int
    {
        $this->loadZbsTemplates();

        return $this->zbsTemplatesError;
    }

    public function zbsQuotaDeniedCode(): ?int
    {
        $code = $this->zbsQuotaError();

        return $code !== null && in_array($code, self::ZBS_DENIED, true) ? $code : null;
    }

    public function zbsDeniedCode(): ?int
    {
        $fromQuota = $this->zbsQuotaDeniedCode();

        if ($fromQuota !== null) {
            return $fromQuota;
        }

        $code = $this->zbsTemplatesError();

        return $code !== null && in_array($code, self::ZBS_DENIED, true) ? $code : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function zbsTemplate(string $id): ?array
    {
        foreach ($this->zbsTemplates() as $item) {
            $found = $item['templateId'] ?? $item['template_id'] ?? null;

            if ($found !== null && (string) $found === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function zbsSampleData(string $templateId): array
    {
        try {
            $payload = $this->oa->zbs()->sampleData($templateId)->payload();

            return $this->asArray($payload);
        } catch (ApiException $e) {
            $this->rethrowUnlessZbsDenied($e);

            return ['error' => $e->errorCode, 'message' => $e->getMessage()];
        }
    }

    private function loadOaInfo(): void
    {
        if ($this->oaInfoResponse !== null) {
            return;
        }

        $response = $this->oa->request()->get('/v2.0/oa/getoa');
        $this->throwIfTokenError($response);
        $this->oaInfoResponse = $response;
        $this->oaInfo = $response->successful() ? $this->asArray($response->payload()) : [];
    }

    private function loadOaQuota(): void
    {
        if ($this->oaQuotaAssets !== null) {
            return;
        }

        $assets = [];
        $lastError = null;
        $this->oaQuotaAssets = [];

        foreach (['OA', 'APP'] as $owner) {
            $response = $this->oa->request()->post('/v3.0/oa/quota/message', ['quota_owner' => $owner]);
            $this->throwIfTokenError($response);

            if ($response->failed()) {
                $lastError = $response->errorCode();

                continue;
            }

            foreach ($this->extractAssets($response) as $asset) {
                $asset['quota_owner'] = $owner;
                $assets[] = $asset;
            }
        }

        $this->oaQuotaAssets = $assets;
        $this->oaQuotaError = $assets === [] ? $lastError : null;
    }

    private function loadZbsQuota(): void
    {
        if ($this->zbsQuotaPayload !== false) {
            return;
        }

        try {
            $this->zbsQuotaPayload = $this->asArray($this->oa->zbs()->quota()->payload());
            $this->zbsQuotaError = null;
        } catch (ApiException $e) {
            $this->rethrowUnlessZbsDenied($e);
            $this->zbsQuotaPayload = [];
            $this->zbsQuotaError = $e->errorCode;
        }
    }

    private function loadZbsTemplates(): void
    {
        if ($this->zbsTemplatesPayload !== false) {
            return;
        }

        try {
            $payload = $this->oa->zbs()->templates()->payload();
            $this->zbsTemplatesPayload = $this->normalizeTemplateList($payload);
            $this->zbsTemplatesError = null;
        } catch (ApiException $e) {
            $this->rethrowUnlessZbsDenied($e);
            $this->zbsTemplatesPayload = [];
            $this->zbsTemplatesError = $e->errorCode;
        }
    }

    /** TransportException từ Guzzle; HTTP 5xx và mã token cũng ném — không nuốt thành available:false. */
    private function throwIfTokenError(Response $response): void
    {
        if ($response->status >= 500) {
            throw new TransportException('Không gọi được Zalo API (HTTP '.$response->status.').');
        }

        if ($response->successful()) {
            return;
        }

        $exception = ApiException::fromResponse($response);

        if ($exception->isTokenError()) {
            throw $exception;
        }
    }

    private function rethrowUnlessZbsDenied(ApiException $e): void
    {
        if ($e->isTokenError() || ! in_array($e->errorCode, self::ZBS_DENIED, true)) {
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractAssets(Response $response): array
    {
        $payload = $this->asArray($response->payload());
        $raw = $payload['asset'] ?? $payload['assets'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeTemplateList(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $list = array_is_list($payload) ? $payload : ($payload['templates'] ?? $payload['items'] ?? []);

        if (! is_array($list)) {
            return [];
        }

        $out = [];

        foreach ($list as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
