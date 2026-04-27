<?php

namespace App\Services;

class OfficeDocumentTextExtractionService
{
    public function extractText(string $path, string $extension): string
    {
        $ext = strtolower(trim($extension));

        if ($ext === 'docx') {
            return $this->extractDocxText($path);
        }

        if ($ext === 'xlsx') {
            return $this->extractXlsxText($path);
        }

        // Legacy binary formats are accepted for upload, but plain extraction
        // is not reliable without additional conversion dependencies.
        return '';
    }

    protected function extractDocxText(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        if ($xml === '') {
            return '';
        }

        $text = preg_replace('/<w:p[^>]*>/i', "\n", $xml) ?? $xml;
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function extractXlsxText(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        if ($sharedXml !== '') {
            $sx = @simplexml_load_string($sharedXml);
            if ($sx !== false && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    $parts = [];
                    if (isset($si->t)) {
                        $parts[] = (string) $si->t;
                    }
                    if (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $parts[] = (string) ($run->t ?? '');
                        }
                    }
                    $shared[] = trim(implode('', $parts));
                }
            }
        }

        $allText = [];
        for ($i = 1; $i <= 20; $i++) {
            $sheetXml = $zip->getFromName('xl/worksheets/sheet' . $i . '.xml');
            if ($sheetXml === false) {
                continue;
            }
            $sx = @simplexml_load_string($sheetXml);
            if ($sx === false || !isset($sx->sheetData->row)) {
                continue;
            }
            foreach ($sx->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $type = (string) ($cell['t'] ?? '');
                    $raw = isset($cell->v) ? (string) $cell->v : '';
                    if ($type === 's' && $raw !== '' && isset($shared[(int) $raw])) {
                        $cells[] = $shared[(int) $raw];
                    } elseif ($raw !== '') {
                        $cells[] = $raw;
                    }
                }
                if (!empty($cells)) {
                    $allText[] = implode(' ', $cells);
                }
            }
        }
        $zip->close();

        $text = trim(implode("\n", $allText));
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}

