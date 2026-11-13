/**
 * Read Reference + Subject (Title / Description / Description of Works) from PDF pages 1–2.
 * Runs for every uploaded/searched PDF — filled form values are often only in the scan image.
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
            || /as\s+shown\s+in\s+the\s+attached\s+drawing/i.test(text)
            || /Title\s*[\/&]\s*(?:Subject|Description)\s*[:\-]?\s*[A-Za-z0-9].{12,}/i.test(text)
            || /Material\s+Description\s*[:\-]?\s*[A-Za-z0-9].{8,}/i.test(text)
            || /Drawing\s*Title\s*[:\-]?\s*[A-Za-z0-9].{8,}/i.test(text)
            || /Method\s*Statement\s*Title\s*[:\-]?\s*[A-Za-z0-9].{8,}/i.test(text)
            || /Subject\s*[:\-]\s*[A-Za-z0-9].{12,}/i.test(text);
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

                // Every document: always image-OCR page 1 (and page 2 lighter).
                // Description / Title / Subject values are often italic and missing from the text layer.
                var fullCanvas = await renderPageToCanvas(page, p === 1 ? 2.8 : 2.2);
                var fullOcr = await ocrCanvas(fullCanvas, onProgress, 'OCR page ' + p + '…');

                var bandOcr = '';
                var titleBandOcr = '';
                var detailOcr = '';
                if (p === 1) {
                    onProgress('Reading Description / Title…');
                    // Mid band covers Description of Works, Material Description, Title/Subject for all form types.
                    bandOcr = await ocrCanvas(cropBand(fullCanvas, 0.18, 0.55), onProgress, 'Reading Description / Title…');
                    // Upper-mid band for Title block / IR header refs on many forms.
                    titleBandOcr = await ocrCanvas(cropBand(fullCanvas, 0.08, 0.32), onProgress, 'Reading Reference / header…');

                    if (!hasFilledSubjectPhrase(mergeUniqueTexts([layer, fullOcr, bandOcr, titleBandOcr]))) {
                        onProgress('Reading description detail…');
                        var sharp = await renderPageToCanvas(page, 3.4);
                        detailOcr = await ocrCanvas(cropBand(sharp, 0.22, 0.50), onProgress, 'Reading description detail…');
                    }
                }

                var pageText = mergeUniqueTexts([layer, fullOcr, titleBandOcr, bandOcr, detailOcr]);
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
