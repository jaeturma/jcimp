<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('view permissions');
        }

        $permissions = Permission::paginate(50);

        return response()->json($permissions);
    }

    public function assign(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('assign permissions');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user = \App\Models\User::find($validated['user_id']);
        $user->syncPermissions($validated['permissions']);

        return response()->json(['message' => 'Permissions assigned.']);
    }
}