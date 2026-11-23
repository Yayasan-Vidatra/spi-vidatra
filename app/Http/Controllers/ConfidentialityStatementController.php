<?php

namespace App\Http\Controllers;

use App\Models\ConfidentialityStatement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Persetujuan pernyataan kerahasiaan saat login pertama (H-02, US-03).
 */
class ConfidentialityStatementController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasAcceptedConfidentialityStatement()) {
            return redirect()->route('dasbor');
        }

        $statement = ConfidentialityStatement::current();

        abort_if($statement === null, 503, 'Pernyataan kerahasiaan belum tersedia.');

        return Inertia::render('Keamanan/PernyataanKerahasiaan', [
            'statement' => $statement->only(['id', 'body']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasAcceptedConfidentialityStatement()) {
            return redirect()->route('dasbor');
        }

        $statement = ConfidentialityStatement::current();

        abort_if($statement === null, 503, 'Pernyataan kerahasiaan belum tersedia.');

        $request->validate(
            [
                'statement_id' => ['required', 'integer', Rule::in([$statement->id])],
                'agreed' => ['accepted'],
            ],
            [
                'statement_id.*' => 'Teks pernyataan telah diperbarui. Silakan baca ulang sebelum menyetujui.',
                'agreed.accepted' => 'Centang kotak persetujuan untuk melanjutkan.',
            ],
        );

        $user->confidentialityAcceptances()->create([
            'confidentiality_statement_id' => $statement->id,
            'accepted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('dasbor'));
    }
}
