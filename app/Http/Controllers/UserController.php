<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Only admin can view all users
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = User::query();
        
        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        // Search by name or email
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }
        
        $users = $query->paginate($request->per_page ?? 10);
        
        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ], 200);
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        // Users can only view their own profile unless they are admin
        if ($request->user()->id != $id && !$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ], 200);
    }

    /**
     * Update the specified user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Users can only update their own profile unless they are admin
        if ($request->user()->id != $id && !$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent any updates to admin account via API
        if ($user->role === 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Admin account updates are locked. Contact backend to change credentials.'
            ], 403);
        }

        // Disallow role changes via API to prevent promoting users to admin
        if ($request->has('role')) {
            return response()->json([
                'status' => false,
                'message' => 'Role cannot be updated via API'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'address' => 'nullable|string',
            'phone' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['password']);

        // Update password if provided
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ], 200);
    }

    /**
     * Remove the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        // Only admin can delete users
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent deleting self
        if ($request->user()->id == $id) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete your own account'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully'
        ], 200);
    }

    /**
     * Get the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function profile(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'User profile retrieved successfully',
            'data' => $request->user()
        ], 200);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // Block admin from changing email or password via API
        if ($user->role === 'admin' && ($request->has('email') || $request->has('password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Admin credentials are locked. Contact backend to change email or password.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'address' => 'nullable|string',
            'phone' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['password', 'role']);

        // Update password if provided (non-admin only)
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => $user
        ], 200);
    }
}