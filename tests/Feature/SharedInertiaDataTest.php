<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedInertiaDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_fields_used_by_the_interface_are_shared_for_the_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => null,
                ])
            );
    }

    public function test_guests_receive_no_user(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.user', null));
    }
}
