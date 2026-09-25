<?php

use App\Services\PdfFirstPageOcrService;

test('thin pdf text layer is treated as needing ocr so body words are not skipped', function () {
    $ocr = new PdfFirstPageOcrService();

    expect($ocr->shouldSupplementWithOcr(''))->toBeTrue();
    expect($ocr->shouldSupplementWithOcr('CMO-PSP'))->toBeTrue();
    expect($ocr->shouldSupplementWithOcr(str_repeat('Phoenix irrigation shop drawings approved ', 20)))->toBeFalse();
});
