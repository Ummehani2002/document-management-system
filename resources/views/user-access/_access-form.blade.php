<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Access level</h3>
    <label>
        <select name="role" style="min-width: 220px; padding: 8px;">
            <option value="">— Limited access —</option>
            @foreach ($roles as $roleName)
                <option value="{{ $roleName }}" @selected(old('role', $user?->roles->first()?->name) === $roleName)>{{ $roleName }}</option>
            @endforeach
        </select>
    </label>
    <p style="color: #64748b; margin: 8px 0 0;">
        Choose <strong>Admin</strong> for access to <em>all</em> PDFs and folders.
        Otherwise set entities, folders, and/or specific files below.
    </p>
</div>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Entity &amp; folder access</h3>
    <p style="color: #64748b;">
        Check an entity for access to all its documents, or restrict to certain folders.
        Leave folder boxes unchecked to allow <strong>every folder</strong> in that entity.
    </p>

    @if($entities->isEmpty())
        <p>No entities yet. <a href="{{ route('entities.create') }}">Create an entity</a> first.</p>
    @else
        @foreach ($entities as $entity)
            @php
                $entityChecked = in_array($entity->id, $selectedEntityIds, true);
                $entityFolderKeys = $selectedFolders[$entity->id] ?? [];
            @endphp
            <div class="access-entity-block" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                    <input
                        type="checkbox"
                        name="entity_ids[]"
                        value="{{ $entity->id }}"
                        class="entity-toggle"
                        data-entity="{{ $entity->id }}"
                        @checked($entityChecked)
                    >
                    <strong>{{ $entity->name }}</strong>
                </label>

                <div class="entity-folders" data-entity-folders="{{ $entity->id }}" style="margin-top: 14px; @if(!$entityChecked) display: none; @endif">
                    @foreach ($folderTree as $mainFolder => $subfolders)
                        <div style="margin-bottom: 12px;">
                            <div style="font-weight: 500; margin-bottom: 6px;">{{ $mainFolder }}</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px 12px; padding-left: 8px;">
                                @foreach ($subfolders as $sub)
                                    @php $key = $mainFolder.'|'.$sub; @endphp
                                    <label style="display: flex; align-items: flex-start; gap: 6px; cursor: pointer;">
                                        <input
                                            type="checkbox"
                                            name="folders[{{ $entity->id }}][]"
                                            value="{{ $key }}"
                                            class="folder-check"
                                            data-entity="{{ $entity->id }}"
                                            @checked(in_array($key, $entityFolderKeys, true))
                                        >
                                        <span>{{ $sub }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Specific documents (optional)</h3>
    <p style="color: #64748b;">
        Grant access to individual PDFs/files — useful when a user should see only certain documents,
        or selected files <em>in addition to</em> folder access above.
    </p>

    @if($grantedDocuments->isNotEmpty())
        <div style="margin-bottom: 16px;">
            <div style="font-weight: 500; margin-bottom: 8px;">Currently granted</div>
            @foreach ($grantedDocuments as $doc)
                <label style="display:flex; gap:8px; align-items:flex-start; margin-bottom:6px;">
                    <input type="checkbox" name="document_ids[]" value="{{ $doc->id }}" checked>
                    <span>{{ $doc->file_name }} <span style="color:#64748b;">({{ $doc->document_type ?: 'Other' }})</span></span>
                </label>
            @endforeach
        </div>
    @endif

    @if($isEdit ?? false)
        @if($documentResults->isNotEmpty())
            <div style="max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px;">
                <div style="font-weight: 500; margin-bottom: 8px;">Search results — check to add</div>
                @foreach ($documentResults as $doc)
                    @if(!in_array($doc->id, $selectedDocumentIds, true))
                        <label style="display:flex; gap:8px; align-items:flex-start; margin-bottom:6px;">
                            <input type="checkbox" name="document_ids[]" value="{{ $doc->id }}">
                            <span>
                                {{ $doc->file_name }}
                                <span style="color:#64748b;">— {{ $doc->entity?->name ?? 'Entity' }} / {{ $doc->document_type ?: 'Other' }}</span>
                            </span>
                        </label>
                    @endif
                @endforeach
            </div>
            <p style="color:#64748b; margin:8px 0 0;">Then click <strong>Save access</strong> below.</p>
        @elseif($documentSearch !== '')
            <p style="color:#64748b; margin:0;">No new results. Try another search term.</p>
        @else
            <p style="color:#64748b; margin:0;">Search by file name above to grant individual documents.</p>
        @endif
    @else
        <p style="color:#64748b;">After creating the user, open <strong>Manage access</strong> to search and attach specific documents.</p>
    @endif
</div>

<p style="margin-top: 20px;">
    <button type="submit" style="padding: 10px 24px; background: #212d3e; color: #fff; border: none; border-radius: 5px; cursor: pointer;">
        {{ ($isEdit ?? false) ? 'Save access' : 'Add user' }}
    </button>
</p>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.entity-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var entityId = toggle.getAttribute('data-entity');
                var panel = document.querySelector('[data-entity-folders="' + entityId + '"]');
                if (!panel) return;
                panel.style.display = toggle.checked ? '' : 'none';
                if (!toggle.checked) {
                    panel.querySelectorAll('.folder-check').forEach(function (cb) {
                        cb.checked = false;
                    });
                }
            });
        });
    });
</script>
