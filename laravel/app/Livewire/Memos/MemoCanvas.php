<?php

namespace App\Livewire\Memos;

use App\Models\Memo;
use App\Services\Memo\MemoGenerationService;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MemoCanvas extends Component
{
    public ?Memo $memo = null;

    public string $memoType = 'memo_internal';

    public string $title = '';

    public string $context = '';

    public bool $isGenerating = false;

    public function mount(?Memo $memo = null): void
    {
        if ($memo?->exists) {
            abort_unless(Auth::user()?->can('view', $memo), 403);
            $this->memo = $memo;
            $this->memoType = $memo->memo_type;
            $this->title = $memo->title;
        }
    }

    public function generate(MemoGenerationService $generationService): void
    {
        $data = $this->validate([
            'memoType' => ['required', 'in:'.implode(',', array_keys(Memo::TYPES))],
            'title' => ['required', 'string', 'max:160'],
            'context' => ['required', 'string', 'max:12000'],
        ]);

        $this->isGenerating = true;

        try {
            $memo = $generationService->generate(
                Auth::user(),
                $data['memoType'],
                $data['title'],
                $data['context'],
            );

            $this->redirectRoute('memos.edit', $memo, navigate: true);
        } finally {
            $this->isGenerating = false;
        }
    }

    public function editorConfig(): ?array
    {
        if ($this->memo === null || ! $this->memo->file_path) {
            return null;
        }

        $signer = app(JwtSigner::class);
        $laravelInternalUrl = rtrim((string) config('services.onlyoffice.laravel_internal_url', config('app.url')), '/');
        $documentPath = URL::temporarySignedRoute('memos.file.signed', now()->addHours(12), $this->memo, false);
        $callbackPath = route('onlyoffice.callback', $this->memo, false);

        $config = [
            'document' => [
                'fileType' => 'docx',
                'key' => 'memo-'.$this->memo->id.'-'.$this->memo->updated_at?->timestamp,
                'title' => $this->memo->title.'.docx',
                'url' => $laravelInternalUrl.$documentPath,
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'callbackUrl' => $laravelInternalUrl.$callbackPath,
                'mode' => 'edit',
                'lang' => 'id',
                'user' => [
                    'id' => (string) Auth::id(),
                    'name' => (string) Auth::user()?->name,
                ],
            ],
        ];

        $config['token'] = $signer->sign($config + ['memo_id' => $this->memo->id]);

        return $config;
    }

    public function render()
    {
        return view('livewire.memos.memo-canvas', [
            'memoTypes' => Memo::TYPES,
            'editorConfig' => $this->editorConfig(),
            'onlyOfficeApiUrl' => rtrim((string) config('services.onlyoffice.public_url'), '/').'/web-apps/apps/api/documents/api.js',
        ]);
    }
}
