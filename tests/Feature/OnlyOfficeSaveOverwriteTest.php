<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentVersionSaver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlyOfficeSaveOverwriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin');
    }

    public function test_overwrite_from_contents_updates_same_document_row(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create();
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Test',
        ]);

        $path = 'documents/acme/p1/other/sheet.xlsx';
        Storage::disk(config('filesystems.default'))->put($path, 'old-bytes');

        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'sheet.xlsx',
            'file_path' => $path,
        ]);

        $saved = (new DocumentVersionSaver)->overwriteFromContents($document, 'new-excel-bytes', $user->id);

        $this->assertSame($document->id, $saved->id);
        $this->assertSame(1, Document::query()->where('project_id', $project->id)->count());
        $this->assertSame('new-excel-bytes', Storage::disk(config('filesystems.default'))->get($path));
        $this->assertSame($user->id, $saved->modified_by_user_id);
    }

    public function test_onlyoffice_callback_overwrites_file_on_force_save_status(): void
    {
        Storage::fake(config('filesystems.default'));
        config(['services.onlyoffice.document_server_url' => 'https://onlyoffice.test']);

        $user = User::factory()->create();
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Test',
        ]);

        $path = 'documents/acme/p1/other/budget.xlsx';
        Storage::disk(config('filesystems.default'))->put($path, 'before');

        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'budget.xlsx',
            'file_path' => $path,
        ]);

        Http::fake([
            'http://127.0.0.1/cache/files/edited.xlsx' => Http::response('should-not-use', 500),
            'https://onlyoffice.test/cache/files/edited.xlsx' => Http::response('edited-xlsx-content', 200),
        ]);

        $this->postJson(route('onlyoffice.callback', ['id' => $document->id]), [
            'status' => 6,
            'url' => 'http://127.0.0.1/cache/files/edited.xlsx',
            'actions' => [['type' => 2, 'userid' => (string) $user->id]],
        ])
            ->assertOk()
            ->assertJson(['error' => 0]);

        $document->refresh();
        $this->assertSame(1, Document::count());
        $this->assertSame('edited-xlsx-content', Storage::disk(config('filesystems.default'))->get($path));
        $this->assertSame($user->id, $document->modified_by_user_id);
    }

    public function test_overwrite_upload_replaces_excel_in_place(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create();
        $user->assignRole('Admin');
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Test',
        ]);

        $path = 'documents/acme/p1/other/sheet.xlsx';
        Storage::disk(config('filesystems.default'))->put($path, 'old');

        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'sheet.xlsx',
            'file_path' => $path,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('sheet.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user)
            ->post(route('documents.overwrite', ['id' => $document->id]), [
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertSame(1, Document::count());
        $this->assertNotSame('old', Storage::disk(config('filesystems.default'))->get($path));
    }

    public function test_force_save_endpoint_calls_onlyoffice_command_service(): void
    {
        config([
            'services.onlyoffice.document_server_url' => 'http://onlyoffice.test',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Admin');
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Test',
        ]);
        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'budget.xlsx',
            'file_path' => 'documents/acme/p1/other/budget.xlsx',
        ]);

        Http::fake([
            'http://onlyoffice.test/coauthoring/CommandService.ashx' => Http::response(['error' => 0, 'key' => 'abc'], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('documents.office-forcesave', ['id' => $document->id]), [
                'key' => 'abc-document-key',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'CommandService.ashx')
                && ($request['c'] ?? null) === 'forcesave'
                && ($request['key'] ?? null) === 'abc-document-key';
        });
    }
}
