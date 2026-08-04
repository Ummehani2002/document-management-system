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
        'client_name' => 'Acme Client',
        'consultant' => 'ACE Consultants',
    ]);

    $document = Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'document_type' => 'Other',
        'file_name' => 'sample.pdf',
        'file_path' => 'documents/test/sample.pdf',
        'discipline' => 'Architecture',
    ]);

    UserActivityLogger::uploaded($document, ['upload_mode' => 'auto']);

    $activity = UserActivity::query()->first();

    expect($activity)->not->toBeNull();
    expect($activity->user_id)->toBe($user->id);
    expect($activity->action)->toBe(UserActivity::ACTION_UPLOADED);
    expect($activity->document_id)->toBe($document->id);
    expect($activity->properties['file_name'])->toBe('sample.pdf');
    expect($activity->properties['upload_mode'])->toBe('auto');
    expect($activity->properties['project_number'])->toBe('PSE20260001');
    expect($activity->properties['project_name'])->toBe('Test Project');
    expect($activity->properties['project_client'])->toBe('Acme Client');
    expect($activity->properties['project_consultant'])->toBe('ACE Consultants');
    expect($activity->properties['project_discipline'])->toBe('Architecture');
    expect($activity->properties['created_by'])->toBe($user->username);
});

test('activity log page is available to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('user-activities.index'))
        ->assertOk()
        ->assertSee('User Activity Log');
});

test('activity log shows uploaded edited and deleted actions', function () {
    $user = User::factory()->create(['name' => 'Activity Tester']);
    $this->actingAs($user);

    $entity = Entity::create(['name' => 'Test Entity']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'PSE20260002',
        'project_name' => 'Activity Project',
        'client_name' => 'Beta Client',
        'consultant' => 'Delta Consulting',
    ]);

    $document = Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'document_type' => 'Other',
        'file_name' => 'tracked.pdf',
        'file_path' => 'documents/test/tracked.pdf',
        'discipline' => 'Structural',
    ]);

    UserActivityLogger::uploaded($document);
    UserActivityLogger::replaced($document);
    UserActivityLogger::deleted($document);
    $document->delete();

    $this->actingAs($user)
        ->get(route('user-activities.index'))
        ->assertOk()
        ->assertSee('Uploaded')
        ->assertSee('Edited')
        ->assertSee('Deleted')
        ->assertSee('Activity Tester')
        ->assertSee('tracked.pdf')
        ->assertSee('PSE20260002')
        ->assertSee('Activity Project')
        ->assertSee('Beta Client')
        ->assertSee('Delta Consulting')
        ->assertSee('Structural');
});

test('activity log hydrates project fields from project_id when document is gone', function () {
    $user = User::factory()->create(['name' => 'Hydrate Tester']);
    $this->actingAs($user);

    $entity = Entity::create(['name' => 'Hydrate Entity']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'PSE20268888',
        'project_name' => 'Hydrate Project',
        'client_name' => 'Hydrate Client',
        'consultant' => 'Hydrate Consulting',
    ]);

    // Older thin delete snapshots only store ids + file name.
    UserActivity::create([
        'user_id' => $user->id,
        'action' => UserActivity::ACTION_DELETED,
        'document_id' => null,
        'properties' => [
            'file_name' => 'test5.pdf',
            'document_type' => 'Other',
            'project_id' => $project->id,
            'entity_id' => $entity->id,
        ],
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($user)
        ->get(route('user-activities.index'))
        ->assertOk()
        ->assertSee('Deleted')
        ->assertSee('test5.pdf')
        ->assertSee('PSE20268888')
        ->assertSee('Hydrate Project')
        ->assertSee('Hydrate Client')
        ->assertSee('Hydrate Consulting');
});
