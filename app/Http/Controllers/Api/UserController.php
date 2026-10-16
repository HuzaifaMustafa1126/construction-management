<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // GET /api/users
    public function index(Request $request, TenantContext $tenantContext)
    {
        abort_unless($request->user()->role === 'admin', 403);

        return response()->json([
            'users' => UserResource::collection(User::query()
                ->with('company:id,name,slug')
                ->where('company_id', $tenantContext->companyId())
                ->latest()
                ->get()),
        ]);
    }

    // POST /api/users
    public function store(Request $request, TenantContext $tenantContext)
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
                Password::min(8),
            ],
            'role' => [
                'required',
                Rule::in(['admin', 'supervisor', 'client']),
            ],
            'department' => [
                'nullable',
                'string',
                'max:100',
            ],
            'company_id' => ['prohibited'],
        ]);

        $user = new User([
            'name' => trim(
                $validated['first_name'].' '.
                $validated['last_name']
            ),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => 'Active',
            'department' => $validated['department'] ?? null,
        ]);
        $user->company_id = $tenantContext->companyId();
        $user->save();

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'role',
                'status',
                'department',
            ]),
        ], 201);
    }

    // DELETE /api/users/{id}
    public function destroy(Request $request, TenantContext $tenantContext, $id)
    {
        // Only Admin can delete users
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Access denied. Only Admin can delete users.',
            ], 403);
        }

        // Find user
        $user = User::query()
            ->where('company_id', $tenantContext->companyId())
            ->find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Prevent deleting your own account
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        // Delete user
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ], 200);
    }
}
