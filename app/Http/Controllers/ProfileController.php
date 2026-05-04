<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(): View
    {
        $user = Auth::user()->load('role.permissions');

        return view('profile.index', compact('user'));
    }

    // ─── Update Name / Email ──────────────────────────────────────────────────

    public function updateInfo(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($data);

        return redirect()
            ->route('profile')
            ->with('success', 'Profile updated successfully.');
    }

    // ─── Change Password ──────────────────────────────────────────────────────

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'The current password is incorrect.'])
                ->withInput()
                ->with('open_tab', 'password');
        }

        $user->update(['password' => Hash::make($request->password)]);

        return redirect()
            ->route('profile')
            ->with('success', 'Password changed successfully.');
    }
}
