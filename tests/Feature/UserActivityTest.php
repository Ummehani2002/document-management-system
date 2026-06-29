<?php

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\UserActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user activity logger records document upload', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $entity = Entity::create(['name' => 'Test Entity']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'PSE20260001',
        'project_name' => 'Test Project',
    ]);

    $document = Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'document_type' => 'Other',
        'file_name' => 'sample.pdf',
        'file_path' => 'documents/test/sample.pdf',
    ]);

    UserActivityLogger::uploaded($document, ['upload_mode' => 'auto']);

    $activity = UserActivity::query()->first();

    expect($activity)->not->toBeNull();
    expect($activity->user_id)->toBe($user->id);
    expect($activity->action)->toBe(UserActivity::ACTION_UPLOADED);
    expect($activity->document_id)->toBe($document->id);
    expect($activity->properties['file_name'])->toBe('sample.pdf');
    expect($activity->properties['upload_mode'])->toBe('auto');
});

test('activity log page is available to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('user-activities.index'))
        ->assertOk()
        ->assertSee('User Activity Log');
});
