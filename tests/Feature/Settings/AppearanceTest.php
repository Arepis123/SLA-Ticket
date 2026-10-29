<?php

namespace Tests\Feature\Settings;

use App\Enums\FontSize;
use App\Livewire\Settings\Appearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_appearance_page_offers_text_sizes(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertSee('Text size')
            ->assertSee('Extra large');
    }

    public function test_users_can_change_their_text_size(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Appearance::class)
            ->assertSet('fontSize', 'default')
            ->set('fontSize', 'large');

        $this->assertSame(FontSize::Large, $user->fresh()->font_size);
    }

    public function test_unknown_sizes_fall_back_to_default(): void
    {
        $user = User::factory()->create(['font_size' => FontSize::Large]);

        Livewire::actingAs($user)->test(Appearance::class)
            ->set('fontSize', '300%')
            ->assertSet('fontSize', 'default');

        $this->assertSame(FontSize::Default, $user->fresh()->font_size);
    }

    public function test_the_chosen_size_is_applied_on_every_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertDontSee('html { font-size', false);

        $this->actingAs(User::factory()->create(['font_size' => FontSize::ExtraLarge]))
            ->get(route('dashboard'))
            ->assertSee('html { font-size: 125%; }', false);
    }
}
