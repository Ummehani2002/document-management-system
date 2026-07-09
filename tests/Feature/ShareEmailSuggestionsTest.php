<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareEmailSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_at_least_two_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('documents.share.email-suggestions', ['q' => 'm']))
            ->assertOk()
            ->assertJson(['suggestions' => []]);
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
            ])
            ->assertJsonMissing([
                'email' => 'other@tanseeqinvestment.com',
            ]);
    }

    public function test_guest_cannot_fetch_suggestions(): void
    {
        $this->getJson(route('documents.share.email-suggestions', ['q' => 'moha']))
            ->assertUnauthorized();
    }
}
