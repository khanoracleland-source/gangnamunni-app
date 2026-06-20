<?php

namespace Modules\Bed\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Bed\Models\BedMaster;
use Modules\Bed\Models\BedType;
use Modules\Bed\Models\BedAllocation;
use Modules\Clinic\Models\DoctorClinicMapping;
use Modules\Clinic\Models\Receptionist;
use Illuminate\Http\Request;

class BedStatusController extends Controller
{
    protected $module_name = 'bed-status';
    protected $module_title = 'messages.bed_status';

    /**
     * Display the bed status overview
     */
    public function index()
    {
        $module_action = 'List';
        $module_name = $this->module_name;
        $module_title = $this->module_title;

        $user = auth()->user();
        
        // Get receptionist's clinic ID if user is a receptionist (needed for filtering)
        $receptionistClinicId = null;
        if ($user && $user->hasRole('receptionist')) {
            $receptionist = Receptionist::where('receptionist_id', $user->id)->first();
            if ($receptionist && $receptionist->clinic_id) {
                $receptionistClinicId = $receptionist->clinic_id;
            }
        }
        
        // Get all active bed allocations once (to avoid N+1 queries)
        // An active allocation must:
        // 1. Have status = 1 (true)
        // 2. Not be deleted
        // 3. Have assign_date <= today (allocation has started)
        // 4. Have discharge_date >= today OR null (allocation hasn't ended)
        $today = now()->format('Y-m-d');
        
        // Use whereDate for proper date comparison
        // An allocation is active if:
        // 1. It has valid dates (assign_date <= today AND discharge_date >= today OR null)
        // 2. The encounter is not closed (status != 0)
        // Note: We don't filter by allocation status here because if dates are valid and encounter is active,
        // the bed is occupied regardless of the allocation's status field
        $activeAllocationsQuery = BedAllocation::whereNull('deleted_at')
            ->with('patientEncounter')
            ->whereDate('assign_date', '<=', $today)
            ->where(function($q) use ($today) {
                $q->whereNull('discharge_date')
                  ->orWhereDate('discharge_date', '>=', $today);
            });
        
        // Filter by doctor if user is a doctor
        if ($user && $user->hasRole('doctor')) {
            $activeAllocationsQuery->whereHas('patientEncounter', function ($q) use ($user) {
                $q->where('doctor_id', $user->id);
            });
        }
        
        // Filter by clinic admin if user is a vendor/clinic admin
        if ($user && $user->hasRole('vendor')) {
            $activeAllocationsQuery->where('clinic_admin_id', $user->id);
        }
        
        // Filter by clinic if user is a receptionist
        if ($user && $user->hasRole('receptionist') && $receptionistClinicId) {
            $activeAllocationsQuery->where('clinic_id', $receptionistClinicId);
        }
        
        // Get all allocations before filtering
        $allAllocationsBeforeFilter = $activeAllocationsQuery->get();
        
        \Log::info('All Allocations Before Encounter Filter:', [
            'count' => $allAllocationsBeforeFilter->count(),
            'allocations' => $allAllocationsBeforeFilter->map(function($a) {
                return [
                    'id' => $a->id,
                    'bed_master_id' => $a->bed_master_id,
                    'status' => $a->status,
                    'assign_date' => $a->assign_date,
                    'discharge_date' => $a->discharge_date,
                    'encounter_id' => $a->encounter_id,
                    'encounter_status' => $a->patientEncounter ? $a->patientEncounter->status : null,
                ];
            })->toArray()
        ]);
        
        $activeAllocations = $allAllocationsBeforeFilter->filter(function($allocation) {
                // Exclude allocations where encounter is closed (status = 0)
                // If encounter is closed, bed should be available even if discharge date hasn't passed
                if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                    return false;
                }
                return true;
            })
            ->keyBy('bed_master_id');
        
