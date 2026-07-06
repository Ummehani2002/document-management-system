<?php

use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('uploading a document records a user activity', function () {
    Storage::fake(config('filesystems.default'));

    $user = User::factory()->create();
    $entity = Entity::create(['name' => 'Acme']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'PKE20231003',
        'project_name' => 'Demo Project',
    ]);

    $file = UploadedFile::fake()->create('1TB03300-007C33-PIC-MAT-IR-0009.pdf', 40, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'upload_mode' => 'auto',
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'documents' => [$file],
        ])
        ->assertRedirect();

    $activity = UserActivity::query()->where('action', UserActivity::ACTION_UPLOADED)->first();

    expect($activity)->not->toBeNull();
    expect($activity->user_id)->toBe($user->id);
    expect($activity->properties['file_name'] ?? null)->not->toBeNull();
});
