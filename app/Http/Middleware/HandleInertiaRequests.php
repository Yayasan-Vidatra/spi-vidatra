<?php

namespace App\Http\Middleware;

use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->name,
                    'unread_notification_count' => $user->unreadNotifications()->count(),
                ],
                'can' => [
                    'viewAssignments' => (bool) $user?->can('viewAny', Assignment::class),
                    'viewGeneralFolders' => (bool) $user?->can('viewAnyGeneral', Folder::class),
                    'searchFiles' => (bool) $user?->can('viewAny', File::class),
                    'viewUnitRequests' => (bool) $user?->can('viewAnyForUnit', DocumentRequest::class),
                ],
            ],
        ];
    }
}
