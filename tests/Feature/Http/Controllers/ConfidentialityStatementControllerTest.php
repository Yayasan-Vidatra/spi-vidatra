<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Models\ConfidentialityStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithGoogleLogin;
use Tests\TestCase;

class ConfidentialityStatementControllerTest extends TestCase
{
    use InteractsWithGoogleLogin;
    use LazilyRefreshDatabase;

    public function test_new_user_is_held_at_the_statement_before_any_page(): void
    {
        ConfidentialityStatement::factory()->create();
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create();

        $response = $this->actingAs($user)->get(route('dasbor'));

        $response->assertRedirect(route('kerahasiaan.show'));
    }

    public function test_statement_page_shows_the_current_text_from_the_database(): void
    {
        ConfidentialityStatement::factory()->create(['body' => 'Teks lama.']);
        $currentStatement = ConfidentialityStatement::factory()->create(['body' => 'Saya menjaga kerahasiaan dokumen SPI.']);
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create();

        $response = $this->actingAs($user)->get(route('kerahasiaan.show'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Keamanan/PernyataanKerahasiaan')
            ->where('statement.id', $currentStatement->id)
            ->where('statement.body', 'Saya menjaga kerahasiaan dokumen SPI.'));
    }

    public function test_acceptance_is_recorded_with_time_statement_and_client(): void
    {
        $this->travelTo('2026-11-23 08:15:00');
        $statement = ConfidentialityStatement::factory()->create();
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create();

        $response = $this->actingAs($user)
            ->withHeader('User-Agent', 'Peramban Uji')
            ->post(route('kerahasiaan.store'), ['statement_id' => $statement->id, 'agreed' => '1']);

        $response->assertRedirect(route('dasbor'));
        $this->assertDatabaseHas('confidentiality_acceptances', [
            'user_id' => $user->id,
            'confidentiality_statement_id' => $statement->id,
            'accepted_at' => '2026-11-23 08:15:00',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Peramban Uji',
        ]);
    }

    public function test_rejects_submission_without_agreement(): void
    {
        $statement = ConfidentialityStatement::factory()->create();
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create();

        $response = $this->actingAs($user)->post(route('kerahasiaan.store'), ['statement_id' => $statement->id]);

        $response->assertSessionHasErrors(['agreed' => 'Centang kotak persetujuan untuk melanjutkan.']);
        $this->assertDatabaseCount('confidentiality_acceptances', 0);
    }

    public function test_rejects_acceptance_of_an_outdated_statement(): void
    {
        $outdatedStatement = ConfidentialityStatement::factory()->create();
        ConfidentialityStatement::factory()->create();
        $user = User::factory()->withRole(RoleSlug::PicUnit)->create();

        $response = $this->actingAs($user)->post(route('kerahasiaan.store'), [
            'statement_id' => $outdatedStatement->id,
            'agreed' => '1',
        ]);

        $response->assertSessionHasErrors(['statement_id' => 'Teks pernyataan telah diperbarui. Silakan baca ulang sebelum menyetujui.']);
        $this->assertDatabaseCount('confidentiality_acceptances', 0);
    }

    public function test_statement_is_not_shown_again_on_a_later_login(): void
    {
        $user = User::factory()->withRole(RoleSlug::PicUnit)->acceptedConfidentiality()->create(['email' => 'siti@vidatra.sch.id']);
        $this->loginWithGoogle($this->googleUser('siti@vidatra.sch.id', $user->google_id));

        $response = $this->get(route('dasbor'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Dasbor'));
    }

    public function test_user_who_already_accepted_is_sent_away_from_the_statement_page(): void
    {
        $user = User::factory()->withRole(RoleSlug::PicUnit)->acceptedConfidentiality()->create();

        $response = $this->actingAs($user)->get(route('kerahasiaan.show'));

        $response->assertRedirect(route('dasbor'));
    }

    public function test_panel_is_held_at_the_statement_for_super_admin(): void
    {
        ConfidentialityStatement::factory()->create();
        $user = User::factory()->withRole(RoleSlug::SuperAdmin)->withTwoFactor()->create();

        $response = $this->actingAs($user)
            ->withSession([EnsureTwoFactorIsVerified::SessionKey => true])
            ->get('/panel');

        $response->assertRedirect(route('kerahasiaan.show'));
    }
}
