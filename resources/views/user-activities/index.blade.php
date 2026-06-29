@extends('layouts.app')

@section('content')
    <h2>User Activity Log</h2>
    <p style="color: #64748b; margin-top: -8px;">Track document uploads, replacements, re-attaches, and deletions.</p>

    <form method="GET" action="{{ route('user-activities.index') }}" class="card" style="margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
        <div style="min-width: 220px;">
            <label for="user_id" style="display: block; margin-bottom: 6px;">User</label>
            <select name="user_id" id="user_id" style="width: 100%; margin: 0;">
                <option value="">All users</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ (int) $selectedUserId === (int) $user->id ? 'selected' : '' }}>
                        {{ $user->name }}@if($user->username) ({{ $user->username }})@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div style="min-width: 220px;">
            <label for="action" style="display: block; margin-bottom: 6px;">Action</label>
            <select name="action" id="action" style="width: 100%; margin: 0;">
                <option value="">All actions</option>
                @foreach($actions as $value => $label)
                    <option value="{{ $value }}" {{ $selectedAction === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Filter</button>
        @if($selectedUserId || $selectedAction !== '')
            <a href="{{ route('user-activities.index') }}">Clear</a>
        @endif
    </form>

    <div class="card" style="padding: 0; overflow-x: auto;">
        @if($activities->isEmpty())
            <p style="margin: 0; padding: 16px;">No activity recorded yet.</p>
        @else
            <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
                <thead>
                    <tr style="background: #212d3e; color: #fff;">
                        <th style="text-align: left; padding: 10px 12px;">When</th>
                        <th style="text-align: left; padding: 10px 12px;">User</th>
                        <th style="text-align: left; padding: 10px 12px;">Action</th>
                        <th style="text-align: left; padding: 10px 12px;">Document</th>
                        <th style="text-align: left; padding: 10px 12px;">Project / Entity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activities as $activity)
                        @php
                            $props = $activity->properties ?? [];
                            $fileName = $props['file_name'] ?? ($activity->document?->file_name ?? '—');
                            $docType = $props['document_type'] ?? ($activity->document?->document_type ?? '—');
                            $projectLabel = $activity->document?->project?->project_number
                                ?? (isset($props['project_id']) ? 'Project #'.$props['project_id'] : '—');
                            $entityLabel = $activity->document?->entity?->name
                                ?? (isset($props['entity_id']) ? 'Entity #'.$props['entity_id'] : '—');
                        @endphp
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 12px; white-space: nowrap;">
                                {{ format_model_datetime($activity, 'created_at') }}
                            </td>
                            <td style="padding: 10px 12px;">
                                {{ $activity->user?->name ?? 'System' }}
                                @if($activity->user?->username)
                                    <br><span style="font-size: 0.85rem; color: #64748b;">{{ $activity->user->username }}</span>
                                @endif
                            </td>
                            <td style="padding: 10px 12px;">{{ $activity->actionLabel() }}</td>
                            <td style="padding: 10px 12px;">
                                <span style="word-break: break-word;">{{ $fileName }}</span>
                                @if($docType && $docType !== '—')
                                    <br><span style="font-size: 0.85rem; color: #64748b;">{{ $docType }}</span>
                                @endif
                            </td>
                            <td style="padding: 10px 12px;">
                                {{ $projectLabel }}
                                <br><span style="font-size: 0.85rem; color: #64748b;">{{ $entityLabel }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding: 12px 16px;">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
@endsection
