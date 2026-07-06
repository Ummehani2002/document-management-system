<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftGraphMailService
{
    public function __construct(
        protected MicrosoftTokenService $tokens
    ) {
    }

    public function canSendAsUser(User $user): bool
    {
        return $user->azure_mail_consent_at !== null
            && $this->tokens->accessToken($user) !== null;
    }

    /**
     * @throws \RuntimeException
     */
    public function sendMailWithAttachment(
        User $sender,
        string $recipient,
        string $subject,
        string $body,
        string $fileName,
        string $fileBytes,
        string $mimeType,
        bool $isHtml = false
    ): void {
        $accessToken = $this->tokens->accessToken($sender);
        if ($accessToken === null) {
            throw new \RuntimeException('Microsoft sign-in is required to send email from your account. Please sign in with Microsoft and try again.');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://graph.microsoft.com/v1.0/me/sendMail', [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => $isHtml ? 'HTML' : 'Text',
                        'content' => $body,
                    ],
                    'toRecipients' => [[
                        'emailAddress' => ['address' => $recipient],
                    ]],
                    'attachments' => [[
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'name' => $fileName,
                        'contentType' => $mimeType,
                        'contentBytes' => base64_encode($fileBytes),
                    ]],
                ],
                'saveToSentItems' => true,
            ]);

        if ($response->successful()) {
            return;
        }

        Log::warning('Microsoft Graph sendMail failed', [
            'sender_id' => $sender->id,
            'recipient' => $recipient,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException('Could not send email through Microsoft. Ensure Mail.Send permission is granted and try signing in with Microsoft again.');
    }
}
