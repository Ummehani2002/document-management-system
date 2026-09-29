<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyEmailDomainAuthAndShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_mentions_llc_and_projects_domains(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('tanseeqllc.com', false)
            ->assertSee('tanseeqprojects.com', false);
    }

    public function test_microsoft_login_rejects_non_company_email(): void
    {
        $this->from(route('login'))
            ->post(route('login.microsoft'), [
                'email' => 'someone@gmail.com',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('microsoft');
    }

    public function test_microsoft_login_accepts_llc_and_projects_emails(): void
    {
        $this->from(route('login'))
            ->post(route('login.microsoft'), [
                'email' => 'hani@tanseeqllc.com',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('microsoft');

        $this->from(route('login'))
            ->post(route('login.microsoft'), [
                'email' => 'hani@tanseeqprojects.com',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('microsoft');

        $llcErrors = session('errors');
        $this->assertNotNull($llcErrors);
        $this->assertStringContainsString(
            'not configured',
            strtolower((string) $llcErrors->first('microsoft'))
        );
    }

    public function test_share_rejects_non_company_recipient(): void
    {
        $user = User::factory()->create(['email' => 'sender@tanseeqllc.com']);
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
            'file_name' => 'note.pdf',
            'file_path' => 'documents/acme/p1/other/note.pdf',
        ]);

        $this->actingAs($user)
            ->from(route('documents.search'))
            ->post(route('documents.share', ['id' => $document->id]), [
                'email' => 'outside@gmail.com',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('share_email_'.$document->id);
    }

    public function test_share_suggestions_include_llc_and_projects_but_not_gmail(): void
    {
        $user = User::factory()->create(['email' => 'sender@tanseeqllc.com']);
        User::factory()->create([
            'name' => 'LLC User',
            'email' => 'colleague@tanseeqllc.com',
        ]);
        User::factory()->create([
            'name' => 'Projects User',
            'email' => 'colleague@tanseeqprojects.com',
        ]);
        User::factory()->create([
            'name' => 'Gmail User',
            'email' => 'someone@gmail.com',
        ]);

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'colleague']))
            ->assertOk()
            ->assertJsonFragment(['email' => 'colleague@tanseeqllc.com'])
            ->assertJsonFragment(['email' => 'colleague@tanseeqprojects.com']);

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'someone']))
            ->assertOk()
            ->assertJsonMissing(['email' => 'someone@gmail.com']);
    }
}
