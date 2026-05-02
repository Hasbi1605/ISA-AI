<?php

namespace Tests\Unit\Services\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Services\Auth\PendingRegistrationService;
use App\Services\Auth\PendingRegistrationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PendingRegistrationWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_registration_replaces_existing_unverified_user_and_stores_pending_registration(): void
    {
        Mail::fake();

        User::factory()->unverified()->create([
            'name' => 'Old Pending User',
            'email' => 'workflow@example.com',
        ]);

        $service = app(PendingRegistrationWorkflowService::class);
        $pendingToken = $service->startRegistration(
            name: 'New Workflow User',
            email: 'workflow@example.com',
            password: 'password',
            ipAddress: '127.0.0.1',
        );

        $this->assertSame(0, User::where('email', 'workflow@example.com')->count());
        $this->assertSame($pendingToken, app(PendingRegistrationService::class)->pendingTokenByEmail('workflow@example.com'));

        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) {
            return $mail->hasTo('workflow@example.com');
        });
    }

    public function test_verify_otp_creates_verified_user_and_clears_pending_state(): void
    {
        Mail::fake();

        $service = app(PendingRegistrationWorkflowService::class);
        $pendingToken = $service->startRegistration(
            name: 'Verify Workflow User',
            email: 'verify-workflow@example.com',
            password: 'password',
            ipAddress: '127.0.0.1',
        );

        $otpCode = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$otpCode) {
            $otpCode = $mail->code;

            return $mail->hasTo('verify-workflow@example.com');
        });

        $this->assertNotNull($otpCode);

        $user = $service->verifyOtp($pendingToken, (string) $otpCode, '127.0.0.1');

        $this->assertSame('verify-workflow@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('users', [
            'email' => 'verify-workflow@example.com',
        ]);
        $this->assertNull(app(PendingRegistrationService::class)->pendingTokenByEmail('verify-workflow@example.com'));
    }
}
