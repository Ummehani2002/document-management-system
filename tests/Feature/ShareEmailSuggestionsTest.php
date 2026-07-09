<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MicrosoftGraphPeopleService;
use App\Services\MicrosoftTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShareEmailSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_query_returns_no_suggestions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => '']))
            ->assertOk()
            ->assertJson(['suggestions' => [], 'hint' => null]);
    }

    public function test_returns_matching_local_users_from_single_character(): void
    {
        $user = User::factory()->create(['email' => 'sender@tanseeqinvestment.com']);
        User::factory()->create([
            'name' => 'Mohammad Ali',
            'email' => 'mohammad@tanseeqinvestment.com',
        ]);

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'm']))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Mohammad Ali',
                'email' => 'mohammad@tanseeqinvestment.com',
                'group' => 'other',
            ]);
    }

    public function test_returns_matching_local_users(): void
    {
        $user = User::factory()->create(['email' => 'sender@tanseeqinvestment.com']);
        User::factory()->create([
            'name' => 'Mohammad Ali',
            'email' => 'mohammad@tanseeqinvestment.com',
        ]);
        User::factory()->create([
            'name' => 'Other Person',
            'email' => 'other@tanseeqinvestment.com',
        ]);

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'moha']))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Mohammad Ali',
                'email' => 'mohammad@tanseeqinvestment.com',
                'group' => 'other',
            ])
            ->assertJsonMissing([
                'email' => 'other@tanseeqinvestment.com',
            ]);
    }

    public function test_returns_hint_when_no_matches_and_no_graph_token(): void
    {
        $user = User::factory()->create(['email' => 'sender@tanseeqinvestment.com']);

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'zzz']))
            ->assertOk()
            ->assertJsonPath('suggestions', [])
            ->assertJsonPath('hint', 'Company directory search needs Microsoft mail permission. Click Share once and approve access, then try again.');
    }

    public function test_returns_directory_users_from_graph_search(): void
    {
        $user = User::factory()->create([
            'email' => 'sender@tanseeqinvestment.com',
            'azure_access_token' => 'test-token',
            'azure_refresh_token' => 'refresh-token',
            'azure_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://graph.microsoft.com/v1.0/me/people*' => Http::response(['value' => []]),
            'https://graph.microsoft.com/v1.0/users*' => Http::response([
                'value' => [
                    [
                        'displayName' => 'Mohammad Ali',
                        'mail' => 'mohammad@tanseeqinvestment.com',
                        'userPrincipalName' => 'mohammad@tanseeqinvestment.com',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'moh']))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Mohammad Ali',
                'email' => 'mohammad@tanseeqinvestment.com',
                'group' => 'other',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/users')
                && ($request->data()['$count'] ?? null) === 'true';
        });
    }

    public function test_returns_permission_hint_when_directory_search_is_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'sender@tanseeqinvestment.com',
            'azure_access_token' => 'test-token',
            'azure_refresh_token' => 'refresh-token',
            'azure_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://graph.microsoft.com/v1.0/me/people*' => Http::response(['value' => []]),
            'https://graph.microsoft.com/v1.0/users*' => Http::response(['error' => ['code' => 'Authorization_RequestDenied']], 403),
        ]);

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'moh']))
            ->assertOk()
            ->assertJsonPath('suggestions', [])
            ->assertJsonPath('hint', 'Company directory search is not allowed yet. Ask your IT admin to add User.ReadBasic.All and grant admin consent in Azure, then open Share and approve permissions again.');
    }

    public function test_guest_cannot_fetch_suggestions(): void
    {
        $this->getJson(route('documents.share.email-suggestions', ['q' => 'moha']))
            ->assertUnauthorized();
    }
}
