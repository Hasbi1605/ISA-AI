<?php

namespace App\Http\Controllers\CloudStorage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CloudStorage\GoogleDriveOAuthService;
use App\Support\UserFacingError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleDriveOAuthController extends Controller
{
    public function connect(Request $request, GoogleDriveOAuthService $oauthService): RedirectResponse
    {
        if (! $oauthService->canUseSetupKey($request->query('setup_key'))) {
            abort(403, 'Setup key Google Drive tidak valid.');
        }

        /** @var User $user */
        $user = Auth::user();

        return redirect()->away($oauthService->authorizationUrl($user));
    }

    public function callback(Request $request, GoogleDriveOAuthService $oauthService): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $connection = $oauthService->completeCallback(
                (string) $request->query('code'),
                (string) $request->query('state'),
                $user,
            );
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route('chat')
                ->with('error', UserFacingError::message($e, 'Gagal menghubungkan Google Drive pusat. Coba ulangi koneksi atau minta admin memeriksa konfigurasi.'));
        }

        $accountLabel = $connection->account_email ?: 'akun Google pusat';

        return redirect()
            ->route('chat')
            ->with('message', 'Google Drive pusat tersambung ke '.$accountLabel.'. Upload Drive sekarang aktif untuk semua user.');
    }
}
