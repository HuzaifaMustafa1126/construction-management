<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // GET /api/users
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);

        return response()->json([
            'users' => User::query()
                ->latest()
                ->get(),
        ]);
    }

    // POST /api/users
    public function store(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [
                'required',
                Password::min(8)->letters()->numbers(),
            ],
            'role' => [
                'required',
                Rule::in(['admin', 'supervisor', 'client']),
            ],
        ]);

        $user = User::create([
            'name' => trim(
                $validated['first_name'] . ' ' .
                $validated['last_name']
            ),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }
}