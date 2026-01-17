<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\MembershipPlan;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Security;

class MasterPlanMembership extends Controller
{    
    public function index(Request $request)
    {
        try {
            
            $membershipPlans = MembershipPlan::all();

            return response()->json([
                'status' => 'success',
                'message' => 'Membership plans fetched successfully',
                'data' => $membershipPlans
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get membership plans',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
