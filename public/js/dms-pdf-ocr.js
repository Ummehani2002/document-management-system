/**
 * Read Title / Subject / Description of Works from PDF pages 1–2 in the browser.
 * Always image-OCRs page 1 (filled italic text is often missing from the PDF text layer).
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
        var viewport = page.getViewport({ scale: scale || 2.8 });
        var canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        await page.render({
            canvasContext: ctx,
            viewport: viewport
        }).promise;
        return canvas;
    }

    function enhanceForOcr(sourceCanvas) {
        var canvas = document.createElement('canvas');
        canvas.width = sourceCanvas.width;
        canvas.height = sourceCanvas.height;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(sourceCanvas, 0, 0);
        var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var d = img.data;
        for (var i = 0; i < d.length; i += 4) {
            var g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            g = (g - 128) * 1.55 + 128;
            if (g < 0) g = 0;
            if (g > 255) g = 255;
            d[i] = d[i + 1] = d[i + 2] = g;
        }
        ctx.putImageData(img, 0, 0);
        return canvas;
    }

    function cropBand(sourceCanvas, y0Ratio, y1Ratio) {
        var y0 = Math.floor(sourceCanvas.height * y0Ratio);
        var y1 = Math.floor(sourceCanvas.height * y1Ratio);
        var h = Math.max(60, y1 - y0);
        var canvas = document.createElement('canvas');
        canvas.width = sourceCanvas.width;
        canvas.height = h;
        canvas.getContext('2d').drawImage(
            sourceCanvas,
            0, y0, sourceCanvas.width, h,
            0, 0, sourceCanvas.width, h
        );
        return canvas;
    }

    function hasFilledSubjectPhrase(text) {
        return /Inspection\s+request\s+for\b/i.test(text)
            || /Material\s+Submittal\s+for\b/i.test(text)
            || /Material\s+Inspection\s+(?:request|for)\s+[A-Za-z0-9(]/i.test(text)
            || /Palm\s+tree\s+pits/i.test(text)
            || /as\s+shown\s+in\s+the\s+attached\s+drawing/i.test(text);
    }

    async function ocrCanvas(canvas, onProgress, label) {
        onProgress(label || 'OCR…');
        var enhanced = enhanceForOcr(canvas);
        var result = await Tesseract.recognize(enhanced, 'eng');
        return String((result && result.data && result.data.text) || '').trim();
    }

    function mergeUniqueTexts(parts) {
        var out = [];
        var seen = {};
        parts.forEach(function (part) {
            var t = String(part || '').trim();
            if (!t) return;
            var key = t.slice(0, 180).toLowerCase();
            if (seen[key]) return;
            seen[key] = true;
            out.push(t);
        });
        return out.join('\n\n').trim();
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
        var timeoutMs = options.timeoutMs || 90000;
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

                // Always image-OCR page 1. Text layers often have labels only;
                // Description of Works / Title values are frequently italic in the scan.
                var fullCanvas = await renderPageToCanvas(page, p === 1 ? 2.8 : 2.2);
                var fullOcr = await ocrCanvas(fullCanvas, onProgress, 'OCR page ' + p + '…');

                var bandOcr = '';
                var detailOcr = '';
                if (p === 1) {
                    // Mid-page band: Description of Works / Title & Description sit here on IR/MS forms.
                    onProgress('OCR description area…');
                    var band = cropBand(fullCanvas, 0.20, 0.52);
                    bandOcr = await ocrCanvas(band, onProgress, 'OCR description area…');

                    if (!hasFilledSubjectPhrase(fullOcr + '\n' + bandOcr + '\n' + layer)) {
                        onProgress('OCR description detail…');
                        var sharp = await renderPageToCanvas(page, 3.4);
                        var detailBand = cropBand(sharp, 0.24, 0.48);
                        detailOcr = await ocrCanvas(detailBand, onProgress, 'OCR description detail…');
                    }
                }

                var pageText = mergeUniqueTexts([layer, fullOcr, bandOcr, detailOcr]);
                if (pageText) {
                    chunks.push(pageText);
                }
            }

            return chunks.join('\n\n').trim();
        })();

        return withTimeout(work, timeoutMs, 'PDF form OCR');
    }

    global.DmsPdfOcr = {
        extractPdfFormText: extractPdfFormText,
        withTimeout: withTimeout,
        hasFilledSubjectPhrase: hasFilledSubjectPhrase
    };
})(window);
