<?php

namespace Modules\Earning\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\CustomField\Models\CustomField;
use Modules\CustomField\Models\CustomFieldGroup;
use Yajra\DataTables\DataTables;
use Modules\Earning\Models\Earning;
use App\Models\User;
use Currency;
use Modules\Commission\Models\CommissionEarning;
use Modules\Appointment\Models\Appointment;
use Modules\Earning\Models\EmployeeEarning;
use Modules\Clinic\Models\Clinics;

class VendorEarningsController extends Controller
{
    public function __construct()
    {
        // Page Title
        $this->module_title = 'earning.clinicadmin_earning';
        // module name
        $this->module_name = 'vendor-earnings';

        // module icon
        $this->module_icon = 'fa-solid fa-clipboard-list';

        view()->share([
            'module_title' => $this->module_title,
            'module_icon' => $this->module_icon,
            'module_name' => $this->module_name,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = [
            'status' => $request->status,
        ];

        $module_action = 'List';
        $columns = CustomFieldGroup::columnJsonValues(new Earning());
        $customefield = CustomField::exportCustomFields(new Earning());

        $export_import = true;
        $export_columns = [
            [
                'value' => 'name',
                'text' => ' Name',
            ]
        ];
        $export_url = route('backend.vendor-earnings.export');

        return view('earning::backend.vendor-earnings.index_datatable', compact('module_action', 'filter', 'columns', 'customefield', 'export_import', 'export_columns', 'export_url'));
    }

    public function index_data(DataTables $datatable)
    {
        // dd('test');
        $module_name = $this->module_name;

        // Log: Check all commission earnings with vendor type
        $allVendorCommissions = \Modules\Commission\Models\CommissionEarning::where('user_type', 'vendor')
            ->where('commission_status', 'unpaid')
            ->get();
        
        \Log::info('Vendor Earnings - All Vendor Commissions', [
            'total_vendor_commissions' => $allVendorCommissions->count(),
            'commissions' => $allVendorCommissions->map(function($c) {
                return [
                    'id' => $c->id,
                    'employee_id' => $c->employee_id,
                    'user_type' => $c->user_type,
                    'commission_status' => $c->commission_status,
                    'commissionable_type' => $c->commissionable_type,
                    'commissionable_id' => $c->commissionable_id,
                    'commission_amount' => $c->commission_amount,
                ];
            })->toArray()
        ]);

        // Log: Check commission earnings with Appointment type
        $appointmentCommissions = \Modules\Commission\Models\CommissionEarning::where('user_type', 'vendor')
            ->where('commission_status', 'unpaid')
            ->where('commissionable_type', 'Modules\Appointment\Models\Appointment')
            ->get();
        
        \Log::info('Vendor Earnings - Appointment Type Commissions', [
            'total_appointment_commissions' => $appointmentCommissions->count(),
            'commissions' => $appointmentCommissions->map(function($c) {
                return [
                    'id' => $c->id,
                    'employee_id' => $c->employee_id,
                    'commissionable_id' => $c->commissionable_id,
                    'commission_amount' => $c->commission_amount,
                ];
            })->toArray()
        ]);

        // Log: Check unique vendor employee IDs
        $vendorEmployeeIds = \Modules\Commission\Models\CommissionEarning::where('user_type', 'vendor')
            ->where('commission_status', 'unpaid')
            ->where('commissionable_type', 'Modules\Appointment\Models\Appointment')
            ->distinct('employee_id')
            ->pluck('employee_id');
        
        \Log::info('Vendor Earnings - Vendor Employee IDs', [
            'total_vendors' => $vendorEmployeeIds->count(),
            'vendor_ids' => $vendorEmployeeIds->toArray()
        ]);

        // Log: Check if these users exist
        if ($vendorEmployeeIds->isNotEmpty()) {
            $vendorUsers = \App\Models\User::whereIn('id', $vendorEmployeeIds)->get();
            \Log::info('Vendor Earnings - Vendor Users Found', [
                'total_users' => $vendorUsers->count(),
                'users' => $vendorUsers->map(function($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->first_name . ' ' . $u->last_name,
                        'email' => $u->email,
                        'user_type' => $u->user_type,
                    ];
                })->toArray()
            ]);
        }

        $query = User::select('users.*')
                ->with('commission_earning')
                ->with('commissionData')
                ->whereHas('commission_earning', function ($q) {
                    $q->where('commission_status', 'unpaid')
                    ->where('user_type', 'vendor')
                    ->where('commissionable_type', 'Modules\Appointment\Models\Appointment');
                })->orderBy('updated_at', 'desc');

        // Log: Check query result count
        $queryCount = $query->count();
        \Log::info('Vendor Earnings - Query Result Count', [
            'users_found' => $queryCount,
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings()
        ]);

        return $datatable->eloquent($query)
            ->addColumn('action', function ($data) use ($module_name) {
                \Log::info('Vendor Earnings - Processing User', [
                    'user_id' => $data->id,
                    'user_name' => $data->first_name . ' ' . $data->last_name,
                    'user_type' => $data->user_type,
                ]);

                // Get all vendor commissions for this user first
                $allCommissions = $data->commission_earning()
                    ->where('commission_status', 'unpaid')
                    ->where('user_type', 'vendor')
                    ->get();
                
                \Log::info('Vendor Earnings - All Commissions for User', [
                    'user_id' => $data->id,
                    'total_commissions' => $allCommissions->count(),
                    'commissions' => $allCommissions->map(function($c) {
                        return [
                            'id' => $c->id,
                            'commissionable_id' => $c->commissionable_id,
                            'commissionable_type' => $c->commissionable_type,
                            'commission_amount' => $c->commission_amount,
                        ];
                    })->toArray()
                ]);

                // Filter by appointment status
                $commissionData = $data->commission_earning()
                    ->whereHas('getAppointment', function ($query) {
                        $query->where('status', 'checkout');
                    })
                    ->where('commission_status', 'unpaid')
                    ->where('user_type', 'vendor');

                // Log appointments status
                $appointmentIds = $allCommissions->pluck('commissionable_id')->unique();
                if ($appointmentIds->isNotEmpty()) {
                    $appointments = \Modules\Appointment\Models\Appointment::whereIn('id', $appointmentIds)->get();
                    \Log::info('Vendor Earnings - Appointment Statuses', [
                        'user_id' => $data->id,
                        'appointments' => $appointments->map(function($a) {
                            return [
                                'id' => $a->id,
                                'status' => $a->status,
                            ];
                        })->toArray()
                    ]);
                }
                
                $commissionAmount = $commissionData->sum('commission_amount');
                $totalAppointment = $commissionData->distinct('commissionable_id')->count();
                
                \Log::info('Vendor Earnings - Filtered Commission Data', [
                    'user_id' => $data->id,
                    'commission_amount' => $commissionAmount,
                    'total_appointment' => $totalAppointment,
                    'filtered_commissions_count' => $commissionData->count(),
                ]);
                
                $data['total_pay'] = $commissionAmount;
                $data['commission'] = $commissionData->get();
                $data['total_appointment'] = $totalAppointment;
            
                return view('earning::backend.vendor-earnings.action_column', compact('module_name', 'data'));
            })

            ->editColumn('user_id', function ($data) {
                return view('earning::backend.vendor-earnings.user_id', compact('data'));
            })
            ->filterColumn('user_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    $query->where('first_name', 'like', '%'.$keyword.'%')->orWhere('last_name', 'like', '%'.$keyword.'%')->orWhere('email', 'like', '%'.$keyword.'%');
                }
            })
            ->orderColumn('user_id', function ($query, $order) {
                $query->orderByRaw("CONCAT(first_name, ' ', last_name) $order");
            }, 1)  

