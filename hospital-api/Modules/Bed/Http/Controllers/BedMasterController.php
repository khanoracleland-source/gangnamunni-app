<?php

namespace Modules\Bed\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Bed\Models\BedType;
use Modules\Bed\Models\BedMaster;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Modules\Clinic\Models\Clinics;
use Modules\Clinic\Models\DoctorClinicMapping;
use Modules\Clinic\Models\Receptionist;
use Modules\Bed\Http\Requests\BedMasterRequest;
use Illuminate\Support\Facades\Log;

class BedMasterController extends Controller
{
    protected $module_name = 'bed-master';
    protected $module_title = 'messages.bed_master';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
                $filter = [
            'status' => $request->get('status'),
            'maintenance' => $request->get('maintenance'),
        ];

        $module_action = 'List';
        $module_name = $this->module_name;
        $module_title = $this->module_title;
        
        return view('bed::bed_master.index', compact('module_action', 'module_name', 'module_title', 'filter'));
    }

    /**
     * Get data for DataTables
     */
    public function index_data(Request $request)
    {
        $user = auth()->user();
        
        // Doctors can see only beds from their clinics (view only)
        if ($user && $user->hasRole('doctor')) {
            // Get clinic IDs where the doctor is mapped
            $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id')
                ->toArray();
            
            // If doctor has no clinic mappings, return empty query
            if (empty($doctorClinicIds)) {
                $query = BedMaster::whereRaw('1 = 0')->with(['bedType', 'clinicAdmin', 'clinic']);
            } else {
                // Filter beds by doctor's clinic IDs
                $query = BedMaster::whereIn('clinic_id', $doctorClinicIds)
                    ->with(['bedType', 'clinicAdmin', 'clinic']);
            }
        } elseif ($user && $user->hasRole('receptionist')) {
            // For receptionists, show all beds in their clinic
            $receptionist = Receptionist::where('receptionist_id', $user->id)->first();
            
            if ($receptionist && $receptionist->clinic_id) {
                // Filter beds by the receptionist's clinic_id
                $query = BedMaster::where('clinic_id', $receptionist->clinic_id)
                    ->with(['bedType', 'clinicAdmin', 'clinic']);
            } else {
                // If receptionist has no clinic association, return empty
                $query = BedMaster::whereRaw('1 = 0')->with(['bedType', 'clinicAdmin', 'clinic']);
            }
        } else {
            // For other roles (admin, vendor), use SetRole filtering
            $query = BedMaster::SetRole($user)->with(['bedType', 'clinicAdmin', 'clinic']);
        }

        // Handle ordering by related columns
        if ($request->has('order')) {
            $orderColumnIndex = $request->input('order.0.column');
            $orderDir = $request->input('order.0.dir', 'asc');
            $columns = $request->input('columns');
            $orderColumnName = $columns[$orderColumnIndex]['name'] ?? null;

            if ($orderColumnName === 'bed_type') {
                $query = $query->leftJoin('bed_type', 'bed_master.bed_type_id', '=', 'bed_type.id')
                    ->orderBy('bed_type.type', $orderDir)
                    ->select('bed_master.*');
            }
        }

        $datatable = DataTables::of($query);
        
        // Only add check and action columns if user is not a doctor or receptionist
        if (!$user || (!$user->hasRole('doctor') && !$user->hasRole('receptionist'))) {
            $datatable->addColumn('check', function ($row) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $row->id . '" name="datatable_ids[]" value="' . $row->id . '" data-type="bedmaster" onclick="dataTableRowCheck(' . $row->id . ')">';
            })
            ->addColumn('action', function ($bedMaster) {
                return view('bed::bed_master.action', compact('bedMaster'))->render();
            });
        }
        
        $datatable->editColumn('bed', function ($row) {
            $bed = $row->bed ?? '';
            return is_array($bed) ? e(json_encode($bed)) : e($bed);
        })
        ->editColumn('bed_type', function ($row) {
            if (!$row->bedType) {
                return 'N/A';
            }
            $bedType = $row->bedType->type ?? '';
            return is_array($bedType) ? e(json_encode($bedType)) : e($bedType);
        });

        // Only add clinic admin column if multi-vendor is enabled
        if (multiVendor() == 1) {
            $datatable->addColumn('clinic_admin', function ($row) {
                if (!$row->clinicAdmin) {
                    return 'N/A';
                }
                $fullName = $row->clinicAdmin->full_name ?? '';
                return is_array($fullName) ? e(json_encode($fullName)) : e($fullName);
            });
        }

        // Only add clinic column if multi-vendor is enabled
        if (multiVendor() == 1) {
            $datatable->addColumn('clinic', function ($row) {
                if (!$row->clinic) {
                    return 'N/A';
                }
                $clinicName = $row->clinic->name ?? '';
                return is_array($clinicName) ? e(json_encode($clinicName)) : e($clinicName);
            });
        }

        return $datatable->editColumn('charges', function ($row) {
            return \Currency::format($row->charges);
        })
        ->editColumn('capacity', function ($row) {
            return $row->capacity;
        })
        ->editColumn('status', function ($row) use ($user) {
            $checked = $row->status ? 'checked' : '';
            $disabled = ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) ? 'disabled' : '';
            return '<div class="form-check form-switch form-switch-sm">
                        <input class="form-check-input status-toggle" 
                               type="checkbox" 
                               id="status-toggle-' . $row->id . '" 
                               data-id="' . $row->id . '" 
                               data-field="status"
                               ' . $checked . ' 
                               ' . $disabled . '>
                    </div>';
        })
        ->editColumn('is_under_maintenance', function ($row) use ($user) {
            $checked = $row->is_under_maintenance ? 'checked' : '';
            $disabled = ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) ? 'disabled' : '';
            return '<div class="form-check form-switch form-switch-sm">
                        <input class="form-check-input maintenance-toggle" 
                               type="checkbox" 
                               id="maintenance-toggle-' . $row->id . '" 
                               data-id="' . $row->id . '" 
                               data-field="is_under_maintenance"
                               ' . $checked . ' 
                               ' . $disabled . '>
                    </div>';
            })
        ->editColumn('description', function ($row) {
            return $row->description ? \Str::limit(strip_tags($row->description), 50) : 'N/A';
        })
        ->editColumn('updated_at', function ($row) {
            return date('Y-m-d H:i:s', strtotime($row->updated_at));
        })
        ->filter(function ($query) use ($request) {
            if ($request->has('search') && !empty($request->search['value'])) {
                $search = $request->search['value'];
                $query->where(function ($q) use ($search) {
                    $q->where('bed', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('charges', 'like', "%{$search}%")
                      ->orWhereHas('bedType', function($bedQuery) use ($search) {
                          $bedQuery->where('type', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('maintenance')) {
                $query->where('is_under_maintenance', $request->maintenance);
            }
        })
        ->rawColumns(['check', 'action', 'status', 'is_under_maintenance'])
        ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from creating beds
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            abort(403, 'You are not allowed to create beds. View only access.');
        }
        
        $module_action = 'Create';
        $module_name = $this->module_name;
        $module_title = $this->module_title;

        $bedMasterData = new BedMaster();
        $bedTypes = BedType::select('id', 'type')->get();
        
        // Filter clinic admins based on user role
        if ($user && $user->hasRole('vendor')) {
            // If user is a vendor, only show their own ID
            $clinicAdmins = \App\Models\User::where('id', $user->id)->get();
            // Auto-set clinic_admin_id for vendor
            $bedMasterData->clinic_admin_id = $user->id;
        } else {
            // For admin/demo_admin, show all vendors
            $clinicAdmins = \App\Models\User::where('user_type', 'vendor')->get();
        }
        
        // Filter clinics based on user role
        if ($user && $user->hasRole('vendor')) {
            // If user is a vendor, only show clinics belonging to them
            $clinics = \Modules\Clinic\Models\Clinics::where('vendor_id', $user->id)->get();
        } else {
            // For admin/demo_admin, show all clinics
            $clinics = \Modules\Clinic\Models\Clinics::all();
        }

        return view('bed::bed_master.create', compact('module_action', 'module_name', 'module_title', 'bedMasterData', 'bedTypes', 'clinicAdmins', 'clinics'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BedMasterRequest $request)
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from creating beds
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            abort(403, 'You are not allowed to create beds. View only access.');
        }
        
        try {
            $data = $request->validated();

            // Convert checkboxes to boolean
            $data['status'] = $request->boolean('status');
            $data['is_under_maintenance'] = $request->boolean('is_under_maintenance');

            // If multivendor is off, set clinic_admin_id to current user
            if (multiVendor() == 0) {
                $data['clinic_admin_id'] = auth()->user()->id;
            }

            \Log::info('Creating Bed Master:', $data);

            $bed = BedMaster::create($data);

            $message = 'Bed Master Created successfully';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => true,
                    'message' => $message,
                    'data' => $bed,
                ], 201);
            }

            return redirect()
                ->route("backend.{$this->module_name}.index")
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Bed Master Store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $errorMessage = 'An error occurred while creating the bed master.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => $errorMessage,
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', $errorMessage)
                ->withInput();
        }
    }

    public function show(Request $request,$id)
    {
        $user = auth()->user();
        
        // Doctors can view only beds from their clinics (view only), other roles use SetRole filtering
        if ($user && $user->hasRole('doctor')) {
            // Get clinic IDs where the doctor is mapped
            $doctorClinicIds = DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id')
                ->toArray();
            
            // Filter beds by doctor's clinic IDs
            if (empty($doctorClinicIds)) {
                $beddata = null;
            } else {
                $beddata = BedMaster::whereIn('clinic_id', $doctorClinicIds)->find($id);
            }
        } else {
            $beddata = BedMaster::SetRole($user)->find($id);
        }
        if(empty($beddata)){
            $message = 'Bed Master not found.';
            if (request()->is('api/*') || request()->ajax()) {
                return response()->json([
                    'status' => false,
                    'message' => $message
                ], 422);
            }
        }

        $module_action = 'Show';
        $module_name = $this->module_name;
        $module_title = $this->module_title;

        if ($request->is('api/*') || $request->ajax()) {
          
            $data[] = [
            'id' => $beddata->id,
            'bed' => $beddata->bed,
            'bed_id' => $beddata->bed_id,
            'bed_type_id' => $beddata->bedType ? $beddata->bedType->type : null,
            'charges' => $beddata->charges,
            'capacity' => $beddata->capacity,
            'description' => $beddata->description,
            'status' => $beddata->status,
            'status_text' => $beddata->status ? 'Active' : 'Inactive',
            'is_under_maintenance' => $beddata->is_under_maintenance,
            'maintenance_text' => $beddata->is_under_maintenance ? 'Under Maintenance' : 'Available',
        ];

            $message = __('messages.show', ['name' => __('messages.bed_master')]);
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $beddata,
            ]);
        }

        return view('bed::bed_master.create', compact('module_action', 'module_name', 'module_title','beddata'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from editing beds
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            abort(403, 'You are not allowed to edit beds. View only access.');
        }
        
        $module_action = 'Edit';
        $module_name = $this->module_name;
        $module_title = $this->module_title;

        $bedMasterData = BedMaster::SetRole($user)->findOrFail($id);
        $bedTypes = BedType::select('id', 'type')->get();
        $clinicAdmins = \App\Models\User::where('user_type', 'vendor')->get();
        $clinics = \Modules\Clinic\Models\Clinics::all();

        return view('bed::bed_master.edit', compact('module_action', 'module_name', 'module_title', 'bedMasterData', 'bedTypes', 'clinicAdmins', 'clinics'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BedMasterRequest $request, $id)
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from updating beds
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            abort(403, 'You are not allowed to update beds. View only access.');
        }
        
        try {
            $bedMaster = BedMaster::SetRole($user)->findOrFail($id);

            $data = $request->validated();

            // Boolean checkbox conversion
            $data['status'] = $request->has('status') && in_array($request->status, ['1', 'on']) ? 1 : 0;
            $data['is_under_maintenance'] = $request->has('is_under_maintenance') && in_array($request->is_under_maintenance, ['1', 'on']) ? 1 : 0;

            // Handle multivendor condition
            if (multiVendor() == 0) {
                $data['clinic_admin_id'] = auth()->user()->id;
            }

            $bedMaster->update($data);

            $message = __('messages.update_form', ['form' => __('messages.bed_master')]);

            // API or AJAX response
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => true,
                    'message' => $message,
                    'data' => $bedMaster->fresh()
                ]);
            }

            // Web redirect response
            return redirect()
                ->route('backend.' . $this->module_name . '.index')
                ->with('success', $message);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            $message = 'Bed Master not found';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['status' => false, 'message' => $message], 404);
            }

            return redirect()
                ->route('backend.' . $this->module_name . '.index')
                ->with('error', $message);

        } catch (\Exception $e) {

            $message = 'Something went wrong: ' . $e->getMessage();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => $message,
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $message);
        }
    }

    public function index_list(Request $request)
    {
        // Support both 'q' and 'search' parameters for search term
        $term = trim($request->q ?? $request->search ?? '');
        $bed_type_id = $request->bed_type_id;
        $clinic_id = $request->clinic_id;
        $user = auth()->user();

        // For doctors, filter by clinics they're associated with through doctor_clinic_mapping
        // For receptionists, filter by clinic_id (show all beds in their clinic)
        // For other roles, use SetRole scope
        if ($user->hasRole('doctor')) {
            $query_data = BedMaster::with(['bedType', 'clinic', 'clinicAdmin'])
                ->orderByDesc('id')
                ->where('deleted_at', null);
            
            // Get clinics the doctor is associated with
            $doctorClinicIds = \Modules\Clinic\Models\DoctorClinicMapping::where('doctor_id', $user->id)
                ->pluck('clinic_id')
                ->toArray();
            
            // Filter beds by clinics the doctor is associated with
            if (!empty($doctorClinicIds)) {
                $query_data->whereIn('clinic_id', $doctorClinicIds);
            } else {
                // If doctor has no clinic associations, return empty
                $query_data->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole('receptionist')) {
            // For receptionists, show all beds in their clinic
            $query_data = BedMaster::with(['bedType', 'clinic', 'clinicAdmin'])
                ->orderByDesc('id')
                ->where('deleted_at', null);
            
            // Get the receptionist's clinic_id
            $receptionist = \Modules\Clinic\Models\Receptionist::where('receptionist_id', $user->id)->first();
            
            if ($receptionist && $receptionist->clinic_id) {
                // Filter beds by the receptionist's clinic_id
                $query_data->where('clinic_id', $receptionist->clinic_id);
            } else {
                // If receptionist has no clinic association, return empty
                $query_data->whereRaw('1 = 0');
            }
        } else {
            // For other roles (admin, vendor), use SetRole scope
            $query_data = BedMaster::SetRole($user)->with(['bedType', 'clinic', 'clinicAdmin'])
                ->orderByDesc('id')
                ->where('deleted_at', null);
        }
        
        // Filter by bed_type_id if provided
        if ($bed_type_id) {
            $query_data->where('bed_type_id', $bed_type_id);
        }
        
        // Filter by clinic_id if provided
        if ($clinic_id) {
            $query_data->where('clinic_id', $clinic_id);
        }
        
        // Filter by search term if provided (works for all roles)
        // Search across all searchable fields
        if (!empty($term)) {
            $query_data->where(function ($q) use ($term) {
                // Search in bed master direct fields
                $q->where('bed', 'LIKE', "%$term%")
                  ->orWhere('description', 'LIKE', "%$term%");
                
                // Search by ID (exact match if numeric)
                if (is_numeric($term)) {
                    $q->orWhere('id', '=', (int)$term);
                }
                
                // Search in numeric fields (charges and capacity)
                if (is_numeric($term)) {
                    $q->orWhere('charges', '=', (float)$term)
                      ->orWhere('capacity', '=', (int)$term);
                } else {
                    // For non-numeric terms, search as string
                    $q->orWhereRaw('CAST(charges AS CHAR) LIKE ?', ["%$term%"])
                      ->orWhereRaw('CAST(capacity AS CHAR) LIKE ?', ["%$term%"]);
                }
                
                // Search by status text (Active/Inactive)
                $termLower = strtolower($term);
                if (strpos($termLower, 'active') !== false && strpos($termLower, 'inactive') === false) {
                    $q->orWhere('status', 1);
                } elseif (strpos($termLower, 'inactive') !== false) {
                    $q->orWhere('status', 0);
                }
                
                // Search by maintenance text
                if (strpos($termLower, 'maintenance') !== false || strpos($termLower, 'under maintenance') !== false) {
                    $q->orWhere('is_under_maintenance', 1);
                } elseif (strpos($termLower, 'available') !== false && strpos($termLower, 'maintenance') === false) {
                    $q->orWhere(function($subQ) {
                        $subQ->where('is_under_maintenance', 0)
                             ->where('status', 1);
                    });
                }
                
                // Search in bed type
                $q->orWhereHas('bedType', function($query) use ($term) {
                      $query->where('type', 'LIKE', "%$term%")
                            ->orWhere('description', 'LIKE', "%$term%");
                  })
                  // Search in clinic
                  ->orWhereHas('clinic', function($query) use ($term) {
                      $query->where('name', 'LIKE', "%$term%");
                  })
                  // Search in clinic admin (vendor)
                  ->orWhereHas('clinicAdmin', function($query) use ($term) {
                      $query->where(function($subQuery) use ($term) {
                          $subQuery->where('first_name', 'LIKE', "%$term%")
                                   ->orWhere('last_name', 'LIKE', "%$term%")
                                   ->orWhere('email', 'LIKE', "%$term%")
                                   ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%$term%"]);
                      });
                  });
            });
        }

        // Get total count before pagination (clone query to avoid affecting main query)
        $totalCount = (clone $query_data)->count();

        // Apply pagination
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Apply pagination to query
        $query_data = $query_data->offset($offset)->limit($perPage)->get();

        // Debug logging
        \Log::info('Bed Master List API Request', [
            'bed_type_id' => $bed_type_id,
            'clinic_id' => $clinic_id,
            'term' => $term,
            'search' => $request->search,
            'q' => $request->q,
            'per_page' => $perPage,
            'page' => $page,
            'total_count' => $totalCount,
            'user_id' => auth()->id(),
            'user_roles' => auth()->user()->roles->pluck('name')->toArray() ?? [],
        ]);

        \Log::info('Bed Master List API Results', [
            'count' => $query_data->count(),
            'total_count' => $totalCount,
            'bed_ids' => $query_data->pluck('id')->toArray(),
        ]);

        $data = [];

        foreach ($query_data as $row) {
            // Default status
            $bed_status = 'available';

            // Check maintenance
            if ($row->is_under_maintenance) {
                $bed_status = 'maintenance';
            } else {
                // Check if occupied (active allocation)
                // An allocation is active if:
                // 1. Not deleted
                // 2. assign_date <= today (allocation has started)
                // 3. discharge_date >= today OR null (allocation hasn't ended)
                // 4. Encounter is active (status = 1) - if encounter is closed, bed is available
                // Note: We don't check allocation status here because if dates are valid and encounter is active,
                // the bed is occupied regardless of the allocation's status field
                $today = now()->format('Y-m-d');
                $activeAllocation = \Modules\Bed\Models\BedAllocation::where('bed_master_id', $row->id)
                    ->whereNull('deleted_at')
                    ->whereDate('assign_date', '<=', $today)
                    ->where(function($q) use ($today) {
                        $q->whereNull('discharge_date')
                          ->orWhereDate('discharge_date', '>=', $today);
                    })
                    ->with('patientEncounter')
                    ->get()
                    ->filter(function($allocation) {
                        // Exclude allocations where encounter is closed (status = 0)
                        // If encounter is closed, bed should be available even if discharge date hasn't passed
                        if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                            return false; // Encounter is closed, so this allocation doesn't occupy the bed
                        }
                        return true; // Encounter is active, so this allocation occupies the bed
                    })
                    ->isNotEmpty();

                if ($activeAllocation) {
                    $bed_status = 'occupied';
                }
            }

            $data[] = [
                'id' => $row->id,
                'bed' => $row->bed,
                'bed_id' => $row->bed_id,
                'bed_type_id' => $row->bed_type_id,
                'bed_type_name' => $row->bedType ? $row->bedType->type : null,
                'charges' => (float)$row->charges,
                'capacity' => (int)$row->capacity,
                'description' => $row->description,
                'status' => $row->status,
                'status_text' => $row->status ? 'Active' : 'Inactive',
                'bed_status' => $bed_status,
                'is_under_maintenance' => $row->is_under_maintenance,
                'maintenance_text' => $row->is_under_maintenance ? 'Under Maintenance' : 'Available',
                'clinic_id' => $row->clinic_id,
            ];
        }
        $message = __('messages.list_data', ['form' => __('messages.bed_master')]);

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'total' => $totalCount,
            'count' => $totalCount, // Alias for total count
            'per_page' => (int)$perPage,
            'current_page' => (int)$page,
            'last_page' => ceil($totalCount / $perPage),
            'data_count' => count($data), // Count of items in current page
        ]);

    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from deleting beds
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            abort(403, 'You are not allowed to delete beds. View only access.');
        }
        
    \Log::info('Bed Master Delete Method Called', [
        'id' => $id,
        'method' => request()->method()
    ]);

    try {
        $bedMaster = BedMaster::SetRole($user)->find($id);
        if(empty($bedMaster)) {
            \Log::error('Bed Master not found for deletion', ['id' => $id]);
            $message = 'Bed Master not found.';
            
            if (request()->is('api/*') || request()->ajax()) {
                return response()->json([
                    'status' => false,
                    'message' => $message
                ], 422);
            }

            return redirect()
                ->route('backend.' . $this->module_name . '.index')
                ->with('error', $message);
        }
        
        // Log the bed master being deleted
        \Log::info('Deleting Bed Master:', $bedMaster->toArray());
        
        $bedMaster->delete();

        $message = __('messages.delete_form', ['form' => __('messages.bed_master')]);
        
        \Log::info('Bed Master deleted successfully', ['id' => $id]);

        // Check if it's an API or AJAX request
        if (request()->is('api/*') || request()->ajax()) {
                return response()->json([
                    'status' => true, 
                    'message' => $message,
                    'redirect' => route('backend.' . $this->module_name . '.index')
                ]);
            }
            
        return redirect()
            ->route('backend.' . $this->module_name . '.index')
                           ->with('success', $message);

    } catch (\Illuminate\Database\QueryException $e) {
        \Log::error('Database constraint error during Bed Master deletion: ' . $e->getMessage());
        
        $message = 'Cannot delete this bed master as it may be referenced by other records.';

        if (request()->is('api/*') || request()->ajax()) {
            return response()->json([
                'status' => false,
                'message' => $message
            ], 409); // Conflict status code
        }

        return redirect()
            ->back()
            ->with('error', $message);

        } catch (\Exception $e) {
            \Log::error('Bed Master Delete Error: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());

            $message = 'Something went wrong. Please try again.';
            
        if (request()->is('api/*') || request()->ajax()) {
            return response()->json([
                'status' => false,
                'message' => $message
            ], 500);
        }

        return redirect()
            ->back()
            ->with('error', $message);
    }
}
    /**
     * Handle bulk actions
     */
    public function bulk_action(Request $request)
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from performing bulk actions
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            return response()->json([
                'status' => false,
                'message' => 'You are not allowed to perform bulk actions. View only access.'
            ], 403);
        }
        
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;

        $message = '';
        
        try {
            switch ($actionType) {
                case 'delete':
                    BedMaster::whereIn('id', $ids)->delete();
                    $message = __('messages.delete_form', ['form' => __('messages.bed_master')]);
                    break;
                    
                case 'change-status':
                    $status = ($request->status == '1' || $request->status == 'on') ? 1 : 0;
                    BedMaster::whereIn('id', $ids)->update(['status' => $status]);
                    $message = $status ? __('messages.bulk_activate_form', ['form' => __('messages.bed_master')]) 
                                      : __('messages.bulk_deactivate_form', ['form' => __('messages.bed_master')]);
                    break;
                    
                case 'change-maintenance':
                    $maintenance = ($request->is_under_maintenance == '1' || $request->is_under_maintenance == 'on') ? 1 : 0;
                    BedMaster::whereIn('id', $ids)->update(['is_under_maintenance' => $maintenance]);
                    $message = $maintenance ? __('messages.bulk_maintenance_on', ['form' => __('messages.bed_master')])
                                           : __('messages.bulk_maintenance_off', ['form' => __('messages.bed_master')]);
                    break;
                    
                default:
                    return response()->json(['status' => false, 'message' => 'Action not found']);
            }

            return response()->json(['status' => true, 'message' => $message]);
        } catch (\Exception $e) {
            \Log::error('Bed Master Bulk Action Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Something went wrong']);
        }
    }

    /**
     * Handle restore and force delete actions
     */
    public function action(Request $request, $id)
    {
        // Soft delete actions removed
        return redirect()->back()->with('error', 'Restore and force delete are not supported.');
    }

    /**
     * Toggle status of bed master
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from toggling status
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            return response()->json([
                'status' => false,
                'message' => 'You are not allowed to toggle bed status. View only access.'
            ], 403);
        }
        
        try {
            $bedMaster = BedMaster::findOrFail($id);
            $bedMaster->status = !$bedMaster->status;
            $bedMaster->save();
            
            $message = $bedMaster->status 
                ? __('messages.status_activated', ['form' => __('messages.bed_master')])
                : __('messages.status_deactivated', ['form' => __('messages.bed_master')]);
            
                return response()->json([
                    'status' => true, 
                    'message' => $message,
                    'new_status' => $bedMaster->status
                ]);
        } catch (\Exception $e) {
            \Log::error('Bed Master Status Toggle Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ]);
        }
    }

    /**
     * Toggle maintenance status of bed master
     */
    public function toggleMaintenance(Request $request, $id)
    {
        $user = auth()->user();
        
        // Prevent doctors and receptionists from toggling maintenance
        if ($user && ($user->hasRole('doctor') || $user->hasRole('receptionist'))) {
            return response()->json([
                'status' => false,
                'message' => 'You are not allowed to toggle bed maintenance. View only access.'
            ], 403);
        }
        
        try {
            $bedMaster = BedMaster::findOrFail($id);
            $bedMaster->is_under_maintenance = !$bedMaster->is_under_maintenance;
            $bedMaster->save();

            $message = $bedMaster->is_under_maintenance 
                ? __('messages.maintenance_activated', ['form' => __('messages.bed_master')])
                : __('messages.maintenance_deactivated', ['form' => __('messages.bed_master')]);

            return response()->json([
                'status' => true,
                'message' => $message,
                'new_maintenance' => $bedMaster->is_under_maintenance
            ]);
        } catch (\Exception $e) {
            \Log::error('Bed Master Maintenance Toggle Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ]);
        }
    }

    /**
     * Check if bed name already exists for the given bed type
     */
    public function checkDuplicate(Request $request)
    {
        $bedName = $request->input('bed');
        $bedTypeId = $request->input('bed_type_id');
        $excludeId = $request->input('exclude_id'); // For edit form

        if (empty($bedName) || empty($bedTypeId)) {
            return response()->json([
                'status' => false,
                'message' => 'Bed name and bed type are required'
            ], 400);
        }

        $query = BedMaster::where('bed', $bedName)
            ->where('bed_type_id', $bedTypeId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'status' => true,
            'exists' => $exists,
            'message' => $exists ? 'A bed with this name already exists for the selected bed type.' : 'Bed name is available.'
        ]);
    }
}
