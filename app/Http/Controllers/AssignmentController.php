<?php

namespace App\Http\Controllers;

use App\Actions\CreateAssignment;
use App\Enums\AssignmentStatus;
use App\Enums\Classification;
use App\Enums\RoleSlug;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentMember;
use App\Models\Folder;
use App\Models\Unit;
use App\Models\User;
use App\Services\FolderFileList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-04 Penugasan dan H-05 Ruang Penugasan (US-05 sampai US-08).
 */
class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Assignment::class);

        $user = $request->user();
        $isArchiveTab = $request->query('tab') === 'arsip';

        $assignments = Assignment::query()
            ->with(['unit', 'teamLead'])
            ->where('status', $isArchiveTab ? AssignmentStatus::Ditutup : AssignmentStatus::Aktif)
            // Sama dengan AssignmentPolicy::view: auditor hanya melihat penugasan tempat ia ditunjuk.
            ->unless($user->hasRole(RoleSlug::DewanPengawas), fn (Builder $query) => $query->whereMember($user))
            ->orderByDesc('year')
            ->orderByDesc('sequence_number')
            ->get();

        return Inertia::render('Penugasan/Index', [
            'tab' => $isArchiveTab ? 'arsip' : 'aktif',
            'assignments' => $assignments->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id,
                'code' => $assignment->code,
                'title' => $assignment->title,
                'year' => $assignment->year,
                'unit' => $assignment->unit->name,
                'team_lead' => $assignment->teamLead?->name,
                'start_date' => $assignment->start_date->toDateString(),
                'closed_at' => $assignment->closed_at?->toDateString(),
            ]),
            'can' => ['create' => $user->can('create', Assignment::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Assignment::class);

        return Inertia::render('Penugasan/Buat', [
            'units' => Unit::query()->orderBy('id')->get(['id', 'code', 'name']),
            'auditors' => $this->activeAuditors()->map->only(['id', 'name', 'email']),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreAssignmentRequest $request, CreateAssignment $createAssignment): RedirectResponse
    {
        $assignment = $createAssignment->handle($request->user(), $request->validated());

        Inertia::flash('success', "Penugasan {$assignment->code} dibuat.");

        return redirect()->route('penugasan.show', $assignment);
    }

    public function show(Request $request, Assignment $assignment, FolderFileList $folderFileList): Response
    {
        Gate::authorize('view', $assignment);

        $user = $request->user();
        $assignment->load(['unit', 'teamLead', 'members.user']);
        $visibleFolders = $this->visibleFolders($assignment, $user);
        $canManageMembers = $user->can('manageMembers', $assignment);
        $activeFolders = $visibleFolders->reject->isArchived()->values();

        // Daftar berkas folder yang dipilih (US-09); tanpa pilihan, folder teratas pertama.
        $selectedFolder = $activeFolders->firstWhere('id', $request->integer('folder'))
            ?? $activeFolders->firstWhere('parent_id', null);

        return Inertia::render('Penugasan/Show', [
            'assignment' => [
                'id' => $assignment->id,
                'code' => $assignment->code,
                'title' => $assignment->title,
                'year' => $assignment->year,
                'unit' => $assignment->unit->name,
                'status' => $assignment->status->value,
                'status_label' => $assignment->status->label(),
                'start_date' => $assignment->start_date->toDateString(),
                'end_date' => $assignment->end_date?->toDateString(),
                'closed_at' => $assignment->closed_at?->toDateString(),
                'team_lead' => $assignment->teamLead?->name,
            ],
            'folders' => $activeFolders->map(fn (Folder $folder): array => $this->folderData($folder, $user)),
            'selectedFolder' => $selectedFolder === null ? null : $folderFileList->forFolder($selectedFolder, $user),
            'uploadRules' => $folderFileList->uploadRules(),
            'archivedFolders' => $visibleFolders->filter->isArchived()
                ->map(fn (Folder $folder): array => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'archived_at' => $folder->archived_at->toDateString(),
                ])
                ->values(),
            'members' => $assignment->members
                ->sortBy(fn (AssignmentMember $member): string => $member->user->name)
                ->map(fn (AssignmentMember $member): array => [
                    'id' => $member->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'can_access_confidential' => $member->can_access_confidential,
                    'is_team_lead' => $member->user_id === $assignment->team_lead_id,
                ])
                ->values(),
            'availableAuditors' => $canManageMembers
                ? $this->activeAuditors()
                    ->whereNotIn('id', $assignment->members->pluck('user_id'))
                    ->map->only(['id', 'name', 'email'])
                    ->values()
                : [],
            'can' => [
                'close' => $user->can('close', $assignment),
                'reopen' => $user->can('reopen', $assignment),
                'manageMembers' => $canManageMembers,
            ],
        ]);
    }

    /**
     * Load every folder of the assignment once and wire up parents in memory, so the
     * policy can walk ancestors for the Rahasia check without extra queries.
     *
     * @return Collection<int, Folder>
     */
    private function visibleFolders(Assignment $assignment, User $user): Collection
    {
        $folders = $assignment->folders()->with('documentRequest')->orderBy('name')->get();
        $foldersById = $folders->keyBy('id');

        foreach ($folders as $folder) {
            $folder->setRelation('assignment', $assignment);
            $folder->setRelation('parent', $foldersById->get($folder->parent_id));
        }

        return $folders->filter(fn (Folder $folder): bool => $user->can('view', $folder))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function folderData(Folder $folder, User $user): array
    {
        $allowedLabels = fn (callable $isAllowed): array => collect(Classification::cases())
            ->filter($isAllowed)
            ->map(fn (Classification $classification): string => $classification->value)
            ->values()
            ->all();

        return [
            'id' => $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'classification' => $folder->classification->value,
            'classification_label' => $folder->classification->label(),
            'is_protected' => $folder->isProtected(),
            'is_locked' => $folder->is_locked,
            'can' => [
                'createSubfolder' => $user->can('createSubfolder', $folder),
                'rename' => $user->can('rename', $folder),
                'move' => $user->can('move', $folder),
                'archive' => $user->can('archive', $folder),
                'changeLabel' => $user->can('changeLabel', $folder),
            ],
            'allowed_labels' => $allowedLabels(fn (Classification $label): bool => $user->can('changeLabel', [$folder, $label])),
            'allowed_child_labels' => $allowedLabels(fn (Classification $label): bool => $user->can('applyLabelUnder', [$folder, $label])),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function activeAuditors(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereRelation('role', 'slug', RoleSlug::AuditorSpi)
            ->orderBy('name')
            ->get();
    }
}
