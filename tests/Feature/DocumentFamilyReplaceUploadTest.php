<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentFileVersioning;
use App\Services\EntityContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentFamilyReplaceUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_drawing_revision_names_share_one_family(): void
    {
        $rev01 = 'PJE20231001-BK-SD-0001 Rev01 AAN.pdf';
        $rev02 = 'PJE20231001-BK-SD-0001 REV-02 AAN.pdf';
        $r01 = 'SD-0002-R01-Plot B4 Ramp Layout, Section Detalls & Elevation Details Sheet 1,2&3_B. Approved as Noted.pdf';
        $r02 = 'SD-0002-R-02-Plot B4 Ramp Layout, Section Detalls & Elevation Details Sheet 1,2&3_B. Approved as Noted.pdf';

        $this->assertSame(
            DocumentFileVersioning::logicalFamilyKey($rev01),
            DocumentFileVersioning::logicalFamilyKey($rev02)
        );
        $this->assertSame(
            DocumentFileVersioning::logicalFamilyKey($r01),
            DocumentFileVersioning::logicalFamilyKey($r02)
        );
        $this->assertNotSame(
            DocumentFileVersioning::logicalFamilyKey($rev01),
            DocumentFileVersioning::logicalFamilyKey($r01)
        );
    }

    public function test_second_revision_replaces_existing_document_in_same_project(): void
    {
        Storage::fake(config('filesystems.default'));

        [$user, $entity, $project] = $this->seedProject();

        $first = UploadedFile::fake()->create('PJE20231001-BK-SD-0001 Rev01 AAN.pdf', 40, 'application/pdf');
        $second = UploadedFile::fake()->create('PJE20231001-BK-SD-0001 REV-02 AAN.pdf', 48, 'application/pdf');

        $this->postDocuments($user, $entity, $project, [$first]);
        $this->postDocuments($user, $entity, $project, [$second]);

        $this->assertSame(1, Document::query()->where('project_id', $project->id)->count());
        $this->assertSame(
            'PJE20231001-BK-SD-0001 REV-02 AAN.pdf',
            Document::query()->where('project_id', $project->id)->value('file_name')
        );
        $this->assertSame(0, Document::onlyTrashed()->where('project_id', $project->id)->count());
    }

    public function test_two_revisions_in_one_upload_keep_a_single_row(): void
    {
        Storage::fake(config('filesystems.default'));

        [$user, $entity, $project] = $this->seedProject();

        $rev01 = UploadedFile::fake()->create('SD-0002-R01-Plot B4 Ramp.pdf', 30, 'application/pdf');
        $rev02 = UploadedFile::fake()->create('SD-0002-R-02-Plot B4 Ramp.pdf', 36, 'application/pdf');

        $this->postDocuments($user, $entity, $project, [$rev01, $rev02]);

        $this->assertSame(1, Document::query()->where('project_id', $project->id)->count());
    }

    public function test_same_document_can_exist_once_per_project(): void
    {
        Storage::fake(config('filesystems.default'));

        [$user, $entity, $projectA] = $this->seedProject();
        $projectB = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'PJE20231002',
            'project_name' => 'Plot B',
        ]);

        $fileA = UploadedFile::fake()->create('PJE20231001-BK-SD-0001 Rev01 AAN.pdf', 40, 'application/pdf');
        $fileB = UploadedFile::fake()->create('PJE20231001-BK-SD-0001 Rev01 AAN.pdf', 40, 'application/pdf');

        $this->postDocuments($user, $entity, $projectA, [$fileA]);
        $this->postDocuments($user, $entity, $projectB, [$fileB]);

        $this->assertSame(1, Document::query()->where('project_id', $projectA->id)->count());
        $this->assertSame(1, Document::query()->where('project_id', $projectB->id)->count());
    }

    public function test_same_filename_in_same_project_is_skipped_as_duplicate(): void
    {
        Storage::fake(config('filesystems.default'));

        [$user, $entity, $project] = $this->seedProject();

        $first = UploadedFile::fake()->create('P18S6G_N.PDF', 40, 'application/pdf');
        $second = UploadedFile::fake()->create('P18S6G_N.PDF', 40, 'application/pdf');

        $this->postDocuments($user, $entity, $project, [$first]);
        $this->postDocuments($user, $entity, $project, [$second]);

        $this->assertSame(1, Document::query()->where('project_id', $project->id)->count());
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    protected function postDocuments(User $user, Entity $entity, Project $project, array $files): void
    {
        $this->actingAs($user)
            ->withSession([EntityContextService::SESSION_KEY => $entity->id])
            ->post(route('documents.store'), [
                'upload_mode' => 'auto',
                'entity_id' => $entity->id,
                'project_id' => $project->id,
                'documents' => $files,
            ])
            ->assertRedirect();
    }

    /**
     * @return array{0:User,1:Entity,2:Project}
     */
    protected function seedProject(): array
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'PJE20231001',
            'project_name' => 'Demo Project',
        ]);

        return [$user, $entity, $project];
    }
}
