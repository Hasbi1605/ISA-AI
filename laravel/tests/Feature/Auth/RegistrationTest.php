<?php

namespace Tests\Feature\Auth;

use App\Mail\VerificationCodeMail;
use App\Livewire\Chat\ChatIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertRedirect('/login?view=register');

        $this->get('/login?view=register')
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_register_from_login_shows_verification_phase_without_creating_active_account(): void
    {
        Mail::fake();

        $component = Volt::test('pages.auth.login')
            ->set('view', 'register')
            ->set('name', 'Test User')
            ->set('register_email', 'test@example.com')
            ->set('register_password', 'password')
            ->set('register_password_confirmation', 'password');

        $component->call('register')
            ->assertSet('showVerificationModal', true)
            ->assertNoRedirect();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

        Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail) => $mail->hasTo('test@example.com') && $mail->queue === 'mail' && $mail->tries === 1 && $mail->timeout === 15);
    }

    public function test_valid_otp_finalizes_registration_logs_in_and_redirects_to_intended_chat(): void
    {
        Mail::fake();

        $this->get('/guest-chat?q=tolong ringkas agenda hari ini')
            ->assertRedirect(route('login'));

        $component = Volt::test('pages.auth.login')
            ->set('view', 'register')
            ->set('name', 'Test User')
            ->set('register_email', 'test-register@example.com')
            ->set('register_password', 'password')
            ->set('register_password_confirmation', 'password');

        $component->call('register');

        $otpCode = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$otpCode) {
            $otpCode = $mail->code;

            return $mail->hasTo('test-register@example.com') && $mail->queue === 'mail' && $mail->tries === 1 && $mail->timeout === 15;
        });

        $this->assertNotNull($otpCode);

        $component->set('verification_code_input', $otpCode)
            ->call('verifyOtp')
            ->assertRedirect(route('chat', absolute: false));

        $this->assertAuthenticated();

        $user = User::where('email', 'test-register@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('tolong ringkas agenda hari ini', session('pending_prompt'));

        Livewire::actingAs($user)
            ->test(ChatIndex::class)
            ->assertSet('prompt', 'tolong ringkas agenda hari ini');
    }

    public function test_cancel_verification_keeps_email_unregistered_and_reusable(): void
    {
        Mail::fake();
        Notification::fake();

        $component = Volt::test('pages.auth.login')
            ->set('view', 'register')
            ->set('name', 'Cancelled User')
            ->set('register_email', 'cancel@example.com')
            ->set('register_password', 'password')
            ->set('register_password_confirmation', 'password');

        $component->call('register')
            ->assertSet('showVerificationModal', true);

        $component->call('cancelVerification')
            ->assertSet('showVerificationModal', false);

        $this->assertDatabaseMissing('users', ['email' => 'cancel@example.com']);

        Volt::test('pages.auth.login')
            ->set('form.email', 'cancel@example.com')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email']);

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'cancel@example.com')
            ->call('sendPasswordResetLink');

        Notification::assertNothingSent();

        $component->set('name', 'Retry User')
            ->set('register_email', 'cancel@example.com')
            ->set('register_password', 'password')
            ->set('register_password_confirmation', 'password')
            ->call('register')
            ->assertSet('showVerificationModal', true);

        Mail::assertQueued(VerificationCodeMail::class, 2);
    }

    public function test_otp_attempts_are_rate_limited_after_multiple_failures(): void
    {
        Mail::fake();

        $component = Volt::test('pages.auth.login')
            ->set('view', 'register')
            ->set('name', 'Rate Limit User')
            ->set('register_email', 'rate-limit@example.com')
            ->set('register_password', 'password')
            ->set('register_password_confirmation', 'password');

        $component->call('register')
            ->assertSet('showVerificationModal', true)
            ->assertNoRedirect();

        $otpCode = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$otpCode) {
            $otpCode = $mail->code;

            return $mail->hasTo('rate-limit@example.com') && $mail->queue === 'mail' && $mail->tries === 1 && $mail->timeout === 15;
        });

        $wrongCode = $otpCode === '000000' ? '999999' : '000000';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $component->set('verification_code_input', $wrongCode)
                ->call('verifyOtp')
                ->assertHasErrors(['verification_code_input']);
        }

        $component->set('verification_code_input', (string) $otpCode)
            ->call('verifyOtp')
            ->assertHasErrors(['verification_code_input'])
            ->assertNoRedirect();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'rate-limit@example.com']);
    }

    public function test_resend_otp_replaces_code_and_latest_code_can_be_used_for_verification(): void
    {
        Mail::fake();

        $component = Volt::test('pages.auth.login')
            ->set('view', 'register')
            ->set('name', 'Resend OTP User')
            ->set('register_email', 'resend@example.com')
            ->set('register_password', 'password')
            ->set('register_password_confirmation', 'password');

        $component->call('register')
            ->assertSet('showVerificationModal', true)
            ->assertNoRedirect();

        $initialQueuedMail = Mail::queued(VerificationCodeMail::class)->first();
        $initialOtpCode = $initialQueuedMail->code;
        $this->assertSame('mail', $initialQueuedMail->queue);
        $this->assertSame(1, $initialQueuedMail->tries);
        $this->assertSame(15, $initialQueuedMail->timeout);

        $component->call('resendOtp')
            ->assertSet('otp_status', 'Kode OTP baru telah dikirim ke email Anda.');

        Mail::assertQueued(VerificationCodeMail::class, 2);

        $resentQueuedMail = Mail::queued(VerificationCodeMail::class)->last();
        $resentOtpCode = $resentQueuedMail->code;
        $this->assertSame('mail', $resentQueuedMail->queue);
        $this->assertSame(1, $resentQueuedMail->tries);
        $this->assertSame(15, $resentQueuedMail->timeout);

        $this->assertNotSame($initialOtpCode, $resentOtpCode);

        $component->set('verification_code_input', (string) $initialOtpCode)
            ->call('verifyOtp')
            ->assertHasErrors(['verification_code_input'])
            ->assertNoRedirect();

        $component->set('verification_code_input', (string) $resentOtpCode)
            ->call('verifyOtp')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'resend@example.com']);
    }

    public function test_resend_otp_is_rate_limited_by_cooldown(): void
    {
        Mail::fake();

        $component = Volt::test('pages.auth.login')
            ->set('view', 'register')
            ->set('name', 'Resend Cooldown User')
            ->set('register_email', 'resend-cooldown@example.com')
            ->set('register_password', 'password')
            ->set('register_password_confirmation', 'password');

        $component->call('register')
            ->assertSet('showVerificationModal', true)
            ->assertNoRedirect();

        $component->call('resendOtp')
            ->assertSet('otp_status', 'Kode OTP baru telah dikirim ke email Anda.');

        $component->call('resendOtp')
            ->assertHasErrors(['verification_code_input'])
            ->assertNoRedirect();

        Mail::assertQueued(VerificationCodeMail::class, 2);
        $this->assertGuest();
    }
}
