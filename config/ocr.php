<?php

return [
    /*
    | How many PDF pages to OCR for keyword search (scanned / image PDFs).
    | Higher = more complete search, slower indexing.
    */
    'search_pages' => (int) env('OCR_SEARCH_PAGES', 10),

    'min_text_layer_chars' => (int) env('OCR_MIN_TEXT_LAYER_CHARS', 400),

    'tesseract_binary' => env('OCR_TESSERACT_BINARY'),
    'pdftotext_binary' => env('OCR_PDFTOTEXT_BINARY'),
    'pdftoppm_binary' => env('OCR_PDFTOPPM_BINARY'),
    'node_binary' => env('OCR_NODE_BINARY'),
];
