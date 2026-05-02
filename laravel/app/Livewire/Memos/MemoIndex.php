<?php

namespace App\Livewire\Memos;

use App\Models\Memo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MemoIndex extends Component
{
    public function render()
    {
        return view('livewire.memos.memo-index', [
            'memos' => Memo::query()
                ->where('user_id', Auth::id())
                ->latest()
                ->get(),
        ]);
    }
}
