<?php

namespace Modules\Bed\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Bed\Models\BedAllocation;
use Modules\Bed\Models\BedMaster;
use Modules\Bed\Models\BedType;
use Modules\Bed\Models\PatientInfo;
use App\Models\User;
use Modules\Appointment\Models\PatientEncounter;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Import Carbon here
use Modules\Clinic\Models\Clinics;
use Modules\Clinic\Models\DoctorClinicMapping;
use Modules\Bed\Http\Requests\BedAllocationRequest;

class BedAllocationController extends Controller
{
    // Index view - shows list
    public function index(Request $request)
    {
        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            // Return JSON response for API requests
            try {
                $allocations = BedAllocation::SetRole(auth()->user())
                    ->with(['patient', 'bedMaster', 'bedType'])
                    ->whereNull('deleted_at')
                    ->latest()
                    ->get();

                // Use BedAllocationResource for consistent formatting
                $allocationsResource = \Modules\Bed\Transformers\BedAllocationResource::collection($allocations);

                return response()->json([
                    'status' => true,
                    'data' => $allocationsResource
                ]);
            } catch (\Exception $e) {
                \Log::error('API Error fetching bed allocations: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error fetching bed allocations'
                ], 500);
            }
        }

        // Return view for web requests
        $module_name = 'bed-allocation';
        $module_title = 'messages.bed_assign';
        return view('bed::allocations.index', compact('module_name', 'module_title'));
    }

