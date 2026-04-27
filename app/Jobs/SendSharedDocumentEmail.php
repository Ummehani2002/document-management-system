<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSharedDocumentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public int $documentId,
        public string $recipient,
        public string $subject,
        public string $fileName,
        public string $fileBytes,
        public string $mimeType,
        public string $projectNumber,
        public string $fromAddress,
        public string $fromName
    ) {
    }

    public function handle(): void
    {
        $body = "Hello,\n\nA document has been shared with you from Document Management System.\n\nFile: {$this->fileName}\nProject: {$this->projectNumber}\n\nRegards,\nDocument Management System";

        Mail::raw($body, function ($message) {
            $message->from($this->fromAddress, $this->fromName)
                ->replyTo($this->fromAddress, $this->fromName)
                ->to($this->recipient)
                ->subject($this->subject)
                ->attachData($this->fileBytes, $this->fileName, [
                    'mime' => $this->mimeType,
                ]);
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Document share queue send failed', [
            'document_id' => $this->documentId,
            'email' => $this->recipient,
            'error' => $e->getMessage(),
        ]);
    }
}
