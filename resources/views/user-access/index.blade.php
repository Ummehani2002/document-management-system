@extends('layouts.app')

@section('content')

<h2>User Access Control</h2>

@if (session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

<p style="color: #64748b; margin-bottom: 20px;">
    Grant users access by entity and document folder. Users with the <strong>Admin</strong> role have full access.
    For other users, select entities first, then choose folders and subfolders per entity.
</p>

@if($users->isEmpty())
    <div class="card">
        <p>No users found.</p>
    </div>
@else
    <div class="card">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                    <th style="padding: 10px 8px;">User</th>
                    <th style="padding: 10px 8px;">Email</th>
                    <th style="padding: 10px 8px;">Role</th>
                    <th style="padding: 10px 8px;">Entities</th>
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
                                <span style="color: #1a5c38;">Admin (full access)</span>
                            @else
                                {{ $user->roles->pluck('name')->join(', ') ?: '—' }}
                            @endif
                        </td>
                        <td style="padding: 10px 8px;">
                            @if($user->hasRole('Admin'))
                                All
                            @elseif($user->entityAccess->isEmpty())
                                <span style="color: #b91c1c;">No access</span>
                            @else
                                {{ $user->entityAccess->map(fn ($a) => $a->entity?->name)->filter()->join(', ') }}
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
