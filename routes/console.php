<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Document;
use App\Http\Controllers\DocumentController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('documents:cleanup-unavailable', function () {
    $controller = app(DocumentController::class);
    $resolver = new ReflectionMethod(DocumentController::class, 'resolveDocumentLocation');
    $resolver->setAccessible(true);

    $deleted = 0;
    Document::query()
        ->select(['id', 'file_path'])
        ->orderBy('id')
        ->chunkById(200, function ($docs) use (&$deleted, $controller, $resolver) {
            foreach ($docs as $doc) {
                if ($resolver->invoke($controller, (string) $doc->file_path) !== null) {
                    continue;
                }
                $doc->delete();
                $deleted++;
            }
        });

    $this->info('Cleaned unavailable records: '.$deleted);
})->purpose('Delete document rows whose file is missing from storage');
