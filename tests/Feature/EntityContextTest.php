<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use App\Models\UserEntityAccess;
use App\Models\Project;
use App\Models\Document;
use App\Services\EntityContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_sees_sector_choices_on_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        Entity::create(['name' => 'Metaline LLC', 'business_type' => 'trading']);
        Entity::create(['name' => 'Proscape Infra', 'business_type' => 'construction']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Select Category')
            ->assertSee('Trading')
            ->assertSee('Construction')
            ->assertDontSee('Metaline LLC')
            ->assertDontSee('Proscape Infra');
    }

    public function test_trading_sector_shows_only_trading_companies(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        Entity::create(['name' => 'Metaline LLC', 'business_type' => 'trading']);
        Entity::create(['name' => 'Tanseeq LLC', 'business_type' => 'trading']);
        Entity::create(['name' => 'Stones and Slates LLC', 'business_type' => 'trading']);
        Entity::create(['name' => 'Proscape Infra', 'business_type' => 'construction']);

        $this->actingAs($admin)
            ->post(route('dashboard.sector'), ['sector' => 'trading'])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Metaline LLC')
            ->assertSee('Tanseeq LLC')
            ->assertSee('Stones and Slates LLC')
            ->assertDontSee('Proscape Infra');
    }

    public function test_construction_sector_shows_only_construction_companies(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        Entity::create(['name' => 'Metaline LLC', 'business_type' => 'trading']);
        Entity::create(['name' => 'Proscape Infra', 'business_type' => 'construction']);
        Entity::create(['name' => 'Water in Motion', 'business_type' => 'construction']);

        $this->actingAs($admin)
            ->post(route('dashboard.sector'), ['sector' => 'construction'])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Proscape Infra')
            ->assertSee('Water in Motion')
            ->assertDontSee('Metaline LLC');
    }

    public function test_user_sees_only_assigned_entities_after_sector_select(): void
    {
        $user = User::factory()->create();
        $assigned = Entity::create(['name' => 'Metaline LLC', 'business_type' => 'trading']);
        Entity::create(['name' => 'Tanseeq LLC', 'business_type' => 'trading']);

        UserEntityAccess::create([
            'user_id' => $user->id,
            'entity_id' => $assigned->id,
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.sector'), ['sector' => 'trading'])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Metaline LLC')
            ->assertDontSee('Tanseeq LLC');
    }

    public function test_opening_entity_sets_context_and_scopes_workspace(): void
    {
        $user = User::factory()->create();
        $entity = Entity::create(['name' => 'Workspace Co']);
        UserEntityAccess::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
        ]);

        $this->actingAs($user)
            ->post(route('entities.enter', $entity))
            ->assertRedirect(route('workspace'));

        $this->actingAs($user)
            ->get(route('workspace'))
            ->assertOk()
            ->assertSee('Workspace Co');
    }

    public function test_upload_requires_entity_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.upload'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_project_master_is_scoped_to_current_entity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entityA = Entity::create(['name' => 'Entity A']);
        $entityB = Entity::create(['name' => 'Entity B']);

        Project::create([
            'entity_id' => $entityA->id,
            'project_number' => 'A-001',
            'project_name' => 'Alpha Project',
        ]);
        Project::create([
            'entity_id' => $entityB->id,
            'project_number' => 'B-001',
            'project_name' => 'Beta Project',
        ]);

        app(EntityContextService::class)->set($admin, $entityA->id);

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Alpha Project')
            ->assertDontSee('Beta Project');
    }

    public function test_non_admin_cannot_access_project_master(): void
    {
        $user = User::factory()->create();
        $entity = Entity::create(['name' => 'Entity A']);
        UserEntityAccess::create(['user_id' => $user->id, 'entity_id' => $entity->id]);

        app(EntityContextService::class)->set($user, $entity->id);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_project_create_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entity = Entity::create(['name' => 'Create Form Co']);

        app(EntityContextService::class)->set($admin, $entity->id);

        $this->actingAs($admin)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('Add Project')
            ->assertSee('Create Form Co')
            ->assertSee('Save Project');
    }

    public function test_admin_can_add_project_for_current_entity_via_project_master(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entityA = Entity::create(['name' => 'Entity A']);
        $entityB = Entity::create(['name' => 'Entity B']);

        app(EntityContextService::class)->set($admin, $entityA->id);

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'entity_id' => $entityA->id,
                'project_number' => 'A-NEW',
                'project_name' => 'New Alpha Project',
            ])
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'entity_id' => $entityA->id,
            'project_number' => 'A-NEW',
            'project_name' => 'New Alpha Project',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'entity_id' => $entityB->id,
                'project_number' => 'B-NEW',
                'project_name' => 'Wrong Entity Project',
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_enter_unassigned_entity(): void
    {
        $user = User::factory()->create();
        $entity = Entity::create(['name' => 'Restricted Co']);

        $this->actingAs($user)
            ->post(route('entities.enter', $entity))
            ->assertForbidden();
    }

    public function test_dashboard_clears_entity_context(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entity = Entity::create(['name' => 'Clear Test Co']);

        app(EntityContextService::class)->set($admin, $entity->id);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertNull(app(EntityContextService::class)->getId($admin));
    }

    public function test_workspace_shows_latest_five_uploads_for_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entity = Entity::create(['name' => 'Recent Docs Co']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'R-001',
            'project_name' => 'Recent Project',
        ]);

        for ($i = 1; $i <= 7; $i++) {
            Document::create([
                'entity_id' => $entity->id,
                'project_id' => $project->id,
                'file_name' => "file-{$i}.pdf",
                'file_path' => "documents/test/file-{$i}.pdf",
                'document_type' => 'Other',
                'created_at' => now()->subDays(7 - $i),
                'updated_at' => now()->subDays(7 - $i),
            ]);
        }

        app(EntityContextService::class)->set($admin, $entity->id);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('file-7.pdf');

        $this->actingAs($admin)
            ->get(route('workspace'))
            ->assertOk()
            ->assertSee('Latest uploads')
            ->assertSee('file-7.pdf')
            ->assertSee('file-6.pdf')
            ->assertSee('file-3.pdf')
            ->assertDontSee('file-2.pdf');
    }
}
