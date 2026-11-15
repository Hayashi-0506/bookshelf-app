<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ログイン画面を表示できる。
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    /**
     * 正しいメールアドレスとパスワードでログインできる。
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect('/books');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * パスワードが間違っている場合はログインできない。
     */
    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->from(route('login'))
            ->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * メールアドレスとパスワードが未入力の場合はログインできない。
     */
    public function test_user_cannot_login_without_email_and_password(): void
    {
        $this->post(route('login'), [])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    /**
     * 会員登録するとユーザーが作成されログイン状態になる。
     */
    public function test_registration_creates_user_and_logs_in(): void
    {
        $this->post(route('register'), [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/books');

        $user = User::firstWhere('email', 'taro@example.com');
        $this->assertSame('山田太郎', $user->name);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * 入力内容に誤りがある場合は会員登録できない。
     */
    public function test_registration_fails_with_invalid_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register'), [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    /**
     * ログアウトできる。
     */
    public function test_user_can_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
