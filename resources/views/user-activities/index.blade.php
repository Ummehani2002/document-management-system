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
        <div style="flex: 0 0 100%;">
            <button type="submit" style="margin: 4px 0 0; padding: 9px 22px;">Filter</button>
            @if($selectedUserId || $selectedAction !== '')
                <a href="{{ route('user-activities.index') }}" style="margin-left: 10px;">Clear</a>
            @endif
        </div>
    </form>

    <div class="card dms-grid-wrap">
        @if($activities->isEmpty())
            <p style="margin: 0; padding: 16px;">No activity recorded yet.</p>
        @else
            <table class="dms-grid-table min-w-xl">
                <thead>
                    <tr>
                        <th>File Type</th>
                        <th>File Name</th>
                        <th>Date</th>
                        <th>Reference Number</th>
                        <th>Subject</th>
                        <th>Project Number</th>
                        <th>Project Name</th>
                        <th>Project Client</th>
                        <th>Project Consultant</th>
                        <th>Project Discipline</th>
                        <th>Modified Date</th>
                        <th>Modified By</th>
                        <th>Created Date</th>
                        <th>Created By</th>
                        <th>File Size</th>
                        <th>Item Child Count</th>
                        <th>Folder Child Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activities as $activity)
                        @php($row = $activity->grid_row ?? [])
                        <tr>
                            <td>{{ $row['file_type'] ?? '—' }}</td>
                            <td style="word-break: break-word;">{{ $row['file_name'] ?? '—' }}</td>
                            <td style="white-space: nowrap;">{{ $row['date'] ?? '—' }}</td>
                            <td>{{ $row['reference_no'] ?? '—' }}</td>
                            <td>{{ $row['subject'] ?? '—' }}</td>
                            <td>{{ $row['project_number'] ?? '—' }}</td>
                            <td>{{ $row['project_name'] ?? '—' }}</td>
                            <td>{{ $row['project_client'] ?? '—' }}</td>
                            <td>{{ $row['project_consultant'] ?? '—' }}</td>
                            <td>{{ $row['project_discipline'] ?? '—' }}</td>
                            <td style="white-space: nowrap;">{{ $row['modified_date'] ?? '—' }}</td>
                            <td>{{ $row['modified_by'] ?? '—' }}</td>
                            <td style="white-space: nowrap;">{{ $row['created_date'] ?? '—' }}</td>
                            <td>{{ $row['created_by'] ?? '—' }}</td>
                            <td>{{ $row['file_size'] ?? '—' }}</td>
                            <td>{{ $row['item_child_count'] ?? '0' }}</td>
                            <td>{{ $row['folder_child_count'] ?? '0' }}</td>
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
