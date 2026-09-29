<?php

namespace App\Console\Commands;

use App\Services\OnlyOfficeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class OnlyOfficeStatusCommand extends Command
{
    protected $signature = 'onlyoffice:status';

    protected $description = 'Check OnlyOffice Document Server connectivity for DMS edit/save';

    public function handle(OnlyOfficeService $onlyOffice): int
    {
        $server = $onlyOffice->serverUrl();
        $appUrl = $onlyOffice->appUrl();
        $jwt = trim((string) config('services.onlyoffice.jwt_secret', '')) !== '';

        $this->line('ONLYOFFICE_DOCUMENT_SERVER_URL: '.($server !== '' ? $server : '(not set)'));
        $this->line('ONLYOFFICE_APP_URL / APP_URL: '.$appUrl);
        $this->line('JWT enabled in DMS: '.($jwt ? 'yes' : 'no'));

        if ($server === '') {
            $this->error('OnlyOffice is not configured. Set ONLYOFFICE_DOCUMENT_SERVER_URL.');

            return self::FAILURE;
        }

        $ok = true;

        try {
            $health = Http::timeout(5)->get($server.'/healthcheck');
            if ($health->successful() && trim($health->body()) === 'true') {
                $this->info('Healthcheck: OK');
            } else {
                $this->error('Healthcheck failed: HTTP '.$health->status().' body='.substr($health->body(), 0, 80));
                $ok = false;
            }
        } catch (\Throwable $e) {
            $this->error('Healthcheck unreachable: '.$e->getMessage());
            $ok = false;
        }

        try {
            $api = Http::timeout(5)->get($server.'/web-apps/apps/api/documents/api.js');
            if ($api->successful()) {
                $this->info('Editor API script: OK');
            } else {
                $this->error('Editor API script failed: HTTP '.$api->status());
                $ok = false;
            }
        } catch (\Throwable $e) {
            $this->error('Editor API unreachable: '.$e->getMessage());
            $ok = false;
        }

        $callbackExample = rtrim($appUrl, '/').'/onlyoffice/callback/{id}';
        $this->line('Callback URL OnlyOffice must reach: '.$callbackExample);
        $this->line('Document source URL pattern: '.rtrim($appUrl, '/').'/documents/{id}/office-source?signature=...');

        if ($ok) {
            $this->info('OnlyOffice looks reachable from this app.');

            return self::SUCCESS;
        }

        $this->warn('Fix connectivity before Excel/Word Save to DMS will work.');

        return self::FAILURE;
    }
}