            ->editColumn('total_appointment', function ($data) {

                if($data->total_appointment >0){
                    $clinic_ids = Clinics::where('vendor_id', $data->id)->pluck('id')->toArray();
                    $clinic_ids_string = implode(',', $clinic_ids);

                    return "<b><a href='" . route('backend.appointments.index', ['clinic_id' => $clinic_ids_string]) . "' data-assign-module='".$data->id."'  class='text-primary text-nowrap px-1' data-bs-toggle='tooltip' title='View Clinic Admin Appointments'>".$data->total_appointment."</a> </b>";
                }else{

                    return "<b><span  data-assign-module='".$data->id."'  class='text-primary text-nowrap px-1' data-bs-toggle='tooltip' title='View Clinic Admin Appointments'>0</span>";
                }

            })
            ->editColumn('total_service_amount', function ($data) {
                $totalEarning = 0; // Total earning = Full amount (Service + Bed charges)
                $appointmentDetails = [];
                
                foreach($data['commission'] as $commission){
                    $appointmentData = Appointment::where('id', $commission->commissionable_id)
                        ->with(['appointmenttransaction', 'patientEncounter.billingrecord'])
                        ->first();

                    $appointmentAmount = 0;
                    $fullAmount = 0;
                    $bedCharges = 0;
                    // Total earning = Full appointment amount (Service + Bed charges)
                    if ($appointmentData && $appointmentData->patientEncounter && $appointmentData->patientEncounter->billingrecord) {
                        $billingRecord = $appointmentData->patientEncounter->billingrecord;
                        // Get full amount and bed charges
                        $fullAmount = $billingRecord->final_total_amount ?? 0;
                        $bedCharges = $billingRecord->bed_charges ?? 0;
                        // Total earning = Full amount (includes service + bed charges)
                        $appointmentAmount = $fullAmount;
                        $totalEarning += $appointmentAmount;
                        
                        $appointmentDetails[] = [
                            'appointment_id' => $commission->commissionable_id,
                            'full_amount' => $fullAmount,
                            'bed_charges' => $bedCharges,
                            'service_amount' => $fullAmount - $bedCharges,
                            'total_earning' => $appointmentAmount,
                            'calculation' => "Service ({$fullAmount} - {$bedCharges}) + Bed charges ({$bedCharges}) = {$appointmentAmount}",
                            'source' => 'billing_record'
                        ];
                    } elseif ($appointmentData && $appointmentData->appointmenttransaction) {
                        // Fallback for appointments without encounter (direct appointments)
                        // No bed charges for direct appointments
                        $appointmentAmount = $appointmentData->appointmenttransaction->total_amount ?? 0;
                        $fullAmount = $appointmentAmount;
                        $totalEarning += $appointmentAmount;
                        
                        $appointmentDetails[] = [
                            'appointment_id' => $commission->commissionable_id,
                            'total_amount' => $appointmentAmount,
                            'bed_charges' => 0,
                            'source' => 'appointment_transaction'
                        ];
                    } elseif ($appointmentData) {
                        // Fallback to appointment total_amount
                        $appointmentAmount = $appointmentData->total_amount ?? 0;
                        $fullAmount = $appointmentAmount;
                        $totalEarning += $appointmentAmount;
                        
                        $appointmentDetails[] = [
                            'appointment_id' => $commission->commissionable_id,
                            'total_amount' => $appointmentAmount,
                            'bed_charges' => 0,
                            'source' => 'appointment_total'
                        ];
                    }
                }
                $data['totalServiceAmount'] = $totalEarning;
                
                \Log::info('Vendor Earnings - Total Earning Calculation', [
                    'vendor_id' => $data->id,
                    'vendor_name' => $data->first_name . ' ' . $data->last_name,
                    'total_earning' => $totalEarning,
                    'appointment_count' => count($appointmentDetails),
                    'appointment_details' => $appointmentDetails,
                    'note' => 'Total Earning = Full amount (Service + Bed charges)'
                ]);

                return Currency::format($totalEarning);
            })

