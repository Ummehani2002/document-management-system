@extends('layouts.app')

@section('content')

<h2>User Access Control</h2>

@if (session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

<p style="color: #64748b; margin-bottom: 16px;">
    <strong>Admin</strong> — full access to every document.<br>
    <strong>Project / folder</strong> — PDFs in selected projects and optional folders.<br>
    <strong>Specific documents</strong> — individual files only (or in addition to projects).
</p>

<p style="margin-bottom: 20px;">
    <a href="{{ route('user-access.create') }}" style="display:inline-block; padding:10px 18px; background:#212d3e; color:#fff; text-decoration:none; border-radius:5px;">
        + Add user
    </a>
</p>

@if($users->isEmpty())
    <div class="card">
        <p>No users yet. Add a user or ask them to sign in with Microsoft first.</p>
    </div>
@else
    <div class="card">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                    <th style="padding: 10px 8px;">User</th>
                    <th style="padding: 10px 8px;">Email</th>
                    <th style="padding: 10px 8px;">Role</th>
                    <th style="padding: 10px 8px;">Access</th>
                    <th style="padding: 10px 8px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 10px 8px;">{{ $user->name }}</td>
                        <td style="padding: 10px 8px;">{{ $user->email }}</td>
                        <td style="padding: 10px 8px;">
                            @if($user->hasRole('Admin'))
                                <span style="color: #1a5c38;">Admin</span>
                            @else
                                {{ $user->roles->pluck('name')->join(', ') ?: '—' }}
                            @endif
                        </td>
                        <td style="padding: 10px 8px;">
                            @if($user->hasRole('Admin'))
                                All documents
                            @elseif($user->projectAccess->isEmpty() && $user->documentAccess->isEmpty())
                                <span style="color: #b91c1c;">No access</span>
                            @else
                                @if($user->projectAccess->isNotEmpty())
                                    {{ $user->projectAccess->count() }} project(s)
                                    @php
                                        $projectLabels = $user->projectAccess
                                            ->map(fn ($access) => $access->project?->project_number)
                                            ->filter()
                                            ->take(3)
                                            ->join(', ');
                                    @endphp
                                    @if($projectLabels !== '')
                                        <span style="color:#64748b;"> — {{ $projectLabels }}@if($user->projectAccess->count() > 3)…@endif</span>
                                    @endif
                                @endif
                                @if($user->documentAccess->isNotEmpty())
                                    @if($user->projectAccess->isNotEmpty())<br>@endif
                                    {{ $user->documentAccess->count() }} specific file(s)
                                @endif
                            @endif
                        </td>
                        <td style="padding: 10px 8px; text-align: right;">
                            <a href="{{ route('user-access.edit', $user) }}">Manage access</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top: 16px;">{{ $users->links() }}</div>
    </div>
@endif

@endsection
