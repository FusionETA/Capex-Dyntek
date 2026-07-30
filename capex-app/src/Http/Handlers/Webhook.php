<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Service\RequestProcessor;

/**
 * Handles onCrmDynamicItemUpdate for the Capex Request entity (build plan §4.3):
 *   verify -> load request -> set amountSGD, envelopeId, verdict, overBy
 *   -> re-sum the affected envelope (DERIVED, not incremental) -> write back.
 *
 * Idempotent: a replayed or duplicate event lands on the same figures, so we just
 * log and return 200. Every early exit is still a 200 — Bitrix retries non-2xx.
 */
final class Webhook
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(): void
    {
        // Bitrix delivers auth[member_id] + auth[application_token] with the event.
        $auth = (array) ($_REQUEST['auth'] ?? []);
        $memberId = (string) ($auth['member_id'] ?? '');

        // TODO(M5): also compare auth[application_token] against the value stored at install.
        if (!$this->app->verifyCaller($memberId)) {
            $this->ok('ignored: unrecognised portal');
            return;
        }

        $fields = (array) ($_REQUEST['data']['FIELDS'] ?? []);
        $requestEntityTypeId = (int) ($this->app->config['entities']['request'] ?? 0);
        $entityTypeId = (int) ($fields['ENTITY_TYPE_ID'] ?? 0);
        $id = (int) ($fields['ID'] ?? 0);

        if ($id === 0 || ($entityTypeId !== 0 && $entityTypeId !== $requestEntityTypeId)) {
            $this->ok('ignored: not a capex request');
            return;
        }

        // Same budget side-effects as an in-app submission (shared service).
        $result = (new RequestProcessor($this->app))->process($id);
        $this->ok($result['message']);
    }

    private function ok(string $message): void
    {
        error_log('[capex webhook] ' . $message);
        http_response_code(200);
        echo 'OK';
    }
}
