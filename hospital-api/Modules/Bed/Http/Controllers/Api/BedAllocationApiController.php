<?php

namespace Modules\Bed\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Bed\Models\BedAllocation;
use Modules\Bed\Transformers\BedAllocationResource;

class BedAllocationApiController extends Controller
{
   public function bedAllocationList(Request $request)
   {
       try {
           // Get authenticated user
           $user = auth()->user();

           if (!$user) {
               return response()->json([
                   'status' => false,
                   'message' => 'Unauthenticated'
               ], 401);
           }

           // Start query with SetRole scope to filter based on authenticated user's role
           $bedAllocations = BedAllocation::SetRole($user)
               ->with(['patient', 'bedMaster.bedType'])
               ->whereNull('deleted_at');

           // Optional: Filter by specific patient_id if provided
           if ($request->has('patient_id')) {
               $bedAllocations->where('patient_id', $request->input('patient_id'));
           }
           // Optional: Filter by specific patient_id if provided
           if ($request->has('patient_id')) {
               $bedAllocations->where('patient_id', $request->input('patient_id'));
           }

           // Apply status filter if provided
           if ($request->has('status')) {
               $bedAllocations->where('status', $request->input('status'));
           }
           // Apply status filter if provided
           if ($request->has('status')) {
               $bedAllocations->where('status', $request->input('status'));
           }

           // Get total count before pagination
           $totalCount = $bedAllocations->count();

           // Apply pagination
           $perPage = $request->input('per_page', 10);
           $page = $request->input('page', 1);

           $bedAllocations = $bedAllocations->latest()
               ->skip(($page - 1) * $perPage)
               ->take($perPage)
               ->get();

           $bedAllocationsResource = BedAllocationResource::collection($bedAllocations);

           return response()->json([
               'status' => true,
               'data' => $bedAllocationsResource,
               'total' => $totalCount,
               'per_page' => (int)$perPage,
               'current_page' => (int)$page,
               'last_page' => ceil($totalCount / $perPage),
           ]);
       } catch (\Exception $e) {
           \Log::error('API Error fetching bed allocations: ' . $e->getMessage());
           return response()->json([
               'status' => false,
               'message' => 'Error fetching bed allocations: ' . $e->getMessage()
           ], 500);
       }
   }
}
