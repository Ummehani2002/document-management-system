/**
 * Read Title/Subject (and other form fields) from PDF page 1–2 in the browser.
 * Used on Upload and Search because Laravel Cloud has no server Tesseract.
 */
(function (global) {
    'use strict';

    function ensureLibs() {
        if (typeof pdfjsLib === 'undefined') {
            throw new Error('pdf.js is not loaded');
        }
        if (typeof Tesseract === 'undefined') {
            throw new Error('tesseract.js is not loaded');
        }
        if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
            pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    }

    function withTimeout(promise, ms, label) {
        return new Promise(function (resolve, reject) {
            var timer = setTimeout(function () {
                reject(new Error((label || 'OCR') + ' timed out after ' + ms + 'ms'));
            }, ms);
            promise.then(
                function (value) {
                    clearTimeout(timer);
                    resolve(value);
                },
                function (err) {
                    clearTimeout(timer);
                    reject(err);
                }
            );
        });
    }

    async function renderPageToCanvas(page, scale) {
        var viewport = page.getViewport({ scale: scale || 2.5 });
        var canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        await page.render({
            canvasContext: canvas.getContext('2d'),
            viewport: viewport
        }).promise;
        return canvas;
    }

    function textLayerLooksLikeFormFields(layer) {
        return /Title\s*[\/&]\s*(?:Subject|Description)|Title\s+(?:and|&)\s+Description|Material\s+Description|Description\s+of\s+(?:Item|Material|Works)/i.test(layer);
    }

    /**
     * Labels alone are not enough — filled italic/typed values often live only in the scan image.
     * Skip the text-layer shortcut unless a real subject phrase is already present.
     */
    function textLayerHasUsableFormSubject(layer) {
        if (/Inspection\s+request\s+for\b/i.test(layer)) {
            return true;
        }
        if (/Material\s+Submittal\s+for\b/i.test(layer)) {
            return true;
        }
        if (/Material\s+Inspection\s+(?:request|for)\s+[A-Za-z0-9(]/i.test(layer)) {
            return true;
        }
        if (/Palm\s+tree|Excavation\s+of|as\s+shown\s+in\s+the\s+attached/i.test(layer)) {
            return true;
        }
        // Title/Subject followed by real wording (not the next form label).
        if (/Title\s*[\/&]\s*(?:Subject|Description)\s*[:\-]?\s*[A-Za-z0-9].{15,}/i.test(layer)
            && !/Title\s*[\/&]\s*(?:Subject|Description)\s*[:\-]?\s*(?:Purpose|Manufacturer|Section|Location|Date|Drawing)\b/i.test(layer)) {
            return true;
        }
        return false;
    }

    async function ocrCanvas(canvas, onProgress, label) {
        onProgress(label || 'OCR…');
        var result = await Tesseract.recognize(canvas, 'eng', {
            tessedit_pageseg_mode: '6'
        });
        return String((result && result.data && result.data.text) || '').trim();
    }

    /**
     * @param {File|Blob|string} source File, Blob, or URL
     * @param {{maxPages?: number, timeoutMs?: number, onProgress?: function}} options
     * @returns {Promise<string>}
     */
    async function extractPdfFormText(source, options) {
        ensureLibs();
        options = options || {};
        var maxPages = options.maxPages || 2;
        var timeoutMs = options.timeoutMs || 60000;
        var onProgress = typeof options.onProgress === 'function' ? options.onProgress : function () {};

        var work = (async function () {
            var loadingTask;
            if (typeof source === 'string') {
                loadingTask = pdfjsLib.getDocument({ url: source, withCredentials: true });
            } else {
                var buf = await source.arrayBuffer();
                loadingTask = pdfjsLib.getDocument({ data: buf });
            }

            var pdf = await loadingTask.promise;
            var pages = Math.min(maxPages, pdf.numPages || 1);
            var chunks = [];

            for (var p = 1; p <= pages; p++) {
                onProgress('Reading page ' + p + ' of ' + pages + '…');
                var page = await pdf.getPage(p);
                var textContent = await page.getTextContent();
                var layer = (textContent.items || [])
                    .map(function (item) { return item.str || ''; })
                    .join(' ')
                    .replace(/\s+/g, ' ')
                    .trim();

                // Only trust the text layer when filled subject text is already there.
                // IR forms often have labels in the layer but italic Description of Works only in the image.
                if (layer.length >= 80 && textLayerLooksLikeFormFields(layer) && textLayerHasUsableFormSubject(layer)) {
                    chunks.push(layer);
                    continue;
                }

                var canvas = await renderPageToCanvas(page, 2.5);
                var ocr = await ocrCanvas(canvas, onProgress, 'OCR page ' + p + ' of ' + pages + '…');

                // If we still only have chrome (labels / times) and no Description of Works body, retry sharper.
                if (
                    (!ocr || !/Inspection\s+request\s+for\b|Material\s+Submittal\s+for\b/i.test(ocr))
                    && /Description\s+of\s+Works|INSPECTION\s+REQUEST|Title\s*[\/&]/i.test(ocr || layer)
                ) {
                    var sharp = await renderPageToCanvas(page, 3.2);
                    var ocr2 = await ocrCanvas(sharp, onProgress, 'OCR page ' + p + ' (detail)…');
                    if (ocr2 && ocr2.length > (ocr ? ocr.length : 0)) {
                        ocr = ocr2;
                    }
                }

                if (ocr) {
                    chunks.push(ocr);
                } else if (layer) {
                    chunks.push(layer);
                }
            }

            return chunks.join('\n\n').trim();
        })();

        return withTimeout(work, timeoutMs, 'PDF form OCR');
    }

    global.DmsPdfOcr = {
        extractPdfFormText: extractPdfFormText,
        withTimeout: withTimeout
    };
})(window);