// Data for DataTables (ajax)
public function indexData()
{
    try {
        // Eager load both patient and bedMaster relationships, including patientEncounter, billing record, and appointment details
        $data = BedAllocation::SetRole(auth()->user())
            ->with([
                'patient', 
                'bedMaster', 
                'clinicAdmin', 
                'patientEncounter.billingrecord',
                'patientEncounter.appointmentdetail.appointmenttransaction'
            ])
            ->whereNull('deleted_at')
            ->latest();

        $datatable = datatables()->eloquent($data)
            ->addIndexColumn()
            ->addColumn('check', function ($row) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $row->id . '" name="datatable_ids[]" value="' . $row->id . '" data-type="bed-allocation" onclick="dataTableRowCheck(' . $row->id . ')">';
            })
            ->addColumn('patient_name', function($row) {
                return $row->patient ? $row->patient->first_name . ' ' . $row->patient->last_name : 'N/A';
            });

        // Only add clinic admin column if multi-vendor is enabled
        if (multiVendor() == 1) {
            $datatable->addColumn('clinic_admin_name', function($row) {
                return $row->clinicAdmin ? $row->clinicAdmin->first_name . ' ' . $row->clinicAdmin->last_name : 'N/A';
            });
        }

        $datatable->addColumn('room_number', function($row) {
                return $row->bedMaster ? $row->bedMaster->bed : 'N/A';
            })
            ->addColumn('bed_type', function($row) {
                return $row->bedMaster ? optional($row->bedMaster->bedType)->type : 'N/A';
            })
            ->addColumn('assign_date', function($row) {
                return $row->assign_date ? date('Y-m-d', strtotime($row->assign_date)) : 'N/A';
            })
            ->addColumn('discharge_date', function($row) {
                return $row->discharge_date ? date('Y-m-d', strtotime($row->discharge_date)) : 'N/A';
            })
            ->addColumn('description', fn($row) => $row->description ?? '')
            ->addColumn('temperature', fn($row) => $row->temperature ?? '')
            ->addColumn('symptoms', fn($row) => $row->symptoms ?? '')
            ->addColumn('notes', fn($row) => $row->notes ?? '')
            ->addColumn('charge', fn($row) => $row->charge ? \Currency::format($row->charge) : 'N/A')
            ->addColumn('payment_status', function($row) {
                // Check if appointment/encounter is closed (status = 0)
                $encounterClosed = false;
                $paymentStatus = 0;
                
                if ($row->patientEncounter) {
                    $encounterClosed = ($row->patientEncounter->status == 0);
                    
                    // If encounter is closed, check payment status from billing record
                    if ($encounterClosed && $row->patientEncounter->billingrecord) {
                        $paymentStatus = $row->patientEncounter->billingrecord->payment_status ?? 0;
                    } elseif ($encounterClosed) {
                        // Check appointment transaction payment status as fallback
                        $appointmentTransaction = optional(optional($row->patientEncounter->appointmentdetail)->appointmenttransaction);
                        $paymentStatus = $appointmentTransaction->payment_status ?? 0;
                    }
                }
                
                // If encounter is closed, show payment status from billing/appointment
                if ($encounterClosed) {
                    return $paymentStatus == 1 ? 'Paid' : 'Unpaid';
                }
                
                // If encounter is not closed, show bed payment status
                return $row->bed_payment_status ? 'Paid' : 'Unpaid';
            })
            ->addColumn('created_at', fn($row) => $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '')
            ->addColumn('updated_at', fn($row) => $row->updated_at ? $row->updated_at->format('Y-m-d H:i:s') : '')
            ->addColumn('status_toggle', function ($row) {
                $checked = $row->status ? 'checked' : '';
                $id = $row->id;
                return <<<HTML
<label class="form-check form-switch mb-0">
  <input type="checkbox" class="form-check-input toggle-status" data-id="{$id}" {$checked}>
  <span class="form-check-label"></span>
</label>
HTML;
                })
          ->addColumn('action', function ($row) {
    return view('bed::allocations.action', ['allocation' => $row])->render();
})
            ->filter(function ($query) {
                $request = request();
                if ($request->has('search') && !empty($request->search['value'])) {
                    $search = $request->search['value'];
                    $query->where(function ($q) use ($search) {
                        // Search in patient name
                        $q->whereHas('patient', function ($patientQuery) use ($search) {
                            $patientQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        // Search in bed type
                        ->orWhereHas('bedMaster.bedType', function ($bedTypeQuery) use ($search) {
                            $bedTypeQuery->where('type', 'like', "%{$search}%");
                        })
                        // Search in room/bed number
                        ->orWhereHas('bedMaster', function ($bedQuery) use ($search) {
                            $bedQuery->where('bed', 'like', "%{$search}%");
                        })
                        // Search in symptoms
                        ->orWhere('symptoms', 'like', "%{$search}%")
                        // Search in temperature
                        ->orWhere('temperature', 'like', "%{$search}%")
                        // Search in charge
                        ->orWhere('charge', 'like', "%{$search}%")
                        // Search in payment status
                        ->orWhereRaw("CASE WHEN bed_payment_status = 1 THEN 'Paid' ELSE 'Unpaid' END LIKE ?", ["%{$search}%"]);
                    });
                }
            })
                ->rawColumns(['check', 'status_toggle', 'action']);

        return $datatable->make(true);

    } catch (\Exception $e) {
        \Log::error('DataTables error: ' . $e->getMessage());
        return response()->json(['error' => 'Server error occurred'], 500);
    }
}

// Create new allocation
public function create()
{
    $user = auth()->user();
    
    // Filter clinic admins and clinics based on user role
    if ($user && $user->hasRole('doctor')) {
        // Get clinic IDs where the doctor is mapped
        $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
            ->pluck('clinic_id')
            ->toArray();
        
        if (empty($doctorClinicIds)) {
            // If doctor has no clinic mappings, return empty lists
            $clinicAdmins = collect([]);
            $clinics = collect([]);
        } else {
            // Get clinics where doctor is mapped
            $doctorClinics = Clinics::whereIn('id', $doctorClinicIds)
                ->where('status', 1)
                ->get();
            
            // Get unique vendor IDs (clinic admin IDs) from those clinics
            $vendorIds = $doctorClinics->pluck('vendor_id')->unique()->filter()->toArray();
            
            // Filter clinic admins to only show those vendors
            if (!empty($vendorIds)) {
                $clinicAdmins = User::whereIn('id', $vendorIds)
                    ->where('user_type', 'vendor')
                    ->where('status', 1)
                    ->get()
                    ->mapWithKeys(function ($user) {
                        return [$user->id => $user->first_name . ' ' . $user->last_name];
                    });
            } else {
                $clinicAdmins = collect([]);
            }
            
            // Filter clinics to only show doctor's clinics
            $clinics = $doctorClinics->pluck('name', 'id');
        }
    } elseif ($user && $user->hasRole('vendor')) {
        // If user is a vendor/clinic admin, only show their own ID and their clinics
        $clinicAdmins = User::where('id', $user->id)
            ->where('user_type', 'vendor')
            ->where('status', 1)
            ->get()
            ->mapWithKeys(function ($user) {
                return [$user->id => $user->first_name . ' ' . $user->last_name];
            });
        
        // Get only clinics belonging to this vendor
        $clinics = Clinics::where('vendor_id', $user->id)
            ->where('status', 1)
            ->pluck('name', 'id');
    } else {
        // Get all users with user_type 'vendor' for clinic admin dropdown
        $clinicAdmins = User::where('user_type', 'vendor')
            ->where('status', 1)
            ->get()
            ->mapWithKeys(function ($user) {
                return [$user->id => $user->first_name . ' ' . $user->last_name];
            });
        
        // Get all clinics for dropdown (will be filtered by clinic admin)
        $clinics = Clinics::pluck('name', 'id');
    }
    
    // Get encounter ID from request if coming from encounter detail page
    $encounterId = request()->input('encounter_id');
    $preSetClinicAdminId = null;
    $preSetClinicId = null;
    
    // If encounter_id is provided, fetch only that encounter and get clinic/vendor info
    if ($encounterId) {
        $encounter = PatientEncounter::with(['user', 'clinic'])->find($encounterId);
        if ($encounter) {
            $patientName = $encounter->user ? ($encounter->user->first_name . ' ' . $encounter->user->last_name) : 'Patient';
            $patientEncounters = [$encounterId => $patientName . ' (Encounter: ' . $encounterId . ')'];
            
            // Get clinic admin (vendor) and clinic from encounter
            $preSetClinicAdminId = $encounter->vendor_id;
            $preSetClinicId = $encounter->clinic_id;
            
            // Ensure pre-set clinic admin is in the clinicAdmins collection
            if ($preSetClinicAdminId) {
                // Check if vendor exists in collection (handle both array and collection)
                $vendorExists = false;
                if (is_array($clinicAdmins)) {
                    $vendorExists = isset($clinicAdmins[$preSetClinicAdminId]);
                } else {
                    $vendorExists = $clinicAdmins->has($preSetClinicAdminId);
                }
                
                if (!$vendorExists) {
                    $vendor = User::find($preSetClinicAdminId);
                    if ($vendor) {
                        // Get full name, ensuring we have both first and last name
                        $vendorName = trim(($vendor->first_name ?? '') . ' ' . ($vendor->last_name ?? ''));
                        if (empty($vendorName)) {
                            // Fallback to name field if first_name/last_name are empty
                            $vendorName = $vendor->name ?? $vendor->email ?? 'Vendor #' . $vendor->id;
                        }
                        
                        // Convert to array if it's a collection, then add the vendor
                        if (!is_array($clinicAdmins)) {
                            $clinicAdmins = $clinicAdmins->toArray();
                        }
                        $clinicAdmins[$preSetClinicAdminId] = $vendorName;
                    }
                }
            }
            
            // Ensure pre-set clinic is in the clinics collection
            if ($preSetClinicId) {
                // Check if clinic exists in collection (handle both array and collection)
                $clinicExists = false;
                if (is_array($clinics)) {
                    $clinicExists = isset($clinics[$preSetClinicId]);
                } else {
                    $clinicExists = $clinics->has($preSetClinicId);
                }
                
                if (!$clinicExists) {
                    $clinic = Clinics::find($preSetClinicId);
                    if ($clinic) {
                        // Convert to array if it's a collection, then add the clinic
                        if (!is_array($clinics)) {
                            $clinics = $clinics->toArray();
                        }
                        $clinics[$preSetClinicId] = $clinic->name;
                    }
                }
            }
        } else {
            $patientEncounters = collect([]);
        }
    } else {
        // Patient encounters will be loaded dynamically based on selected clinic
        $patientEncounters = collect([]);
    }
    
    $bedTypes = BedType::pluck('type', 'id');
    
    // Initialize beds as empty - beds will be loaded dynamically based on selected bed type
    // This ensures only beds of the selected bed type are shown
    $beds = collect([]);
    
    // Store the intended URL (where user came from) for redirect after save
    $intendedUrl = request()->input('redirect') ?? url()->previous();
    // Only store if it's not the create page itself
    if (!str_contains($intendedUrl, '/bed-allocation/create')) {
        session(['url.intended' => $intendedUrl]);
    }
    
    // Convert collections to arrays for consistent access in view
    if (!is_array($clinicAdmins)) {
        $clinicAdmins = $clinicAdmins->toArray();
    }
    if (!is_array($clinics)) {
        $clinics = $clinics->toArray();
    }
    if (!is_array($patientEncounters)) {
        $patientEncounters = $patientEncounters->toArray();
    }
    if (!is_array($bedTypes)) {
        $bedTypes = $bedTypes->toArray();
    }
    if (!is_array($beds)) {
        $beds = $beds->toArray();
    }
    
    $module_name = 'bed-allocation';
    $module_title = 'messages.bed_assign';
    return view('bed::allocations.create', compact('clinics', 'clinicAdmins', 'patientEncounters', 'bedTypes', 'beds', 'module_name', 'module_title', 'encounterId', 'preSetClinicAdminId', 'preSetClinicId'));
}

    public function store(BedAllocationRequest $request)
    {
        $data = $request->validated();

        $encounter = PatientEncounter::select('id', 'user_id', 'vendor_id', 'clinic_id')
            ->find($data['encounter_id']);

        if (!$encounter) {
            return $this->sendError('Invalid encounter selected.', $request, 'encounter_id');
        }

        // Validate that encounter has a valid user_id
        if (!$encounter->user_id) {
            return $this->sendError('Encounter does not have a valid patient assigned.', $request, 'encounter_id');
        }

        // Validate that the user exists in the users table
        $patient = User::find($encounter->user_id);
        if (!$patient) {
            return $this->sendError('Patient associated with this encounter does not exist.', $request, 'encounter_id');
        }

        $bed = BedMaster::find($data['room_no']);
        if (!$bed || is_null($bed->charges)) {
            return $this->sendError('Invalid bed selected. Charge not found.', $request, 'room_no');
        }

        if ($bed->is_under_maintenance) {
            return $this->sendError('This bed is currently under maintenance and cannot be assigned.', $request, 'room_no');
        }

        // Check if the same encounter already has an active bed allocation
        // For the same encounter, you cannot assign a second bed at the same time
        // After the discharge date, you can assign a second bed
        $assignDate = Carbon::parse($data['assign_date']);
        $dischargeDate = isset($data['discharge_date']) ? Carbon::parse($data['discharge_date']) : $assignDate;
        $today = Carbon::today();
        
        // Check for active bed allocations for the same encounter
        $activeEncounterAllocation = BedAllocation::where('encounter_id', $encounter->id)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($today) {
                // Active allocation: either no discharge date or discharge date is in the future
                $query->whereNull('discharge_date')
                      ->orWhere('discharge_date', '>', $today->format('Y-m-d'));
            })
            ->first();

        if ($activeEncounterAllocation) {
            $dischargeDateStr = $activeEncounterAllocation->discharge_date 
                ? Carbon::parse($activeEncounterAllocation->discharge_date)->format('Y-m-d') 
                : 'not set';
            return $this->sendError(
                'This encounter already has an active bed allocation. You cannot assign a second bed for the same encounter until the previous bed is discharged. Current discharge date: ' . $dischargeDateStr . '. Please discharge the previous bed first or assign a new bed after the discharge date.', 
                $request, 
                'encounter_id'
            );
        }

        // Check if this patient already has an active allocation that overlaps with the new assign date
        // Exclude allocations where the encounter is closed (status = 0), even if discharge date is in the future
        $activeAllocation = BedAllocation::where('patient_id', $encounter->user_id)
            ->where('encounter_id', '!=', $encounter->id) // Exclude the current encounter (already checked above)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->with('patientEncounter') // Load encounter relationship to check status
            ->where(function ($q) use ($assignDate, $dischargeDate) {
                // Check for overlapping dates
                $q->where(function ($query) use ($assignDate, $dischargeDate) {
                    // Existing allocation's assign_date is before or on new discharge_date
                    // AND existing allocation's discharge_date is after or on new assign_date
                    $query->where('assign_date', '<=', $dischargeDate->format('Y-m-d'))
                          ->where(function ($subQuery) use ($assignDate) {
                              $subQuery->whereNull('discharge_date')
                                      ->orWhere('discharge_date', '>=', $assignDate->format('Y-m-d'));
                          });
                });
            })
            ->get()
            ->filter(function ($allocation) {
                // Exclude allocations where encounter is closed (status = 0)
                // If encounter is closed, allow new bed allocation even if discharge date is in the future
                if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                    return false; // Encounter is closed, so this allocation is not considered active
                }
                return true; // Encounter is active, so this allocation is active
            })
            ->first();

        if ($activeAllocation) {
            return $this->sendError('This patient already has an active bed assigned during the selected date range. Please select a date after the existing discharge date (' . ($activeAllocation->discharge_date ? Carbon::parse($activeAllocation->discharge_date)->format('Y-m-d') : 'N/A') . ') or discharge the previous allocation first.', $request, 'assign_date');
        }

        // Prevent overlapping allocations for the same bed
        // Exclude allocations where the encounter is closed (status = 0)
        $overlapExists = BedAllocation::where('bed_master_id', $data['room_no'])
            ->whereNull('deleted_at')
            ->with('patientEncounter') // Load encounter relationship to check status
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->whereNull('discharge_date')
                    ->orWhere('discharge_date', '>=', $data['assign_date']);
                })
                ->where('assign_date', '<=', $data['discharge_date'] ?? $data['assign_date']);
            })
            ->get()
            ->filter(function ($allocation) {
                // Exclude allocations where encounter is closed (status = 0)
                // If encounter is closed, bed is available for new allocation
                if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                    return false; // Encounter is closed, so this allocation doesn't block the bed
                }
                return true; // Encounter is active, so this allocation blocks the bed
            })
            ->isNotEmpty();

        if ($overlapExists) {
            return $this->sendError('This bed is already assigned during the selected date range.', $request, 'assign_date');
        }

        // Charge calculation (assignDate and dischargeDate already parsed above)
        $nights = max($assignDate->diffInDays($dischargeDate), 1);
        $totalCharge = $nights * $bed->charges;

        // Handle status field - checkbox sends array when checked, or just hidden value when unchecked
        $status = 0;
        if ($request->has('status')) {
            $statusValue = $request->input('status');
            // If it's an array (checkbox + hidden input), get the last value (checkbox value)
            if (is_array($statusValue)) {
                $status = end($statusValue) ? 1 : 0;
            } else {
                $status = $statusValue ? 1 : 0;
            }
        }

        // Final allocation data
        $allocationData = [
            'patient_id'       => $encounter->user_id,
            'encounter_id'     => $encounter->id,
            'clinic_id'        => $data['clinic_id'] ?? $encounter->clinic_id,
            'clinic_admin_id'  => multiVendor() ? ($data['clinic_admin_id'] ?? $encounter->vendor_id) : null,
            'bed_type_id'      => $data['bed_type_id'],
            'bed_master_id'    => $data['room_no'],
            'assign_date'      => $data['assign_date'],
            'discharge_date'   => $data['discharge_date'] ?? null,
            'status'           => $status,
            'description'      => $data['description'] ?? null,
            'temperature'      => $data['temperature'] ?? null,
            'symptoms'         => $data['symptoms'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'charge'           => $totalCharge,
            'per_bed_charge'   => $bed->charges,
        ];

        $allocation = BedAllocation::create($allocationData);

        // Store patient vitals if provided
        PatientInfo::updateOrCreate(
            ['patient_id' => $encounter->user_id],
            [
                'weight'         => $data['weight'] ?? null,
                'height'         => $data['height'] ?? null,
                'blood_pressure' => $data['blood_pressure'] ?? null,
                'heart_rate'     => $data['heart_rate'] ?? null,
                'blood_group'    => $data['blood_group'] ?? null,
            ]
        );

        if ($request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Bed allocated successfully.',
                'data' => $allocation->load(['patient', 'bedMaster.bedType', 'clinicAdmin'])
            ]);
        }

        // Redirect to intended URL or check for redirect parameter
        $redirectUrl = $request->input('redirect') 
            ?? $request->session()->pull('url.intended') 
            ?? url()->previous();
        
        // If previous URL is the create page itself, redirect to index
        if (str_contains($redirectUrl, '/bed-allocation/create')) {
            $redirectUrl = route('backend.bed-allocation.index');
        }

        return redirect($redirectUrl)->withSuccess('Bed allocated successfully.');
    }

    // Edit allocation
    public function edit($id)
    {
        try {
            $allocation = BedAllocation::SetRole(auth()->user())->with(['bedMaster', 'patientInfo', 'clinicAdmin'])->findOrFail($id);

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $allocation
                ]);
            }

            // Store the intended URL (where user came from) for redirect after update
            $intendedUrl = request()->input('redirect') ?? url()->previous();
            // Only store if it's not the edit page itself
            if (!str_contains($intendedUrl, '/bed-allocation/' . $id . '/edit')) {
                session(['url.intended' => $intendedUrl]);
            }

            $bedTypes = BedType::pluck('type', 'id');
            // Initialize beds as empty - beds will be loaded dynamically based on selected bed type
            // This ensures only beds of the selected bed type are shown
            // The current bed will be loaded via AJAX when bed type is selected
            $beds = collect([]);
            $patientInfo = PatientInfo::where('patient_id', $allocation->patient_id)->first();

            // Get all users with user_type 'vendor' for clinic admin dropdown
            $clinicAdmins = \App\Models\User::where('user_type', 'vendor')
                ->where('status', 1)
                ->get()
                ->mapWithKeys(function ($user) {
                    return [$user->id => $user->first_name . ' ' . $user->last_name];
                });

            // Get clinics for the selected clinic admin
            $clinics = \Modules\Clinic\Models\Clinics::where('vendor_id', $allocation->clinic_admin_id)
                ->where('status', 1)
                ->pluck('name', 'id');

            // Get patient encounters for the selected clinic
            $patientEncounters = DB::table('patient_encounters')
                ->join('users', 'patient_encounters.user_id', '=', 'users.id')
                ->where('patient_encounters.clinic_id', $allocation->clinic_id)
                ->where('patient_encounters.status', 1)
                ->select('patient_encounters.id', 'patient_encounters.user_id', 'users.first_name', 'users.last_name')
                ->get()
                ->mapWithKeys(function ($record) {
                    return [
                        $record->id => $record->first_name . ' ' . $record->last_name . ' (Encounter: ' . $record->id . ')'
                    ];
                });
            
            // Ensure the current allocation's encounter is in the list (even if clinic changed)
            if ($allocation->encounter_id && !isset($patientEncounters[$allocation->encounter_id])) {
                $encounter = PatientEncounter::with('user')->find($allocation->encounter_id);
                if ($encounter && $encounter->user) {
                    $patientName = $encounter->user->first_name . ' ' . $encounter->user->last_name;
                    $patientEncounters[$allocation->encounter_id] = $patientName . ' (Encounter: ' . $allocation->encounter_id . ')';
                }
            }

            $module_name = 'bed-allocation';
            $module_title = 'messages.bed_assign';
            return view('bed::allocations.edit', compact('allocation', 'bedTypes', 'beds', 'patientInfo', 'patientEncounters', 'clinicAdmins', 'clinics', 'module_name', 'module_title'));
        } catch (\Exception $e) {
            \Log::error('Error fetching bed allocation for edit: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bed allocation not found'
                ], 404);
            }
            
            return redirect()->route('backend.bed-allocation.index')->with('error', 'Bed allocation not found');
        }
    }

