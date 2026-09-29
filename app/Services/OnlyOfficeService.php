<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class OnlyOfficeService
{
    public function serverUrl(): string
    {
        return rtrim(trim((string) config('services.onlyoffice.document_server_url', '')), '/');
    }

    /**
     * URL the Document Server uses to reach this app (may differ from APP_URL in local Docker).
     */
    public function appUrl(): string
    {
        $url = trim((string) config('services.onlyoffice.app_url', ''));

        return rtrim($url !== '' ? $url : (string) config('app.url'), '/');
    }

    public function isEnabled(): bool
    {
        return $this->serverUrl() !== '';
    }

    public function isReachable(): bool
    {
        $url = $this->serverUrl();
        if ($url === '') {
            return false;
        }

        try {
            return Http::timeout(3)->get($url.'/healthcheck')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function supportsFile(string $fileName): bool
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function editorConfig(Document $document, User $user): array
    {
        $ext = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
        $documentUrl = $this->withServerFacingRoot(fn () => URL::temporarySignedRoute(
            'documents.office-source',
            now()->addHours(2),
            ['id' => $document->id]
        ));
        $callbackUrl = $this->withServerFacingRoot(fn () => route('onlyoffice.callback', ['id' => $document->id]));

        $permissions = [
            'download' => true,
            'print' => true,
        ];
        if ($ext === 'pdf') {
            // Drawing/scanned PDFs crash in full "Edit PDF" mode; annotation mode still saves via callback.
            $permissions['edit'] = false;
            $permissions['comment'] = true;
            $permissions['review'] = true;
        } else {
            $permissions['edit'] = true;
        }

        $config = [
            'documentType' => $this->documentType($ext),
            'document' => [
                'fileType' => $ext,
                'key' => $this->documentKey($document),
                'title' => $document->file_name,
                'url' => $documentUrl,
                'permissions' => $permissions,
            ],
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'lang' => 'en',
                'mode' => 'edit',
                'user' => [
                    'id' => (string) $user->id,
                    'name' => (string) ($user->name ?: $user->username ?: $user->email),
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                ],
            ],
        ];

        $secret = trim((string) config('services.onlyoffice.jwt_secret', ''));
        if ($secret !== '') {
            $config['token'] = $this->jwtEncode($config, $secret);
        }

        return $config;
    }

    public function documentKey(Document $document): string
    {
        return hash('sha256', $document->id.'|'.$document->file_path.'|'.$document->updated_at?->timestamp);
    }

    /**
     * Ask Document Server to push the current edit to our callback without closing the editor.
     *
     * @return array{ok: bool, error: int|null, message: string}
     */
    public function forceSave(string $documentKey): array
    {
        $server = $this->serverUrl();
        if ($server === '') {
            return ['ok' => false, 'error' => null, 'message' => 'OnlyOffice is not configured.'];
        }

        $payload = [
            'c' => 'forcesave',
            'key' => $documentKey,
            'userdata' => 'dms-editor-save',
        ];

        $secret = trim((string) config('services.onlyoffice.jwt_secret', ''));
        $body = $payload;
        if ($secret !== '') {
            $body['token'] = $this->jwtEncode($payload, $secret);
        }

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post($server.'/coauthoring/CommandService.ashx', $body);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => null,
                'message' => 'Could not reach OnlyOffice Command Service: '.$e->getMessage(),
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => null,
                'message' => 'OnlyOffice Command Service HTTP '.$response->status(),
            ];
        }

        $error = (int) ($response->json('error') ?? -1);
        // 0 = ok, 4 = no changes since last save (treat as success for the user)
        if (! in_array($error, [0, 4], true)) {
            return [
                'ok' => false,
                'error' => $error,
                'message' => 'OnlyOffice force save failed (error '.$error.').',
            ];
        }

        return [
            'ok' => true,
            'error' => $error,
            'message' => $error === 4 ? 'No new changes to save.' : 'Force save requested.',
        ];
    }

    protected function documentType(string $ext): string
    {
        return match ($ext) {
            'xls', 'xlsx' => 'cell',
            'ppt', 'pptx' => 'slide',
            'pdf' => 'pdf',
            default => 'word',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function jwtEncode(array $payload, string $secret): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $header.'.'.$body, $secret, true));

        return $header.'.'.$body.'.'.$signature;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withServerFacingRoot(callable $callback): mixed
    {
        $original = (string) config('app.url');
        URL::forceRootUrl($this->appUrl());

        try {
            return $callback();
        } finally {
            URL::forceRootUrl($original);
        }
    }
}
