@extends('layouts.app')

@section('content')
    <h2>Edit document</h2>

    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div style="margin-bottom: 12px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #b91c1c;">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 12px; padding: 12px 16px;">
        <p style="margin: 0 0 4px;"><strong>{{ $document->file_name }}</strong></p>
        <p style="margin: 0; color: #64748b; font-size: 0.88rem;">
            {{ $document->entity?->name ?? '—' }} · {{ $document->project?->project_number ?? '—' }} · {{ $document->display_folder }}
            · Next save: <strong>{{ $nextVersionName }}</strong>
        </p>
    </div>

    <div id="save-status" style="display:none; margin-bottom: 12px; padding: 12px 16px; background: #e8f4ec; border: 1px solid rgba(35,134,81,0.2); border-radius: 6px; color: #1a5c38;"></div>

    @if(!$fileAvailable)
        <div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #b91c1c;">
            The stored file is missing and cannot be edited.
        </div>
    @elseif(!empty($isPdf))
        <div class="card" style="margin-bottom: 12px; padding: 12px 16px; background: #f0f9ff; border: 1px solid #bae6fd;">
            <p style="margin: 0; color: #0c4a6e; font-size: 0.9rem; line-height: 1.5;">
                Edit the PDF below in your browser. Click <strong>Add text</strong>, then click on the page to type.
                When finished, click <strong>Save</strong> — the system stores <strong>{{ $nextVersionName }}</strong> automatically.
            </p>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                    <button type="button" id="pdf-prev" style="padding: 8px 14px;">Previous</button>
                    <span id="pdf-page-label" style="color: #64748b; font-size: 0.88rem; min-width: 90px; text-align: center;">Page 1</span>
                    <button type="button" id="pdf-next" style="padding: 8px 14px;">Next</button>
                    <button type="button" id="pdf-add-text" style="padding: 8px 14px; background: #334155;">Add text</button>
                    <button type="button" id="pdf-save" class="btn-primary" style="padding: 8px 16px;">Save as {{ $nextVersionName }}</button>
                </div>
                <a href="{{ request('return_url', route('documents.search')) }}" style="color: #334155; font-size: 0.88rem;">Back to search</a>
            </div>

            <div id="pdf-loading" style="padding: 40px 20px; text-align: center; color: #64748b;">Loading PDF…</div>
            <div id="pdf-editor-wrap" style="display: none; overflow: auto; max-height: calc(100vh - 260px); background: #525659; padding: 16px; text-align: center;">
                <div id="pdf-page-shell" style="position: relative; display: inline-block; box-shadow: 0 2px 12px rgba(0,0,0,0.35);">
                    <canvas id="pdf-canvas"></canvas>
                    <div id="pdf-overlays" style="position: absolute; inset: 0;"></div>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var pdfUrl = @json($pdfEditorUrl ?? route('documents.view', ['id' => $document->id, 'proxy' => 1]));
                var saveUrl = @json(route('documents.replace', ['id' => $document->id]));
                var csrf = @json(csrf_token());
                var returnUrl = @json(request('return_url', route('documents.search')));
                var nextName = @json($nextVersionName);

                var loadingEl = document.getElementById('pdf-loading');
                var wrapEl = document.getElementById('pdf-editor-wrap');
                var canvas = document.getElementById('pdf-canvas');
                var overlays = document.getElementById('pdf-overlays');
                var saveStatus = document.getElementById('save-status');
                var pageLabel = document.getElementById('pdf-page-label');
                var addTextBtn = document.getElementById('pdf-add-text');
                var saveBtn = document.getElementById('pdf-save');

                var pdfDoc = null;
                var originalBytes = null;
                var currentPage = 1;
                var scale = 1.35;
                var addTextMode = false;
                var annotations = [];
                var saving = false;

                if (typeof pdfjsLib !== 'undefined') {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                }

                function showStatus(message, isError) {
                    if (!saveStatus) return;
                    saveStatus.style.display = 'block';
                    saveStatus.style.background = isError ? '#fef2f2' : '#e8f4ec';
                    saveStatus.style.borderColor = isError ? '#fecaca' : 'rgba(35,134,81,0.2)';
                    saveStatus.style.color = isError ? '#b91c1c' : '#1a5c38';
                    saveStatus.textContent = message;
                }

                function pageAnnotations(pageNum) {
                    return annotations.filter(function (a) { return a.page === pageNum; });
                }

                function renderOverlays(pageNum, viewport) {
                    overlays.innerHTML = '';
                    pageAnnotations(pageNum).forEach(function (ann, index) {
                        var el = document.createElement('textarea');
                        el.style.position = 'absolute';
                        el.style.left = ann.x + 'px';
                        el.style.top = ann.y + 'px';
                        el.style.width = ann.width + 'px';
                        el.style.minHeight = '34px';
                        el.style.padding = '6px 8px';
                        el.style.background = 'rgba(255, 255, 255, 0.92)';
                        el.style.border = '2px solid #2563eb';
                        el.style.borderRadius = '4px';
                        el.style.fontSize = '14px';
                        el.style.color = '#1e293b';
                        el.style.textAlign = 'left';
                        el.style.resize = 'both';
                        el.style.boxSizing = 'border-box';
                        el.style.fontFamily = 'Arial, sans-serif';
                        el.placeholder = 'Type here...';
                        el.value = ann.text;

                        var pageIndex = pageNum;
                        var annIndex = annotations.indexOf(ann);
                        var dragging = false;
                        var startX = 0;
                        var startY = 0;
                        var originX = ann.x;
                        var originY = ann.y;

                        el.addEventListener('input', function () {
                            ann.text = el.value;
                            ann.width = el.offsetWidth;
                        });

                        el.addEventListener('mousedown', function (e) {
                            if (e.target === el && document.activeElement === el) return;
                            dragging = true;
                            startX = e.clientX;
                            startY = e.clientY;
                            originX = ann.x;
                            originY = ann.y;
                        });

                        window.addEventListener('mousemove', function (e) {
                            if (!dragging) return;
                            ann.x = originX + (e.clientX - startX);
                            ann.y = originY + (e.clientY - startY);
                            el.style.left = ann.x + 'px';
                            el.style.top = ann.y + 'px';
                        });

                        window.addEventListener('mouseup', function () {
                            dragging = false;
                        });

                        overlays.appendChild(el);
                        if (ann.focus) {
                            ann.focus = false;
                            window.setTimeout(function () {
                                el.focus();
                                el.select();
                            }, 0);
                        }
                    });
                }

                function renderPage(pageNum) {
                    return pdfDoc.getPage(pageNum).then(function (page) {
                        var viewport = page.getViewport({ scale: scale });
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        canvas.style.width = viewport.width + 'px';
                        canvas.style.height = viewport.height + 'px';
                        overlays.style.width = viewport.width + 'px';
                        overlays.style.height = viewport.height + 'px';

                        return page.render({
                            canvasContext: canvas.getContext('2d'),
                            viewport: viewport
                        }).promise.then(function () {
                            pageLabel.textContent = 'Page ' + pageNum + ' / ' + pdfDoc.numPages;
                            renderOverlays(pageNum, viewport);
                        });
                    });
                }

                function placeText(x, y) {
                    annotations.push({
                        page: currentPage,
                        x: x,
                        y: y,
                        width: 220,
                        text: '',
                        focus: true,
                        canvasWidth: canvas.width,
                        canvasHeight: canvas.height
                    });

                    renderPage(currentPage);
                    addTextMode = false;
                    addTextBtn.style.background = '#334155';
                }

                overlays.addEventListener('click', function (event) {
                    if (!addTextMode) return;
                    if (event.target !== overlays) return;
                    var rect = overlays.getBoundingClientRect();
                    placeText(event.clientX - rect.left, event.clientY - rect.top);
                });

                document.getElementById('pdf-prev').addEventListener('click', function () {
                    if (!pdfDoc || currentPage <= 1) return;
                    currentPage -= 1;
                    renderPage(currentPage);
                });

                document.getElementById('pdf-next').addEventListener('click', function () {
                    if (!pdfDoc || currentPage >= pdfDoc.numPages) return;
                    currentPage += 1;
                    renderPage(currentPage);
                });

                addTextBtn.addEventListener('click', function () {
                    addTextMode = !addTextMode;
                    addTextBtn.style.background = addTextMode ? '#1d4ed8' : '#334155';
                });

                saveBtn.addEventListener('click', function () {
                    if (saving || !originalBytes) return;
                    saving = true;
                    saveBtn.disabled = true;
                    showStatus('Saving ' + nextName + '…', false);

                    PDFLib.PDFDocument.load(originalBytes).then(function (doc) {
                        var pages = doc.getPages();

                        annotations.forEach(function (ann) {
                            var page = pages[ann.page - 1];
                            if (!page || !ann.text.trim()) return;
                            var size = page.getSize();
                            var pdfX = (ann.x / ann.canvasWidth) * size.width;
                            var pdfY = size.height - (ann.y / ann.canvasHeight) * size.height - 14;
                            page.drawText(ann.text.trim(), {
                                x: Math.max(8, pdfX),
                                y: Math.max(8, pdfY),
                                size: 12,
                                color: PDFLib.rgb(0.1, 0.1, 0.1)
                            });
                        });

                        return doc.save();
                    }).then(function (bytes) {
                        var blob = new Blob([bytes], { type: 'application/pdf' });
                        var form = new FormData();
                        form.append('_token', csrf);
                        form.append('file', blob, 'edited.pdf');
                        form.append('return_url', returnUrl);

                        return fetch(saveUrl, {
                            method: 'POST',
                            body: form,
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                    }).then(function (res) {
                        return res.json().then(function (data) {
                            if (!res.ok || !data.success) {
                                throw new Error((data && data.message) || 'Save failed.');
                            }
                            showStatus('Saved as ' + data.new_file_name + '. Redirecting…', false);
                            window.setTimeout(function () {
                                window.location.href = returnUrl;
                            }, 1200);
                        });
                    }).catch(function (err) {
                        showStatus(err.message || 'Could not save the new version.', true);
                        saving = false;
                        saveBtn.disabled = false;
                    });
                });

                fetch(pdfUrl, { credentials: 'same-origin' })
                    .then(function (res) {
                        if (!res.ok) throw new Error('Could not load the PDF.');
                        return res.arrayBuffer();
                    })
                    .then(function (buffer) {
                        originalBytes = buffer;
                        return pdfjsLib.getDocument({ data: buffer.slice(0) }).promise;
                    })
                    .then(function (doc) {
                        pdfDoc = doc;
                        loadingEl.style.display = 'none';
                        wrapEl.style.display = 'block';
                        return renderPage(currentPage);
                    })
                    .catch(function (err) {
                        var msg = err && err.message ? err.message : 'Could not open the PDF.';
                        if (msg === 'Failed to fetch' || msg.indexOf('fetch') !== -1) {
                            msg = 'Could not load the PDF from storage. Try again or download the file first.';
                        }
                        loadingEl.innerHTML = '<p style="margin:0;color:#b91c1c;">' + msg + '</p>';
                    });
            });
        </script>
    @elseif($onlyOfficeEnabled && !empty($onlyOfficeConfig))
        <div class="card" style="margin-bottom: 12px; padding: 12px 16px; background: #f0f9ff; border: 1px solid #bae6fd;">
            <p style="margin: 0; color: #0c4a6e; font-size: 0.9rem;">
                Edit below, then press <strong>Ctrl+S</strong>. The system auto-saves as <strong>{{ $nextVersionName }}</strong> and keeps the original.
            </p>
        </div>
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span style="color: #64748b; font-size: 0.88rem;">
                    Press <strong>Ctrl+S</strong> when finished.
                </span>
                <a href="{{ request('return_url', route('documents.search')) }}" style="color: #334155; font-size: 0.88rem;">Back to search</a>
            </div>
            <div id="editor-loading" style="padding: 48px 20px; text-align: center; color: #64748b;">
                Loading editor… first open can take up to a minute while OnlyOffice starts.
            </div>
            <div id="onlyoffice-editor" style="width: 100%; height: calc(100vh - 220px); min-height: 520px; display: none;"></div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var serverUrl = @json($onlyOfficeServerUrl);
                var apiUrl = serverUrl + '/web-apps/apps/api/documents/api.js';
                var loadingEl = document.getElementById('editor-loading');
                var editorEl = document.getElementById('onlyoffice-editor');
                var saveStatus = document.getElementById('save-status');
                var statusUrl = @json(route('documents.version-save-status', ['id' => $document->id]));
                var editBaseUrl = @json(url('/documents'));
                var returnUrl = @json(request('return_url', route('documents.search')));
                var pollTimer = null;
                var saving = false;
                var wasEdited = false;

                function showError(message) {
                    if (loadingEl) {
                        loadingEl.innerHTML = '<p style="margin:0 0 8px;color:#b91c1c;"><strong>Editor could not load</strong></p><p style="margin:0;line-height:1.6;">' + message + '</p>';
                    }
                }

                function showSaved(fileName, newId) {
                    if (saveStatus) {
                        saveStatus.style.display = 'block';
                        saveStatus.textContent = 'Saved as ' + fileName + '. Opening latest version…';
                    }
                    window.setTimeout(function () {
                        window.location.href = editBaseUrl + '/' + newId + '/edit?return_url=' + encodeURIComponent(returnUrl);
                    }, 1200);
                }

                function pollSaveStatus() {
                    if (saving) return;
                    saving = true;
                    if (saveStatus) {
                        saveStatus.style.display = 'block';
                        saveStatus.textContent = 'Saving {{ $nextVersionName }}…';
                    }

                    var attempts = 0;
                    pollTimer = window.setInterval(function () {
                        attempts++;
                        fetch(statusUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data && data.saved && data.new_document_id) {
                                window.clearInterval(pollTimer);
                                showSaved(data.new_file_name || '{{ $nextVersionName }}', data.new_document_id);
                            } else if (attempts >= 30) {
                                window.clearInterval(pollTimer);
                                saving = false;
                                if (saveStatus) {
                                    saveStatus.textContent = 'Save is taking longer than expected. Check Search for {{ $nextVersionName }}.';
                                }
                            }
                        })
                        .catch(function () {
                            if (attempts >= 30) {
                                window.clearInterval(pollTimer);
                                saving = false;
                            }
                        });
                    }, 1000);
                }

                function initEditor() {
                    if (typeof DocsAPI === 'undefined') {
                        showError('OnlyOffice script did not load from ' + serverUrl);
                        return;
                    }

                    var config = @json($onlyOfficeConfig);
                    config.events = {
                        onAppReady: function () {
                            if (loadingEl) loadingEl.style.display = 'none';
                            if (editorEl) editorEl.style.display = 'block';
                        },
                        onDocumentStateChange: function (event) {
                            if (event && event.data) {
                                wasEdited = true;
                                return;
                            }
                            if (wasEdited && event && event.data === false) {
                                pollSaveStatus();
                            }
                        },
                        onRequestSave: function () {
                            pollSaveStatus();
                        },
                        onRequestSaveAs: function () {
                            pollSaveStatus();
                        },
                        onError: function () {
                            showError('OnlyOffice error. Check that Docker is running and the document server is up.');
                        }
                    };

                    new DocsAPI.DocEditor('onlyoffice-editor', config);
                }

                var script = document.createElement('script');
                script.src = apiUrl;
                script.async = true;
                script.onload = initEditor;
                script.onerror = function () {
                    showError('Cannot reach OnlyOffice at <code>' + serverUrl + '</code>. Start Docker Desktop, then run:<br><code>docker compose -f docker-compose.onlyoffice.yml up -d</code>');
                };
                document.head.appendChild(script);

                window.setTimeout(function () {
                    if (typeof DocsAPI === 'undefined' && loadingEl && loadingEl.style.display !== 'none') {
                        showError('OnlyOffice is taking too long. Make sure Docker Desktop is running, then start the editor with:<br><code>docker compose -f docker-compose.onlyoffice.yml up -d</code>');
                    }
                }, 20000);
            });
        </script>
    @else
        <div class="card" style="padding: 16px;">
            <p style="margin: 0 0 10px; color: #b91c1c;"><strong>Document editor is not running.</strong></p>
            @if(!empty($onlyOfficeConfigured) && empty($onlyOfficeReachable))
                <p style="margin: 0 0 12px; color: #64748b; line-height: 1.6;">
                    OnlyOffice is configured at <code>{{ $onlyOfficeServerUrl }}</code> but it is not reachable.
                    The editor cannot open until it is started.
                </p>
                <ol style="margin: 0; padding-left: 1.25rem; color: #334155; line-height: 1.7;">
                    <li>Open <strong>Docker Desktop</strong> and wait until it says running.</li>
                    <li>In your project folder run:<br>
                        <code style="display:inline-block;margin-top:6px;padding:6px 10px;background:#f1f5f9;border-radius:6px;">docker compose -f docker-compose.onlyoffice.yml up -d</code>
                    </li>
                    <li>Wait 1–2 minutes, then refresh this page.</li>
                </ol>
            @else
                <p style="margin: 0; color: #64748b; line-height: 1.6;">
                    Set <code>ONLYOFFICE_DOCUMENT_SERVER_URL</code> in <code>.env</code> (for example <code>http://localhost:8082</code>).
                </p>
            @endif
            <p style="margin: 14px 0 0;">
                <a href="{{ request('return_url', route('documents.search')) }}">Back to search</a>
            </p>
        </div>
    @endif
@endsection
