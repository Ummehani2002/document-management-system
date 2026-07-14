<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserEntityAccess;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_authenticated_user_can_open_summary_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('summary-dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Entity-wise')
            ->assertSee('Download report (CSV)');
    }

    public function test_summary_dashboard_respects_document_access_scope(): void
    {
        $entityA = Entity::create(['name' => 'Alpha']);
        $entityB = Entity::create(['name' => 'Beta']);
        $projectA = Project::create([
            'entity_id' => $entityA->id,
            'project_number' => 'A-001',
            'project_name' => 'Alpha Project',
        ]);
        $projectB = Project::create([
            'entity_id' => $entityB->id,
            'project_number' => 'B-001',
            'project_name' => 'Beta Project',
        ]);

        Document::create([
            'entity_id' => $entityA->id,
            'project_id' => $projectA->id,
            'document_type' => 'Invoice',
            'file_name' => 'alpha.pdf',
            'file_path' => 'documents/alpha.pdf',
            'created_at' => now()->subDays(2),
        ]);
        Document::create([
            'entity_id' => $entityB->id,
            'project_id' => $projectB->id,
            'document_type' => 'Drawing',
            'file_name' => 'beta.pdf',
            'file_path' => 'documents/beta.pdf',
            'created_at' => now()->subDay(),
        ]);

        $user = User::factory()->create();
        UserEntityAccess::create(['user_id' => $user->id, 'entity_id' => $entityA->id]);

        $response = $this->actingAs($user)->get(route('summary-dashboard'));

        $response->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Beta Project');
    }

    public function test_summary_dashboard_filters_by_date_range(): void
    {
        $entity = Entity::create(['name' => 'Alpha']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'A-001',
            'project_name' => 'Alpha Project',
        ]);

        $old = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Invoice',
            'file_name' => 'old.pdf',
            'file_path' => 'documents/old.pdf',
        ]);
        $old->created_at = '2026-01-10 10:00:00';
        $old->saveQuietly();

        $new = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Invoice',
            'file_name' => 'new.pdf',
            'file_path' => 'documents/new.pdf',
        ]);
        $new->created_at = '2026-03-15 10:00:00';
        $new->saveQuietly();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('summary-dashboard', [
                'date_from' => '2026-03-01',
                'date_to' => '2026-03-31',
            ]))
            ->assertOk()
            ->assertSee('Entity-wise report')
            ->assertSee('1 document(s)');
    }

    public function test_summary_dashboard_filters_by_entity_project_and_folder(): void
    {
        $entity = Entity::create(['name' => 'Alpha']);
        $projectA = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'A-001',
            'project_name' => 'Alpha One',
        ]);
        $projectB = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'A-002',
            'project_name' => 'Alpha Two',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $projectA->id,
            'document_type' => 'Invoice',
            'file_name' => 'invoice-a.pdf',
            'file_path' => 'documents/invoice-a.pdf',
        ]);
        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $projectB->id,
            'document_type' => 'Shop Drawing',
            'file_name' => 'drawing-b.pdf',
            'file_path' => 'documents/drawing-b.pdf',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('summary-dashboard', [
                'entity_id' => $entity->id,
                'project_id' => $projectA->id,
                'main_folder' => 'Financial Documents',
                'document_type' => 'Invoice',
                'tab' => 'category',
            ]))
            ->assertOk()
            ->assertSee('Category-wise report')
            ->assertSee('1 document(s)')
            ->assertSee('Invoice');
    }

    public function test_summary_dashboard_download_returns_csv_for_active_tab(): void
    {
        $entity = Entity::create(['name' => 'Alpha']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'A-001',
            'project_name' => 'Alpha Project',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Invoice',
            'file_name' => 'alpha.pdf',
            'file_path' => 'documents/alpha.pdf',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)
            ->get(route('summary-dashboard.download', ['tab' => 'entity']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Alpha', $response->streamedContent());
        $this->assertStringContainsString('Entity', $response->streamedContent());
    }

    public function test_category_tab_hides_other_documents(): void
    {
        $entity = Entity::create(['name' => 'Alpha']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'A-001',
            'project_name' => 'Alpha Project',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Invoice',
            'file_name' => 'invoice.pdf',
            'file_path' => 'documents/invoice.pdf',
        ]);
        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'misc.pdf',
            'file_path' => 'documents/misc.pdf',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('summary-dashboard', ['tab' => 'category']))
            ->assertOk()
            ->assertSee('Invoice')
            ->assertSee('1 document(s)')
            ->assertDontSee('"label":"Other"', false);
    }
}
