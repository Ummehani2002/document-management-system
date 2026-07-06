<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentLocationResolver;
use App\Services\MicrosoftGraphMailService;
use App\Services\SharedDocumentMailBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendSharedDocumentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * @param  array<string, string|null>  $mailData
     */
    public function __construct(
        public int $documentId,
        public int $senderUserId,
        public string $recipient,
        public string $subject,
        public string $fileName,
        public string $fromAddress,
        public string $fromName,
        public array $mailData = []
    ) {
    }

    public function handle(MicrosoftGraphMailService $graphMail): void
    {
        $sender = User::find($this->senderUserId);
        if ($sender === null) {
            throw new \RuntimeException('Sender account not found.');
        }

        $document = Document::find($this->documentId);
        if ($document === null) {
            throw new \RuntimeException('Document not found.');
        }

        [$fileBytes, $mimeType] = $this->readDocumentFile($document);

        $payload = array_merge([
            'recipient' => $this->recipient,
            'senderName' => $this->fromName,
            'senderEmail' => $this->fromAddress,
            'fileName' => $this->fileName,
        ], $this->mailData);

        $htmlBody = SharedDocumentMailBuilder::htmlBody($payload);
        $plainBody = SharedDocumentMailBuilder::plainTextBody($payload);

        if ($graphMail->canSendAsUser($sender)) {
            $graphMail->sendMailWithAttachment(
                sender: $sender,
                recipient: $this->recipient,
                subject: $this->subject,
                body: $htmlBody,
                fileName: $this->fileName,
                fileBytes: $fileBytes,
                mimeType: $mimeType,
                isHtml: true
            );

            return;
        }

        Mail::send([], [], function ($message) use ($plainBody, $fileBytes, $mimeType) {
            $message->from($this->fromAddress, $this->fromName)
                ->replyTo($this->fromAddress, $this->fromName)
                ->to($this->recipient)
                ->subject($this->subject)
                ->text($plainBody)
                ->attachData($fileBytes, $this->fileName, [
                    'mime' => $mimeType,
                ]);
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function readDocumentFile(Document $document): array
    {
        $location = DocumentLocationResolver::resolve((string) $document->file_path);
        if ($location === null) {
            throw new \RuntimeException('File not found in storage.');
        }

        if ($location['source'] === 'disk') {
            $fileBytes = Storage::disk($location['disk'])->get($location['path']);
        } else {
            $fileBytes = @file_get_contents($location['path']);
            if ($fileBytes === false) {
                throw new \RuntimeException('Unable to read file content.');
            }
        }

        return [$fileBytes, $this->detectMimeType($location, $document->file_name)];
    }

    protected function detectMimeType(array $location, string $fileName): string
    {
        try {
            if (($location['source'] ?? '') === 'disk') {
                $mime = Storage::disk($location['disk'])->mimeType($location['path']);
                if (is_string($mime) && trim($mime) !== '') {
                    return $mime;
                }
            } elseif (($location['source'] ?? '') === 'file') {
                $mime = @mime_content_type($location['path']);
                if (is_string($mime) && trim($mime) !== '') {
                    return $mime;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Share mime detect failed', ['error' => $e->getMessage(), 'file_name' => $fileName]);
        }

        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Document share queue send failed', [
            'document_id' => $this->documentId,
            'sender_user_id' => $this->senderUserId,
            'email' => $this->recipient,
            'error' => $e->getMessage(),
        ]);
    }
}