        \Log::info('Active Allocations After All Filters:', [
            'count' => $activeAllocations->count(),
            'bed_master_ids' => $activeAllocations->keys()->toArray(),
            'allocations' => $activeAllocations->map(function($a) {
                return [
                    'id' => $a->id,
                    'bed_master_id' => $a->bed_master_id,
                    'status' => $a->status,
                    'encounter_status' => $a->patientEncounter ? $a->patientEncounter->status : null,
                ];
            })->values()->toArray()
        ]);
        
        // Get clinic IDs where the doctor is mapped
        $doctorClinicIds = collect();
        if ($user && $user->hasRole('doctor')) {
            $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id');
        }

        // Get all bed types with their beds
        $bedTypesQuery = BedType::with('beds');
        
        // Filter beds by doctor's clinics if user is a doctor
        if ($user && $user->hasRole('doctor') && $doctorClinicIds->isNotEmpty()) {
            $bedTypesQuery->whereHas('beds', function ($q) use ($doctorClinicIds) {
                $q->whereIn('clinic_id', $doctorClinicIds);
            });
        }
        
        // Filter beds by clinic admin if user is a vendor/clinic admin
        if ($user && $user->hasRole('vendor')) {
            $bedTypesQuery->whereHas('beds', function ($q) use ($user) {
                $q->where('clinic_admin_id', $user->id);
            });
        }
        
        // Filter beds by clinic if user is a receptionist
        if ($user && $user->hasRole('receptionist') && $receptionistClinicId) {
            $bedTypesQuery->whereHas('beds', function ($q) use ($receptionistClinicId) {
                $q->where('clinic_id', $receptionistClinicId);
            });
        }
        
        $bedTypes = $bedTypesQuery->get()->map(function ($bedType) use ($activeAllocations, $user, $doctorClinicIds, $receptionistClinicId) {
            // Filter beds by doctor's clinics if user is a doctor
            if ($user && $user->hasRole('doctor') && $doctorClinicIds->isNotEmpty()) {
                $bedType->beds = $bedType->beds->filter(function ($bed) use ($doctorClinicIds) {
                    return $doctorClinicIds->contains($bed->clinic_id);
                });
            }
            
            // Filter beds by clinic admin if user is a vendor/clinic admin
            if ($user && $user->hasRole('vendor')) {
                $bedType->beds = $bedType->beds->filter(function ($bed) use ($user) {
                    return $bed->clinic_admin_id == $user->id;
                });
            }
            
            // Filter beds by clinic if user is a receptionist
            if ($user && $user->hasRole('receptionist') && $receptionistClinicId) {
                $bedType->beds = $bedType->beds->filter(function ($bed) use ($receptionistClinicId) {
                    return $bed->clinic_id == $receptionistClinicId;
                });
            }
            
            // Use each() to modify beds in place instead of map()
            $bedType->beds->each(function ($bed) use ($activeAllocations) {
                // Get active allocation for this bed
                $activeAllocation = $activeAllocations->get($bed->id);
                
                // Debug: Log each bed's status
                \Log::info('Bed Status Check:', [
                    'bed_id' => $bed->id,
                    'bed_name' => $bed->bed,
                    'bed_status' => $bed->status,
                    'is_under_maintenance' => $bed->is_under_maintenance,
                    'has_active_allocation' => $activeAllocation ? true : false,
                    'active_allocation_id' => $activeAllocation ? $activeAllocation->id : null,
                    'active_allocation_status' => $activeAllocation ? $activeAllocation->status : null,
                    'active_allocation_encounter_status' => $activeAllocation && $activeAllocation->patientEncounter ? $activeAllocation->patientEncounter->status : null,
                    'all_active_allocation_bed_ids' => $activeAllocations->keys()->toArray(),
                ]);
                
                // Set current_status consistently
                // Check if bed is inactive (status = 0) or under maintenance - both should be unavailable
                if ($bed->is_under_maintenance || !$bed->status) {
                    $bed->setAttribute('current_status', 'maintenance');
                } elseif ($activeAllocation) {
                    $bed->setAttribute('current_status', 'occupied');
                    // Set both snake_case and camelCase for compatibility with view
                    $bed->setAttribute('current_allocation', $activeAllocation);
                    $bed->setRelation('currentAllocation', $activeAllocation);
                } else {
                    $bed->setAttribute('current_status', 'available');
                    $bed->setAttribute('current_allocation', null);
                    $bed->setRelation('currentAllocation', null);
                }
            });
            return $bedType;
        })->filter(function ($bedType) {
            // Remove bed types that have no beds after filtering
            return $bedType->beds->isNotEmpty();
        });
        