// Update allocation
    public function update(BedAllocationRequest $request, $id)
    {
        $data = $request->validated();
        $bedAllocation = BedAllocation::findOrFail($id);

        $encounter = PatientEncounter::select('id', 'user_id', 'vendor_id', 'clinic_id')
            ->find($data['encounter_id']);

        if (!$encounter) {
            return $this->sendError('Invalid encounter selected.', $request, 'encounter_id');
        }

        // Validate that encounter has a valid user_id
        if (!$encounter->user_id) {
            return $this->sendError('Encounter does not have a valid patient assigned.', $request, 'encounter_id');
        }

        // Validate that the user exists in the users table
        $patient = User::find($encounter->user_id);
        if (!$patient) {
            return $this->sendError('Patient associated with this encounter does not exist.', $request, 'encounter_id');
        }

        $bed = BedMaster::find($data['room_no']);
        if (!$bed || is_null($bed->charges)) {
            return $this->sendError('Invalid bed selected. Charge not found.', $request, 'room_no');
        }

        if ($bed->is_under_maintenance) {
            return $this->sendError('This bed is currently under maintenance and cannot be assigned.', $request, 'room_no');
        }

        // Prevent date overlaps with other allocations of the same bed (excluding current one)
        // Exclude allocations where encounter is closed (status = 0) and soft-deleted records
        $overlapExists = BedAllocation::where('bed_master_id', $data['room_no'])
            ->where('id', '!=', $bedAllocation->id) // Exclude current allocation
            ->whereNull('deleted_at') // Exclude soft-deleted records
            ->with('patientEncounter') // Load encounter relationship to check status
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->whereNull('discharge_date')
                    ->orWhere('discharge_date', '>=', $data['assign_date']);
                })
                ->where('assign_date', '<=', $data['discharge_date'] ?? $data['assign_date']);
            })
            ->get()
            ->filter(function ($allocation) {
                // Exclude allocations where encounter is closed (status = 0)
                // If encounter is closed, bed is available for new allocation
                if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                    return false; // Encounter is closed, so this allocation doesn't block the bed
                }
                return true; // Encounter is active, so this allocation blocks the bed
            })
            ->isNotEmpty();

        if ($overlapExists) {
            return $this->sendError('This bed is already assigned during the selected date range.', $request, 'assign_date');
        }

        $assignDate = Carbon::parse($data['assign_date']);
        $dischargeDate = isset($data['discharge_date']) ? Carbon::parse($data['discharge_date']) : $assignDate;
        $nights = max($assignDate->diffInDays($dischargeDate), 1);
        $totalCharge = $nights * $bed->charges;

        // Handle status field - checkbox sends array when checked, or just hidden value when unchecked
        $status = 0;
        if ($request->has('status')) {
            $statusValue = $request->input('status');
            // If it's an array (checkbox + hidden input), get the last value (checkbox value)
            if (is_array($statusValue)) {
                $status = end($statusValue) ? 1 : 0;
            } else {
                $status = $statusValue ? 1 : 0;
            }
        }

        $bedAllocation->update([
            'clinic_id'        => $data['clinic_id'] ?? $encounter->clinic_id,
            'clinic_admin_id'  => multiVendor() ? ($data['clinic_admin_id'] ?? $encounter->vendor_id) : null,
            'bed_type_id'      => $data['bed_type_id'],
            'bed_master_id'    => $data['room_no'],
            'assign_date'      => $data['assign_date'],
            'discharge_date'   => $data['discharge_date'] ?? null,
            'status'           => $status,
            'description'      => $data['description'] ?? null,
            'temperature'      => $data['temperature'] ?? null,
            'symptoms'         => $data['symptoms'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'charge'           => $totalCharge,
            'per_bed_charge'   => $bed->charges,
        ]);

        PatientInfo::updateOrCreate(
            ['patient_id' => $bedAllocation->patient_id],
            [
                'weight'         => $data['weight'] ?? null,
                'height'         => $data['height'] ?? null,
                'blood_pressure' => $data['blood_pressure'] ?? null,
                'heart_rate'     => $data['heart_rate'] ?? null,
                'blood_group'    => $data['blood_group'] ?? null,
            ]
        );

        if ($request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Bed allocation updated successfully.',
                'data' => $bedAllocation->load(['patient', 'bedMaster.bedType', 'clinicAdmin'])
            ]);
        }

        // Redirect to intended URL or check for redirect parameter
        $redirectUrl = $request->input('redirect') 
            ?? $request->session()->pull('url.intended') 
            ?? url()->previous();
        
        // If previous URL is the edit page itself, redirect to index
        if (str_contains($redirectUrl, '/edit')) {
            $redirectUrl = route('backend.bed-allocation.index');
        }

        return redirect($redirectUrl)->withSuccess('Bed Allocation updated successfully.');
    }

    private function sendError($message, $request, $field = 'error')
    {
        if ($request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->back()->withErrors([$field => $message])->withInput();
    }
    // Delete
    public function destroy($id)
    {
        try {
            // Find the bed allocation using SetRole scope
            $bedAllocation = BedAllocation::SetRole(auth()->user())->findOrFail($id);
            
            // Delete the bed allocation
            $bedAllocation->delete();

            if (request()->ajax()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Bed allocation deleted successfully.'
                ]);
            }
            
            return redirect()->route('backend.bed-allocation.index')->with('success', 'Bed allocation deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Bed allocation not found: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bed allocation not found.'
                ], 404);
            }
            
            return redirect()->route('backend.bed-allocation.index')->with('error', 'Bed allocation not found.');
        } catch (\Exception $e) {
            \Log::error('Error deleting bed allocation: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to delete bed allocation.'
                ], 500);
            }
            
            return redirect()->route('backend.bed-allocation.index')->with('error', 'Failed to delete bed allocation.');
        }
    }

