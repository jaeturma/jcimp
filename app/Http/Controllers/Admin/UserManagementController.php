<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('view users');
        }

        \Log::info('UserManagementController index called by user: ' . auth()->id() . ' with permissions: ' . json_encode(auth()->user()->getAllPermissions()->pluck('name')->toArray()));

        $users = User::with('roles')->paginate(20);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('create users');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return response()->json($user->load('roles'), 201);
    }

    public function show(User $user): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('view users');
        }

        return response()->json($user->load('roles'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('update users');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|exists:roles,name',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json($user->load('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('delete users');
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}