        // Calculate statistics from the actual beds being displayed
        $allDisplayedBeds = collect();
        foreach ($bedTypes as $bedType) {
            $allDisplayedBeds = $allDisplayedBeds->merge($bedType->beds);
        }
        
        $totalBeds = $allDisplayedBeds->count();
        $maintenanceBeds = 0;
        $occupiedBeds = 0;
        $availableBeds = 0;
        
        $bedStatusDetails = [];
        foreach ($allDisplayedBeds as $bed) {
            $status = $bed->getAttribute('current_status');
            $bedStatusDetails[] = [
                'bed_id' => $bed->id,
                'bed_name' => $bed->bed,
                'current_status' => $status,
                'is_under_maintenance' => $bed->is_under_maintenance,
                'bed_status' => $bed->status,
            ];
            if ($status === 'maintenance') {
                $maintenanceBeds++;
            } elseif ($status === 'occupied') {
                $occupiedBeds++;
            } elseif ($status === 'available') {
                $availableBeds++;
            }
        }
        
        \Log::info('Statistics Calculation:', [
            'total_beds' => $totalBeds,
            'available' => $availableBeds,
            'occupied' => $occupiedBeds,
            'maintenance' => $maintenanceBeds,
            'bed_status_details' => $bedStatusDetails
        ]);
        
        $stats = [
            'total' => $totalBeds,
            'available' => $availableBeds,
            'occupied' => $occupiedBeds,
            'maintenance' => $maintenanceBeds,
        ];
        
        \Log::info('Final Stats Being Passed to View:', [
            'stats' => $stats,
            'bed_types_count' => $bedTypes->count(),
            'total_beds_in_bedtypes' => $bedTypes->sum(function($bt) {
                return $bt->beds->count();
            })
        ]);