// Show bed allocation details
public function show($id)
{
    try {
        $allocation = BedAllocation::SetRole(auth()->user())->with(['patient', 'bedMaster.bedType', 'patientInfo', 'clinicAdmin'])->findOrFail($id);
        
        // Get bed base price from bed master charges (not the calculated charge)
        $price = $allocation->bedMaster->charges ?? 0;
        
        // Convert allocation to array and add price field
        $data = $allocation->toArray();
        $data['price'] = $price;
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        \Log::error('Error fetching bed allocation: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Bed allocation not found'
        ], 404);
    }
}

// Action page for preview/edit popup
public function action($id)
{
    $allocation = BedAllocation::with(['patient', 'bed'])->findOrFail($id);
    return view('bed::allocations.action', compact('allocation'));
}

// Bulk delete or update
public function bulkAction(Request $request)
{
    try {
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;

        switch ($actionType) {
            case 'delete':
                BedAllocation::whereIn('id', $ids)->delete();
                $message = __('messages.delete_form', ['form' => 'Bed Allocation']);
                return response()->json(['status' => true, 'message' => $message]);

            case 'change-status':
                $status = $request->status;
                $statusText = $status == 1 ? __('messages.active') : __('messages.inactive');
                BedAllocation::whereIn('id', $ids)->update(['status' => $status]);
                $message = __('messages.update_form', ['form' => 'Bed Allocation']) . ' - ' . __('messages.status') . ': ' . $statusText;
                return response()->json(['status' => true, 'message' => $message]);

            default:
                return response()->json(['status' => false, 'message' => 'Invalid action type.']);
        }
    } catch (\Exception $e) {
        \Log::error('Bed Allocation Bulk Action Error: ' . $e->getMessage());
        return response()->json(['status' => false, 'message' => 'Something went wrong.']);
    }
}

