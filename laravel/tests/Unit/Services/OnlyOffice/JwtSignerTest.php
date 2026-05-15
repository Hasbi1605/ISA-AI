<?php

namespace Tests\Unit\Services\OnlyOffice;

use App\Services\OnlyOffice\JwtSigner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JwtSignerTest extends TestCase
{
    public function test_it_signs_and_verifies_payload(): void
    {
        $signer = new JwtSigner('secret');

        $token = $signer->sign(['memo_id' => 10, 'exp' => time() + 60]);
        $payload = $signer->verify($token);

        $this->assertSame(10, $payload['memo_id']);
    }

    public function test_it_rejects_tampered_token(): void
    {
        $signer = new JwtSigner('secret');
        $token = $signer->sign(['memo_id' => 10, 'exp' => time() + 60]);

        $this->expectException(RuntimeException::class);
        $signer->verify($token.'x');
    }

    public function test_it_rejects_token_without_exp(): void
    {
        $signer = new JwtSigner('secret');
        $token = $signer->sign(['memo_id' => 10]); // deliberately no 'exp'

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/exp wajib/');
        $signer->verify($token);
    }

    public function test_it_rejects_expired_token(): void
    {
        $signer = new JwtSigner('secret');
        $token = $signer->sign(['memo_id' => 10, 'exp' => time() - 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/kedaluwarsa/');
        $signer->verify($token);
    }
}
