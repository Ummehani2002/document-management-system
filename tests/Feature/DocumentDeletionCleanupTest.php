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

test('admin project delete moves documents to trash for restore', function () {
    $admin = User::factory()->create(['username' => 'adminuser']);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    ['project' => $project, 'document' => $document] = makeProjectWithDocument($admin, 'cascade.pdf');

    $this->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
    expect(Project::withTrashed()->whereKey($project->id)->exists())->toBeTrue();
    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
    expect(Document::withTrashed()->whereKey($document->id)->exists())->toBeTrue();
    expect(Storage::disk(config('filesystems.default'))->exists('documents/test/cascade.pdf'))->toBeTrue();

    $activity = UserActivity::query()->where('action', UserActivity::ACTION_DELETED)->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['file_name'])->toBe('cascade.pdf');
    expect($activity->properties['project_number'])->toBe('PSE20269999');
    expect($activity->properties['deleted_via'])->toBe('project');

    $this->get(route('documents.trash'))
        ->assertOk()
        ->assertSee('cascade.pdf');

    $this->post(route('documents.trash.restore', ['id' => $document->id]))
        ->assertRedirect();

    expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
    expect(Project::query()->whereKey($project->id)->exists())->toBeTrue();
});

test('non admin cannot delete an entity', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ['entity' => $entity] = makeProjectWithDocument($user);

    $this->delete(route('entities.destroy', $entity))
        ->assertForbidden();

    expect(Entity::query()->whereKey($entity->id)->exists())->toBeTrue();
});

test('admin entity delete moves nested documents to trash', function () {
    $admin = User::factory()->create(['username' => 'entityadmin']);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    ['entity' => $entity, 'project' => $project, 'document' => $document] = makeProjectWithDocument($admin, 'entity-doc.pdf');

    $this->delete(route('entities.destroy', $entity))
        ->assertRedirect(route('entities.index'));

    expect(Entity::query()->whereKey($entity->id)->exists())->toBeFalse();
    expect(Entity::withTrashed()->whereKey($entity->id)->exists())->toBeTrue();
    expect(Project::withTrashed()->whereKey($project->id)->exists())->toBeTrue();
    expect(Document::withTrashed()->whereKey($document->id)->exists())->toBeTrue();
    expect(Storage::disk(config('filesystems.default'))->exists('documents/test/entity-doc.pdf'))->toBeTrue();

    $activity = UserActivity::query()->where('action', UserActivity::ACTION_DELETED)->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['deleted_via'])->toBe('entity');
    expect($activity->properties['file_name'])->toBe('entity-doc.pdf');

    $this->get(route('documents.trash'))
        ->assertOk()
        ->assertSee('entity-doc.pdf');
});

test('document delete moves file to trash and keeps storage for restore', function () {
    $admin = User::factory()->create(['username' => 'deleter']);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    ['document' => $document] = makeProjectWithDocument($admin, 'solo.pdf');

    $this->delete(route('documents.destroy', ['id' => $document->id]))
        ->assertRedirect();

    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
    expect(Document::withTrashed()->whereKey($document->id)->exists())->toBeTrue();
    expect(Storage::disk(config('filesystems.default'))->exists('documents/test/solo.pdf'))->toBeTrue();

    $activity = UserActivity::query()->where('action', UserActivity::ACTION_DELETED)->first();
    expect($activity)->not->toBeNull();
    expect($activity->document_id)->toBe($document->id);
    expect($activity->properties['project_number'])->toBe('PSE20269999');
    expect($activity->properties['created_by'])->toBe('deleter');
    expect($activity->properties['file_size'] ?? null)->not->toBeNull();

    $this->get(route('user-activities.index'))
        ->assertOk()
        ->assertSee('PSE20269999')
        ->assertSee('Cascade Project')
        ->assertSee('Cascade Client')
        ->assertSee('solo.pdf')
        ->assertSee('Restore');

    $this->post(route('documents.restore', ['id' => $document->id]))
        ->assertRedirect();

    expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
    expect(UserActivity::query()->where('action', UserActivity::ACTION_RESTORED)->exists())->toBeTrue();
});

test('trash page can restore and permanently delete', function () {
    $admin = User::factory()->create(['username' => 'trashadmin']);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    ['document' => $document] = makeProjectWithDocument($admin, 'trash-me.pdf');

    $this->delete(route('documents.destroy', ['id' => $document->id]))->assertRedirect();

    $this->get(route('documents.trash'))
        ->assertOk()
        ->assertSee('trash-me.pdf')
        ->assertSee('Restore');

    $this->post(route('documents.trash.restore', ['id' => $document->id]))
        ->assertRedirect();
    expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();

    $this->delete(route('documents.destroy', ['id' => $document->id]))->assertRedirect();
    $this->delete(route('documents.trash.force-destroy', ['id' => $document->id]))
        ->assertRedirect();

    expect(Document::withTrashed()->whereKey($document->id)->exists())->toBeFalse();
    expect(Storage::disk(config('filesystems.default'))->exists('documents/test/trash-me.pdf'))->toBeFalse();
});