public function actions(Request $request, $id)
{
    // Soft delete actions removed
    return redirect()->back()->with('error', 'Restore and force delete are not supported.');
}

// Toggle active/inactive status
public function updateStatus($id)
{
    $allocation = BedAllocation::findOrFail($id);
    $allocation->status = !$allocation->status;
    $allocation->save();

    return response()->json(['status' => true, 'message' => 'Status updated.']);
}

/**
 * Get rooms by bed type
 */
public function getRoomsByBedType($bedTypeId, Request $request)
{
    try {
        $user = auth()->user();
        $assignDate = $request->input('assign_date', now()->format('Y-m-d'));
        $clinicId = $request->input('clinic_id');
        $currentRoomId = $request->input('current_room_id');
        $bedType = \Modules\Bed\Models\BedType::find($bedTypeId);
        $bedTypeName = $bedType ? $bedType->type : null;
        
        // Build query for beds
        $roomsQuery = BedMaster::where('bed_type_id', $bedTypeId)
            ->where('status', true) // Only include active beds
            ->where('is_under_maintenance', false);
        
        // Filter beds based on user role
        if ($user && $user->hasRole('doctor')) {
            // Get clinic IDs where the doctor is mapped
            $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id')
                ->toArray();
            
            if (!empty($doctorClinicIds)) {
                // Filter beds by doctor's clinics
                $roomsQuery->whereIn('clinic_id', $doctorClinicIds);
            } else {
                // If doctor has no clinic mappings, return empty
                return response()->json([
                    'status' => false,
                    'message' => 'No rooms available for this bed type and clinic',
                    'bed_type' => $bedTypeName,
                    'rooms' => []
                ]);
            }
        } elseif ($user && $user->hasRole('vendor')) {
            // Filter beds by clinic admin
            $roomsQuery->where('clinic_admin_id', $user->id);
        } else {
            // For other roles, use SetRole scope
            $roomsQuery = BedMaster::SetRole($user)->where('bed_type_id', $bedTypeId)
                ->where('status', true)
                ->where('is_under_maintenance', false);
        }
        
        // Apply clinic filter if provided
        if ($clinicId) {
            $roomsQuery->where('clinic_id', $clinicId);
        }
        
        $rooms = $roomsQuery->get(['id', 'bed']);
        if ($rooms->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No rooms available for this bed type and clinic',
                'bed_type' => $bedTypeName,
                'rooms' => []
            ]);
        }
        // Get occupied room IDs, excluding those where encounter is closed (status = 0)
        // If encounter is closed before discharge date, bed should be available
        // Note: We check ALL allocations for these beds, not just user's allocations
        // to correctly determine if a bed is occupied
        $occupiedRoomIds = BedAllocation::whereNull('deleted_at')
            ->whereIn('bed_master_id', $rooms->pluck('id'))
            ->where('status', true)
            ->where('assign_date', '<=', $assignDate)
            ->where(function ($query) use ($assignDate) {
                $query->where(function($q) use ($assignDate) {
                    $q->whereNull('discharge_date')
                      ->orWhere('discharge_date', '>=', $assignDate);
                });
            })
            ->with('patientEncounter')
            ->get()
            ->filter(function($allocation) {
                // Exclude allocations where encounter is closed (status = 0)
                // If encounter is closed, bed should be available even if discharge date hasn't passed
                if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                    return false;
                }
                return true;
            })
            ->pluck('bed_master_id')
            ->toArray();

        // Remove current room ID from exclusion list if editing
        if ($currentRoomId && in_array($currentRoomId, $occupiedRoomIds)) {
            $occupiedRoomIds = array_diff($occupiedRoomIds, [$currentRoomId]);
        }

        $availableRooms = $rooms->filter(function($room) use ($occupiedRoomIds) {
            return !in_array($room->id, $occupiedRoomIds);
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'This Selected Bed Type All Rooms are Reserved For This Date.',
            'bed_type' => $bedTypeName,
            'rooms' => $availableRooms
        ]);
    } catch (\Exception $e) {
        \Log::error('Error fetching rooms: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Error fetching rooms',
            'bed_type' => null,
            'rooms' => []
        ], 500);
    }
}

