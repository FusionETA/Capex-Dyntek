<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;

/**
 * M2 smoke test: proves the OAuth round-trip and Client::call() against a live
 * portal by listing Capex Request items. Not a screen — a health check to hit
 * once after install. Re-checks the caller before touching Bitrix.
 */
final class Diag
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $memberId = (string) ($_REQUEST['member_id'] ?? '');
        if (!$this->app->verifyCaller($memberId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'unrecognised portal']);
            return;
        }

        $entityTypeId = (int) ($this->app->config['entities']['request'] ?? 0);
        if ($entityTypeId === 0) {
            http_response_code(412);
            echo json_encode(['ok' => false, 'error' => 'entities.request not configured yet']);
            return;
        }

        try {
            $res = $this->app->client->call('crm.item.list', [
                'entityTypeId' => $entityTypeId,
                'select'       => ['id', 'title', 'stageId'],
            ]);

            $items = $res['result']['items'] ?? [];
            echo json_encode([
                'ok'    => true,
                'total' => $res['total'] ?? count($items),
                'count' => count($items),
            ]);
        } catch (\Throwable $e) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
