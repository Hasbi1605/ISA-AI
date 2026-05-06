<?php

namespace Tests\Feature\Memos;

use App\Models\Memo;
use App\Models\User;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MemoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_download_memo_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $memo->forceFill(['searchable_text' => 'preview html text'])->save();
        Storage::disk('local')->put($memo->file_path, 'docx-bytes');

        $response = $this->actingAs($user)
            ->get(route('memos.download', $memo))
            ->assertOk()
            ->assertDownload('Memo-Test.docx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertSame('docx-bytes', file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_non_owner_cannot_download_memo_file(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($owner);
        Storage::disk('local')->put($memo->file_path, 'docx-bytes');

        $this->actingAs($other)
            ->get(route('memos.download', $memo))
            ->assertForbidden();
    }

    public function test_signed_file_route_accepts_relative_signature_for_onlyoffice(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        Storage::disk('local')->put($memo->file_path, 'docx-bytes');

        $path = URL::temporarySignedRoute('memos.file.signed', now()->addHour(), $memo, false);

        $this->get($path)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_export_pdf_converts_stored_memo_docx_through_onlyoffice(): void
    {
        Storage::fake('local');
        config([
            'services.onlyoffice.jwt_secret' => 'converter-secret',
            'services.onlyoffice.internal_url' => 'http://onlyoffice',
            'services.onlyoffice.public_url' => 'https://ista-ai.app',
            'services.onlyoffice.laravel_internal_url' => 'http://laravel:8000',
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $memo = $this->createMemo($user);
        $memo->forceFill(['searchable_text' => 'preview html text'])->save();
        Storage::disk('local')->put($memo->file_path, 'docx-bytes');

        $conversionPayload = null;

        Http::fake(function (HttpRequest $request) use (&$conversionPayload) {
            if (str_starts_with($request->url(), 'http://onlyoffice/converter')) {
                $data = $request->data();
                $conversionPayload = (new JwtSigner('converter-secret'))->verify((string) $data['token']);

                return Http::response([
                    'endConvert' => true,
                    'fileType' => 'pdf',
                    'fileUrl' => 'https://ista-ai.app/cache/memo.pdf',
                    'percent' => 100,
                ], 200);
            }

            if ($request->url() === 'https://ista-ai.app/cache/memo.pdf') {
                return Http::response('%PDF-from-docx', 200, [
                    'Content-Type' => 'application/pdf',
                ]);
            }

            return Http::response('unexpected request', 500);
        });

        $response = $this->actingAs($user)
            ->get(route('memos.export.pdf', $memo))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('attachment; filename="Memo-Test.pdf"', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-from-docx', $response->getContent());
        $this->assertIsArray($conversionPayload);
        $this->assertSame('docx', $conversionPayload['filetype']);
        $this->assertSame('pdf', $conversionPayload['outputtype']);
        $this->assertStringStartsWith('memo-'.$memo->id.'-', $conversionPayload['key']);
        $this->assertStringEndsWith('-pdf', $conversionPayload['key']);
        $this->assertStringStartsWith('http://laravel:8000/memos/'.$memo->id.'/signed-file?', $conversionPayload['url']);
        $this->assertSame('Memo-Test.docx', $conversionPayload['title']);
    }

    protected function createMemo(User $user): Memo
    {
        return Memo::create([
            'user_id' => $user->id,
            'title' => 'Memo Test',
            'memo_type' => 'memo_internal',
            'file_path' => 'memos/'.$user->id.'/memo.docx',
            'status' => Memo::STATUS_GENERATED,
        ]);
    }
}