/**
 * API: Get all bed allocations
 */
public function apiIndex()
{
    try {
        $allocations = BedAllocation::SetRole(auth()->user())->with(['patient', 'bedMaster.bedType'])->whereNull('deleted_at')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $allocations
        ]);
    } catch (\Exception $e) {
        \Log::error('API Error fetching bed allocations: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error fetching bed allocations'
        ], 500);
    }
}

/**
 * API: Create a new bed allocation (POST)
 * Endpoint: /api/bed-allocations
 *
 * Request Body:
 *   - encounter_id: integer (required)
 *   - bed_type_id: integer (required)
 *   - room_no: integer (required)
 *   - assign_date: date (required)
 *   - discharge_date: date (optional)
 *   - status: boolean (optional)
 *   - description, temperature, symptoms, notes, weight, height, blood_pressure, heart_rate, blood_group (optional)
 *
 * Returns: JSON with created bed allocation and related info
 */
// public function apiStore(Request $request)
// {
//     $validated = $request->validate([
//         'encounter_id' => 'required|exists:patient_encounters,id',
//         'bed_type_id' => 'required|exists:bed_types,id',
//         'room_no' => 'required|exists:bed_master,id',
//         'assign_date' => 'required|date',
//         'discharge_date' => 'nullable|date|after_or_equal:assign_date',
//         'status' => 'nullable|boolean',
//         'description' => 'nullable|string|max:250',
//         'temperature' => 'nullable|string',
//         'symptoms' => 'nullable|string',
//         'notes' => 'nullable|string',
//         'weight' => 'nullable|string|max:10',
//         'height' => 'nullable|string|max:10',
//         'blood_pressure' => 'nullable|string|max:20',
//         'heart_rate' => 'nullable|string|max:20',
//         'blood_group' => 'nullable|string|max:5',
//     ]);

