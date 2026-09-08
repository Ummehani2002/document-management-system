@extends('layouts.app')

@section('content')

<h2>Edit Entity</h2>
<p style="color: #64748b; margin-bottom: 20px;"><a href="{{ route('entities.index') }}">← Back to Entities</a></p>

@if ($errors->any())
    <div class="card" style="background: #fef2f2; border-color: #fecaca;">
        <ul style="margin: 0; padding-left: 20px; color: #b91c1c;">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('entities.update', $entity) }}">
    @csrf
    @method('PATCH')

    <div class="card">
        <label for="name">Entity Name *</label>
        <input type="text" name="name" id="name" value="{{ old('name', $entity->name) }}" placeholder="Enter entity name" required>

        <label for="business_type" style="margin-top: 16px; display: block;">Business Type *</label>
        <select name="business_type" id="business_type" required>
            <option value="trading" @selected(old('business_type', $entity->business_type) === 'trading')>Trading</option>
            <option value="construction" @selected(old('business_type', $entity->business_type) === 'construction')>Construction</option>
        </select>
        <p style="margin: 8px 0 0; color: #64748b; font-size: 0.85rem;">This decides whether the company appears under Trading or Construction on Home.</p>
    </div>

    <div style="margin-top: 20px;">
        <button type="submit">Save Entity</button>
        <a href="{{ route('entities.index') }}" style="margin-left: 12px; color: #64748b;">Cancel</a>
    </div>
</form>

@endsection
