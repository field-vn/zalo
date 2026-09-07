<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

/**
 * Key built-in. App truyền string cùng giá trị, hoặc CheckSpec khi cần options.
 */
enum OaCapability: string
{
    case OaPackage = 'oa_package';
    case OaQuota = 'oa_quota';
    case CsOutside48h = 'cs_outside_48h';
    case UserQuota = 'user_quota';
    case OpenApi = 'open_api';
    case RateLimit = 'rate_limit';
    case AuthorizedApps = 'authorized_apps';
    case CampaignTool = 'campaign_tool';
    case Zbs = 'zbs';
    case ZbsQuota = 'zbs_quota';
    case ZbsTemplates = 'zbs_templates';
    case ZbsTemplate = 'zbs_template';
    case ZbsSend = 'zbs_send';

    /** @return list<self> */
    public static function defaults(): array
    {
        return [
            self::OaPackage,
            self::OaQuota,
            self::CsOutside48h,
            self::OpenApi,
            self::RateLimit,
            self::AuthorizedApps,
            self::CampaignTool,
            self::Zbs,
            self::ZbsQuota,
            self::ZbsTemplates,
        ];
    }

    public static function userQuota(string $userId): CheckSpec
    {
        return new CheckSpec(self::UserQuota->value, ['user_id' => $userId]);
    }

    public static function zbsTemplate(string $id): CheckSpec
    {
        return new CheckSpec(self::ZbsTemplate->value, ['id' => $id]);
    }

    public static function zbsSend(string $templateId): CheckSpec
    {
        return new CheckSpec(self::ZbsSend->value, ['template_id' => $templateId]);
    }
}