            ->editColumn('total_admin_earning', function ($data) {
                 
                $totalAdminEarning = 0;
                $adminDetails = [];

                foreach($data['commission'] as $commission){ 

                    $commission_data = CommissionEarning::where('commissionable_id', $commission->commissionable_id)->where('user_type', 'admin')->where('commission_status', 'unpaid')->first();
                    if ($commission_data) {
                        $adminAmount = $commission_data->commission_amount ?? 0;
                        $totalAdminEarning += $adminAmount;
                        
                        $adminDetails[] = [
                            'appointment_id' => $commission->commissionable_id,
                            'admin_commission' => $adminAmount
                        ];
                    }

                }
                
                \Log::info('Vendor Earnings - Admin Earning Calculation', [
                    'vendor_id' => $data->id,
                    'vendor_name' => $data->first_name . ' ' . $data->last_name,
                    'total_admin_earning' => $totalAdminEarning,
                    'admin_details' => $adminDetails
                ]);

                return Currency::format($totalAdminEarning);
            })

            ->editColumn('total_pay', function ($data) {
                // Clinic/Vendor earning calculation
                // Bed charges are deducted first, then admin/doctor earnings, remaining goes to clinic
                // Clinic earning = (Service amount - Doctor commission - Admin commission) + Bed charges
                // We use the saved vendor commission amount (which is Service - Doctor - Admin) and add bed charges
                $totalVendorCommission = 0; // This is the saved vendor commission (Service - Doctor - Admin, excluding bed charges)
                $totalBedCharges = 0; // Total bed charges to add back
                $calculationDetails = [];
                
                foreach($data['commission'] as $commission){
                    // Get the saved vendor commission amount (already calculated as Service - Doctor - Admin)
                    $vendorCommissionAmount = $commission->commission_amount ?? 0;
                    $totalVendorCommission += $vendorCommissionAmount;
                    
                    // Get bed charges for this appointment
                    $appointmentData = Appointment::where('id', $commission->commissionable_id)
                        ->with(['appointmenttransaction', 'patientEncounter.billingrecord'])
                        ->first();
                    
                    $bedCharges = 0;
                    if ($appointmentData && $appointmentData->patientEncounter && $appointmentData->patientEncounter->billingrecord) {
                        $billingRecord = $appointmentData->patientEncounter->billingrecord;
                        $bedCharges = $billingRecord->bed_charges ?? 0;
                    }
                        $totalBedCharges += $bedCharges;
                    
                    // Get doctor and admin commissions for logging
                    $doctorCommission = CommissionEarning::where('commissionable_id', $commission->commissionable_id)
                        ->where('user_type', 'doctor')
                        ->where('commission_status', 'unpaid')
                        ->first();
                    $doctorAmount = $doctorCommission->commission_amount ?? 0;
                    
                    $adminCommission = CommissionEarning::where('commissionable_id', $commission->commissionable_id)
                        ->where('user_type', 'admin')
                        ->where('commission_status', 'unpaid')
                        ->first();
                    $adminAmount = $adminCommission->commission_amount ?? 0;
                    
                    $calculationDetails[] = [
                        'appointment_id' => $commission->commissionable_id,
                        'vendor_commission' => $vendorCommissionAmount,
                        'bed_charges' => $bedCharges,
                        'doctor_commission' => $doctorAmount,
                        'admin_commission' => $adminAmount,
                        'clinic_earning' => $vendorCommissionAmount + $bedCharges,
                        'calculation' => "{$vendorCommissionAmount} (Service - Doctor - Admin) + {$bedCharges} (Bed charges) = " . ($vendorCommissionAmount + $bedCharges)
                    ];
                }
                
                // Clinic earning = Vendor commission (Service - Doctor - Admin) + Bed charges
                $clinicEarning = $totalVendorCommission + $totalBedCharges;
                
                \Log::info('Vendor Earnings - Clinic Earning Calculation', [
                    'vendor_id' => $data->id,
                    'vendor_name' => $data->first_name . ' ' . $data->last_name,
                    'total_vendor_commission' => $totalVendorCommission,
                    'total_bed_charges' => $totalBedCharges,
                    'clinic_earning' => $clinicEarning,
                    'calculation' => "{$totalVendorCommission} (Service - Doctor - Admin) + {$totalBedCharges} (Bed charges) = {$clinicEarning}",
                    'note' => 'Clinic earning = (Service amount - Doctor commission - Admin commission) + Bed charges',
                    'appointment_details' => $calculationDetails
                ]);
                
                return Currency::format($clinicEarning);
            })
            
            ->orderColumn('total_service_amount', function ($query, $order) {
                $query->orderBy(new Expression('(SELECT SUM(service_price) FROM booking_services WHERE employee_id = users.id)'), $order);
            }, 1)
           
            ->addIndexColumn()
            ->rawColumns(['action', 'image','user_id','total_commission_earn','total_appointment'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('earning::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('earning::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('earning::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
