<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function getShop()
    {
        try {
            $user = auth()->user()->load('shop');
            if (!$user->shop) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Shop not found',
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Shop retrieved successfully',
                'data' => $user->shop
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve shop',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