//     // Get patient encounter info
//     $patientEncounter = \DB::table('patient_encounters')
//         ->where('id', $request->encounter_id)
//         ->select('id', 'user_id')
//         ->first();

//     if (!$patientEncounter) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Invalid encounter selected.'
//         ], 422);
//     }

//     // Get bed info
//     $bedMaster = \Modules\Bed\Models\BedMaster::find($request->room_no);
//     if (!$bedMaster) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Invalid bed selected.'
//         ], 422);
//     }

//     // Check if bed is under maintenance
//     if ($bedMaster->is_under_maintenance) {
//         return response()->json([
//             'success' => false,
//             'message' => 'This bed is currently under maintenance and cannot be assigned.'
//         ], 422);
//     }

//     // Create bed allocation
//     $bedAllocation = \Modules\Bed\Models\BedAllocation::create([
//         'patient_id' => $patientEncounter->user_id,
//         'encounter_id' => $patientEncounter->id,
//         'bed_type_id' => $request->bed_type_id,
//         'bed_master_id' => $request->room_no,
//         'assign_date' => $request->assign_date,
//         'discharge_date' => $request->discharge_date,
//         'status' => $request->status ? 1 : 0,
//         'description' => $request->description,
//         'temperature' => $request->temperature,
//         'symptoms' => $request->symptoms,
//         'notes' => $request->notes,
//         'charge' => $bedMaster->charges ?? 0,
//     ]);

//     // Update patient info
//     if ($patientEncounter->user_id) {
//         \Modules\Bed\Models\PatientInfo::updateOrCreate(
//             ['patient_id' => $patientEncounter->user_id],
//             [
//                 'weight' => $request->weight,
//                 'height' => $request->height,
//                 'blood_pressure' => $request->blood_pressure,
//                 'heart_rate' => $request->heart_rate,
//                 'blood_group' => $request->blood_group,
//             ]
//         );
//     }

//     return response()->json([
//         'success' => true,
//         'message' => 'Bed allocated successfully.',
//         'data' => $bedAllocation->load(['patient', 'bedMaster.bedType'])
//     ], 201);
// }

