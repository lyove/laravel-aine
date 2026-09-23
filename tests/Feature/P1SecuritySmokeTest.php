<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class P1SecuritySmokeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Smoke',
            'email' => 'smoke@test.local',
            'password' => Hash::make('StrongPass1234!'),
        ], $attrs));
    }

    public function test_weak_password_rejected_on_registration(): void
    {
        // min:8 only — no mixed case / numbers / symbols
        $this->post('/register', [
            'name' => 'Weak',
            'email' => 'weak@test.local',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_strong_password_accepted_on_registration(): void
    {
        $this->post('/register', [
            'name' => 'Strong',
            'email' => 'strong@test.local',
            'password' => 'StrongPass1234!',
            'password_confirmation' => 'StrongPass1234!',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'strong@test.local']);
    }

    public function test_account_lockout_key_isolated_from_ip(): void
    {
        $request = new \App\Http\Requests\Auth\LoginRequest();
        $request->merge(['email' => 'TARGET@TEST.LOCAL']);

        $key = $request->accountLockoutKey();
        $this->assertSame('login:account:target@test.local', $key);

        // The per-{email,IP} throttle key must still include IP
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $this->assertStringStartsWith('target@test.local|', $request->throttleKey());
    }

    public function test_tokens_endpoint_requires_auth(): void
    {
        $this->getJson('/api/me/tokens')->assertStatus(401);
    }

    public function test_tokens_lists_current_user_tokens_and_can_revoke_others(): void
    {
        $user = $this->makeUser();

        // Create two tokens via Sanctum
        $tokenA = $user->createToken('device-a', ['read'])->plainTextToken;
        $tokenB = $user->createToken('device-b', ['read'])->plainTextToken;

        // Authenticate with token A (real bearer token)
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/me/tokens')->assertStatus(200);
        $tokens = $response->json('data.tokens');
        $this->assertCount(2, $tokens);

        // Revoke other devices — token A must survive
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/me/tokens/revoke-others')->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Token A still valid
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/me/tokens')->assertStatus(200)->assertJsonCount(1, 'data.tokens');

        // Token B row has been removed from the database
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'device-b']);
    }

    public function test_revoke_single_token(): void
    {
        $user = $this->makeUser();
        $tokenA = $user->createToken('device-a', ['read'])->plainTextToken;
        $tokenB = $user->createToken('device-b', ['read']);
        $tokenBId = $tokenB->accessToken->id;

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->deleteJson("/api/me/tokens/{$tokenBId}")->assertStatus(200);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenBId]);
    }

    public function test_failed_login_writes_audit_log(): void
    {
        $user = $this->makeUser(['email' => 'audit@test.local']);

        // Attempt wrong password 3 times from the same account
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'email' => 'audit@test.local',
                'password' => 'WrongPass1234!',
            ]);
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'failed_login',
            'entity_type' => 'user',
        ]);
    }
}