        return view('bed::bed_status.index', compact(
            'module_action',
            'module_name',
            'module_title',
            'stats',
            'bedTypes'
        ));
    }

    /**
     * Get bed statistics
     */
    private function getBedStatistics($user = null)
    {
        // Get clinic IDs where the doctor is mapped
        $doctorClinicIds = collect();
        if ($user && $user->hasRole('doctor')) {
            $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id');
            
            // If doctor has no clinic mappings, return empty stats
            if ($doctorClinicIds->isEmpty()) {
                return [
                    'total' => 0,
                    'available' => 0,
                    'occupied' => 0,
                    'maintenance' => 0,
                ];
            }
            
            // Get only beds from doctor's clinics
            $beds = BedMaster::whereIn('clinic_id', $doctorClinicIds)->get();
        } elseif ($user && $user->hasRole('vendor')) {
            // Get only beds that belong to this clinic admin
            $beds = BedMaster::where('clinic_admin_id', $user->id)->get();
        } elseif ($user && $user->hasRole('receptionist')) {
            // Get receptionist's clinic ID
            $receptionist = Receptionist::where('receptionist_id', $user->id)->first();
            if ($receptionist && $receptionist->clinic_id) {
                // Get only beds from receptionist's clinic
                $beds = BedMaster::where('clinic_id', $receptionist->clinic_id)->get();
            } else {
                // If receptionist has no clinic, return empty
                $beds = collect();
            }
        } else {
            // Get all beds for admin and other users
            $beds = BedMaster::all();
        }
        
        // Get all active bed allocations
        // An active allocation must:
        // 1. Not be deleted
        // 2. Have assign_date <= today (allocation has started)
        // 3. Have discharge_date >= today OR null (allocation hasn't ended)
        // 4. Encounter must not be closed (status != 0) - if encounter is closed, bed is available
        // Note: We don't filter by allocation status here because if dates are valid and encounter is active,
        // the bed is occupied regardless of the allocation's status field
        $today = now()->format('Y-m-d');
        // Use whereDate for proper date comparison
        // An allocation is active if it has valid dates AND encounter is not closed
        $activeAllocationsQuery = BedAllocation::whereNull('deleted_at')
            ->with('patientEncounter')
            ->whereDate('assign_date', '<=', $today)
            ->where(function($q) use ($today) {
                $q->whereNull('discharge_date')
                  ->orWhereDate('discharge_date', '>=', $today);
            });
        
        // Filter by doctor if user is a doctor
        if ($user && $user->hasRole('doctor')) {
            $activeAllocationsQuery->whereHas('patientEncounter', function ($q) use ($user) {
                $q->where('doctor_id', $user->id);
            });
        }
        
        // Filter by clinic admin if user is a vendor/clinic admin
        if ($user && $user->hasRole('vendor')) {
            $activeAllocationsQuery->where('clinic_admin_id', $user->id);
        }
        
        $activeAllocations = $activeAllocationsQuery->get()
            ->filter(function($allocation) {
                // Exclude allocations where encounter is closed (status = 0)
                if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                    return false;
                }
                return true;
            })
            ->pluck('bed_master_id')
            ->toArray();
        
        \Log::info('getBedStatistics: Active Allocations:', [
            'count' => count($activeAllocations),
            'bed_master_ids' => $activeAllocations,
            'user_id' => $user ? $user->id : null,
            'user_roles' => $user ? $user->getRoleNames()->toArray() : [],
        ]);

        $totalBeds = $beds->count();
        $maintenanceBeds = 0;
        $occupiedBeds = 0;
        $availableBeds = 0;

        foreach ($beds as $bed) {
            // Check if bed is inactive (status = 0) or under maintenance - both should be unavailable
            if ($bed->is_under_maintenance || !$bed->status) {
                $maintenanceBeds++;
                continue;
            }
            // Check if bed has an active allocation by checking if bed_master_id is in active allocations
            if (in_array($bed->id, $activeAllocations)) {
                $occupiedBeds++;
            } else {
                $availableBeds++;
            }
        }
        
        $stats = [
            'total' => $totalBeds,
            'available' => $availableBeds,
            'occupied' => $occupiedBeds,
            'maintenance' => $maintenanceBeds,
        ];
        
        \Log::info('getBedStatistics: Final Statistics:', [
            'stats' => $stats,
            'beds_count' => $totalBeds,
            'active_allocations_count' => count($activeAllocations),
        ]);

        return $stats;
    }

    /**
     * Get bed details
     */
    public function getBedDetails($id)
    {
        try {
            $bed = BedMaster::with([
                'bedType',
                'currentAllocation.patient'
            ])->findOrFail($id);

            // Check if bed is inactive (status = 0) or under maintenance - both should be unavailable
            if ($bed->is_under_maintenance || !$bed->status) {
                $bed->current_status = 'maintenance';
            } elseif ($bed->currentAllocation) {
                $bed->current_status = 'occupied';
            } else {
                $bed->current_status = 'available';
            }

            // Format charges with proper currency
            $bed->formatted_charges = \Currency::format($bed->charges);

            return response()->json([
                'status' => true,
                'data' => $bed
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching bed details: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching bed details'
            ], 500);
        }
    }

    /**
     * Toggle maintenance status of bed
     */
    public function toggleMaintenance(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $bed = BedMaster::findOrFail($id);
            $bed->is_under_maintenance = !$bed->is_under_maintenance;
            $bed->save();

            // Get updated statistics
            $stats = $this->getBedStatistics($user);

            $message = $bed->is_under_maintenance 
                ? 'Bed marked as under maintenance'
                : 'Bed maintenance status removed';

            return response()->json([
                'status' => true,
                'message' => $message,
                'new_maintenance_status' => $bed->is_under_maintenance,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('Bed Maintenance Toggle Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get beds by status (for filtering)
     */
    public function getBedsByStatus($status)
    {
        try {
            $user = auth()->user();
            $today = now()->format('Y-m-d');
            $query = BedMaster::with('bedType');
            
            // Get clinic IDs where the doctor is mapped
            $doctorClinicIds = collect();
            if ($user && $user->hasRole('doctor')) {
                $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                    ->pluck('clinic_id');
                
                // Filter beds by doctor's clinics
                if ($doctorClinicIds->isNotEmpty()) {
                    $query->whereIn('clinic_id', $doctorClinicIds);
                } else {
                    // If doctor has no clinic mappings, return empty result
                    return response()->json([
                        'status' => true,
                        'data' => collect(),
                        'stats' => $this->getBedStatistics($user)
                    ]);
                }
            } elseif ($user && $user->hasRole('vendor')) {
                // Filter beds by clinic admin if user is a vendor/clinic admin
                $query->where('clinic_admin_id', $user->id);
            } elseif ($user && $user->hasRole('receptionist')) {
                // Get receptionist's clinic ID
                $receptionist = Receptionist::where('receptionist_id', $user->id)->first();
                if ($receptionist && $receptionist->clinic_id) {
                    // Filter beds by receptionist's clinic
                    $query->where('clinic_id', $receptionist->clinic_id);
                } else {
                    // If receptionist has no clinic, return empty result
                    return response()->json([
                        'status' => true,
                        'data' => collect(),
                        'stats' => $this->getBedStatistics($user)
                    ]);
                }
            }

            // Get all active bed allocations
            // An active allocation must:
            // 1. Not be deleted
            // 2. Have assign_date <= today (allocation has started)
            // 3. Have discharge_date >= today OR null (allocation hasn't ended)
            // 4. Encounter must not be closed (status != 0) - if encounter is closed, bed is available
            // Note: We don't filter by allocation status here because if dates are valid and encounter is active,
            // the bed is occupied regardless of the allocation's status field
            // Use whereDate for proper date comparison
            // An allocation is active if it has valid dates AND encounter is not closed
            $activeAllocationsQuery = BedAllocation::whereNull('deleted_at')
                ->with('patientEncounter')
                ->whereDate('assign_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('discharge_date')
                      ->orWhereDate('discharge_date', '>=', $today);
                });
            
            // Filter by doctor if user is a doctor
            if ($user && $user->hasRole('doctor')) {
                $activeAllocationsQuery->whereHas('patientEncounter', function ($q) use ($user) {
                    $q->where('doctor_id', $user->id);
                });
            }
            
            // Filter by clinic admin if user is a vendor/clinic admin
            if ($user && $user->hasRole('vendor')) {
                $activeAllocationsQuery->where('clinic_admin_id', $user->id);
            }
            
            // Filter by clinic if user is a receptionist
            if ($user && $user->hasRole('receptionist')) {
                $receptionist = Receptionist::where('receptionist_id', $user->id)->first();
                if ($receptionist && $receptionist->clinic_id) {
                    $activeAllocationsQuery->where('clinic_id', $receptionist->clinic_id);
                }
            }
            
            $activeAllocations = $activeAllocationsQuery->get()
                ->filter(function($allocation) {
                    // Exclude allocations where encounter is closed (status = 0)
                    if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                        return false;
                    }
                    return true;
                })
                ->pluck('bed_master_id')
                ->toArray();

            switch ($status) {
                case 'available':
                    $query->where('is_under_maintenance', false)
                          ->where('status', true)
                          ->whereNotIn('id', $activeAllocations);
                    break;
                case 'occupied':
                    $query->where('is_under_maintenance', false)
                          ->where('status', true)
                          ->whereIn('id', $activeAllocations);
                    break;
                case 'maintenance':
                    $query->where(function($q) {
                        $q->where('is_under_maintenance', true)
                          ->orWhere('status', false);
                    });
                    break;
                default:
                    // all beds
                    break;
            }

            $beds = $query->get()->map(function($bed) use ($activeAllocations) {
                // Check if bed is inactive (status = 0) or under maintenance - both should be unavailable
                if ($bed->is_under_maintenance || !$bed->status) {
                    $bed->current_status = 'maintenance';
                } elseif (in_array($bed->id, $activeAllocations)) {
                    $bed->current_status = 'occupied';
                } else {
                    $bed->current_status = 'available';
                }
                return $bed;
            });

            // Calculate statistics from the filtered beds, not all beds
            // This ensures the count matches what's actually displayed
            $totalBeds = $beds->count();
            $maintenanceBeds = 0;
            $occupiedBeds = 0;
            $availableBeds = 0;
            
            foreach ($beds as $bed) {
                if ($bed->current_status === 'maintenance') {
                    $maintenanceBeds++;
                } elseif ($bed->current_status === 'occupied') {
                    $occupiedBeds++;
                } elseif ($bed->current_status === 'available') {
                    $availableBeds++;
                }
            }
            
            $stats = [
                'total' => $totalBeds,
                'available' => $availableBeds,
                'occupied' => $occupiedBeds,
                'maintenance' => $maintenanceBeds,
            ];

            return response()->json([
                'status' => true,
                'data' => $beds,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching beds by status: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching beds'
            ], 500);
        }
    }

    /**
     * Refresh bed status
     */
    public function refreshStatus()
    {
        try {
            $user = auth()->user();
            
            // Get all active bed allocations once (to avoid N+1 queries)
            // Use whereDate for proper date comparison
            // An allocation is active if it has valid dates AND encounter is not closed
            $today = now()->format('Y-m-d');
            $activeAllocationsQuery = BedAllocation::whereNull('deleted_at')
                ->with('patientEncounter')
                ->whereDate('assign_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('discharge_date')
                      ->orWhereDate('discharge_date', '>=', $today);
                });
            
            // Filter by doctor if user is a doctor
            if ($user && $user->hasRole('doctor')) {
                $activeAllocationsQuery->whereHas('patientEncounter', function ($q) use ($user) {
                    $q->where('doctor_id', $user->id);
                });
            }
            
            // Filter by clinic admin if user is a vendor/clinic admin
            if ($user && $user->hasRole('vendor')) {
                $activeAllocationsQuery->where('clinic_admin_id', $user->id);
            }
            
            // Filter by clinic if user is a receptionist
            if ($user && $user->hasRole('receptionist')) {
                $receptionist = Receptionist::where('receptionist_id', $user->id)->first();
                if ($receptionist && $receptionist->clinic_id) {
                    $activeAllocationsQuery->where('clinic_id', $receptionist->clinic_id);
                }
            }
            
            $activeAllocations = $activeAllocationsQuery->get()
                ->filter(function($allocation) {
                    // Exclude allocations where encounter is closed (status = 0)
                    if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                        return false;
                    }
                    return true;
                })
                ->keyBy('bed_master_id');
            
            // Get clinic IDs where the doctor is mapped
            $doctorClinicIds = collect();
            if ($user && $user->hasRole('doctor')) {
                $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                    ->pluck('clinic_id');
            }

            // Get all bed types with their beds
            $bedTypesQuery = BedType::with('beds');
            
            // Filter beds by doctor's clinics if user is a doctor
            if ($user && $user->hasRole('doctor') && $doctorClinicIds->isNotEmpty()) {
                $bedTypesQuery->whereHas('beds', function ($q) use ($doctorClinicIds) {
                    $q->whereIn('clinic_id', $doctorClinicIds);
                });
            }
            
            // Filter beds by clinic admin if user is a vendor/clinic admin
            if ($user && $user->hasRole('vendor')) {
                $bedTypesQuery->whereHas('beds', function ($q) use ($user) {
                    $q->where('clinic_admin_id', $user->id);
                });
            }
            
            // Get receptionist's clinic ID if user is a receptionist
            $receptionistClinicId = null;
            if ($user && $user->hasRole('receptionist')) {
                $receptionist = Receptionist::where('receptionist_id', $user->id)->first();
                if ($receptionist && $receptionist->clinic_id) {
                    $receptionistClinicId = $receptionist->clinic_id;
                    $bedTypesQuery->whereHas('beds', function ($q) use ($receptionistClinicId) {
                        $q->where('clinic_id', $receptionistClinicId);
                    });
                }
            }
            
            $bedTypes = $bedTypesQuery->get()->map(function ($bedType) use ($activeAllocations, $user, $doctorClinicIds, $receptionistClinicId) {
                // Filter beds by doctor's clinics if user is a doctor
                if ($user && $user->hasRole('doctor') && $doctorClinicIds->isNotEmpty()) {
                    $bedType->beds = $bedType->beds->filter(function ($bed) use ($doctorClinicIds) {
                        return $doctorClinicIds->contains($bed->clinic_id);
                    });
                }
                
                // Filter beds by clinic admin if user is a vendor/clinic admin
                if ($user && $user->hasRole('vendor')) {
                    $bedType->beds = $bedType->beds->filter(function ($bed) use ($user) {
                        return $bed->clinic_admin_id == $user->id;
                    });
                }
                
                // Filter beds by clinic if user is a receptionist
                if ($user && $user->hasRole('receptionist') && $receptionistClinicId) {
                    $bedType->beds = $bedType->beds->filter(function ($bed) use ($receptionistClinicId) {
                        return $bed->clinic_id == $receptionistClinicId;
                    });
                }
                
                // Use each() to modify beds in place instead of map()
                $bedType->beds->each(function ($bed) use ($activeAllocations) {
                    // Get active allocation for this bed
                    $activeAllocation = $activeAllocations->get($bed->id);
                    
                    // Check if bed is inactive (status = 0) or under maintenance - both should be unavailable
                    if ($bed->is_under_maintenance || !$bed->status) {
                        $bed->setAttribute('current_status', 'maintenance');
                    } elseif ($activeAllocation) {
                        $bed->setAttribute('current_status', 'occupied');
                        $bed->setAttribute('current_allocation', $activeAllocation);
                    } else {
                        $bed->setAttribute('current_status', 'available');
                        $bed->setAttribute('current_allocation', null);
                    }
                });
                return $bedType;
            })->filter(function ($bedType) {
                // Remove bed types that have no beds after filtering
                return $bedType->beds->isNotEmpty();
            });
            
            // Calculate statistics from the actual beds being displayed
            $allDisplayedBeds = collect();
            foreach ($bedTypes as $bedType) {
                $allDisplayedBeds = $allDisplayedBeds->merge($bedType->beds);
            }
            
            $totalBeds = $allDisplayedBeds->count();
            $maintenanceBeds = 0;
            $occupiedBeds = 0;
            $availableBeds = 0;
            
            foreach ($allDisplayedBeds as $bed) {
                $status = $bed->getAttribute('current_status');
                if ($status === 'maintenance') {
                    $maintenanceBeds++;
                } elseif ($status === 'occupied') {
                    $occupiedBeds++;
                } elseif ($status === 'available') {
                    $availableBeds++;
                }
            }
            
            $stats = [
                'total' => $totalBeds,
                'available' => $availableBeds,
                'occupied' => $occupiedBeds,
                'maintenance' => $maintenanceBeds,
            ];

            return response()->json([
                'status' => true,
                'data' => [
                    'stats' => $stats,
                    'bedTypes' => $bedTypes
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error refreshing bed status: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error refreshing bed status'
            ], 500);
        }
    }
}
 