<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\UserActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShareActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin');
    }

    public function test_shared_activity_records_who_shared_to_whom(): void
    {
        $sender = User::factory()->create([
            'name' => 'Hani',
            'email' => 'hani@tanseeqllc.com',
        ]);
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Demo',
        ]);
        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'note.pdf',
            'file_path' => 'documents/acme/p1/other/note.pdf',
        ]);

        $this->actingAs($sender);
        UserActivityLogger::shared($document, [
            'shared_by' => 'Hani',
            'shared_by_email' => 'hani@tanseeqllc.com',
            'shared_to' => 'mammadhukani.s@proscapeuae.com',
        ]);

        $activity = UserActivity::query()->where('action', UserActivity::ACTION_SHARED)->first();
        $this->assertNotNull($activity);
        $this->assertSame($sender->id, $activity->user_id);
        $this->assertSame('mammadhukani.s@proscapeuae.com', $activity->properties['shared_to'] ?? null);
        $this->assertSame('hani@tanseeqllc.com', $activity->properties['shared_by_email'] ?? null);
    }

    public function test_activity_log_shows_shared_by_and_shared_to_columns(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('Admin');
        $sender = User::factory()->create([
            'name' => 'Hani',
            'email' => 'hani@tanseeqllc.com',
        ]);
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Demo',
        ]);
        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'drawing.pdf',
            'file_path' => 'documents/acme/p1/other/drawing.pdf',
        ]);

        UserActivity::create([
            'user_id' => $sender->id,
            'action' => UserActivity::ACTION_SHARED,
            'document_id' => $document->id,
            'properties' => [
                'file_name' => $document->file_name,
                'shared_by' => 'Hani',
                'shared_by_email' => 'hani@tanseeqllc.com',
                'shared_to' => 'colleague@tanseeqprojects.com',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('user-activities.index', ['action' => UserActivity::ACTION_SHARED]))
            ->assertOk()
            ->assertSee('Shared', false)
            ->assertSee('Shared By', false)
            ->assertSee('Shared To', false)
            ->assertSee('hani@tanseeqllc.com', false)
            ->assertSee('colleague@tanseeqprojects.com', false)
            ->assertSee('drawing.pdf', false);
    }
}
