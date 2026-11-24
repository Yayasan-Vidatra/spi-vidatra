<?php

namespace App\Http\Controllers;

use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Http\Requests\SearchFilesRequest;
use App\Models\Assignment;
use App\Models\File;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pencarian berkas di header (US-14): nama, label, unit, tahun, penugasan. Hasil disaring di query lewat
 * File::visibleTo, sehingga berkas yang tidak boleh diakses pencari tidak pernah diambil.
 */
class SearchController extends Controller
{
    private const int PerPage = 25;

    public function index(SearchFilesRequest $request): Response
    {
        Gate::authorize('viewAny', File::class);

        $user = $request->user();
        $filters = $request->filters();
        $hasFilters = array_filter($filters, fn (mixed $value): bool => $value !== null) !== [];

        $files = $hasFilters
            ? File::query()
                ->visibleTo($user)
                ->with(['folder.assignment.unit', 'currentVersion'])
                ->when($filters['q'], fn (Builder $query, string $term) => $query
                    ->where('original_name', 'like', '%'.$this->escapeLike($term).'%'))
                ->when($filters['classification'], fn (Builder $query, string $classification) => $query
                    ->where('classification', $classification))
                ->when($filters['unit_id'], fn (Builder $query, int $unitId) => $query
                    ->whereHas('folder.assignment', fn (Builder $assignments) => $assignments->where('unit_id', $unitId)))
                ->when($filters['year'], fn (Builder $query, int $year) => $query
                    ->whereHas('folder.assignment', fn (Builder $assignments) => $assignments->where('year', $year)))
                ->when($filters['assignment_id'], fn (Builder $query, int $assignmentId) => $query
                    ->whereHas('folder', fn (Builder $folders) => $folders->where('assignment_id', $assignmentId)))
                ->orderBy('original_name')
                ->orderBy('id')
                ->paginate(self::PerPage)
                ->withQueryString()
                ->through(fn (File $file): array => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'classification' => $file->classification->value,
                    'classification_label' => $file->classification->label(),
                    'folder' => $file->folder->name,
                    'is_archived' => $file->folder->isArchived(),
                    'assignment_code' => $file->folder->assignment?->code,
                    'unit' => $file->folder->assignment?->unit->name,
                    'year' => $file->folder->assignment?->year,
                    'version_number' => $file->currentVersion?->version_number,
                    'scan_status' => $file->currentVersion?->scan_status->value,
                    'scan_status_label' => $file->currentVersion?->scan_status->label(),
                ])
            : null;

        $assignments = $this->visibleAssignments($user);

        return Inertia::render('Cari/Index', [
            'filters' => $filters,
            'results' => $files,
            'options' => [
                'classifications' => Classification::options(),
                'units' => Unit::query()->orderBy('id')->get(['id', 'name']),
                'years' => $assignments->pluck('year')->unique()->sortDesc()->values(),
                'assignments' => $assignments->map->only(['id', 'code'])->values(),
            ],
        ]);
    }

    /**
     * Penugasan yang boleh dilihat pencari, sama dengan AssignmentPolicy::view, untuk pilihan filter.
     *
     * @return Collection<int, Assignment>
     */
    private function visibleAssignments(User $user): Collection
    {
        return Assignment::query()
            ->unless($user->hasRole(RoleSlug::DewanPengawas), fn (Builder $query) => $query->whereMember($user))
            ->orderByDesc('year')
            ->orderByDesc('sequence_number')
            ->get(['id', 'code', 'year']);
    }

    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
