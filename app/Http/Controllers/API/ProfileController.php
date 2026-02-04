<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use App\Services\ImageService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProfileController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Get Profile
     * 
     * This endpoint is used to get the profile of the authenticated user.
     */
    public function getProfile()
    {
        try {
            $user = auth()->user()->load('profile');

            return response()->json([
                'status' => 'success',
                'message' => 'Profile fetched successfully',
                'data' => $user
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch profile',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update Profile
     * 
     * This endpoint is used to update the profile of the authenticated user.
     */
    #[BodyParameter('name', description: 'Full name.', type: 'string', example: 'John Doe')]
    #[BodyParameter('email', description: 'Email address.', type: 'string', format: 'email', example: 'test@example.com')]
    #[BodyParameter('phone_number', description: 'Phone number.', type: 'string', example: '081234567890')]
    #[BodyParameter('tax_id_number', description: 'Tax ID number.', type: 'string', example: '1234567890')]
    #[BodyParameter('notify_on_message', description: 'Notify on message.', type: 'boolean', example: true)]
    #[BodyParameter('show_email', description: 'Show email.', type: 'boolean', example: true)]
    #[BodyParameter('show_phone_number', description: 'Show phone number.', type: 'boolean', example: true)]
    #[BodyParameter('profile_picture', description: 'Profile picture.', type: 'string', example: 'base64 string')]
    #[BodyParameter('cover_image', description: 'Cover image.', type: 'string', example: 'base64 string')]
    public function updateProfile(Request $request)
    {
        try {

            $user = auth()->user()->load('profile');

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,'.$user->id,
                'phone_number' => 'nullable|string|max:255',
                'tax_id_number' => 'nullable|string|max:255',
                'notify_on_message' => 'nullable|boolean',
                'show_email' => 'nullable|boolean',
                'show_phone_number' => 'nullable|boolean',                
                'profile_picture' => 'nullable|string',
                'cover_image' => 'nullable|string',
            ]);            

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle profile_picture - base64 or file upload
            if (! empty($request->profile_picture)) {
                // Validate base64 image
                if (! $this->imageService->isValidBase64Image($request->profile_picture)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid base64 image for profile_picture',
                    ], 422);
                }

                $profile_picture_path = $this->imageService->convertBase64ToImage(
                    $request->profile_picture,
                    'profile_pictures',
                    $user->profile->profile_picture
                );

                if ($profile_picture_path) {
                    $user->profile->profile_picture = $profile_picture_path;
                }
            }

            // Handle cover_image - base64 or file upload
            if (! empty($request->cover_image)) {
                // Validate base64 image
                if (! $this->imageService->isValidBase64Image($request->cover_image)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid base64 image for cover_image',
                    ], 422);
                }

                $cover_image_path = $this->imageService->convertBase64ToImage(
                    $request->cover_image,
                    'cover_images',
                    $user->profile->cover_image
                );

                if ($cover_image_path) {
                    $user->profile->cover_image = $cover_image_path;
                }
            }

            DB::beginTransaction();

            $updateUser = User::where('id', $user->id)->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $updateProfile = Profile::where('id', $user->id)->update([
                'phone_number' => $request->phone_number,
                'tax_id_number' => $request->tax_id_number,
                'notify_on_message' => $request->notify_on_message,
                'show_email' => $request->show_email,
                'show_phone_number' => $request->show_phone_number,
                'profile_picture' => $user->profile->profile_picture,
                'cover_image' => $user->profile->cover_image,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => $user
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Change Password
     * 
     * This endpoint is used to change the password of the authenticated user.
     */
    #[BodyParameter('old_password', description: 'Old password.', type: 'string', example: 'password')]
    #[BodyParameter('new_password', description: 'New password.', type: 'string', example: 'password')]
    #[BodyParameter('confirm_password', description: 'Confirm password.', type: 'string', example: 'password')]
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'old_password' => 'required|string',
                'new_password' => 'required|string|min:8',
                'confirm_password' => 'required|string|min:8|same:new_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            if (! Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Old password is incorrect',
                ], 422);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to change password',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
