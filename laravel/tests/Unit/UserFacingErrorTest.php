<?php

namespace Tests\Unit;

use App\Support\UserFacingError;
use Tests\TestCase;

class UserFacingErrorTest extends TestCase
{
    public function test_it_hides_technical_exception_details(): void
    {
        $message = UserFacingError::message(
            new \RuntimeException('SQLSTATE[HY000] Connection refused at /Users/example/app.php'),
            'Pesan aman.'
        );

        $this->assertSame('Pesan aman.', $message);
    }

    public function test_it_keeps_short_user_safe_messages(): void
    {
        $message = UserFacingError::message(new \RuntimeException('File Drive ini sudah pernah diproses di akun Anda.'));

        $this->assertSame('File Drive ini sudah pernah diproses di akun Anda.', $message);
    }
}
