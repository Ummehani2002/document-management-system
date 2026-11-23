<?php

use App\Models\Document;
use App\Services\DocumentFilenameParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist Reference No + Subject like Microsoft DMS properties (set on upload, shown in Search).
     */
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'reference_no')) {
                $table->string('reference_no', 255)->nullable()->after('file_name');
            }
            if (! Schema::hasColumn('documents', 'subject')) {
                $table->string('subject', 1000)->nullable()->after('reference_no');
            }
            if (! Schema::hasColumn('documents', 'meta_source')) {
                $table->string('meta_source', 32)->nullable()->after('subject');
            }
        });

        // Backfill from existing OCR / filename parse (best effort).
        Document::query()
            ->select(['id', 'file_name', 'ocr_text', 'reference_no', 'subject'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $document) {
                    $needsRef = trim((string) ($document->reference_no ?? '')) === '';
                    $needsSubject = trim((string) ($document->subject ?? '')) === '';
                    if (! $needsRef && ! $needsSubject) {
                        continue;
                    }

                    $meta = DocumentFilenameParser::extractReferenceAndSubject(
                        $document->ocr_text,
                        (string) $document->file_name
                    );
                    $updates = ['meta_source' => 'backfill'];
                    if ($needsRef && ($meta['reference_no'] ?? '—') !== '—') {
                        $updates['reference_no'] = mb_substr((string) $meta['reference_no'], 0, 255);
                    }
                    if ($needsSubject && ($meta['subject'] ?? '—') !== '—') {
                        $updates['subject'] = mb_substr((string) $meta['subject'], 0, 1000);
                    }
                    if (count($updates) > 1) {
                        Document::query()->where('id', $document->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            foreach (['meta_source', 'subject', 'reference_no'] as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
