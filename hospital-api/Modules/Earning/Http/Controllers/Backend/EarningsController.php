<?php

namespace Modules\Earning\Http\Controllers\Backend;

use App\Authorizable;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Modules\Earning\Models\Earning;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\CustomField\Models\CustomField;
use Modules\CustomField\Models\CustomFieldGroup;
use Yajra\DataTables\DataTables;
use App\Models\User;
use Currency;
use Modules\Commission\Models\CommissionEarning;
use Modules\Appointment\Models\Appointment;
use Modules\Earning\Models\EmployeeEarning;

class EarningsController extends Controller
{
    // use Authorizable;

    public function __construct()
    {
        // Page Title
        $this->module_title = 'earning.lbl_doctor_earnings';
        // module name
        $this->module_name = 'earnings';

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
     *
     * @return Response
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
        $export_url = route('backend.earnings.export');

        return view('earning::backend.earnings.index_datatable', compact('module_action', 'filter', 'columns', 'customefield', 'export_import', 'export_columns', 'export_url'));
    }

    /**
     * Select Options for Select 2 Request/ Response.
     *
     * @return Response
     */
    public function index_list(Request $request)
    {
        $term = trim($request->q);

        if (empty($term)) {
            return response()->json([]);
        }

        $query_data = Earning::where('name', 'LIKE', "%$term%")->orWhere('slug', 'LIKE', "%$term%")->limit(7)->get();

        $data = [];

        foreach ($query_data as $row) {
            $data[] = [
                'id' => $row->id,
                'text' => $row->name.' (Slug: '.$row->slug.')',
            ];
        }
        return response()->json($data);
    }

