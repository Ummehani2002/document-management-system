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

    async function renderPageToCanvas(page, scale) {
        var viewport = page.getViewport({ scale: scale || 2 });
        var canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        await page.render({
            canvasContext: canvas.getContext('2d'),
            viewport: viewport
        }).promise;
        return canvas;
    }

    /**
     * @param {File|Blob|string} source File, Blob, or URL
     * @param {{maxPages?: number, onProgress?: function}} options
     * @returns {Promise<string>}
     */
    async function extractPdfFormText(source, options) {
        ensureLibs();
        options = options || {};
        var maxPages = options.maxPages || 2;
        var onProgress = typeof options.onProgress === 'function' ? options.onProgress : function () {};

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
            // Prefer embedded text layer when present (fast + accurate).
            var textContent = await page.getTextContent();
            var layer = (textContent.items || [])
                .map(function (item) { return item.str || ''; })
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (layer.length >= 80 && /title|subject|description|material|inspection|submittal/i.test(layer)) {
                chunks.push(layer);
                continue;
            }

            var canvas = await renderPageToCanvas(page, 2);
            onProgress('OCR page ' + p + ' of ' + pages + '…');
            var result = await Tesseract.recognize(canvas, 'eng');
            var ocr = String((result && result.data && result.data.text) || '').trim();
            if (ocr) {
                chunks.push(ocr);
            } else if (layer) {
                chunks.push(layer);
            }
        }

        return chunks.join('\n\n').trim();
    }

    global.DmsPdfOcr = {
        extractPdfFormText: extractPdfFormText
    };
})(window);
