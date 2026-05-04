<?php

use App\Http\Controllers\Documents\DocumentExportController;
use App\Http\Controllers\Documents\DocumentPreviewController;
use App\Http\Controllers\Memos\MemoFileController;
use App\Http\Controllers\OnlyOfficeCallbackController;
use App\Livewire\Chat\ChatIndex;
use App\Livewire\Memos\MemoCanvas;
use App\Livewire\Memos\MemoIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard')
    ->name('dashboard');

Route::get('/guest-chat', function (Request $request) {
    if ($request->has('q')) {
        session()->put('pending_prompt', $request->input('q'));
    }
    session()->put('url.intended', route('chat'));

    return redirect()->route('login');
})->name('guest-chat');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('chat', ChatIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('chat');

Route::post('onlyoffice/callback/{memo}', OnlyOfficeCallbackController::class)
    ->name('onlyoffice.callback');

Route::get('memos/{memo}/signed-file', [MemoFileController::class, 'signed'])
    ->name('memos.file.signed');

Route::middleware(['auth', 'verified'])
    ->prefix('memos')
    ->name('memos.')
    ->group(function () {
        Route::get('/', MemoIndex::class)->name('index');
        Route::get('/create', MemoCanvas::class)->name('create');
        Route::get('/{memo}/download', [MemoFileController::class, 'download'])->name('download');
        Route::get('/{memo}/export-pdf', [MemoFileController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/{memo}', MemoCanvas::class)->name('edit');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('documents')
    ->name('documents.')
    ->group(function () {
        Route::get('/{document}/content-html', [DocumentExportController::class, 'extractContent'])->name('content-html');
        Route::get('/{document}/extract-tables', [DocumentExportController::class, 'extractTables'])->name('extract-tables');
        Route::post('/export', [DocumentExportController::class, 'export'])->name('export');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('documents/{document}/preview')
    ->name('documents.preview.')
    ->group(function () {
        Route::get('/status', [DocumentPreviewController::class, 'status'])->name('status');
        Route::get('/stream', [DocumentPreviewController::class, 'stream'])->name('stream');
        Route::get('/html', [DocumentPreviewController::class, 'html'])->name('html');
    });

require __DIR__.'/auth.php';
