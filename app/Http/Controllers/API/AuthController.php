<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * @unauthenticated
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials',
                ], 401);
            }

            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            $roles = $user->roles->pluck('name')->toArray();
            
            $profile = Profile::where('id', $user->id)->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'user' => $user,
                    'profile' => $profile,
                    'roles' => $roles,                    
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * @unauthenticated
     */
    public function register(Request $request)
    {
        try {
            // Validate the registration request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
            ]);

            // Check if the validation failed
            if ($validator->fails()) {
                // Return a JSON response with the status, message and validation errors
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Start a database transaction
            DB::beginTransaction();

            // Create a new user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            

            // Generate a slug for the user's profile
            $slug = Str::slug(Str::before($request->email, '@'));

            // Check if the slug already exists in the database
            $checkSlugExists = Profile::where('slug', $slug)->first();

            // If the slug already exists, append a random number to the slug
            if ($checkSlugExists) {
                $slug = $slug.'-'.rand(1000, 9999);
            }
            
            $addProfile = Profile::create([
                'id' => $user->id,
                'slug' => $slug,
            ]);

            // Assign the "admin" role to the user
            $user->assignRole('admin');

            // Generate an authentication token for the user
            $token = $user->createToken('auth_token')->plainTextToken;

            // Commit the database transaction
            DB::commit();

            $user = User::with('profile')->find($user->uuid);

            // Return a JSON response with the status, message and user data
            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => [
                    'token' => $token,
                    'user' => $user
                ]
            ], 201);
        } catch (\Throwable $th) {
            // Roll back the database transaction if an error occurs
            DB::rollBack();
            // Return a JSON response with the status, message and error message
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * @unauthenticated
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $token = Str::random(60);
            $user->remember_token = $token;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Forgot password successful',
                'data' => [
                    'token' => $token
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forgot password failed',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * @unauthenticated
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->where('remember_token', $request->token)->first();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $user->password = Hash::make($request->password);
            $user->remember_token = null;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Reset password successful',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reset password failed',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
