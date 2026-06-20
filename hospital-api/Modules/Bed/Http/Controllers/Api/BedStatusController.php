<?php

namespace Modules\Bed\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Bed\Models\BedMaster;
use Modules\Bed\Models\BedType;
use Modules\Bed\Models\BedAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BedStatusController extends Controller
{
    /**
     * Get bed status overview with statistics
     */
    public function index(Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get clinic_id from request if provided, otherwise use user's clinic
            $clinicId = $request->input('clinic_id');

            // Get bed statistics filtered by authenticated user's role
            $stats = $this->getBedStatistics($user, $clinicId);

            // Get all active bed allocations once (to avoid N+1 queries)
            // An active allocation must:
            // 1. Have status = 1 (true)
            // 2. Not be deleted
            // 3. Have assign_date <= today (allocation has started)
            // 4. Have discharge_date >= today OR null (allocation hasn't ended)
            // 5. Encounter must be open (status = 1) - if encounter is closed, bed is available
            // Use whereDate for proper date comparison
            $today = now()->format('Y-m-d');
            
            // First, get ALL allocations (before status filter) for debugging
            $allAllocationsQuery = BedAllocation::whereNull('deleted_at')
                ->whereDate('assign_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('discharge_date')
                      ->orWhereDate('discharge_date', '>=', $today);
                })
                ->with('patientEncounter');
            
            $allAllocationsBeforeFilter = BedAllocation::SetRole($user, $allAllocationsQuery)->get();
            
            Log::info('All Allocations Before Status Filter:', [
                'count' => $allAllocationsBeforeFilter->count(),
                'allocations' => $allAllocationsBeforeFilter->map(function($a) {
                    return [
                        'id' => $a->id,
                        'bed_master_id' => $a->bed_master_id,
                        'status' => $a->status,
                        'assign_date' => $a->assign_date,
                        'discharge_date' => $a->discharge_date,
                        'encounter_status' => $a->patientEncounter ? $a->patientEncounter->status : null,
                    ];
                })->toArray()
            ]);
            
            // Note: We don't filter by allocation status here because if dates are valid and encounter is active,
            // the bed is occupied regardless of the allocation's status field
            $activeAllocationsQuery = BedAllocation::whereNull('deleted_at')
                ->whereDate('assign_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('discharge_date')
                      ->orWhereDate('discharge_date', '>=', $today);
                })
                ->with('patientEncounter'); // Load encounter to check status

            // Filter allocations by authenticated user's role
            $activeAllocationsQuery = BedAllocation::SetRole($user, $activeAllocationsQuery);

            // Filter by clinic_id if provided
            if ($clinicId) {
                $activeAllocationsQuery->where('clinic_id', $clinicId);
            }

            // Get allocations after status filter
            $allocationsAfterStatusFilter = $activeAllocationsQuery->get();
            
            Log::info('Allocations After Status=1 Filter:', [
                'count' => $allocationsAfterStatusFilter->count(),
                'allocations' => $allocationsAfterStatusFilter->map(function($a) {
                    return [
                        'id' => $a->id,
                        'bed_master_id' => $a->bed_master_id,
                        'status' => $a->status,
                        'assign_date' => $a->assign_date,
                        'discharge_date' => $a->discharge_date,
                        'encounter_status' => $a->patientEncounter ? $a->patientEncounter->status : null,
                    ];
                })->toArray()
            ]);

            // Filter out allocations from closed encounters
            $activeAllocations = $allocationsAfterStatusFilter->filter(function($allocation) {
                    // Exclude allocations where encounter is closed (status = 0)
                    // If encounter is closed, bed should be available even if discharge date hasn't passed
                    if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                        return false; // Encounter is closed, so this allocation doesn't occupy the bed
                    }
                    return true; // Encounter is active, so this allocation occupies the bed
                })
                ->keyBy('bed_master_id');

            // Debug: Log allocations found
            Log::info('Active Allocations After All Filters:', [
                'count' => $activeAllocations->count(),
                'bed_master_ids' => $activeAllocations->keys()->toArray(),
                'allocations' => $activeAllocations->map(function($a) {
                    return [
                        'id' => $a->id,
                        'bed_master_id' => $a->bed_master_id,
                        'status' => $a->status,
                        'encounter_status' => $a->patientEncounter ? $a->patientEncounter->status : null,
                    ];
                })->values()->toArray(),
                'clinic_id' => $clinicId
            ]);

            // Get clinic IDs where the doctor is mapped
            $doctorClinicIds = collect();
            if ($user && $user->hasRole('doctor')) {
                $doctorClinicIds = \Modules\Clinic\Models\DoctorClinicMapping::where('doctor_id', $user->id)
                    ->pluck('clinic_id');
            }
            
            // Get receptionist's clinic ID if user is a receptionist
            $receptionistClinicId = null;
            if ($user && $user->hasRole('receptionist')) {
                $receptionist = \Modules\Clinic\Models\Receptionist::where('receptionist_id', $user->id)->first();
                if ($receptionist && $receptionist->clinic_id) {
                    $receptionistClinicId = $receptionist->clinic_id;
                }
            }

            // Get all bed types with their beds - filter by authenticated user's role
            // Only include active beds (status = 1) and not deleted
            $bedTypesQuery = BedType::with(['beds' => function($query) use ($user, $clinicId, $activeAllocations, $doctorClinicIds, $receptionistClinicId) {
                // Only include active beds (status = 1) and not deleted
                $query->where('status', 1)
                    ->whereNull('deleted_at');
                
                // Apply role-based filtering
                if ($user && $user->hasRole('receptionist')) {
                    // For receptionists, filter by clinic_id
                    if ($receptionistClinicId) {
                        $query->where('clinic_id', $receptionistClinicId);
                    } else {
                        // If receptionist has no clinic association, return empty
                        $query->whereRaw('1 = 0');
                    }
                } elseif ($user && $user->hasRole('doctor')) {
                    // For doctors, filter by clinic_id from DoctorClinicMapping
                    if ($doctorClinicIds->isNotEmpty()) {
                        $query->whereIn('clinic_id', $doctorClinicIds);
                    } else {
                        // If doctor has no clinic mappings, return empty
                        $query->whereRaw('1 = 0');
                    }
                } elseif ($user && $user->hasRole('vendor')) {
                    // For vendor/clinic admin roles, filter by clinic_admin_id
                    $query->where('clinic_admin_id', $user->id);
                } elseif ($user && ($user->hasRole('admin') || $user->hasRole('demo_admin'))) {
                    // For admin roles, no additional filtering needed (already filtered by status and deleted_at)
                }
                // For other roles or no user, no additional filtering
                
                // Apply clinic_id filter if provided
                if ($clinicId) {
                    // Include beds that either:
                    // 1. Have clinic_id matching the filter, OR
                    // 2. Have active allocations with clinic_id matching the filter
                    $bedIdsWithAllocations = $activeAllocations->filter(function($allocation) use ($clinicId) {
                        return $allocation->clinic_id == $clinicId;
                    })->keys()->toArray();
                    
                    if (!empty($bedIdsWithAllocations)) {
                        $query->where(function($q) use ($clinicId, $bedIdsWithAllocations) {
                            $q->where('clinic_id', $clinicId)
                              ->orWhereIn('id', $bedIdsWithAllocations);
                        });
                    } else {
                        $query->where('clinic_id', $clinicId);
                    }
                }
            }]);

            $bedTypesBeforeMap = $bedTypesQuery->get();
            
            Log::info('Bed Types Query:', [
                'bed_types_count' => $bedTypesBeforeMap->count(),
                'user_id' => $user ? $user->id : null,
                'user_roles' => $user ? $user->getRoleNames()->toArray() : [],
                'doctor_clinic_ids' => $doctorClinicIds->toArray(),
                'receptionist_clinic_id' => $receptionistClinicId,
            ]);
            
            Log::info('API Bed Types Before Mapping:', [
                'bed_types_count' => $bedTypesBeforeMap->count(),
                'bed_types' => $bedTypesBeforeMap->map(function($bt) {
                    return [
                        'id' => $bt->id,
                        'type' => $bt->type,
                        'beds_count' => $bt->beds->count(),
                        'bed_ids' => $bt->beds->pluck('id')->toArray(),
                        'bed_clinic_ids' => $bt->beds->pluck('clinic_id')->unique()->toArray(),
                    ];
                })->toArray()
            ]);
            
            $bedTypes = $bedTypesBeforeMap->map(function ($bedType) use ($activeAllocations, $user, $doctorClinicIds, $receptionistClinicId) {
                $bedsBeforeFilter = $bedType->beds->count();
                
                // Filter beds by doctor's clinics if user is a doctor
                if ($user && $user->hasRole('doctor') && $doctorClinicIds->isNotEmpty()) {
                    $bedType->beds = $bedType->beds->filter(function ($bed) use ($doctorClinicIds) {
                        return $doctorClinicIds->contains($bed->clinic_id);
                    });
                }
                
                // Filter beds by clinic if user is a receptionist
                if ($user && $user->hasRole('receptionist') && $receptionistClinicId) {
                    $bedType->beds = $bedType->beds->filter(function ($bed) use ($receptionistClinicId) {
                        return $bed->clinic_id == $receptionistClinicId;
                    });
                }
                
                $bedsAfterFilter = $bedType->beds->count();
                
                if ($bedsBeforeFilter != $bedsAfterFilter) {
                    Log::info('Bed Type Filtering:', [
                        'bed_type_id' => $bedType->id,
                        'bed_type_name' => $bedType->type,
                        'beds_before_filter' => $bedsBeforeFilter,
                        'beds_after_filter' => $bedsAfterFilter,
                        'doctor_clinic_ids' => $doctorClinicIds->toArray(),
                    ]);
                }
                
                // Transform beds to ensure current_status and current_allocation are included
                $bedsArray = $bedType->beds->map(function ($bed) use ($activeAllocations) {
                    // Get active allocation for this bed
                    $activeAllocation = $activeAllocations->get($bed->id);
                    
                    // Get bed as array and add current_status and current_allocation
                    $bedArray = $bed->toArray();
                    
                    // Remove any existing current_allocation from the array to avoid conflicts
                    unset($bedArray['current_allocation']);
                    
                    // Determine current_status
                    $determinedStatus = 'available';
                    if ($bed->is_under_maintenance) {
                        $determinedStatus = 'maintenance';
                    } elseif ($activeAllocation) {
                        $determinedStatus = 'occupied';
                        $bedArray['current_allocation'] = $activeAllocation->toArray();
                    }
                    
                    $bedArray['current_status'] = $determinedStatus;
                    
                    // Debug: Log each bed's status check
                    Log::info('API Bed Status Check:', [
                        'bed_id' => $bed->id,
                        'bed_name' => $bed->bed,
                        'bed_status' => $bed->status,
                        'is_under_maintenance' => $bed->is_under_maintenance,
                        'has_active_allocation' => $activeAllocation ? true : false,
                        'active_allocation_id' => $activeAllocation ? $activeAllocation->id : null,
                        'active_allocation_status' => $activeAllocation ? $activeAllocation->status : null,
                        'active_allocation_encounter_status' => $activeAllocation && $activeAllocation->patientEncounter ? $activeAllocation->patientEncounter->status : null,
                        'determined_current_status' => $determinedStatus,
                        'active_allocations_keys' => $activeAllocations->keys()->toArray(),
                    ]);
                    
                    return $bedArray;
                })->toArray();
                
                // Get bedType as array and replace beds with transformed beds
                $bedTypeArray = $bedType->toArray();
                $bedTypeArray['beds'] = $bedsArray;
                
                return $bedTypeArray;
            })->filter(function ($bedType) {
                // Filter out bed types with no beds
                return !empty($bedType['beds']);
            })->values();

            // Log final statistics and bed counts
            $totalBedsInResponse = 0;
            $occupiedBedsInResponse = 0;
            $availableBedsInResponse = 0;
            $maintenanceBedsInResponse = 0;
            
            foreach ($bedTypes as $bedType) {
                foreach ($bedType['beds'] as $bed) {
                    $totalBedsInResponse++;
                    if ($bed['current_status'] === 'occupied') {
                        $occupiedBedsInResponse++;
                    } elseif ($bed['current_status'] === 'available') {
                        $availableBedsInResponse++;
                    } elseif ($bed['current_status'] === 'maintenance') {
                        $maintenanceBedsInResponse++;
                    }
                }
            }
            
            Log::info('API Response Summary:', [
                'statistics_from_calc' => $stats,
                'beds_in_response' => [
                    'total' => $totalBedsInResponse,
                    'occupied' => $occupiedBedsInResponse,
                    'available' => $availableBedsInResponse,
                    'maintenance' => $maintenanceBedsInResponse,
                ],
                'bed_types_count' => $bedTypes->count(),
                'bed_details' => $bedTypes->map(function($bt) {
                    return [
                        'bed_type' => $bt['type'] ?? 'N/A',
                        'beds_count' => count($bt['beds'] ?? []),
                        'beds' => collect($bt['beds'] ?? [])->map(function($bed) {
                            return [
                                'id' => $bed['id'] ?? 'N/A',
                                'name' => $bed['bed'] ?? 'N/A',
                                'current_status' => $bed['current_status'] ?? 'N/A',
                                'has_allocation' => !empty($bed['current_allocation']),
                                'allocation_status' => $bed['current_allocation']['status'] ?? null,
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ]);

            // Use statistics from actual beds in response, not from getBedStatistics
            // This ensures the statistics match what's actually being returned
            $finalStats = [
                'total' => $totalBedsInResponse,
                'available' => $availableBedsInResponse,
                'occupied' => $occupiedBedsInResponse,
                'maintenance' => $maintenanceBedsInResponse,
            ];
            
            Log::info('API Final Statistics (from response beds):', [
                'final_stats' => $finalStats,
                'stats_from_calc' => $stats,
            ]);
            
            return response()->json([
                'status' => true,
                'data' => [
                    'statistics' => $finalStats,
                    'bed_types' => $bedTypes,
                    'bed_status' => 'all'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching bed status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bed statistics
     */
    private function getBedStatistics($user = null, $clinicId = null)
    {
        // Get clinic IDs where the doctor is mapped
        $doctorClinicIds = collect();
        if ($user && $user->hasRole('doctor')) {
            $doctorClinicIds = \Modules\Clinic\Models\DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id');
        }
        
        // Get beds filtered by authenticated user's role
        // Only include active beds (status = 1) and not deleted
        $bedsQuery = BedMaster::where('status', 1)
            ->whereNull('deleted_at');
        
        // Apply role-based filtering
        if ($user) {
            // For receptionists, filter by clinic_id instead of clinic_admin_id
            if ($user->hasRole('receptionist')) {
                // Get the receptionist's clinic_id
                $receptionist = \Modules\Clinic\Models\Receptionist::where('receptionist_id', $user->id)->first();
                
                if ($receptionist && $receptionist->clinic_id) {
                    // Filter beds by the receptionist's clinic_id
                    $bedsQuery->where('clinic_id', $receptionist->clinic_id);
                } else {
                    // If receptionist has no clinic association, return empty
                    $bedsQuery->whereRaw('1 = 0');
                }
            } elseif ($user->hasRole('doctor')) {
                // For doctors, filter by clinic_id from DoctorClinicMapping
                if ($doctorClinicIds->isNotEmpty()) {
                    $bedsQuery->whereIn('clinic_id', $doctorClinicIds);
                } else {
                    // If doctor has no clinic mappings, return empty
                    $bedsQuery->whereRaw('1 = 0');
                }
            } elseif ($user->hasRole('vendor')) {
                // For vendors, filter by clinic_admin_id
                $bedsQuery->where('clinic_admin_id', $user->id);
            } else {
                // For other roles (admin, etc.), use SetRole scope
                // Note: SetRole might override the where clauses, so we need to apply it carefully
                $bedsQuery = BedMaster::SetRole($user)
                    ->where('status', 1)
                    ->whereNull('deleted_at');
            }
        }
        
        if ($clinicId) {
            // First get active allocations for this clinic (excluding closed encounters)
            // Note: We don't filter by allocation status here because if dates are valid and encounter is active,
            // the bed is occupied regardless of the allocation's status field
            $today = now()->format('Y-m-d');
            $activeAllocationsForClinic = BedAllocation::whereNull('deleted_at')
                ->where('clinic_id', $clinicId)
                ->whereDate('assign_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('discharge_date')
                      ->orWhereDate('discharge_date', '>=', $today);
                })
                ->with('patientEncounter')
                ->get()
                ->filter(function($allocation) {
                    // Exclude allocations from closed encounters
                    if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                        return false;
                    }
                    return true;
                })
                ->pluck('bed_master_id')
                ->toArray();
            
            // Include beds that either:
            // 1. Have clinic_id matching the filter, OR
            // 2. Have active allocations with clinic_id matching the filter
            $bedsQuery->where(function($q) use ($clinicId, $activeAllocationsForClinic) {
                $q->where('clinic_id', $clinicId);
                if (!empty($activeAllocationsForClinic)) {
                    $q->orWhereIn('id', $activeAllocationsForClinic);
                }
            });
        }
        $beds = $bedsQuery->get();
        
        // Get all active bed allocations
        // An active allocation must:
        // 1. Have status = 1 (true)
        // 2. Not be deleted
        // 3. Have assign_date <= today (allocation has started)
        // 4. Have discharge_date >= today OR null (allocation hasn't ended)
        // 5. Encounter must be open (status = 1) - if encounter is closed, bed is available
        // Use whereDate for proper date comparison
        // Note: We don't filter by allocation status here because if dates are valid and encounter is active,
        // the bed is occupied regardless of the allocation's status field
        $today = now()->format('Y-m-d');
        $activeAllocationsQuery = BedAllocation::whereNull('deleted_at')
            ->whereDate('assign_date', '<=', $today)
            ->where(function($q) use ($today) {
                $q->whereNull('discharge_date')
                  ->orWhereDate('discharge_date', '>=', $today);
            })
            ->with('patientEncounter'); // Load encounter to check status

        // Filter allocations by authenticated user's role
        if ($user) {
            $activeAllocationsQuery = BedAllocation::SetRole($user, $activeAllocationsQuery);
        }

        // Filter by clinic_id if provided
        if ($clinicId) {
            $activeAllocationsQuery->where('clinic_id', $clinicId);
        }

        // Filter out allocations from closed encounters
        $allocationsBeforeEncounterFilter = $activeAllocationsQuery->get();
        
        Log::info('Statistics: Allocations Before Encounter Filter:', [
            'count' => $allocationsBeforeEncounterFilter->count(),
            'allocations' => $allocationsBeforeEncounterFilter->map(function($a) {
                return [
                    'id' => $a->id,
                    'bed_master_id' => $a->bed_master_id,
                    'status' => $a->status,
                    'encounter_status' => $a->patientEncounter ? $a->patientEncounter->status : null,
                ];
            })->toArray()
        ]);
        
        $activeAllocations = $allocationsBeforeEncounterFilter->filter(function($allocation) {
                // Exclude allocations where encounter is closed (status = 0)
                // If encounter is closed, bed should be available even if discharge date hasn't passed
                if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                    return false; // Encounter is closed, so this allocation doesn't occupy the bed
                }
                return true; // Encounter is active, so this allocation occupies the bed
            })
            ->pluck('bed_master_id')
            ->toArray();

        Log::info('Statistics: Active Allocations After All Filters:', [
            'count' => count($activeAllocations),
            'bed_master_ids' => $activeAllocations
        ]);

        $totalBeds = $beds->count();
        $maintenanceBeds = 0;
        $occupiedBeds = 0;
        $availableBeds = 0;
        
        $bedStatusDetails = [];

        foreach ($beds as $bed) {
            $bedStatus = 'available';
            if ($bed->is_under_maintenance) {
                $maintenanceBeds++;
                $bedStatus = 'maintenance';
            } elseif (in_array($bed->id, $activeAllocations)) {
                $occupiedBeds++;
                $bedStatus = 'occupied';
            } else {
                $availableBeds++;
            }
            
            $bedStatusDetails[] = [
                'bed_id' => $bed->id,
                'bed_name' => $bed->bed,
                'bed_status' => $bed->status,
                'is_under_maintenance' => $bed->is_under_maintenance,
                'has_allocation' => in_array($bed->id, $activeAllocations),
                'determined_status' => $bedStatus
            ];
        }

        Log::info('Bed Statistics:', [
            'total' => $totalBeds,
            'available' => $availableBeds,
            'occupied' => $occupiedBeds,
            'maintenance' => $maintenanceBeds,
            'active_allocations_count' => count($activeAllocations),
            'bed_status_details' => $bedStatusDetails,
            'clinic_id' => $clinicId
        ]);

        return [
            'total' => $totalBeds,
            'available' => $availableBeds,
            'occupied' => $occupiedBeds,
            'maintenance' => $maintenanceBeds,
        ];
    }

    /**
     * Get bed details
     */
    public function getBedDetails($id)
    {
        try {
            $bed = BedMaster::with([
                'bedType',
                'currentAllocation' => function($query) {
                    $query->with('patient')
                        ->where('status', true)
                        ->where(function($q) {
                            $q->whereNull('discharge_date')
                              ->orWhere('discharge_date', '>', now());
                        });
                }
            ])->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $bed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching bed details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle maintenance status of bed
     */
    public function toggleMaintenance(Request $request, $id)
    {
        try {
            $bed = BedMaster::findOrFail($id);
            $bed->is_under_maintenance = !$bed->is_under_maintenance;
            $bed->save();

            $message = $bed->is_under_maintenance 
                ? 'Bed marked as under maintenance'
                : 'Bed maintenance status removed';

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'bed_id' => $bed->id,
                    'is_under_maintenance' => $bed->is_under_maintenance
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error toggling maintenance status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get beds by status (for filtering)
     */
    public function getBedsByStatus($status, Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Start query with SetRole scope to filter based on authenticated user's role
            $query = BedMaster::SetRole($user)
                ->with(['bedType', 'currentAllocation' => function($q) {
                $q->with('patient')
                    ->where('status', true)
                    ->where(function($subQ) {
                        $subQ->whereNull('discharge_date')
                            ->orWhere('discharge_date', '>', now());
                    });
            }]);

            switch ($status) {
                case 'available':
                    $query->where('is_under_maintenance', false)
                        ->where(function($q) {
                            $q->whereDoesntHave('currentAllocation')
                                ->orWhereHas('currentAllocation', function($subQ) {
                                    $subQ->where('status', false)
                                        ->orWhere(function($q) {
                                            $q->where('status', true)
                                                ->where('discharge_date', '<=', now());
                                        });
                                });
                        });
                    break;
                case 'occupied':
                    $query->where('is_under_maintenance', false)
                        ->whereHas('currentAllocation', function($q) {
                            $q->where('status', true)
                                ->where(function($subQ) {
                                    $subQ->whereNull('discharge_date')
                                        ->orWhere('discharge_date', '>', now());
                                });
                        });
                    break;
                case 'maintenance':
                    $query->where('is_under_maintenance', true);
                    break;
                default:
                    // Return all beds
                    break;
            }

            $beds = $query->get()->map(function($bed) {
                if ($bed->is_under_maintenance) {
                    $bed->current_status = 'maintenance';
                } elseif ($bed->currentAllocation && $bed->currentAllocation->status) {
                    if (!$bed->currentAllocation->discharge_date || 
                        strtotime($bed->currentAllocation->discharge_date) > time()) {
                        $bed->current_status = 'occupied';
                    } else {
                        $bed->current_status = 'available';
                    }
                } else {
                    $bed->current_status = 'available';
                }
                return $bed;
            });

            Log::info('Beds by status ' . $status . ':', [
                'count' => $beds->count(),
                'statuses' => $beds->pluck('current_status')->countBy()->toArray()
            ]);

            return response()->json([
                'status' => true,
                'data' => $beds
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getBedsByStatus: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching beds: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available beds
     */
    public function getAvailableBeds(Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Filter beds based on authenticated user's role
            $beds = BedMaster::SetRole($user)
                ->with(['bedType'])
                ->where('is_under_maintenance', false)
                ->where(function($q) {
                    $q->whereDoesntHave('currentAllocation')
                        ->orWhereHas('currentAllocation', function($subQ) {
                            $subQ->where('status', false)
                                ->orWhere(function($q) {
                                    $q->where('status', true)
                                        ->where('discharge_date', '<=', now());
                                });
                        });
                })
                ->get()
                ->map(function($bed) {
                    $bed->current_status = 'available';
                    return $bed;
                });

            Log::info('Available beds count: ' . $beds->count());

            return response()->json([
                'status' => true,
                'data' => [
                    'beds' => $beds,
                    'bed_status' => 'available'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAvailableBeds: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching available beds: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get occupied beds
     */
    public function getOccupiedBeds(Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Filter beds based on authenticated user's role
            $beds = BedMaster::SetRole($user)
                ->with(['bedType', 'currentAllocation.patient'])
                ->where('is_under_maintenance', false)
                ->whereHas('currentAllocation', function($q) {
                    $q->whereNull('deleted_at')
                        ->where('status', true)
                        ->where(function($subQ) {
                            $subQ->whereNull('discharge_date')
                                ->orWhere('discharge_date', '>', now());
                        });
                })
                ->get()
                ->map(function($bed) {
                    $bed->current_status = 'occupied';
                    return $bed;
                });

            Log::info('Occupied beds count: ' . $beds->count());

            return response()->json([
                'status' => true,
                'data' => [
                    'beds' => $beds,
                    'bed_status' => 'occupied'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getOccupiedBeds: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching occupied beds: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get maintenance beds
     */
    public function getMaintenanceBeds(Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Filter beds based on authenticated user's role
            $beds = BedMaster::SetRole($user)
                ->with(['bedType'])
                ->where('is_under_maintenance', true)
                ->get()
                ->map(function($bed) {
                    $bed->current_status = 'maintenance';
                    return $bed;
                });

            Log::info('Maintenance beds count: ' . $beds->count());

            return response()->json([
                'status' => true,
                'data' => [
                    'beds' => $beds,
                    'bed_status' => 'maintenance'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getMaintenanceBeds: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching maintenance beds: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all beds with their current status
     */
    public function getAllBeds(Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Filter beds based on authenticated user's role
            $beds = BedMaster::SetRole($user)
                ->with(['bedType', 'currentAllocation.patient'])
                ->get()
                ->map(function($bed) {
                    if ($bed->is_under_maintenance) {
                        $bed->current_status = 'maintenance';
                    } elseif ($bed->currentAllocation && $bed->currentAllocation->deleted_at === null) {
                        $bed->current_status = 'occupied';
                    } else {
                        $bed->current_status = 'available';
                    }
                    return $bed;
                });

            return response()->json([
                'status' => true,
                'data' => [
                    'beds' => $beds,
                    'bed_status' => 'all'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching all beds: ' . $e->getMessage()
            ], 500);
        }
    }
} 