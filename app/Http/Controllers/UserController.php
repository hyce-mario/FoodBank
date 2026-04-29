<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    // ─── index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = User::with('role')->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleId = $request->input('role_id')) {
            $query->where('role_id', $roleId);
        }

        $users = $query->paginate(20)->withQueryString();
        $roles = Role::orderBy('display_name')->get();

        return view('users.index', compact('users', 'roles', 'search'));
    }

    // ─── create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $roles         = Role::orderBy('display_name')->get();
        $defaultRoleId = SettingService::get('security.default_new_user_role', '');

        return view('users.create', compact('roles', 'defaultRoleId'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'role_id'           => $data['role_id'],
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('users.show', $user)
            ->with('success', "User \"{$user->name}\" created successfully.");
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function show(User $user): View
    {
        $user->load('role.permissions');
        return view('users.show', compact('user'));
    }

    // ─── edit ─────────────────────────────────────────────────────────────────

    public function edit(User $user): View
    {
        $roles = Role::orderBy('display_name')->get();
        return view('users.edit', compact('user', 'roles'));
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->name  = $data['name'];
        $user->email = $data['email'];

        // Defense in depth: role changes remain admin-only even if a future
        // change widens UpdateUserRequest::authorize() beyond ADMIN.
        if ($request->user()?->isAdmin()) {
            $user->role_id = $data['role_id'];
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()
            ->route('users.show', $user)
            ->with('success', "User \"{$user->name}\" updated successfully.");
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Guard: only admins may delete users. Without this, any authenticated
        // user could DELETE /users/{id} for any account (including the only
        // remaining admin), causing permanent loss of administrative access.
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        // Guard: self-deletion blocked unless explicitly allowed in settings
        if ($user->id === Auth::id()) {
            $allowSelfDelete = (bool) SettingService::get('security.allow_self_delete', false);
            $message = $allowSelfDelete
                ? 'You cannot delete your own account while logged in.'
                : 'Self-account deletion is disabled. Contact an administrator.';

            return redirect()->route('users.index')->with('error', $message);
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$name}\" deleted successfully.");
    }
}
