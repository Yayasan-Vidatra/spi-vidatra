<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Classification;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Assignment;
use App\Models\Folder;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Membuat, mengubah nama, memindahkan, dan mengubah label subfolder penugasan (US-07).
 */
class FolderController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function store(StoreFolderRequest $request, Assignment $assignment): RedirectResponse
    {
        $parent = $this->assignmentFolder($assignment, $request->integer('parent_id'));

        Gate::authorize('createSubfolder', $parent);

        // Subfolder baru mewarisi label induk kecuali diubah.
        $classification = $request->enum('classification', Classification::class) ?? $parent->classification;

        if ($classification !== $parent->classification) {
            Gate::authorize('applyLabelUnder', [$parent, $classification]);
        }

        DB::transaction(function () use ($request, $assignment, $parent, $classification): void {
            $folder = (new Folder)->forceFill([
                'assignment_id' => $assignment->id,
                'parent_id' => $parent->id,
                'name' => $request->string('name')->trim()->toString(),
                'classification' => $classification,
                'is_locked' => false,
                'created_by' => $request->user()->id,
            ]);
            $folder->save();

            $this->auditLogger->record(AuditAction::FolderCreated, $folder, [
                'name' => $folder->name,
                'parent_id' => $parent->id,
                'classification' => $classification->value,
            ]);
        });

        return redirect()->route('penugasan.show', $assignment);
    }

    public function update(UpdateFolderRequest $request, Assignment $assignment, Folder $folder): RedirectResponse
    {
        $folder->setRelation('assignment', $assignment);

        DB::transaction(function () use ($request, $assignment, $folder): void {
            if ($request->has('name')) {
                $this->rename($folder, $request->string('name')->trim()->toString());
            }

            if ($request->has('parent_id')) {
                $this->move($folder, $this->assignmentFolder($assignment, $request->integer('parent_id')));
            }

            if ($request->has('classification')) {
                $this->changeLabel($folder, $request->enum('classification', Classification::class));
            }
        });

        return redirect()->route('penugasan.show', $assignment);
    }

    private function rename(Folder $folder, string $name): void
    {
        if ($folder->name === $name) {
            return;
        }

        Gate::authorize('rename', $folder);

        $oldName = $folder->name;
        $folder->forceFill(['name' => $name])->save();

        $this->auditLogger->record(AuditAction::FolderRenamed, $folder, ['from' => $oldName, 'to' => $name]);
    }

    private function move(Folder $folder, Folder $target): void
    {
        if ($folder->parent_id === $target->id) {
            return;
        }

        Gate::authorize('moveTo', [$folder, $target]);

        if ($target->isSameOrDescendantOf($folder)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Folder tidak bisa dipindah ke dalam dirinya sendiri atau subfoldernya.',
            ]);
        }

        $oldParentId = $folder->parent_id;
        $folder->forceFill(['parent_id' => $target->id])->save();
        $folder->setRelation('parent', $target);

        $this->auditLogger->record(AuditAction::FolderMoved, $folder, ['from_parent_id' => $oldParentId, 'to_parent_id' => $target->id]);
    }

    private function changeLabel(Folder $folder, Classification $classification): void
    {
        if ($folder->classification === $classification) {
            return;
        }

        Gate::authorize('changeLabel', [$folder, $classification]);

        $oldClassification = $folder->classification;
        $folder->forceFill(['classification' => $classification])->save();

        $this->auditLogger->record(AuditAction::FolderLabelChanged, $folder, [
            'from' => $oldClassification->value,
            'to' => $classification->value,
        ]);
    }

    private function assignmentFolder(Assignment $assignment, int $folderId): Folder
    {
        $folder = $assignment->folders()->findOrFail($folderId);
        $folder->setRelation('assignment', $assignment);

        return $folder;
    }
}
