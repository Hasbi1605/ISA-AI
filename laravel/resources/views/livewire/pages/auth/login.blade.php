<?php

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use App\Services\Auth\PasswordResetLinkService;
use App\Services\Auth\PendingRegistrationWorkflowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth-canvas')] class extends Component
{
    public LoginForm $form;

    public string $view = 'login';

    // Register fields
    public string $name = '';

    public string $register_email = '';

    public string $register_password = '';

    public string $register_password_confirmation = '';

    // Forgot Password fields
    public string $forgot_email = '';

    public ?string $forgot_status = null;

    // OTP Verification Modal
    public bool $showVerificationModal = false;

    public string $verification_code_input = '';

    public ?string $pendingRegistrationToken = null;

    public ?string $otp_status = null;

    public function mount(): void
    {
        if (request()->query('view') === 'register') {
            $this->view = 'register';
        }
    }

    protected function passwordResetLinkService(): PasswordResetLinkService
    {
        return app(PasswordResetLinkService::class);
    }

    protected function pendingRegistrationWorkflowService(): PendingRegistrationWorkflowService
    {
        return app(PendingRegistrationWorkflowService::class);
    }

    public function setView(string $view): void
    {
        $this->view = $view;
        $this->resetErrorBag();
        $this->forgot_status = null;
        $this->otp_status = null;
    }

    public function toggleRegister(): void
    {
        $this->setView($this->view === 'register' ? 'login' : 'register');
    }

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'forgot_email' => ['required', 'email'],
        ], [], [
            'forgot_email' => 'email',
        ]);

        $this->forgot_status = $this->passwordResetLinkService()->sendResetLink($this->forgot_email, 'forgot_email');
        $this->forgot_email = '';
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate([
            'form.email' => 'required|string|email',
            'form.password' => 'required|string',
        ]);

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'register_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'register_password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'register_email.required' => 'Alamat email wajib diisi.',
            'register_email.unique' => 'Email ini sudah terdaftar.',
            'register_email.email' => 'Format email tidak valid.',
            'register_password.required' => 'Kata sandi wajib diisi.',
            'register_password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ], [
            'register_email' => 'email',
            'register_password' => 'password',
        ]);

        $this->pendingRegistrationToken = $this->pendingRegistrationWorkflowService()->startRegistration(
            name: $validated['name'],
            email: $validated['register_email'],
            password: $validated['register_password'],
            ipAddress: request()->ip(),
        );

        $this->showVerificationModal = true;
        $this->verification_code_input = '';
        $this->otp_status = null;
    }

    public function resendOtp(): void
    {
        $this->pendingRegistrationWorkflowService()->resendOtp(
            $this->pendingRegistrationToken,
            request()->ip(),
        );

        $this->verification_code_input = '';
        $this->otp_status = 'Kode OTP baru telah dikirim ke email Anda.';
    }

    public function cancelVerification(): void
    {
        if (! $this->pendingRegistrationToken) {
            $this->showVerificationModal = false;

            return;
        }

        $this->pendingRegistrationWorkflowService()->cancelRegistration(
            $this->pendingRegistrationToken,
            request()->ip(),
        );

        $this->pendingRegistrationToken = null;
        $this->verification_code_input = '';
        $this->showVerificationModal = false;
        $this->otp_status = null;
    }

    public function verifyOtp(): void
    {
        $this->validate([
            'verification_code_input' => ['required', 'digits:6'],
        ], [
            'verification_code_input.required' => 'Kode verifikasi wajib diisi.',
            'verification_code_input.digits' => 'Kode verifikasi harus 6 digit.',
        ]);

        if (! $this->pendingRegistrationToken) {
            $this->addError('verification_code_input', 'Sesi pendaftaran tidak ditemukan. Silakan daftar ulang.');

            return;
        }

        try {
            $user = $this->pendingRegistrationWorkflowService()->verifyOtp(
                $this->pendingRegistrationToken,
                $this->verification_code_input,
                request()->ip(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->addError('verification_code_input', 'Terjadi kendala saat menyelesaikan pendaftaran. Silakan coba lagi.');

            return;
        }

        Auth::login($user);
        Session::regenerate();

        $this->pendingRegistrationToken = null;
        $this->verification_code_input = '';
        $this->showVerificationModal = false;
        $this->otp_status = null;

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-[#fafaf9]">
    @include('livewire.pages.auth.partials.auth-background')

    @include('livewire.pages.auth.partials.auth-card')

    @if($showVerificationModal)
        @include('livewire.pages.auth.partials.otp-verification-modal')
    @endif
</div>
