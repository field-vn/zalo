<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel\Http\Controllers;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Laravel\Support\OaPresenter;
use Illuminate\Contracts\View\View;

class DashboardController
{
    public function __invoke(OaRepository $oas, BotRepository $bots): View
    {
        $records = $oas->active();

        return view('zalo::dashboard', [
            'oas' => $records,
            'bots' => $bots->active(),
            'appId' => OaPresenter::maskedAppId(),
            'schedulerEnabled' => (bool) config('zalo.scheduler.enabled', true),
            'webhookUrl' => url((string) config('zalo.webhook.path', 'zalo/webhook')),
            'webhookSecretSet' => (string) config('zalo.apps.default.webhook_secret', '') !== '',
            'warnings' => OaPresenter::warnings($records),
            'statusBadge' => OaPresenter::statusBadge(...),
            'tokenSummary' => OaPresenter::tokenSummary(...),
        ]);
    }
}
