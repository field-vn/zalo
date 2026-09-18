<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\AuthorizedAppsChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\CampaignToolChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\CsOutside48hChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\OaPackageChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\OaQuotaChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\OpenApiChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\RateLimitChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\UserQuotaChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\ZbsChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\ZbsQuotaChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\ZbsSendChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\ZbsTemplateChecker;
use FieldVn\Zalo\Core\Channels\OA\Capabilities\Checkers\ZbsTemplatesChecker;

final class CapabilityRegistry
{
    /** @var array<string, CapabilityChecker|callable> */
    private static array $extensions = [];

    /** @var array<string, CapabilityChecker> */
    private array $checkers = [];

    public function __construct()
    {
        foreach ([
            new OaPackageChecker,
            new OaQuotaChecker,
            new CsOutside48hChecker,
            new UserQuotaChecker,
            new OpenApiChecker,
            new RateLimitChecker,
            new AuthorizedAppsChecker,
            new CampaignToolChecker,
            new ZbsChecker,
            new ZbsQuotaChecker,
            new ZbsTemplatesChecker,
            new ZbsTemplateChecker,
            new ZbsSendChecker,
        ] as $checker) {
            $this->checkers[$checker->key()] = $checker;
        }

        foreach (self::$extensions as $key => $extension) {
            $this->checkers[$key] = $this->wrap($key, $extension);
        }
    }

    public static function extend(string $key, CapabilityChecker|callable $checker): void
    {
        self::$extensions[$key] = $checker;
    }

    /** Gỡ extend — chỉ dùng trong test. */
    public static function flushExtensions(): void
    {
        self::$extensions = [];
    }

    public function get(string $key): ?CapabilityChecker
    {
        return $this->checkers[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->checkers);
    }

    private function wrap(string $key, CapabilityChecker|callable $checker): CapabilityChecker
    {
        if ($checker instanceof CapabilityChecker) {
            return $checker;
        }

        return new CallableCapabilityChecker($key, $checker(...));
    }
}