    public function index_data(DataTables $datatable)
    {
        $module_name = $this->module_name;
        $isMultiVendor = multiVendor() == 1;

        \Log::info('Earnings - index_data Start', [
            'multi_vendor' => $isMultiVendor,
            'user_role' => auth()->user()->roles->pluck('name')->toArray() ?? [],
            'user_id' => auth()->id()
        ]);

        // Base query - get all users with doctor commissions
        $query = User::select('users.*')
                ->with('commission_earning')
                ->with('commissionData')
                ->with('doctor')
                ->with('doctorclinic')
                ->where('user_type', 'doctor') // Ensure we only get doctors
                ->whereHas('commission_earning', function ($q) use ($isMultiVendor) {
                    $q->where('commission_status', 'unpaid')
                    ->where('user_type', 'doctor')
                    ->where('commissionable_type', 'Modules\Appointment\Models\Appointment');
                    
                    // When multi-vendor is OFF, show ALL doctors with commissions (no vendor filtering)
                    // When multi-vendor is ON, the SetRole filter will handle vendor filtering
                    // No additional filtering needed here when multi-vendor is OFF
                });

        // Apply SetRole filter for multi-vendor ON or non-admin users
        if ($isMultiVendor || !auth()->user()->hasRole(['admin', 'demo_admin'])) {
            \Log::info('Earnings - Applying SetRole filter', [
                'multi_vendor' => $isMultiVendor,
                'is_admin' => auth()->user()->hasRole(['admin', 'demo_admin'])
            ]);
            $query = $query->SetRole(auth()->user());
        } else {
            \Log::info('Earnings - Skipping SetRole filter (Multi-vendor OFF, Admin user)', [
                'note' => 'Filtering by appointments instead'
            ]);
        }
        
        // Log query result count
        $queryCount = $query->count();
        \Log::info('Earnings - Query Result Count', [
            'total_doctors_found' => $queryCount,
            'multi_vendor' => $isMultiVendor
        ]);

        return $datatable->eloquent($query)
            ->addColumn('action', function ($data) use ($module_name) {
                $commissionData = $data->commission_earning()
                    ->whereHas('getAppointment', function ($query) {
                        $query->where('status', 'checkout');
                    })
                    ->where('commission_status', 'unpaid')
                    ->where('user_type', 'doctor');

                $commissionAmount = $commissionData->sum('commission_amount');
                $totalAppointment = $commissionData->distinct('commissionable_id')->count();
                
                $data['total_pay'] = $commissionAmount;
                $data['commission'] = $commissionData->get();
                $data['total_appointment'] = $totalAppointment;
            
                return view('earning::backend.earnings.action_column', compact('module_name', 'data'));
            })

            ->editColumn('user_id', function ($data) {
                return view('earning::backend.earnings.user_id', compact('data'));
            })
            ->filterColumn('user_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    $query->where('first_name', 'like', '%'.$keyword.'%')->orWhere('last_name', 'like', '%'.$keyword.'%')->orWhere('email', 'like', '%'.$keyword.'%');
                }
            })
            ->orderColumn('user_id', function ($query, $order) {
                $query->orderBy('first_name', $order)
                      ->orderBy('last_name', $order);
            }, 1)

            ->editColumn('total_appointment', function ($data) {

                if($data->total_appointment >0){

                    return "<b><a href='" . route('backend.appointments.index', ['doctor_id' => $data->id]) . "' data-assign-module='".$data->id."'  class='text-primary text-nowrap px-1' data-bs-toggle='tooltip' title='View Doctor Appointments'>".$data->total_appointment."</a> </b>";
                }else{

                    return "<b><span  data-assign-module='".$data->id."'  class='text-primary text-nowrap px-1' data-bs-toggle='tooltip' title='View Doctor Appointments'>0</span>";
                }

            })
            ->editColumn('total_service_amount', function ($data) {
                $isMultiVendor = multiVendor() == 1;
                $totalEarning = 0;
                $appointmentDetails = [];
                
                foreach($data['commission'] as $commission){
                if($commission->commission_status != 'pending'){
                    $appointmentData = Appointment::where('id', $commission->commissionable_id)
                        ->with(['appointmenttransaction', 'patientEncounter.billingrecord'])
                        ->first();

                    $appointmentAmount = 0;
                        
                    if ($appointmentData && $appointmentData->patientEncounter && $appointmentData->patientEncounter->billingrecord) {
                        $billingRecord = $appointmentData->patientEncounter->billingrecord;
                        $fullAmount = $billingRecord->final_total_amount ?? 0;
                        $bedCharges = $billingRecord->bed_charges ?? 0;
                            
                            // Verify: final_total_amount should already include bed charges
                            // If bed_charges field is not set, try to get from bed allocations
                            if ($bedCharges == 0) {
                                $encounter = $appointmentData->patientEncounter;
                                if ($encounter) {
                                    $bedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $encounter->id)->get();
                                    $bedCharges = $bedAllocations->sum('charge') ?? 0;
                                }
                            }
                            
                            if ($isMultiVendor) {
                                // Multi-vendor ON: Total earning = Full amount - Bed charges (Service amount only)
                        $appointmentAmount = $fullAmount - $bedCharges;
                        $totalEarning += $appointmentAmount;
                        
                        $appointmentDetails[] = [
                            'appointment_id' => $commission->commissionable_id,
                            'full_amount' => $fullAmount,
                            'bed_charges' => $bedCharges,
                            'service_amount' => $appointmentAmount,
                            'calculation' => "{$fullAmount} - {$bedCharges} = {$appointmentAmount}",
                                    'source' => 'billing_record',
                                    'multi_vendor' => true
                        ];
                            } else {
                                // Multi-vendor OFF: Total earning = Full amount (Service + Bed charges)
                                // final_total_amount already includes bed charges, so use it directly
                                $appointmentAmount = $fullAmount;
                                $totalEarning += $appointmentAmount;
                                
                                $appointmentDetails[] = [
                                    'appointment_id' => $commission->commissionable_id,
                                    'full_amount' => $fullAmount,
                                    'bed_charges' => $bedCharges,
                                    'service_amount' => $fullAmount - $bedCharges,
                                    'calculation' => "Total = {$fullAmount} (includes Service + Tax + Bed charges)",
                                    'source' => 'billing_record',
                                    'multi_vendor' => false
                                ];
                            }
                    } elseif ($appointmentData && $appointmentData->appointmenttransaction) {
                        // Fallback for appointments without encounter (direct appointments)
                        // No bed charges for direct appointments
                        $appointmentAmount = $appointmentData->appointmenttransaction->total_amount ?? 0;
                        $totalEarning += $appointmentAmount;
                        
                        $appointmentDetails[] = [
                            'appointment_id' => $commission->commissionable_id,
                            'total_amount' => $appointmentAmount,
                            'bed_charges' => 0,
                                'source' => 'appointment_transaction',
                                'multi_vendor' => $isMultiVendor
                        ];
                        }
                    }
                }

                $data['totalServiceAmount'] = $totalEarning;
                
                \Log::info('Earnings - Total Service Amount Calculation', [
                    'user_id' => $data->id,
                    'user_name' => $data->first_name . ' ' . $data->last_name,
                    'multi_vendor' => $isMultiVendor,
                    'total_earning' => $totalEarning,
                    'total_earning_formatted' => Currency::format($totalEarning),
                    'appointment_count' => count($appointmentDetails),
                    'appointment_details' => $appointmentDetails,
                    'note' => $isMultiVendor 
                        ? 'Total Earning = Full amount - Bed charges (Service amount only)' 
                        : 'Total Earning = Full amount (Service + Bed charges)'
                ]);
        
           return Currency::format($totalEarning);
            })
            
            ->editColumn('total_admin_earning', function ($data) {
                $isMultiVendor = multiVendor() == 1;
                $totalAdminEarning = 0;
                $adminDetails = [];
                
                foreach($data['commission'] as $commission){
                    $appointmentData = Appointment::where('id', $commission->commissionable_id)
                        ->with(['appointmenttransaction', 'patientEncounter.billingrecord'])
                        ->first();
                    
                    // Get appointment amounts
                        $appointmentTotal = 0;
                        $bedCharges = 0;
                        $serviceAmount = 0;
                        
                        if ($appointmentData && $appointmentData->patientEncounter && $appointmentData->patientEncounter->billingrecord) {
                            $billingRecord = $appointmentData->patientEncounter->billingrecord;
                            $appointmentTotal = $billingRecord->final_total_amount ?? 0;
                            $bedCharges = $billingRecord->bed_charges ?? 0;
                            $serviceAmount = $appointmentTotal - $bedCharges;
                        } elseif ($appointmentData && $appointmentData->appointmenttransaction) {
                            $appointmentTotal = $appointmentData->appointmenttransaction->total_amount ?? 0;
                            $serviceAmount = $appointmentTotal;
                        }
                        
                        // Get doctor commission for this appointment
                        $doctorCommission = CommissionEarning::where('commissionable_id', $commission->commissionable_id)
                            ->where('user_type', 'doctor')
                            ->where('commission_status', 'unpaid')
                            ->first();
                        $doctorAmount = $doctorCommission->commission_amount ?? 0;
                    
                    if ($isMultiVendor) {
                        // Multi-vendor ON: Use saved admin commission
                        $commission_data = CommissionEarning::where('commissionable_id', $commission->commissionable_id)
                            ->where('user_type', 'admin')
                            ->where('commission_status', 'unpaid')
                            ->first();
                        
                        if ($commission_data) {
                            $adminAmount = $commission_data->commission_amount ?? 0;
                            $totalAdminEarning += $adminAmount;
                            
                            $adminDetails[] = [
                                'appointment_id' => $commission->commissionable_id,
                                'appointment_total' => $appointmentTotal,
                                'bed_charges' => $bedCharges,
                                'service_amount' => $serviceAmount,
                                'doctor_commission' => $doctorAmount,
                                'admin_commission' => $adminAmount,
                                'expected_clinic_earning' => max(0, $appointmentTotal - $doctorAmount - $adminAmount),
                                'commission_earnings_id' => $commission_data->id ?? null,
                                'commissions_json' => $commission_data->commissions ?? null,
                                'multi_vendor' => true
                            ];
                        }
                    } else {
                        // Multi-vendor OFF: Admin gets everything remaining after doctor commission
                        // Admin = Total (Service + Bed) - Doctor Commission
                        $adminAmount = max(0, $appointmentTotal - $doctorAmount);
                        $totalAdminEarning += $adminAmount;
                        
                        $adminDetails[] = [
                            'appointment_id' => $commission->commissionable_id,
                            'appointment_total' => $appointmentTotal,
                            'bed_charges' => $bedCharges,
                            'service_amount' => $serviceAmount,
                            'doctor_commission' => $doctorAmount,
                            'admin_commission' => $adminAmount,
                            'calculation' => "Total ({$appointmentTotal}) - Doctor ({$doctorAmount}) = Admin ({$adminAmount})",
                            'multi_vendor' => false
                        ];
                    }
                }
                
                \Log::info('Earnings - Admin Earning Calculation', [
                    'user_id' => $data->id,
                    'user_name' => $data->first_name . ' ' . $data->last_name,
                    'multi_vendor' => $isMultiVendor,
                    'total_admin_earning' => $totalAdminEarning,
                    'admin_details' => $adminDetails
                ]);

                return Currency::format($totalAdminEarning);
            })


            ->editColumn('total_commission_earn', function ($data) {

               return "<b><span  data-assign-module='".$data->id."' data-assign-commission-type='doctor_commission' data-assign-target='#view_commission_list' data-assign-event='assign_commssions' class='btn text-primary p-0 fs-5' data-bs-toggle='tooltip' title='View'> <i class='ph ph-eye align-middle'></i></span>";
                
            })
            ->editColumn('total_pay', function ($data) {
                $isMultiVendor = multiVendor() == 1;
                $doctorCommission = $data->total_pay ?? 0;
                $totalEarning = $data['totalServiceAmount'] ?? 0;
                
                // Calculate admin earning for this doctor
                $totalAdminEarning = 0;
                
                if ($isMultiVendor) {
                    // Multi-vendor ON: Use saved admin commission from database
                foreach($data['commission'] as $commission){
                    $commission_data = CommissionEarning::where('commissionable_id', $commission->commissionable_id)
                        ->where('user_type', 'admin')
                        ->where('commission_status', 'unpaid')
                        ->first();
                    if ($commission_data) {
                        $totalAdminEarning += $commission_data->commission_amount ?? 0;
                    }
                    }
                } else {
                    // Multi-vendor OFF: Admin gets everything remaining after doctor commission
                    // Admin = Total (Service + Bed) - Doctor Commission
                    $totalAdminEarning = max(0, $totalEarning - $doctorCommission);
                }
                
                // Clinic earning calculation (for logging only, not displayed)
                $clinicEarning = max(0, $totalEarning - $doctorCommission - $totalAdminEarning);
                
                \Log::info('Earnings - Doctor Pay Calculation', [
                    'user_id' => $data->id,
                    'user_name' => $data->first_name . ' ' . $data->last_name,
                    'multi_vendor' => $isMultiVendor,
                    'total_earning' => $totalEarning,
                    'note' => $isMultiVendor 
                        ? 'Total Earning = Full amount - Bed charges (Service amount only)' 
                        : 'Total Earning = Full amount (Service + Bed charges)',
                    'doctor_commission' => $doctorCommission,
                    'admin_commission' => $totalAdminEarning,
                    'clinic_earning' => $clinicEarning,
                    'calculation' => "{$totalEarning} - {$doctorCommission} - {$totalAdminEarning} = {$clinicEarning}"
                ]);
         
                return Currency::format($doctorCommission);
            })
            
            ->orderColumn('total_service_amount', function ($query, $order) {
                $query->orderBy(new Expression('(SELECT SUM(service_price) FROM booking_services WHERE employee_id = users.id)'), $order);
            }, 1)
            // ->orderColumn('total_commission_earn', function ($query, $order) {
            //     $query->orderBy(new Expression('(SELECT SUM(commission_amount) FROM commission_earnings WHERE employee_id = users.id)'), $order);
            // }, 1)
            ->addIndexColumn()
            ->rawColumns(['action', 'image','user_id','total_commission_earn','total_appointment'])
            ->toJson();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $data = Earning::create($request->all());

        $message = 'New Earning Added';

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id, Request $request)
    {
        $commissionType = $request->commission_type;
        $userType = ($commissionType == 'doctor_commission') ? 'doctor' : 'vendor';
    
        $query = User::where('id', $id)
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.mobile')
            ->with('commission_earning')
            ->with('commissionData')
            ->whereHas('commission_earning', function ($q) use ($userType) {
                $q->where('commission_status', 'unpaid')
                    ->where('user_type', $userType)
                    ->where('commissionable_type', 'Modules\Appointment\Models\Appointment');
            })
            ->orderBy('updated_at', 'desc')
            ->first();
    
        $commissionData = $query->commission_earning()
            ->whereHas('getAppointment', function ($query) {
                $query->where('status', 'checkout');
            })
            ->where('commission_status', 'unpaid')
            ->where('user_type', $userType);
    
        $commissionAmount = $commissionData->sum('commission_amount');
        
        $data = [
            'id' => $query->id,
            'full_name' => $query->full_name,
            'email' => $query->email,
            'mobile' => $query->mobile,
            'profile_image' => $query->profile_image,
            'description' => '',
            'commission_earn' => Currency::format($commissionAmount),
            'amount' => Currency::format($commissionAmount),
            'payment_method' => '',
        ];
    
        return response()->json(['data' => $data, 'status' => true]);
    }
    

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $data = $request->all();

        $commissionType = $request->commission_type;
        $userType = ($commissionType == 'doctor_commission') ? 'doctor' : 'vendor';

        $query = User::where('id', $id)
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.mobile')
            ->with('commission_earning')
            ->with('commissionData')
            ->whereHas('commission_earning', function ($q) use ($userType) {
                $q->where('commission_status', 'unpaid')
                    ->where('user_type', $userType)
                    ->where('commissionable_type', 'Modules\Appointment\Models\Appointment');
            })
            ->orderBy('updated_at', 'desc')
            ->first();
    
        $commissionData = $query->commission_earning()
            ->whereHas('getAppointment', function ($query) {
                $query->where('status', 'checkout');
            })
            ->where('commission_status', 'unpaid')
            ->where('user_type', $userType);
    
        $commissionAmount = $commissionData->sum('commission_amount');
        $total_commission_earn = $commissionAmount;
        $total_pay = $total_commission_earn;


        $earning_data = [
            'employee_id' => $id,
            'total_amount' => $total_pay,
            'payment_date' => Carbon::now(),
            'payment_type' => $data['payment_method'],
            'description' => $data['description'],
            'commission_amount' => $total_commission_earn,
            'user_type' => $userType,
        ];

        $earning_data = EmployeeEarning::create($earning_data);

        CommissionEarning::where('employee_id', $id)->where('commission_status','unpaid')->update(['commission_status' => 'paid']);

        $message = __('messages.payment_done');

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $data = Earning::findOrFail($id);

        $data->delete();

        $message = 'Earnings Deleted Successfully';

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    public function get_employee_commissions(Request $request){

        if($request->has('type') && $request->type !='' && $request->has('id') && $request->id !='' ){
 
         $type = $request->type;
         $data =  User::where('id', $request->id)->with(['commissionData' => function($query) use ($type) {
                     $query->whereHas('mainCommission', function($subQuery) use ($type) {
                         $subQuery->where('type', $type);
                     });
                 }])->first();
        }   
 
        return response()->json(['data' => $data, 'status' => true]);
     
         
     }
}
