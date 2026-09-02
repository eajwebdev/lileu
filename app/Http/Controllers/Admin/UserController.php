<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->orderByRaw("FIELD(role, 'admin', 'cashier', 'reseller')")
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'phone', 'is_active', 'created_at'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'phone' => $u->phone,
                    'is_active' => $u->is_active,
                    'joined_on' => $u->created_at->format('M j, Y'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'in:admin,cashier'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([...$data, 'password' => Hash::make($data['password'])]);

        return back()->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'in:admin,cashier,reseller'],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Never let the last admin lock everyone out of the back office.
        if ($user->isAdmin() && $data['role'] !== User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->with('error', 'You cannot demote the only remaining admin.');
        }

        $payload = collect($data)->except('password')->all();

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return back()->with('success', 'Account updated.');
    }
}