/**
 * Get bed allocations for a specific encounter
 */
public function getEncounterBedAllocations($encounterId)
{
    try {
        // Get the encounter to find the patient
        $encounter = \DB::table('patient_encounters')
            ->where('id', $encounterId)
            ->select('id', 'user_id')
            ->first();

        if (!$encounter) {
            return response()->json([
                'success' => false,
                'message' => 'Encounter not found'
            ], 404);
        }

        // Get bed allocations for this patient with bed_master and bed_type relationships
        $bedAllocations = BedAllocation::where('patient_id', $encounter->user_id)
            ->whereNull('deleted_at')
            ->with(['patient', 'bedMaster.bedType'])
            ->get();

        // Generate HTML for the table
        $html = view('appointment::backend.patient_encounter.component.bed_allocation_table', [
            'data' => ['status' => 1], // Assuming encounter is still active
            'bedAllocations' => $bedAllocations
        ])->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'data' => $bedAllocations
        ]);
    } catch (\Exception $e) {
        \Log::error('Error fetching encounter bed allocations: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error fetching bed allocations'
        ], 500);
    }
}

public function currentAllocation()
{
    return $this->hasOne(BedAllocation::class, 'bed_master_id')
        ->active()
        ->latest();
}

public function getCurrentStatusAttribute()
{
    if ($this->is_under_maintenance) {
        return 'maintenance';
    }
    if (!$this->status) {
        return 'unavailable';
    }
    if ($this->currentAllocation) {
        return 'occupied';
    }
    return 'available';
}

/**
 * Get clinics by clinic admin
 */
public function getClinicsByAdmin($adminId, Request $request)
{
    try {
        $user = auth()->user();
        
        $query = \Modules\Clinic\Models\Clinics::where('vendor_id', $adminId)
            ->where('status', 1);
        
        // Filter clinics for doctors - only show clinics where doctor is mapped
        if ($user && $user->hasRole('doctor')) {
            $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id')
                ->toArray();
            
            if (!empty($doctorClinicIds)) {
                $query->whereIn('id', $doctorClinicIds);
            } else {
                // If doctor has no clinic mappings, return empty
                return response()->json([
                    'status' => false,
                    'message' => 'No clinics available for this clinic admin',
                    'clinics' => []
                ]);
            }
        }
        
        // Filter clinics for vendors - only show their own clinics
        if ($user && $user->hasRole('vendor')) {
            // Ensure vendor can only access their own clinics
            if ($adminId != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No clinics available for this clinic admin',
                    'clinics' => []
                ]);
            }
            // Query already filters by vendor_id (adminId), so no additional filtering needed
        }
        
        $clinics = $query->get(['id', 'name']);

        if ($clinics->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No clinics available for this clinic admin',
                'clinics' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Clinics fetched successfully',
            'clinics' => $clinics
        ]);
    } catch (\Exception $e) {
        \Log::error('Error fetching clinics: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Error fetching clinics',
            'clinics' => []
        ], 500);
    }
}

/**
 * Get patient encounters by clinic
 */
    public function getPatientEncountersByClinic($clinicId, Request $request)
    {
        try {
            $user = auth()->user();
            $doctorId = $request->input('doctor_id'); // Get doctor_id from request if provided
            
            $query = PatientEncounter::withTrashed()
                ->when($clinicId !== 'all', function ($q) use ($clinicId) {
                    return $q->where('clinic_id', $clinicId);
                })
                ->where('status', 1) // Only active encounters
                ->whereHas('appointmentdetail', function ($subQ) {
                    // Only show encounters that have appointments
                    // and the appointment is not closed (not checkout)
                    $subQ->where('status', '!=', 'checkout');
                });
            
            // Filter by doctor if doctor is logged in or doctor_id is provided
            if ($user && $user->hasRole('doctor')) {
                // When doctor is logged in, show only their encounters
                $query->where(function ($q) use ($user) {
                    // Filter by encounter's doctor_id
                    $q->where('doctor_id', $user->id)
                      // Or filter by appointment's doctor_id
                      ->orWhereHas('appointmentdetail', function ($subQ) use ($user) {
                          $subQ->where('doctor_id', $user->id);
                      });
                });
            } elseif ($doctorId) {
                // If doctor_id is provided in request, filter by that doctor
                $query->where(function ($q) use ($doctorId) {
                    // Filter by encounter's doctor_id
                    $q->where('doctor_id', $doctorId)
                      // Or filter by appointment's doctor_id
                      ->orWhereHas('appointmentdetail', function ($subQ) use ($doctorId) {
                          $subQ->where('doctor_id', $doctorId);
                      });
                });
            }

            $patientEncounters = $query->get()->map(fn($encounter) => [
                'id' => $encounter->id,
                'text' => 'Encounter: ' . $encounter->id,
            ]);

            return response()->json([
                'status' => $patientEncounters->isNotEmpty(),
                'message' => $patientEncounters->isNotEmpty()
                    ? 'Patient encounters fetched successfully'
                    : 'No patient encounters available',
                'encounters' => $patientEncounters,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error fetching patient encounters: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching patient encounters',
                'encounters' => [],
            ], 500);
        }
    }


    public function getEncounterDetails($encounterId)
    {
        if (!$encounterId) {
            return response()->json([
                'status' => false,
                'message' => 'Encounter ID is required',
                'data' => [],
            ], 400);
        }

        $encounter = PatientEncounter::withTrashed()->find($encounterId);

        if (!$encounter) {
            return response()->json([
                'status' => false,
                'message' => 'Encounter not found',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Encounter details fetched successfully',
            'data' => $encounter,
        ]);
    }
}