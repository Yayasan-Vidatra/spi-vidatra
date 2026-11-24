<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Signed URL isi berkas (US-11, Rancangan 6.2): berlaku 5 menit, terikat ke pengguna dan sesinya.
 *
 * Pengikat sesi adalah HMAC dari token acak yang hanya ada di sesi pengguna. Tautan yang disalin ke
 * pengguna atau sesi lain ditolak, begitu juga setelah logout karena sesinya dihapus.
 */
class FileContentUrl
{
    private const string SessionKey = 'berkas.signing_token';

    public const string ModePreview = 'pratinjau';

    public const string ModeDownload = 'unduh';

    public function __construct(private Request $request) {}

    public function generate(File $file, FileVersion $version, User $user, string $mode): string
    {
        return URL::temporarySignedRoute(
            'berkas.konten',
            now()->addMinutes(config('berkas.signed_url_ttl_minutes')),
            [
                'file' => $file->id,
                'version' => $version->id,
                'mode' => $mode,
                'u' => $user->id,
                's' => $this->sessionBinding(),
            ],
        );
    }

    /**
     * Whether the signed URL of the current request was issued to the signed-in user in this session.
     */
    public function isBoundToCurrentSession(): bool
    {
        $userId = (string) $this->request->user()?->getAuthIdentifier();
        $token = $this->request->session()->get(self::SessionKey);

        return $userId !== ''
            && is_string($token)
            && hash_equals($userId, (string) $this->request->query('u'))
            && hash_equals($this->bindingFor($token), (string) $this->request->query('s'));
    }

    private function sessionBinding(): string
    {
        $session = $this->request->session();
        $token = $session->get(self::SessionKey);

        if (! is_string($token)) {
            $token = Str::random(40);
            $session->put(self::SessionKey, $token);
        }

        return $this->bindingFor($token);
    }

    private function bindingFor(string $token): string
    {
        return hash_hmac('sha256', $token, config('app.key'));
    }
}
