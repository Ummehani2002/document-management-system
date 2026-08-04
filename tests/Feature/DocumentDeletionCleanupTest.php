<?php

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake(config('filesystems.default'));
});

function makeProjectWithDocument(User $uploader, string $fileName = 'tracked.pdf'): array
{
    $entity = Entity::create(['name' => 'Test Entity']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'PSE20269999',
        'project_name' => 'Cascade Project',
        'client_name' => 'Cascade Client',
        'consultant' => 'Cascade Consultant',
    ]);

    $path = 'documents/test/'.$fileName;
    Storage::disk(config('filesystems.default'))->put($path, 'pdf-bytes-here');

    $document = Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'document_type' => 'Other',
        'file_name' => $fileName,
        'file_path' => $path,
        'discipline' => 'Architecture',
    ]);

    return compact('entity', 'project', 'document');
}

test('non admin cannot delete a project', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ['project' => $project] = makeProjectWithDocument($user);

    $this->delete(route('projects.destroy', $project))
        ->assertForbidden();

    expect(Project::query()->whereKey($project->id)->exists())->toBeTrue();
});

test('admin project delete cleans storage logs activity and removes documents', function () {
    $admin = User::factory()->create(['username' => 'adminuser']);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    ['project' => $project, 'document' => $document] = makeProjectWithDocument($admin, 'cascade.pdf');

    $this->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
    expect(Storage::disk(config('filesystems.default'))->exists('documents/test/cascade.pdf'))->toBeFalse();

    $activity = UserActivity::query()->where('action', UserActivity::ACTION_DELETED)->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['file_name'])->toBe('cascade.pdf');
    expect($activity->properties['project_number'])->toBe('PSE20269999');
    expect($activity->properties['project_name'])->toBe('Cascade Project');
    expect($activity->properties['project_client'])->toBe('Cascade Client');
    expect($activity->properties['deleted_via'])->toBe('project');
    expect($activity->properties['file_size'] ?? null)->not->toBeNull();
});

test('non admin cannot delete an entity', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ['entity' => $entity] = makeProjectWithDocument($user);

    $this->delete(route('entities.destroy', $entity))
        ->assertForbidden();

    expect(Entity::query()->whereKey($entity->id)->exists())->toBeTrue();
});

test('admin entity delete removes nested documents with activity log', function () {
    $admin = User::factory()->create(['username' => 'entityadmin']);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    ['entity' => $entity, 'document' => $document] = makeProjectWithDocument($admin, 'entity-doc.pdf');

    $this->delete(route('entities.destroy', $entity))
        ->assertRedirect(route('entities.index'));

    expect(Entity::query()->whereKey($entity->id)->exists())->toBeFalse();
    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
    expect(Storage::disk(config('filesystems.default'))->exists('documents/test/entity-doc.pdf'))->toBeFalse();

    $activity = UserActivity::query()->where('action', UserActivity::ACTION_DELETED)->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['deleted_via'])->toBe('entity');
    expect($activity->properties['file_name'])->toBe('entity-doc.pdf');
});

test('document delete snapshots metadata before storage is removed', function () {
    $admin = User::factory()->create(['username' => 'deleter']);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    ['document' => $document] = makeProjectWithDocument($admin, 'solo.pdf');

    $this->delete(route('documents.destroy', ['id' => $document->id]))
        ->assertRedirect();

    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
    expect(Storage::disk(config('filesystems.default'))->exists('documents/test/solo.pdf'))->toBeFalse();

    $activity = UserActivity::query()->where('action', UserActivity::ACTION_DELETED)->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['project_number'])->toBe('PSE20269999');
    expect($activity->properties['created_by'])->toBe('deleter');
    expect($activity->properties['file_size'] ?? null)->not->toBeNull();

    $this->get(route('user-activities.index'))
        ->assertOk()
        ->assertSee('PSE20269999')
        ->assertSee('Cascade Project')
        ->assertSee('Cascade Client')
        ->assertSee('solo.pdf');
});
