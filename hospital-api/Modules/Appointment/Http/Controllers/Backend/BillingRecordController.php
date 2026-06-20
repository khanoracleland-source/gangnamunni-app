<?php

namespace Modules\Appointment\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\CustomField\Models\CustomField;
use Modules\CustomField\Models\CustomFieldGroup;
use Yajra\DataTables\DataTables;
use Modules\Appointment\Models\BillingRecord;
use Modules\Appointment\Models\PatientEncounter;
use Modules\Appointment\Models\AppointmentTransaction;
use Modules\Appointment\Models\Appointment;
use Carbon\Carbon;
use Modules\Clinic\Models\ClinicsService;
use Modules\Commission\Models\CommissionEarning;
use Modules\Appointment\Trait\AppointmentTrait;
use App\Models\Setting;
use Modules\Appointment\Models\BillingItem;
use Modules\Appointment\Trait\BillingRecordTrait;
use Modules\Appointment\Transformers\BillingItemResource;
use Modules\Clinic\Http\Controllers\ClinicsServiceController;
use App\Models\User;
use Modules\Tip\Models\TipEarning;
use Modules\Clinic\Models\Clinics;
use Modules\Appointment\Models\EncounterPrescriptionBillingDetail;
use Modules\Appointment\Models\EncounterPrescription;
use Modules\Clinic\Models\Receptionist;

use Modules\Bed\Models\BedMaster;
use Modules\Bed\Models\BedType;

class BillingRecordController extends Controller
{
    use AppointmentTrait;
    use BillingRecordTrait;
    protected string $exportClass = '\App\Exports\BillingExport';
    public function __construct()
    {
        // Page Title
        $this->module_title = 'appointment.billing_record';
        // module name
        $this->module_name = 'billing-record';

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
            'payment_status' => $request->payment_status,
        ];

        $module_action = 'List';
        $columns = CustomFieldGroup::columnJsonValues(new BillingRecord());
        $customefield = CustomField::exportCustomFields(new BillingRecord());
        $service = ClinicsService::SetRole(auth()->user())->with('sub_category', 'doctor_service', 'ClinicServiceMapping', 'systemservice')->where('status', 1)->get();

        $export_import = true;
        $export_columns = [
            [
                'value' => 'encounter_id',
                'text' => __('appointment.lbl_encounter_id'),
            ],
            [
                'value' => 'user_id',
                'text' => __('appointment.lbl_patient_name'),
            ],
            [
                'value' => 'clinic_id',
                'text' => __('appointment.lbl_clinic'),
            ],
            [
                'value' => 'doctor_id',
                'text' => __('appointment.lbl_doctor'),
            ],
            [
                'value' => 'service_id',
                'text' => __('appointment.lbl_service'),
            ],
            [
                'value' => 'total_amount',
                'text' => __('appointment.lbl_total_amount'),
            ],
            [
                'value' => 'date',
                'text' => __('appointment.lbl_date'),
            ],
            [
                'value' => 'payment_status',
                'text' => __('appointment.lbl_payment_status'),
            ],

        ];
        $export_url = route('backend.billing-record.export');

        return view('appointment::backend.billing_record.index_datatable', compact('service', 'module_action', 'filter', 'columns', 'customefield', 'export_import', 'export_columns', 'export_url'));
    }

    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);

        $actionType = $request->action_type;

        $message = __('messages.bulk_update');

        switch ($actionType) {
            case 'change-status':
                $clinic = BillingRecord::whereIn('id', $ids)->update(['status' => $request->status]);
                $message = __('clinic.clinic_status');
                break;

            case 'delete':
                if (env('IS_DEMO')) {
                    return response()->json(['message' => __('messages.permission_denied'), 'status' => false], 200);
                }
                BillingRecord::whereIn('id', $ids)->delete();
                $message = __('clinic.clinic_delete');
                break;

            default:
                return response()->json(['status' => false, 'message' => __('service_providers.invalid_action')]);
        }

        return response()->json(['status' => true, 'message' => $message]);
    }
    public function index_data(Datatables $datatable, Request $request)
    {
        $query = BillingRecord::SetRole(auth()->user());

        $filter = $request->filter;

        if (isset($filter)) {
            if (isset($filter['column_status'])) {
                $query->where('payment_status', $filter['column_status']);
            }
        }


        if (isset($filter)) {
            if (isset($filter['doctor_name']) && $filter['doctor_name'] !== '') {
                $query->where('doctor_id', $filter['doctor_name']);
            }
            if (isset($filter['patient_name']) && $filter['patient_name'] !== '') {
                $query->where("user_id", $filter['patient_name']);
            }
            if (isset($filter['clinic_name']) && $filter['clinic_name'] !== '') {
                $query->where("clinic_id", $filter['clinic_name']);
            }
            if (isset($filter['service_name']) && $filter['service_name'] !== '') {
                $query->where('service_id', $filter['service_name']);
            }


            if (!empty($filter['date_start']) && !empty($filter['date_end'])) {
                $query->whereBetween('date', [$filter['date_start'], $filter['date_end']]);
            } elseif (!empty($filter['date_start'])) {
                $query->whereDate('date', '>=', $filter['date_start']);
            } elseif (!empty($filter['date_end'])) {
                $query->whereDate('date', '<=', $filter['date_end']);
            }
        }

        $datatable = $datatable->eloquent($query)
            ->addColumn('check', function ($data) {
                return '<input type="checkbox" class="form-check-input select-table-row"  id="datatable-row-' . $data->id . '"  name="datatable_ids[]" value="' . $data->id . '" onclick="dataTableRowCheck(' . $data->id . ')">';
            })
            ->addColumn('action', function ($data) {
                return view('appointment::backend.billing_record.action_column', compact('data'));
            })

            ->editColumn('clinic_id', function ($data) {
                return view('appointment::backend.patient_encounter.clinic_id', compact('data'));
            })

            ->editColumn('user_id', function ($data) {
                return view('appointment::backend.clinic_appointment.user_id', compact('data'));
            })

            ->editColumn('date', function ($data) {
                return $data->date ? DateFormate($data->date) : '--';
            })

            ->editColumn('doctor_id', function ($data) {
                return view('appointment::backend.clinic_appointment.doctor_id', compact('data'));
            })

            ->editColumn('payment_status', function ($data) {
                return view('appointment::backend.billing_record.verify_action', compact('data'));
            })

            ->editColumn('service_id', function ($data) {
                if ($data->clinicservice) {
                    return optional($data->clinicservice)->name;
                } else {
                    return '-';
                }
            })

            ->editColumn('total_amount', function ($data) {
                if ($data->final_total_amount) {
                    return '<span>' . \Currency::format($data->final_total_amount) . '</span>';
                } else {
                    return '<span>' . \Currency::format($data->total_amount) . '</span>';
                }
            })

            ->filterColumn('doctor_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    $query->whereHas('doctor', function ($query) use ($keyword) {
                        $query->where('first_name', 'like', '%' . $keyword . '%')
                            ->orWhere('last_name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    });
                }
            })

            ->filterColumn('user_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    $query->whereHas('user', function ($query) use ($keyword) {
                        $query->where('first_name', 'like', '%' . $keyword . '%')
                            ->orWhere('last_name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    });
                }
            })

            ->filterColumn('clinic_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    $query->whereHas('clinic', function ($query) use ($keyword) {
                        $query->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    });
                }
            })
            ->filterColumn('service_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    $query->whereHas('clinicservice', function ($query) use ($keyword) {
                        $query->where('name', 'like', '%' . $keyword . '%');
                    });
                }
            })

            ->editColumn('updated_at', function ($data) {
                $module_name = $this->module_name;

                $diff = Carbon::now()->diffInHours($data->updated_at);

                if ($diff < 25) {
                    return $data->updated_at->diffForHumans();
                } else {
                    return $data->updated_at->isoFormat('llll');
                }
            })
            ->orderColumns(['id'], '-:column $1');

        // Custom Fields For export
        $customFieldColumns = CustomField::customFieldData($datatable, BillingRecord::CUSTOM_FIELD_MODEL, null);

        return $datatable->rawColumns(array_merge(['action', 'payment_status', 'check', 'total_amount'], $customFieldColumns))
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('appointment::create');
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
        return view('appointment::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $data = BillingRecord::where('id', $id)->first();

        return response()->json(['data' => $data, 'status' => true]);
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

    public function saveBillingDetails(Request $request)
    {

        $data = $request->all();

        $encounter_details = PatientEncounter::where('id', $data['encounter_id'])->with('appointment', 'billingrecord')->first();

        $service_price_data = $data['service_details']['service_price_data'] ?? null;

        $tax_data = isset($data['service_details']['tax_data']) ? json_encode($data['service_details']['tax_data'], true) : null;

        $date = isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : (isset($encounter_details['encounter_date']) ? date('Y-m-d', strtotime($encounter_details['encounter_date'])) : null);

        $service_id = $data['service_details']['id'] ?? null;

        $billingData = optional($encounter_details->billingrecord)->billingItem ?? collect();

        $pharmaId = $request->pharma_id
            ?? $request->pharma
            ?? ($data['pharma_id'] ?? null)
            ?? ($data['pharma'] ?? null)
            ?? data_get($data, 'service_details.pharma_id')
            ?? $encounter_details->pharma_id
            ?? optional($encounter_details->billingrecord)->pharma_id;

        if ($request->is('api/*')) {
            $service_id = $request->input('service_id');
            if ($request->service_id == null) {
                $billingData = optional($encounter_details->billingrecord)->billingItem ?? collect();
                $service_id = optional($billingData->first())->item_id;
            }

            $data['service_details'] = ClinicsService::where('id', $service_id)->first();

            $newRequest = new Request([
                'service_id' => $service_id,
                'encounter_id' => $request->input('encounter_id')
            ]);

            $data['final_discount'] = $data['final_discount_enabled'] ?? 0;

            $serviceController = new ClinicsServiceController();
            $serviceDetailsResponse = $serviceController->ServiceDetails($newRequest);

            $serviceDetailsData = $serviceDetailsResponse->getData();

            $serviceDetails = $serviceDetailsData->data ?? [];

            $service_id = $serviceDetails->id;
            $service_price_data = (array) $serviceDetails->service_price_data;

            $taxData = json_encode($serviceDetails->tax_data);

            $billingData = optional($encounter_details->billingrecord)->billingItem ?? collect();
            $total_amount = $billingData->sum('total_amount');

            if ($data['final_discount'] == 1) {
                $discount = 0;
                if ($request->final_discount_type == 'fixed') {
                    $discount = $request->final_discount_value;
                } else {
                    $discount = $total_amount * $request->final_discount_value / 100;
                }
                $total_amount = $total_amount - $discount;
            }

            $tax_data = $this->calculateTaxAmounts(null, $total_amount);

            $data['final_tax_amount'] = array_sum(array_column($tax_data, 'amount'));
            $data['final_total_amount'] = $total_amount + $data['final_tax_amount'];
        }

        if ($pharmaId) {
            $encounter_details->update(['pharma_id' => $pharmaId]);
            $data['pharma_id'] = $pharmaId;
            $pharmaUser = User::find($pharmaId);
            $data['pharma_name'] = $pharmaUser
                ? trim(($pharmaUser->first_name ?? '') . ' ' . ($pharmaUser->last_name ?? ''))
                : null;
        }

        $biling_details = [
            'encounter_id' => $data['encounter_id'],
            'user_id' => $data['user_id'],
            'clinic_id' => $encounter_details['clinic_id'],
            'doctor_id' => $encounter_details['doctor_id'],
            'service_id' => $service_id ?? null,
            'total_amount' => $service_price_data['total_amount'] ?? 0,
            'service_amount' => $service_price_data['service_price'] ?? 0,
            'discount_amount' => $service_price_data['discount_amount'] ?? 0,
            'discount_type' => $service_price_data['discount_type'] ?? null,
            'discount_value' => $service_price_data['discount_value'] ?? null,
            'tax_data' => $tax_data,
            'date' => $date,
            'payment_status' => $data['payment_status'],
            'final_discount' => $data['final_discount'] ?? 0,
            'final_discount_value' => $data['final_discount_value'] ?? null,
            'final_discount_type' => $data['final_discount_type'] ?? null,
            'final_tax_amount' => $data['final_tax_amount'] ?? 0,
            'final_total_amount' => $data['final_total_amount'] ?? 0,
            'pharma_id' => $pharmaId,
        ];

        $billing_data = BillingRecord::updateOrCreate(
            ['encounter_id' => $data['encounter_id']],
            $biling_details
        );
        $billing_record = $billing_data->where('id', $billing_data->id)->with('clinicservice', 'patientencounter')->first();
        if ($billing_record && !empty($billing_record['service_id'])) {
            $billing_item = $this->generateBillingItem($billing_record);
        }

        if ($encounter_details['appointment_id'] !== null && $data['payment_status'] == 1) {
            $finalTotalAmount = $data['final_total_amount'] ?? 0;
            $paymentStatus = $data['payment_status'];
            
            // Calculate service amount (excluding bed charges) for commission calculations
            $bedCharges = $data['bed_charges'] ?? 0;
            $serviceAmount = $finalTotalAmount - $bedCharges; // Service amount for commission calculation

            \Log::info('BillingRecord - Commission Calculation Start', [
                'encounter_id' => $data['encounter_id'],
                'appointment_id' => $encounter_details['appointment_id'],
                'payment_status' => $paymentStatus,
                'final_total_amount' => $finalTotalAmount,
                'bed_charges' => $bedCharges,
                'service_amount' => $serviceAmount,
                'multi_vendor_enabled' => multiVendor(),
            ]);

            // Update the appointment transaction
            AppointmentTransaction::where('appointment_id', $encounter_details['appointment_id'])
                ->update([
                    'total_amount' => $finalTotalAmount,
                    'payment_status' => $paymentStatus,
                ]);

            if ($encounter_details['doctor_id'] && $earning_data = $this->commissionData($encounter_details)) {
                $appointment = Appointment::findOrFail($encounter_details['appointment_id']);

                // Save doctor commission
                $earning_data['commission_data']['user_type'] = 'doctor';
                $earning_data['commission_data']['commission_status'] = $paymentStatus == 1 ? 'unpaid' : 'pending';
                $appointment->commission()->updateOrCreate(
                    ['user_type' => 'doctor'],
                    $earning_data['commission_data']
                );

                \Log::info('BillingRecord - Doctor Commission Saved', [
                    'appointment_id' => $encounter_details['appointment_id'],
                    'doctor_commission_amount' => $earning_data['commission_data']['commission_amount'] ?? 0,
                ]);

                // $vendor_id = $data['service_details']['vendor_id'] ?? null;
                // Get vendor_id from the service through billing record relationship
                $vendor_id = null;
                // if ($billingData && $billingData->service_id) {
                //     $service = ClinicsService::find($billingData->service_id);
                //     $vendor_id = $service ? $service->vendor_id : null;
                // }
                if ($billingData && $billingData->count() > 0) {
                    foreach ($billingData as $item) {
                        $serviceId = $item->service_id ?? $item->item_id ?? null;

                        if ($item->service_id) {
                            $service = ClinicsService::find($item->service_id);
                            if ($service && $service->vendor_id) {
                                $vendor_id = $service->vendor_id;
                                break; // Exit loop once vendor_id is found
                            }
                        }
                    }
                }

                // Fallback: If vendor_id not found in billing data, try from service_details
                if (!$vendor_id) {
                    $vendor_id = $data['service_details']['vendor_id'] ?? null;
                }

                // Also try to get vendor_id from clinic
                if (!$vendor_id && isset($encounter_details['clinic_id'])) {
                    $clinic = Clinics::find($encounter_details['clinic_id']);
                    if ($clinic && $clinic->vendor_id) {
                        $vendor_id = $clinic->vendor_id;
                    }
                }

                \Log::info('BillingRecord - Vendor ID Detection', [
                    'appointment_id' => $encounter_details['appointment_id'],
                    'vendor_id_from_billing' => $vendor_id,
                    'vendor_id_from_service_details' => $data['service_details']['vendor_id'] ?? null,
                    'clinic_id' => $encounter_details['clinic_id'] ?? null,
                    'billing_data_count' => $billingData ? $billingData->count() : 0,
                ]);

                $vendor = User::find($vendor_id);
                $doctorCommissionAmount = $earning_data['commission_data']['commission_amount'] ?? 0;

                \Log::info('BillingRecord - Vendor User Check', [
                    'appointment_id' => $encounter_details['appointment_id'],
                    'vendor_id' => $vendor_id,
                    'vendor_found' => $vendor ? true : false,
                    'vendor_user_type' => $vendor ? $vendor->user_type : null,
                    'multi_vendor_setting' => multiVendor(),
                ]);

                // Determine admin and vendor commission logic
                if (multiVendor() != 1) {
                    // Admin commission when not multi-vendor
                    $adminEarning = $this->AdminEarningData($encounter_details);

                    if (!empty($adminEarning)) {
                        $adminEarning['user_type'] = 'admin';
                        $adminEarning['commission_status'] = $paymentStatus == 1 ? 'unpaid' : 'pending';
                        $adminEarningData = $adminEarning;
                    } else {
                        // Admin commission fallback: Service amount - Doctor commission
                        // This ensures admin commission is calculated on service amount, not full amount
                        $adminEarningData = [
                            'user_type' => $vendor->user_type ?? 'admin',
                            'employee_id' => $vendor->id ?? User::where('user_type', 'admin')->value('id'),
                            'commissions' => null,
                            'commission_status' => $paymentStatus == 1 ? 'unpaid' : 'pending',
                            'commission_amount' => max(0, $serviceAmount - $doctorCommissionAmount),
                        ];
                    }

                    $appointment->commission()->updateOrCreate(
                        ['user_type' => 'admin'],
                        $adminEarningData
                    );
                    
                    \Log::info('BillingRecord - Admin Commission Saved (Non Multi-Vendor)', [
                        'appointment_id' => $encounter_details['appointment_id'],
                        'admin_commission_amount' => $adminEarningData['commission_amount'] ?? 0,
                    ]);
                } else {
                    // Logic for multi-vendor scenario
                    \Log::info('BillingRecord - Multi-Vendor Mode Active', [
                        'appointment_id' => $encounter_details['appointment_id'],
                        'multi_vendor_value' => multiVendor(),
                    ]);
                    
                    if ($vendor && $vendor->user_type == 'vendor') {
                        \Log::info('BillingRecord - Vendor Found, Creating Commissions', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'vendor_id' => $vendor->id,
                            'vendor_name' => $vendor->first_name . ' ' . $vendor->last_name,
                        ]);
                        
                        // Admin earning for vendor
                        $adminEarning = $this->AdminEarningData($encounter_details);
                        $adminEarning['user_type'] = 'admin';
                        $adminEarning['commission_status'] = $paymentStatus == 1 ? 'unpaid' : 'pending';

                        $appointment->commission()->updateOrCreate(
                            ['user_type' => 'admin'],
                            $adminEarning
                        );
                        
                        $adminCommissionAmount = $adminEarning['commission_amount'] ?? 0;

                        \Log::info('BillingRecord - Admin Commission Saved (Multi-Vendor)', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'admin_commission_amount' => $adminCommissionAmount,
                        ]);

                        // Vendor earning = Service amount (excluding bed charges) - Doctor Commission - Admin Commission
                        // Bed charges are deducted first, then admin/doctor earnings, remaining goes to clinic
                        $vendorEarningData = [
                            'user_type' => $vendor->user_type,
                            'employee_id' => $vendor->id,
                            'commissions' => null,
                            'commission_status' => $paymentStatus == 1 ? 'unpaid' : 'pending',
                            'commission_amount' => max(0, $serviceAmount - $adminCommissionAmount - $doctorCommissionAmount),
                        ];
                        
                        \Log::info('BillingRecord - Vendor Commission Data', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'vendor_earning_data' => $vendorEarningData,
                            'calculation' => "{$serviceAmount} (Service) - {$adminCommissionAmount} (Admin) - {$doctorCommissionAmount} (Doctor) = {$vendorEarningData['commission_amount']}",
                        ]);
                        
                        $result = $appointment->commission()->updateOrCreate(
                            ['user_type' => 'vendor'],
                            $vendorEarningData
                        );
                        
                        \Log::info('BillingRecord - Vendor Commission Saved', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'vendor_commission_id' => $result->id ?? null,
                            'vendor_commission_amount' => $result->commission_amount ?? 0,
                            'wasRecentlyCreated' => $result->wasRecentlyCreated ?? false,
                        ]);
                    } else {
                        // Fallback to admin earning if vendor is not found
                        // Admin commission fallback: Service amount - Doctor commission
                        $adminEarningData = [
                            'user_type' => 'admin',
                            'employee_id' => User::where('user_type', 'admin')->value('id'),
                            'commissions' => null,
                            'commission_status' => $paymentStatus == 1 ? 'unpaid' : 'pending',
                            'commission_amount' => max(0, $serviceAmount - $doctorCommissionAmount),
                        ];

                        $appointment->commission()->updateOrCreate(
                            ['user_type' => 'admin'],
                            $adminEarningData
                        );
                        
                        \Log::info('BillingRecord - Fallback Admin Commission Saved (Vendor Not Found)', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'admin_commission_amount' => $adminEarningData['commission_amount'] ?? 0,
                            'reason' => 'Vendor not found or not a vendor user type',
                        ]);
                    }
                }
            } else {
                \Log::info('BillingRecord - Commission Calculation Skipped', [
                    'encounter_id' => $data['encounter_id'],
                    'appointment_id' => $encounter_details['appointment_id'],
                    'doctor_id' => $encounter_details['doctor_id'] ?? null,
                    'earning_data' => $earning_data ? 'exists' : 'null',
                ]);
            }
        } else {
            \Log::info('BillingRecord - Commission Calculation Not Triggered', [
                'encounter_id' => $data['encounter_id'],
                'appointment_id' => $encounter_details['appointment_id'] ?? null,
                'payment_status' => $data['payment_status'] ?? null,
            ]);
        }

        if ($request->has('encounter_status') && $request->encounter_status == 0 && $data['payment_status'] == 1) {

            PatientEncounter::where('id', $data['encounter_id'])->update(['status' => $request->encounter_status]);

            if ($encounter_details['appointment_id'] != null && $data['payment_status'] == 1) {

                $appointment = Appointment::where('id', $encounter_details['appointment_id'])->first();
                $clinic_data = Clinics::where('id', $appointment->clinic_id)->first();
                $receptionist = Receptionist::with('users')->where('clinic_id',$appointment->clinic_id)->first();
                // $data['service_name'] = $service_data->systemservice->name ?? '--'; // Removed undefined $service_data
                $data['clinic_name'] = $clinic_data->name ?? '--';
                if ($appointment && $appointment->status == 'check_in') {
                    $finalTotalAmount = $data['final_total_amount'] ?? 0;
                    $appointment->update([
                        'total_amount' => $finalTotalAmount,
                        'status' => 'checkout',
                    ]);
                    $startDate = Carbon::parse($appointment['start_date_time']);
                    $notification_data = [
                        'id' => $appointment->id,
                        'description' => $appointment->description,
                        'appointment_duration' => $appointment->duration,
                        'user_id' => $appointment->user_id,
                        'user_name' => optional($appointment->user)->first_name ?? default_user_name(),
                        'doctor_id' => $appointment->doctor_id,
                        'doctor_name' => optional($appointment->doctor)->first_name,
                        'appointment_date' => Carbon::parse($appointment->appointment_date)->format('d/m/Y'),
                        'appointment_time' => Carbon::parse($appointment->appointment_time)->format('h:i A'),
                        'appointment_services_names' => ClinicsService::with('systemservice')->find($appointment->service_id)->systemservice->name ?? '--',
                        'appointment_services_image' => optional($appointment->clinicservice)->file_url,
                        'appointment_date_and_time' => $startDate->format('Y-m-d H:i'),
                        'clinic_name' => optional($appointment->cliniccenter)->name,
                        'clinic_id' => optional($appointment->cliniccenter)->id,
                        'latitude' => null,
                        'longitude' => null,
                        'clinic_name' => $clinic_data->name,
                        'clinic_id' => $clinic_data->id,
                        'vendor_id' => $clinic_data->vendor_id,
                        'receptionist_id' => $clinic_data->receptionist->receptionist_id ?? $receptionist->receptionist_id ?? null,
                        'receptionist_name' => isset($receptionist) ? $receptionist->users->first_name.' '.$receptionist->users->last_name : 'unknown',

                    ];
                    $this->sendNotificationOnBookingUpdate('checkout_appointment', $notification_data);
                }
            }
        }

        $message = __('clinic.save_biiling_form');

        if ($request->is('api/*') || $request->wantsJson()) {
            $encounterId = $data['encounter_id'] ?? $request->input('encounter_id');

            $prescriptionsQuery = EncounterPrescription::query();
            
            // Only eager load medicine relationship if Medicine class exists
            if (class_exists('Modules\Pharma\Models\Medicine')) {
                $prescriptionsQuery->with('medicine:id,name');
            }
            
            $prescriptions = $prescriptionsQuery
                ->where('encounter_id', $encounterId)
                ->get()
                ->map(function ($prescription) {
                    return [
                        'id' => $prescription->id,
                        'encounter_id' => $prescription->encounter_id,
                        'medicine_id' => $prescription->medicine_id,
                        'medicine_name' => optional($prescription->medicine)->name,
                        'quantity' => $prescription->quantity,
                        'frequency' => $prescription->frequency,
                        'duration' => $prescription->duration,
                        'instruction' => $prescription->instruction,
                        'total_amount' => $prescription->total_amount,
                    ];
                });

            $responseData = $data;
            $responseData['encounter_id'] = $encounterId;
            $responseData['prescriptions'] = $prescriptions;

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $responseData,
            ]);
        }

        return response()->json(['message' => $message, 'status' => true], 200);
    }
    public function billing_detail(Request $request)
    {
        $id = $request->id;
        $module_action = 'Billing Detail';
        $appointments = BillingRecord::with('user', 'doctor', 'clinicservice', 'clinic', 'billingItem', 'patientencounter.prescriptions')
            ->where('id', $id)
            ->first();
        // dd($appointments);
        $pharma = User::where('id', $appointments->pharma_id)->first();
        $billing = $appointments;
        $timezone = Setting::where('name', 'default_time_zone')->value('val') ?? 'UTC';
        $setting = Setting::where('name', 'date_formate')->first();
        $dateformate = $setting ? $setting->val : 'Y-m-d';
        $setting = Setting::where('name', 'time_formate')->first();
        $timeformate = $setting ? $setting->val : 'h:i A';
        $combinedFormat = $dateformate . ' ' . $timeformate;
        $prescriptionBilling = EncounterPrescriptionBillingDetail::where('encounter_id', $id)->first();
        $totalAmountWithExclusiveTax = $prescriptionBilling->total_amount ?? 0;
        $prescriptions = EncounterPrescription::with('billingDetail')->where('encounter_id', $id)->get();
        $totalMedicinePrice = \Currency::format($prescriptions->sum('total_amount'));
        $totalExclusiveTaxAmount = $prescriptionBilling->exclusive_tax_amount ?? 0;

        // Fetch bed allocations by patient_id (like encounter_detail_page)
        // But only show beds from open encounters (status == 1)
        $bedAllocations = collect();
        if ($billing && isset($billing['user_id'])) {
            $bedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $billing['user_id'])
                ->whereHas('patientEncounter', function($query) {
                    $query->where('status', 1); // Only show beds from open encounters
                })
                ->with(['patient', 'bedMaster.bedType', 'patientEncounter'])
                ->get();
        }

        return view('appointment::backend.billing_record.billing_detail', compact('module_action', 'billing', 'pharma', 'dateformate', 'timeformate', 'timezone', 'combinedFormat', 'totalAmountWithExclusiveTax', 'totalExclusiveTaxAmount', 'totalMedicinePrice', 'prescriptionBilling','bedAllocations'));

        // Fetch bed allocations by patient_id (like encounter_detail_page)
        // return view('appointment::backend.billing_record.billing_detail', compact('module_action', 'billing', 'dateformate', 'timeformate', 'timezone', 'combinedFormat', 'bedAllocations'));
    }

    public function EditBillingDetails(Request $request)
    {
        $encounter_id = $request->encounter_id;
        $data = [];
        $encounter_details = PatientEncounter::where('id', $encounter_id)->with('appointmentdetail', 'billingrecord')->first();
        if ($encounter_details->appointmentdetail) {
            $data['service_id'] = optional($encounter_details->appointmentdetail)->service_id ?? null;
            $data['payment_status'] = optional($encounter_details->appointmentdetail)->appointmenttransaction->payment_status ?? 0;
        } else {
            $data['service_id'] = optional($encounter_details->billingrecord)->service_id ?? null;
            $data['payment_status'] = optional($encounter_details->billingrecord)->payment_status ?? 0;
        }
        $data['billing_id'] = optional($encounter_details->billingrecord)->id ?? null;
        $data['final_discount'] = optional($encounter_details->billingrecord)->final_discount ?? 0;
        $data['final_discount_type'] = optional($encounter_details->billingrecord)->final_discount_type ?? 0;
        $data['final_discount_value'] = optional($encounter_details->billingrecord)->final_discount_value ?? 0;
        $data['appointment'] = $encounter_details->appointmentdetail;

        // Fetch bed allocations for this patient (for the modal)
        // But only show beds from open encounters (status == 1)
        $bedAllocations = collect();
        if ($encounter_details && isset($encounter_details->user_id)) {
            $bedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $encounter_details->user_id)
                ->whereHas('patientEncounter', function($query) {
                    $query->where('status', 1); // Only show beds from open encounters
                })
                ->with(['patient', 'bedMaster.bedType', 'patientEncounter'])
                ->get();
        }

        return response()->json(['data' => $data, 'status' => true, 'bedAllocations' => $bedAllocations]);
    }

    public function encounter_billing_detail(Request $request)
    {
        $id = $request->id;
        $module_action = 'Billing Detail';
        $appointments = BillingRecord::with('user', 'doctor', 'clinicservice', 'clinic', 'billingItem.clinicservice', 'patientencounter')
            ->where('id', $id)
            ->first();

        $billing = $appointments;

        // Settings
        $dateformate = Setting::where('name', 'date_formate')->value('val') ?? 'Y-m-d';
        $timeformate = Setting::where('name', 'time_formate')->value('val') ?? 'h:i A';
        $timezone = Setting::where('name', 'default_time_zone')->value('val') ?? 'UTC';
        $combinedFormat = $dateformate . ' ' . $timeformate;

        // Prescription Billing Details
        $prescriptionBilling = EncounterPrescriptionBillingDetail::where('encounter_id', $id)->first();
        $totalAmountWithExclusiveTax = $prescriptionBilling->total_amount ?? 0;
        $totalExclusiveTaxAmount = $prescriptionBilling->exclusive_tax_amount ?? 0;

        // Prescriptions with billing details
        $prescriptions = EncounterPrescription::with('billingDetail')
            ->where('encounter_id', $id)
            ->get();

        $totalMedicinePrice = \Currency::format($prescriptions->sum('total_amount'));

        // Get pharma user (ensure it's of role pharma if needed)
        $pharma = User::where('id', $appointments->pharma_id)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'pharma');
            })
            ->first();

     
        // Fetch bed allocations by encounter_id (preferred) or patient_id (fallback)
        $bedAllocations = collect();
        $totalBedCharges = 0;
        
        if ($billing) {
            if (isset($billing['encounter_id']) && $billing['encounter_id']) {
                // Fetch by encounter_id if available (more specific)
                $bedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $billing['encounter_id'])
                    ->with(['patient', 'bedMaster.bedType'])
                    ->get();
                
                // Calculate total bed charges from allocations
                if ($bedAllocations->isNotEmpty()) {
                    $totalBedCharges = $bedAllocations->sum('charge') ?? 0;
                }
            }
            
            // If no allocations found by encounter_id, try patient_id
            // But only include beds from open encounters (status == 1) to avoid including closed encounter charges
            if ($bedAllocations->isEmpty() && isset($billing['user_id']) && $billing['user_id']) {
                $bedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $billing['user_id'])
                    ->whereHas('patientEncounter', function($query) {
                        $query->where('status', 1); // Only include beds from open encounters
                    })
                    ->with(['patient', 'bedMaster.bedType', 'patientEncounter'])
                    ->get();
                
                // Calculate total bed charges from allocations
                if ($bedAllocations->isNotEmpty()) {
                    $totalBedCharges = $bedAllocations->sum('charge') ?? 0;
                }
            }
            
            // If still no bed charges calculated, use the value from billing record
            if ($totalBedCharges == 0 && isset($billing['bed_charges'])) {
                $totalBedCharges = $billing['bed_charges'] ?? 0;
            }
            
            // Update billing record with calculated bed charges
            if ($totalBedCharges > 0) {
                $billing['bed_charges'] = $totalBedCharges;
            }
        }

           return view('appointment::backend.billing_record.billing_detail', compact(
            'module_action',
            'billing',
            'dateformate',
            'timeformate',
            'timezone',
            'combinedFormat',
            'totalMedicinePrice',
            'totalExclusiveTaxAmount',
            'totalAmountWithExclusiveTax',
            'pharma',
            'prescriptionBilling',
            'bedAllocations'
        ));

    }


    public function saveBillingItems(Request $request)
    {
        $data = $request->all();
        $quantity = $data['quantity'] ?? 1;
        $unitPrice = $data['service_amount'];
        $totalInclusiveTax = 0;

        // Decode inclusive tax array only if provided
        if (isset($data['inclusive_tax']) && $data['inclusive_tax'] != null) {
            $inclusiveTaxes = json_decode($data['inclusive_tax'], true);

            foreach ($inclusiveTaxes as $tax) {
                if ($tax['type'] == 'fixed') {
                    $totalInclusiveTax += $tax['value'];
                } elseif ($tax['type'] == 'percent') {
                    $totalInclusiveTax += ($unitPrice * $tax['value']) / 100;
                }
            }
        }

        $discountAmount = 0;
        if (isset($data['discount_value'], $data['discount_type']) &&
            $data['discount_value'] != null && $data['discount_type'] != null) {

            if ($data['discount_type'] == 'fixed') {
                $discountAmount = $data['discount_value'];
            } elseif ($data['discount_type'] == 'percentage') {
                $discountAmount = (($unitPrice + $totalInclusiveTax) * $data['discount_value']) / 100;
            }
        }

        $discountedUnitPrice = ($unitPrice + $totalInclusiveTax) - $discountAmount;
        $finalTotal = $discountedUnitPrice * $quantity;
        $data['inclusive_tax_amount'] = $totalInclusiveTax;
        $data['total_amount'] = $finalTotal;

        $item = ClinicsService::where('id', $data['item_id'])->first();

        $html = '';

        $data['item_name'] = $item ? $item->name : '';

        $billing_item = BillingItem::updateOrCreate(
            [
                'billing_id' => $data['billing_id'],
                'item_id' => $data['item_id'],
            ],
            $data
        );

        $message = __('clinic.save_billing_item');

        if ($request->is('api/*')) {
            return response()->json(['message' => $message, 'data' => $data, 'status' => true], 200);
        } else {
            if ($data['type'] === 'encounter_details') {

                $service_details = [];
                $html = '';

                $dataModel = BillingRecord::where('id', $data['billing_id'])->with('billingItem')->first();
                $encounter = PatientEncounter::where('id', $dataModel['encounter_id'])->first();

                if (!empty($dataModel)) {
                    $html = view('appointment::backend.patient_encounter.component.service_list', [
                        'data' => $dataModel,
                        'status' => $encounter['status']
                    ])->render();
                }

                $service_details['service_total'] = 0; // Default value
                $service_details['total_tax'] = 0;
                $service_details['total_amount'] = 0;

                $service_details['final_discount'] =  0;
                $service_details['final_discount_value'] =  0;
                $service_details['final_discount_type'] =  null;
                $service_details['final_discount_amount'] = 0;

                if (!empty($dataModel->billingItem) && is_array($dataModel->billingItem->toArray())) {

                    $service_details['service_total'] = array_sum(array_column($dataModel->billingItem->toArray(), 'total_amount'));

                    if ($dataModel['final_discount'] == 1 && $dataModel['final_discount_value'] > 0) {
                        $service_details['final_discount'] =  $dataModel['final_discount'];
                        $service_details['final_discount_value'] =  $dataModel['final_discount_value'];
                        $service_details['final_discount_type'] =  $dataModel['final_discount_type'];

                        if ($dataModel['final_discount_type'] == 'fixed') {
                            $service_details['final_discount_amount'] = $dataModel['final_discount_value'];
                        } else {
                            $service_details['final_discount_amount'] = ($dataModel['final_discount_value'] * $service_details['service_total']) / 100;
                        }
                    }

                    $taxDetails = getBookingTaxamount($service_details['service_total'] - $service_details['final_discount_amount'], null);
                    $service_details['total_tax'] = $taxDetails['total_tax_amount'] ?? 0;

                    $service_details['total_amount'] = $service_details['total_tax'] + $service_details['service_total'] - $service_details['final_discount_amount'];

                    $service_details['service_total'] = $service_details['service_total'];
                }

                return response()->json([
                    'html' => $html,
                    'service_details' => $service_details,
                ]);
            } else {
                return response()->json(['message' => $message, 'data' => $data, 'status' => true], 200);
            }
        }
    }

    public function billing_item_list(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $query = BillingItem::with('clinicservice');
        if ($request->has('filter')) {
            $filters = $request->input('filter');
            if (isset($filters['name'])) {
                $query->where('name', 'like', '%' . $filters['name'] . '%');
            }
        }
        $billingItems = $query->orderBy('updated_at', 'desc')->paginate($perPage);
        $billingitemCollection = BillingItemResource::collection($billingItems);

        return response()->json([
            'status' => true,
            'data' => $billingitemCollection,
            'message' => __('appointment.lbl_billing_item_list'),
        ], 200);
    }

    public function billing_item_detail(Request $request)
    {
        $billingid = $request->billing_id;
        $data = [];
        if ($billingid != null) {
            $billingItems = BillingItem::with('clinicservice')
                ->where('billing_id', $billingid)
                ->get();

            foreach ($billingItems as $billingItem) {
                $name = optional($billingItem->clinicservice)->name;
                $data[] = [
                    'id' => $billingItem->id,
                    'billing_id' => $billingItem->billing_id,
                    'name' => $name,
                    'item_id' => $billingItem->item_id,
                    'service_price' => $billingItem->service_amount,
                    'total_amount' => $billingItem->total_amount,
                    'discount_value' => $billingItem->discount_value,
                    'discount_type' => $billingItem->discount_type,
                    'quantity' => $billingItem->quantity,
                ];
            }
        }

        return response()->json(['data' => $data, 'status' => true]);
    }
    public function editBillingItem(Request $request, $id)
    {
        $billing_item = BillingItem::where('id', $id)->first();

        return response()->json(['data' => $billing_item, 'status' => true]);
    }
    public function deleteBillingItem(Request $request, $id)
    {
        $billing_item = BillingItem::where('id', $id)->first();

        $billing_id = $billing_item->billing_id;

        $billing_item->forceDelete();

        if ($request->is('api/*')) {

            $message = __('appointment.billing_item_delete');

            return response()->json(['message' => $message, 'status' => true], 200);
        } else {

            $service_details = [];
            $html = '';

            $dataModel = BillingRecord::where('id', $billing_id)->with('billingItem')->first();
            $encounter = PatientEncounter::where('id', $dataModel['encounter_id'])->first();

            if (!empty($dataModel)) {
                $html = view('appointment::backend.patient_encounter.component.service_list', [
                    'data' => $dataModel,
                    'status' => $encounter['status']
                ])->render();
            }

            $service_details['service_total'] = 0; // Default value
            $service_details['total_tax'] = 0;
            $service_details['total_amount'] = 0;
            $service_details['final_discount'] =  0;
            $service_details['final_discount_value'] =  0;
            $service_details['final_discount_type'] =  null;
            $service_details['final_discount_amount'] = 0;

            if (!empty($dataModel->billingItem) && is_array($dataModel->billingItem->toArray())) {
                $service_details['service_total'] = array_sum(array_column($dataModel->billingItem->toArray(), 'total_amount'));

                if ($dataModel['final_discount'] == 1 && $dataModel['final_discount_value'] > 0) {
                    $service_details['final_discount'] =  $dataModel['final_discount'];
                    $service_details['final_discount_value'] =  $dataModel['final_discount_value'];
                    $service_details['final_discount_type'] =  $dataModel['final_discount_type'];

                    if ($dataModel['final_discount_type'] == 'fixed') {
                        $service_details['final_discount_amount'] = $dataModel['final_discount_value'];
                    } else {
                        $service_details['final_discount_amount'] = ($dataModel['final_discount_value'] * $service_details['service_total']) / 100;
                    }
                }

                $taxDetails = getBookingTaxamount($service_details['service_total'] - $service_details['final_discount_amount'], null);
                $service_details['total_tax'] = $taxDetails['total_tax_amount'] ?? 0;

                $service_details['total_amount'] = $service_details['total_tax'] + $service_details['service_total'] - $service_details['final_discount_amount'];
            }

            return response()->json([
                'html' => $html,
                'service_details' => $service_details,
            ]);
        }
    }

    public function getBillingItem($id)
    {
        $service_details = [];
        $html = '';

        $dataModel = BillingRecord::where('id', $id)->with('billingItem')->first();
        $encounter = PatientEncounter::with('bedAllocations')->where('id', $dataModel['encounter_id'])->first();

        $service_details['service_total'] = 0; // Default value
        $service_details['total_bed_charges'] = 0;
        $service_details['total_tax'] = 0;
        $service_details['total_amount'] = 0;

        $service_details['final_discount'] = 0;
        $service_details['final_discount_value'] = 0;
        $service_details['final_discount_type'] = 'percentage';
        $service_details['final_discount_amount'] = 0;

        // Calculate bed charges from bed allocations
        // Query all bed allocations for this encounter directly (since relationship is hasOne but we need all)
        $totalBedCharges = 0;
        if ($encounter) {
            // First try by encounter_id
            $allBedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $encounter->id)->get();
            
            \Log::info('Bed Charges Calculation Debug', [
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->user_id ?? null,
                'bed_allocations_count' => $allBedAllocations->count(),
                'bed_allocations' => $allBedAllocations->map(function($allocation) {
                    return [
                        'id' => $allocation->id,
                        'encounter_id' => $allocation->encounter_id,
                        'charge' => $allocation->charge,
                        'deleted_at' => $allocation->deleted_at ?? null,
                    ];
                })->toArray(),
            ]);
            
            // If no bed allocations found by encounter_id, try by patient_id as fallback
            // But only include beds from open encounters (status == 1) to avoid including closed encounter charges
            if ($allBedAllocations->isEmpty() && isset($encounter->user_id)) {
                $allBedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $encounter->user_id)
                    ->whereHas('patientEncounter', function($query) {
                        $query->where('status', 1); // Only include beds from open encounters
                    })
                    ->get();
                
                \Log::info('Bed Charges Fallback by patient_id', [
                    'patient_id' => $encounter->user_id,
                    'bed_allocations_count' => $allBedAllocations->count(),
                    'bed_allocations' => $allBedAllocations->map(function($allocation) {
                        return [
                            'id' => $allocation->id,
                            'encounter_id' => $allocation->encounter_id,
                            'patient_id' => $allocation->patient_id,
                            'charge' => $allocation->charge,
                        ];
                    })->toArray(),
                ]);
            }
            
            if ($allBedAllocations->isNotEmpty()) {
                $totalBedCharges = $allBedAllocations->sum('charge') ?? 0;
            } elseif ($encounter->bedAllocations) {
                // Fallback to relationship if direct query returns nothing
                if (is_iterable($encounter->bedAllocations) && method_exists($encounter->bedAllocations, 'sum')) {
                    $totalBedCharges = $encounter->bedAllocations->sum('charge') ?? 0;
                } else {
                    $totalBedCharges = $encounter->bedAllocations->charge ?? 0;
                }
            }
        } elseif (isset($data['bed_charges'])) {
            $totalBedCharges = $data['bed_charges'] ?? 0;
        }
        
        \Log::info('Bed Charges Final Calculation', [
            'total_bed_charges' => $totalBedCharges,
        ]);

        \Log::info('Checking billing items', [
            'billingItem_exists' => isset($data->billingItem),
            'billingItem_count' => $data->billingItem ? $data->billingItem->count() : 0,
            'billingItem_is_collection' => $data->billingItem instanceof \Illuminate\Database\Eloquent\Collection,
        ]);
        
        if (!empty($data->billingItem) && $data->billingItem->count() > 0) {
            
            // Calculate service price, inclusive tax, and service discount from billing items
            $servicePrice = 0;
            $totalInclusiveTax = 0;
            $serviceDiscountAmount = 0;
            
            foreach ($data->billingItem as $item) {
                $quantity = $item->quantity ?? 1;
                $unitPrice = $item->service_amount ?? 0;
                $inclusiveTax = $item->inclusive_tax_amount ?? 0;
                
                // Recalculate inclusive tax if it's 0 but service has inclusive tax enabled
                if (($inclusiveTax == 0 || $inclusiveTax == null) && !empty($item->item_id)) {
                    $service = \Modules\Clinic\Models\ClinicsService::where('id', $item->item_id)->first();
                    if ($service && $service->is_inclusive_tax == 1 && !empty($service->inclusive_tax)) {
                        $inclusiveTaxJson = json_decode($service->inclusive_tax, true);
                        $recalculatedTax = 0;
                        foreach ($inclusiveTaxJson as $tax) {
                            if (isset($tax['status']) && $tax['status'] == 1) {
                                if ($tax['type'] == 'percent') {
                                    $recalculatedTax += ($unitPrice * $tax['value']) / 100;
                                } elseif ($tax['type'] == 'fixed') {
                                    $recalculatedTax += $tax['value'];
                                }
                            }
                        }
                        $inclusiveTax = $recalculatedTax;
                    }
                }
                
                $servicePrice += $unitPrice * $quantity;
                $totalInclusiveTax += $inclusiveTax * $quantity;
                
                // Calculate service discount for this item (applied to base price only, not inclusive tax)
                $itemBasePriceTotal = $unitPrice * $quantity;
                $itemDiscountValue = $item->discount_value ?? null;
                $itemDiscountType = $item->discount_type ?? 'percentage';
                $itemDiscountStatus = $item->discount_status ?? null;
                
                // If billing item has no discount, check service for discount
                if (empty($itemDiscountValue) || $itemDiscountValue == 0) {
                    if (!empty($item->item_id)) {
                        $service = \Modules\Clinic\Models\ClinicsService::where('id', $item->item_id)->first();
                        if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                            $itemDiscountValue = $service->discount_value;
                            $itemDiscountType = $service->discount_type ?? 'percentage';
                            $itemDiscountStatus = 1;
                        }
                    }
                }
                
                // Calculate discount on base price only
                if (!empty($itemDiscountValue) && $itemDiscountValue > 0) {
                    // If discount_status doesn't exist, default to 1 if discount exists
                    if ($itemDiscountStatus === null) {
                        $itemDiscountStatus = 1;
                    }
                    
                    // Apply discount only if status is 1 (active)
                    if ($itemDiscountStatus == 1) {
                        if ($itemDiscountType == 'percentage') {
                            // Percentage discount on base price total
                            $serviceDiscountAmount += $itemBasePriceTotal * ($itemDiscountValue / 100);
                        } else {
                            // Fixed discount per quantity
                            $serviceDiscountAmount += $itemDiscountValue * $quantity;
                        }
                    }
                }
            }
            
            // Calculate amount after service discount: Base Service Price - Service Discount
            // Service discount is already calculated on base price only
            $amountAfterServiceDiscount = $servicePrice - $serviceDiscountAmount;
            
            // Store calculated values
            $service_details['service_total'] = $servicePrice; // Service price only (without inclusive tax)
            $service_details['total_inclusive_tax'] = $totalInclusiveTax;
            $service_details['subtotal'] = $servicePrice + $totalInclusiveTax;
            $service_details['service_discount_amount'] = $serviceDiscountAmount;
            $service_details['amount_after_service_discount'] = $amountAfterServiceDiscount;
            $service_details['total_bed_charges'] = $totalBedCharges;

            // Get tax_data from billing record if available
            // getBookingTaxamount handles JSON decoding internally, so pass it as-is
            $taxData = $data->tax_data ?? null;

            // Tax calculation logic:
            // - If there's NO overall discount, calculate tax on BASE service amount (ignoring service-level discounts)
            // - If there IS an overall discount, calculate tax on (Base Service Amount - Overall Discount) - still ignoring service-level discounts
            $amountForTaxCalculation = $servicePrice; // Start with base service price
            
            // Check if there's an overall discount
            $overallDiscountAmount = 0;
            if (isset($data->final_discount) && $data->final_discount == 1 && isset($data->final_discount_value) && $data->final_discount_value > 0) {
                $overallDiscountType = $data->final_discount_type ?? 'percentage';
                if ($overallDiscountType == 'fixed') {
                    $overallDiscountAmount = $data->final_discount_value;
                } else {
                    // Percentage discount on base service amount (not on amount after service discount)
                    $overallDiscountAmount = ($servicePrice * $data->final_discount_value) / 100;
                }
                // When overall discount exists, calculate tax on (Base Service Amount - Overall Discount)
                $amountForTaxCalculation = $servicePrice - $overallDiscountAmount;
            }
            // Note: Service-level discounts are NOT included in tax calculation base

            \Log::info('getServiceDetails - Tax Calculation', [
                'amount_for_tax' => $amountForTaxCalculation,
                'amount_after_service_discount' => $amountAfterServiceDiscount,
                'tax_data_raw' => $taxData,
                'service_price' => $servicePrice,
                'service_discount_amount' => $serviceDiscountAmount,
                'overall_discount_amount' => $overallDiscountAmount,
            ]);
            
            $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxData);

            $service_details['total_tax'] = $taxDetails['total_tax_amount'] ?? 0;
            $service_details['tax_breakdown'] = $taxDetails['tax_details'] ?? [];

            // Store original service total for reference
            $originalServiceTotal = $service_details['service_total'];
            
            // Initial total: (Subtotal - Service Discount) + Tax (Exclusive) + Bed Charges
            $service_details['total_amount'] = $amountAfterServiceDiscount + $service_details['total_tax'] + $service_details['total_bed_charges'];


            if (isset($data->final_discount) && $data->final_discount == 1 && isset($data->final_discount_value) && $data->final_discount_value > 0) {
                $service_details['final_discount'] = $data->final_discount;
                $service_details['final_discount_value'] = $data->final_discount_value;
                $service_details['final_discount_type'] = $data->final_discount_type ?? 'percentage';

                // Overall discount is applied to base service amount only (not after service discount)
                $amountForOverallDiscount = $servicePrice; // Base service price (not after service discount)
                
                // Apply overall discount on base service amount
                if ($data->final_discount_type == 'fixed') {
                    $service_details['final_discount_amount'] = $data->final_discount_value;
                } else {
                    // Percentage discount on base service amount
                    $service_details['final_discount_amount'] = ($data->final_discount_value * $amountForOverallDiscount) / 100;
                }
                
                // Calculate amount after overall discount: Base Service Price - Overall Discount
                $amountAfterOverallDiscount = $amountForOverallDiscount - $service_details['final_discount_amount'];
                
                // Tax is already calculated above on (Base Service Price - Overall Discount)
                // No need to recalculate, it's already done
                
                // Final total: (Base Service Price - Overall Discount) + Tax + Bed Charges
                // Note: Service-level discounts are separate and don't affect tax calculation
                $service_details['final_total_amount'] = $amountAfterOverallDiscount + $service_details['total_tax'] + $service_details['total_bed_charges'];
            } else {
                $service_details['final_total_amount'] = $service_details['total_amount'];
            }
        } else {
            // If no billing items, still calculate totals with bed charges
            $service_details['total_bed_charges'] = $totalBedCharges;
            $service_details['total_amount'] = $service_details['service_total'] + $service_details['total_tax'] + $totalBedCharges;
            $service_details['final_total_amount'] = $service_details['total_amount'];
        }

        // Always set bed charges, even if no billing items
        $service_details['total_bed_charges'] = $totalBedCharges;
        
        \Log::info('Service Details Final', [
            'service_total' => $service_details['service_total'],
            'total_bed_charges' => $service_details['total_bed_charges'],
            'total_tax' => $service_details['total_tax'],
            'total_amount' => $service_details['total_amount'],
            'final_total_amount' => $service_details['final_total_amount'],
        ]);

        return response()->json([
            'service_details' => $service_details,
        ]);
    }

    public function CalculateDiscount(Request $request)
    {
        $service_details = [];

        $data = BillingRecord::where('id', $request->billing_id)->with('billingItem')->first();
        $encounter = PatientEncounter::with('bedAllocations')->where('id', $data['encounter_id'])->first();

        // Calculate bed charges from bed allocations
        // Note: bedAllocations is hasOne relationship, but we need to get all bed allocations
        $totalBedCharges = 0;
        if ($encounter) {
            // First try by encounter_id
            $allBedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $encounter->id)->get();
            
            // If no bed allocations found by encounter_id, try by patient_id as fallback
            // But only include beds from open encounters (status == 1) to avoid including closed encounter charges
            if ($allBedAllocations->isEmpty() && isset($encounter->user_id)) {
                $allBedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $encounter->user_id)
                    ->whereHas('patientEncounter', function($query) {
                        $query->where('status', 1); // Only include beds from open encounters
                    })
                    ->get();
            }
            
            if ($allBedAllocations->isNotEmpty()) {
                $totalBedCharges = $allBedAllocations->sum('charge') ?? 0;
            } elseif ($encounter->bedAllocations) {
                // Fallback to relationship if direct query returns nothing
                if (is_iterable($encounter->bedAllocations) && method_exists($encounter->bedAllocations, 'sum')) {
                    $totalBedCharges = $encounter->bedAllocations->sum('charge') ?? 0;
                } else {
                    $totalBedCharges = $encounter->bedAllocations->charge ?? 0;
                }
            }
        } elseif (isset($data['bed_charges'])) {
            $totalBedCharges = $data['bed_charges'] ?? 0;
        }

        $service_details['service_total'] = 0; // Default value
        $service_details['total_bed_charges'] = 0;
        $service_details['total_tax'] = 0;
        $service_details['total_amount'] = 0;
        $service_details['final_discount_amount'] = 0;


        if (!empty($data->billingItem) && is_array($data->billingItem->toArray())) {
            
            // Calculate sum of all service totals: Base Price only (without inclusive tax)
            $totalServiceAmount = 0;
            $totalServiceDiscount = 0;
            
            foreach ($data->billingItem as $item) {
                $quantity = $item->quantity ?? 1;
                $unitPrice = $item->service_amount ?? 0;
                
                // Service price total (base price only, without inclusive tax)
                $itemBasePriceTotal = $unitPrice * $quantity;
                
                // Get discount information
                $itemDiscountValue = $item->discount_value ?? null;
                $itemDiscountType = $item->discount_type ?? 'percentage';
                $itemDiscountStatus = $item->discount_status ?? null;
                
                // If billing item has no discount, check service for discount
                if (empty($itemDiscountValue) || $itemDiscountValue == 0) {
                    if (!empty($item->item_id)) {
                        $service = \Modules\Clinic\Models\ClinicsService::where('id', $item->item_id)->first();
                        if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                            $itemDiscountValue = $service->discount_value;
                            $itemDiscountType = $service->discount_type ?? 'percentage';
                            $itemDiscountStatus = 1;
                        }
                    }
                }
                
                // Calculate service discount amount (applied to base price only)
                $itemDiscountAmount = 0;
                if (!empty($itemDiscountValue) && $itemDiscountValue > 0) {
                    // If discount_status doesn't exist, default to 1 if discount exists
                    if ($itemDiscountStatus === null) {
                        $itemDiscountStatus = 1;
                    }
                    
                    // Apply discount only if status is 1 (active)
                    if ($itemDiscountStatus == 1) {
                        if ($itemDiscountType == 'percentage') {
                            // Percentage discount on base price total
                            $itemDiscountAmount = ($itemBasePriceTotal * $itemDiscountValue) / 100;
                        } else {
                            // Fixed discount per quantity
                            $itemDiscountAmount = $itemDiscountValue * $quantity;
                        }
                    }
                }
                
                // Add to total service amount (base price only)
                $totalServiceAmount += $itemBasePriceTotal;
                
                // Add to total service discount
                $totalServiceDiscount += $itemDiscountAmount;
            }
            
            // Store calculated values
            $service_details['service_total'] = $totalServiceAmount; // Base service price only
            $service_details['total_bed_charges'] = $totalBedCharges;
            
            // Calculate overall discount (final_discount) - Apply only to base service amount
            $overallDiscountAmount = 0;
            if ($request->discount_value > 0) {
                if ($request->discount_type == 'fixed') {
                    $overallDiscountAmount = $request->discount_value;
                } else {
                    // Percentage discount on base service amount
                    $overallDiscountAmount = ($totalServiceAmount * $request->discount_value) / 100;
                }
            }
            
            $service_details['final_discount_amount'] = $overallDiscountAmount;
            
            // Total discount = Service discounts + Overall discount
            $totalDiscountAmount = $totalServiceDiscount + $overallDiscountAmount;
            
            // Calculate amount after discount: Base Service Amount - Total Discount
            $amountAfterDiscount = $totalServiceAmount - $totalDiscountAmount;
            
            // Get tax_data from billing record if available
            // getBookingTaxamount handles JSON decoding internally, so pass it as-is
            $taxData = $data->tax_data ?? null;
            
            // Tax calculation logic:
            // - If there's NO overall discount (overallDiscountAmount = 0), calculate tax on BASE service amount (ignoring service-level discounts)
            // - If there IS an overall discount, calculate tax on (Base Service Amount - Overall Discount) - still ignoring service-level discounts
            $amountForTaxCalculation = $totalServiceAmount;
            if ($overallDiscountAmount > 0) {
                // When overall discount exists, calculate tax on (Base Service Amount - Overall Discount)
                $amountForTaxCalculation = $totalServiceAmount - $overallDiscountAmount;
            }
            // Note: Service-level discounts are NOT included in tax calculation base
            
            \Log::info('CalculateDiscount - Tax Calculation', [
                'amount_for_tax' => $amountForTaxCalculation,
                'amount_after_discount' => $amountAfterDiscount,
                'tax_data_raw' => $taxData,
                'total_service_amount' => $totalServiceAmount,
                'service_discount' => $totalServiceDiscount,
                'overall_discount' => $overallDiscountAmount,
                'total_discount_amount' => $totalDiscountAmount,
            ]);
            
            $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxData);
            $service_details['total_tax'] = $taxDetails['total_tax_amount'] ?? 0;
            $service_details['tax_breakdown'] = $taxDetails['tax_details'] ?? [];
            
            \Log::info('CalculateDiscount - Tax Result', [
                'total_tax' => $service_details['total_tax'],
                'tax_breakdown' => $service_details['tax_breakdown'],
            ]);
            
            // Calculate total payable: (Base Service Amount - Overall Discount) + Tax
            // Note: Service-level discounts are separate and don't affect tax or total payable calculation
            $totalPayableAmount = $amountForTaxCalculation + $service_details['total_tax'];
            
            // Final total: Total Payable Amount + Bed Charges
            $service_details['final_total_amount'] = $totalPayableAmount + $totalBedCharges;
            $service_details['total_amount'] = $service_details['final_total_amount'];
        }

        return response()->json([
            'service_details' => $service_details,
        ]);
    }

    public function SaveBillingData(Request $request)
    {
        $data = $request->all();
        
        \Log::info('SaveBillingData - Request Received', [
            'request_method' => $request->method(),
            'is_ajax' => $request->ajax(),
            'is_json' => $request->wantsJson(),
            'content_type' => $request->header('Content-Type'),
            'all_data' => $data,
        ]);
        
        \Log::info('SaveBillingData - Request Data', [
            'encounter_id' => $data['encount_id'] ?? null,
            'final_discount' => $data['final_discount'] ?? null,
            'final_discount_value' => $data['final_discount_value'] ?? null,
            'final_discount_type' => $data['final_discount_type'] ?? null,
            'payment_status' => $data['payment_status'] ?? null,
            'payment_status_type' => gettype($data['payment_status'] ?? null),
            'final_total_amount' => $data['final_total_amount'] ?? null,
            'total_tax_amount' => $data['total_tax_amount'] ?? null,
            'total_amount' => $data['total_amount'] ?? null,
            'bed_charges' => $data['bed_charges'] ?? null,
        ]);
        
        $billingData = BillingRecord::where('encounter_id', $data['encount_id'])->with('billingItem')->first();
        $encounter = PatientEncounter::with('bedAllocations')->find($data['encount_id']);

        $serviceDetails = [
            'service_total' => 0,
            'total_tax' => 0,
            'total_amount' => 0,
            'final_discount_amount' => 0,
            'bed_allocation_charges' => null,
            'bed_charges' => 0,
        ];

        if (!empty($billingData->billingItem)) {
            $billingItems = $billingData->billingItem->toArray();
            
            \Log::info('SaveBillingData - Billing Items', [
                'encounter_id' => $data['encount_id'],
                'items_count' => count($billingItems),
                'items' => $billingItems,
            ]);

            $serviceDetails['service_total'] = array_sum(array_column($billingItems, 'total_amount'));
            
            // Get bed charges - handle both collection and single object
            $bedCharges = 0;
            if ($encounter && $encounter->bedAllocations) {
                if (is_iterable($encounter->bedAllocations)) {
                    $bedCharges = $encounter->bedAllocations->sum('charge') ?? 0;
                } else {
                    $bedCharges = $encounter->bedAllocations->charge ?? 0;
                }
            }
            $serviceDetails['bed_charges'] = $bedCharges;
            $serviceDetails['bed_allocation_charges'] = $encounter->bedAllocations ?? null;

            \Log::info('SaveBillingData - Step 1 (Service and Bed Charges)', [
                'encounter_id' => $data['encount_id'],
                'service_total' => $serviceDetails['service_total'],
                'bed_charges' => $serviceDetails['bed_charges'],
            ]);

            // Calculate tax on service amount first (before discount)
            $tax_details = $billingData->appointmentTransaction->tax_data ?? null;
            $taxDetails = getBookingTaxamount($serviceDetails['service_total'], $tax_details);
            $serviceDetails['total_tax'] = $taxDetails['total_tax_amount'] ?? 0;

            \Log::info('SaveBillingData - Step 2 (Tax)', [
                'encounter_id' => $data['encount_id'],
                'service_total' => $serviceDetails['service_total'],
                'total_tax' => $serviceDetails['total_tax'],
            ]);

            // Apply discount on (service + tax) amount after tax is calculated
            if ($request->final_discount_value > 0 && $request->final_discount == 1) {
                // Calculate amount with tax (service + tax) for discount calculation
                $amountWithTax = $serviceDetails['service_total'] + $serviceDetails['total_tax'];
                
                if ($request->final_discount_type == 'fixed') {
                    $serviceDetails['final_discount_amount'] = $request->final_discount_value;
                } else {
                    // Percentage discount on (service + tax)
                    $serviceDetails['final_discount_amount'] = ($request->final_discount_value * $amountWithTax) / 100;
                }
                
                \Log::info('SaveBillingData - Step 3 (Discount)', [
                    'encounter_id' => $data['encount_id'],
                    'discount_type' => $request->final_discount_type,
                    'discount_value' => $request->final_discount_value,
                    'discount_amount' => $serviceDetails['final_discount_amount'],
                    'amount_with_tax' => $amountWithTax,
                ]);
            } else {
                $serviceDetails['final_discount_amount'] = 0;
            }

            // Calculate final total: (Service + Tax - Discount) + Bed Charges
            $amountWithTax = $serviceDetails['service_total'] + $serviceDetails['total_tax'];
            $serviceDetails['total_amount'] = $amountWithTax - $serviceDetails['final_discount_amount'] + $serviceDetails['bed_charges'];
            $serviceDetails['final_total_amount'] = $serviceDetails['total_amount'];

            \Log::info('SaveBillingData - Step 4 (Final Total)', [
                'encounter_id' => $data['encount_id'],
                'service_total' => $serviceDetails['service_total'],
                'total_tax' => $serviceDetails['total_tax'],
                'discount_amount' => $serviceDetails['final_discount_amount'],
                'bed_charges' => $serviceDetails['bed_charges'],
                'final_total_amount' => $serviceDetails['final_total_amount'],
            ]);
        }


        \Log::info('SaveBillingData - Before Update', [
            'encounter_id' => $data['encount_id'],
            'calculated_total_amount' => $serviceDetails['total_amount'],
            'calculated_final_total_amount' => $serviceDetails['final_total_amount'],
            'calculated_total_tax' => $serviceDetails['total_tax'],
            'request_final_total_amount' => $data['final_total_amount'] ?? null,
            'request_total_tax_amount' => $data['total_tax_amount'] ?? null,
        ]);

        // Ensure payment_status is properly cast
        $paymentStatus = isset($data['payment_status']) ? (int)$data['payment_status'] : 0;
        if ($paymentStatus !== 0 && $paymentStatus !== 1) {
            $paymentStatus = 0;
        }
        
        \Log::info('SaveBillingData - Payment Status Processing', [
            'raw_payment_status' => $data['payment_status'] ?? null,
            'processed_payment_status' => $paymentStatus,
            'billing_record_id' => $billingData->id ?? null,
            'current_payment_status' => $billingData->payment_status ?? null,
        ]);
        
        $billingData->update([
            'payment_status' => $paymentStatus,
            'final_discount' => $data['final_discount'] ?? 0,
            'final_discount_type' => $data['final_discount_type'] ?? null,
            'final_discount_value' => $data['final_discount_value'] ?? 0,
            'final_tax_amount' => $serviceDetails['total_tax'], // Use calculated tax, not from request
            'total_amount' => $serviceDetails['total_amount'],
            'final_total_amount' => $serviceDetails['final_total_amount'], // Use calculated total, not from request
            'bed_charges' => $serviceDetails['bed_charges'], // Use calculated bed charges
            'bed_allocation_charges' => $serviceDetails['bed_allocation_charges'] ?? null,
        ]);
        
        // Refresh to get updated values
        $billingData->refresh();
        
        \Log::info('SaveBillingData - After Update', [
            'encounter_id' => $data['encount_id'],
            'billing_record_id' => $billingData->id ?? null,
            'billing_record_updated' => true,
            'updated_payment_status' => $billingData->payment_status,
            'updated_final_total_amount' => $billingData->final_total_amount,
        ]);

        // Close encounter when payment status is paid
        $encounterClosed = false;
        if ($paymentStatus == 1) {
            \Log::info('SaveBillingData - Closing Encounter', [
                'encounter_id' => $data['encount_id'],
                'encounter_exists' => $encounter ? true : false,
                'current_status' => $encounter ? $encounter->status : null,
            ]);
            
            if ($encounter) {
                $encounter->update(['status' => 0]);
                $encounterClosed = true;
                \Log::info('SaveBillingData - Encounter Closed', [
                    'encounter_id' => $data['encount_id'],
                    'new_status' => 0,
                ]);
            } else {
                \Log::warning('SaveBillingData - Encounter Not Found', [
                    'encounter_id' => $data['encount_id'],
                ]);
                // Try to find and update encounter directly
                $encounterUpdate = PatientEncounter::where('id', $data['encount_id'])->update(['status' => 0]);
                if ($encounterUpdate) {
                    $encounterClosed = true;
                    \Log::info('SaveBillingData - Encounter Closed (Direct Update)', [
                        'encounter_id' => $data['encount_id'],
                    ]);
                }
            }
        }

        $encounter_details = PatientEncounter::find($data['encount_id']);
      
        if ($encounter_details && $encounter_details['appointment_id'] !== null && $paymentStatus == 1) {
            $finalTotalAmount = $billingData['final_total_amount'] ?? 0;
            
            // Calculate service amount (excluding bed charges) for commission calculations
            $bedCharges = $billingData['bed_charges'] ?? 0;
            $serviceAmount = $finalTotalAmount - $bedCharges; // Service amount for commission calculation

            \Log::info('SaveBillingData - Commission Calculation Start', [
                'encounter_id' => $data['encount_id'],
                'appointment_id' => $encounter_details['appointment_id'],
                'payment_status' => $paymentStatus,
                'final_total_amount' => $finalTotalAmount,
                'bed_charges' => $bedCharges,
                'service_amount' => $serviceAmount,
                'multi_vendor_enabled' => multiVendor(),
            ]);

            // Update the appointment transaction
            $transactionUpdated = AppointmentTransaction::where('appointment_id', $encounter_details['appointment_id'])
                ->update([
                    'total_amount' => $finalTotalAmount,
                    'payment_status' => $paymentStatus,
                    'transaction_type' => $data['payment_method'],
                ]);
            
            \Log::info('SaveBillingData - Appointment Transaction Updated', [
                'appointment_id' => $encounter_details['appointment_id'],
                'payment_status' => $paymentStatus,
                'total_amount' => $finalTotalAmount,
                'rows_affected' => $transactionUpdated,
            ]);

            if ($encounter_details['doctor_id'] && $earning_data = $this->commissionData($encounter_details)) {
                $appointment = Appointment::findOrFail($encounter_details['appointment_id']);

                $earning_data['commission_data']['user_type'] = 'doctor';
                $earning_data['commission_data']['commission_status'] = $paymentStatus == 1 ? 'unpaid' : 'pending';

                $appointment->commission()->updateOrCreate(
                    ['user_type' => 'doctor'],
                    $earning_data['commission_data']
                );

                \Log::info('SaveBillingData - Doctor Commission Saved', [
                    'appointment_id' => $encounter_details['appointment_id'],
                    'doctor_commission_amount' => $earning_data['commission_data']['commission_amount'] ?? 0,
                ]);

                // Try multiple sources for vendor_id
                $vendor_id = $data['service_details']['vendor_id'] ?? null;
                
                // Try from clinic
                if (!$vendor_id && isset($encounter_details['clinic_id'])) {
                    $clinic = Clinics::find($encounter_details['clinic_id']);
                    if ($clinic && $clinic->vendor_id) {
                        $vendor_id = $clinic->vendor_id;
                    }
                }
                
                \Log::info('SaveBillingData - Vendor ID Detection', [
                    'appointment_id' => $encounter_details['appointment_id'],
                    'vendor_id_from_service_details' => $data['service_details']['vendor_id'] ?? null,
                    'vendor_id_from_clinic' => $vendor_id,
                    'clinic_id' => $encounter_details['clinic_id'] ?? null,
                ]);
                
                $vendor = User::find($vendor_id);
                $doctorCommissionAmount = $earning_data['commission_data']['commission_amount'] ?? 0;

                \Log::info('SaveBillingData - Vendor User Check', [
                    'appointment_id' => $encounter_details['appointment_id'],
                    'vendor_id' => $vendor_id,
                    'vendor_found' => $vendor ? true : false,
                    'vendor_user_type' => $vendor ? $vendor->user_type : null,
                    'multi_vendor_setting' => multiVendor(),
                ]);

                if (multiVendor() != 1) {
                    // Admin commission fallback: Service amount - Doctor commission
                    $adminEarningData = [
                        'user_type' => $vendor->user_type ?? 'admin',
                        'employee_id' => $vendor->id ?? User::where('user_type', 'admin')->value('id'),
                        'commissions' => null,
                        'commission_status' => $paymentStatus == 1 ? 'unpaid' : 'pending',
                        'commission_amount' => max(0, $serviceAmount - $doctorCommissionAmount),
                    ];

                    $appointment->commission()->updateOrCreate(
                        ['user_type' => 'admin'],
                        $adminEarningData
                    );
                    
                    \Log::info('SaveBillingData - Admin Commission Saved (Non Multi-Vendor)', [
                        'appointment_id' => $encounter_details['appointment_id'],
                        'admin_commission_amount' => $adminEarningData['commission_amount'] ?? 0,
                    ]);
                } else {
                    \Log::info('SaveBillingData - Multi-Vendor Mode Active', [
                        'appointment_id' => $encounter_details['appointment_id'],
                        'multi_vendor_value' => multiVendor(),
                    ]);
                    
                    if ($vendor && $vendor->user_type == 'vendor') {
                        \Log::info('SaveBillingData - Vendor Found, Creating Commissions', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'vendor_id' => $vendor->id,
                            'vendor_name' => $vendor->first_name . ' ' . $vendor->last_name,
                        ]);
                        
                        $adminEarning = $this->AdminEarningData($encounter_details);
                        $adminEarning['user_type'] = 'admin';
                        $adminEarning['commission_status'] = $paymentStatus == 1 ? 'unpaid' : 'pending';

                        $appointment->commission()->updateOrCreate(['user_type' => 'admin'], $adminEarning);
                        
                        $adminCommissionAmount = $adminEarning['commission_amount'] ?? 0;

                        \Log::info('SaveBillingData - Admin Commission Saved (Multi-Vendor)', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'admin_commission_amount' => $adminCommissionAmount,
                        ]);

                        // Vendor earning = Service amount (excluding bed charges) - Doctor Commission - Admin Commission
                        // Bed charges are deducted first, then admin/doctor earnings, remaining goes to clinic
                        $vendorEarningData = [
                            'user_type' => $vendor->user_type,
                            'employee_id' => $vendor->id,
                            'commissions' => null,
                            'commission_status' => $paymentStatus == 1 ? 'unpaid' : 'pending',
                            'commission_amount' => max(0, $serviceAmount - $adminCommissionAmount - $doctorCommissionAmount),
                        ];

                        \Log::info('SaveBillingData - Vendor Commission Data', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'vendor_earning_data' => $vendorEarningData,
                            'calculation' => "{$serviceAmount} (Service) - {$adminCommissionAmount} (Admin) - {$doctorCommissionAmount} (Doctor) = {$vendorEarningData['commission_amount']}",
                        ]);

                        $result = $appointment->commission()->updateOrCreate(['user_type' => 'vendor'], $vendorEarningData);
                        
                        \Log::info('SaveBillingData - Vendor Commission Saved', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'vendor_commission_id' => $result->id ?? null,
                            'vendor_commission_amount' => $result->commission_amount ?? 0,
                            'wasRecentlyCreated' => $result->wasRecentlyCreated ?? false,
                        ]);
                    } else {
                        // Admin commission fallback: Service amount - Doctor commission
                        $adminEarningData = [
                            'user_type' => 'admin',
                            'employee_id' => User::where('user_type', 'admin')->value('id'),
                            'commissions' => null,
                            'commission_status' => $paymentStatus == 1 ? 'unpaid' : 'pending',
                            'commission_amount' => max(0, $serviceAmount - $doctorCommissionAmount),
                        ];
                        $appointment->commission()->updateOrCreate(
                             ['user_type' => 'admin'],
                            $adminEarningData
                        );
                        
                        \Log::info('SaveBillingData - Fallback Admin Commission Saved (Vendor Not Found)', [
                            'appointment_id' => $encounter_details['appointment_id'],
                            'admin_commission_amount' => $adminEarningData['commission_amount'] ?? 0,
                            'reason' => 'Vendor not found or not a vendor user type',
                        ]);
                    }
                }
            } else {
                \Log::info('SaveBillingData - Commission Calculation Skipped', [
                    'encounter_id' => $data['encount_id'],
                    'appointment_id' => $encounter_details['appointment_id'] ?? null,
                    'doctor_id' => $encounter_details['doctor_id'] ?? null,
                    'earning_data' => $earning_data ? 'exists' : 'null',
                ]);
            }
        } else {
            \Log::info('SaveBillingData - Commission Calculation Not Triggered', [
                'encounter_id' => $data['encount_id'],
                'appointment_id' => $encounter_details['appointment_id'] ?? null,
                'payment_status' => $paymentStatus,
            ]);
        }

        if ($data['payment_status'] == 1) {
            if ($encounter_details['appointment_id'] != null && $data['payment_status'] == 1) {
                $appointment = Appointment::where('id', $encounter_details['appointment_id'])->first();
                $clinic_data = Clinics::where('id', $appointment->clinic_id)->first();
                $receptionist = Receptionist::with('users')->where('clinic_id',$appointment->clinic_id)->first();
                if ($appointment && $appointment->status == 'check_in') {
                    $finalTotalAmount = $data['final_total_amount'] ?? 0;
                    $appointment->update([
                        'total_amount' => $finalTotalAmount,
                        'status' => 'checkout',
                    ]);
                    $startDate = Carbon::parse($appointment['start_date_time']);
                    $notification_data = [
                        'id' => $appointment->id,
                        'description' => $appointment->description,
                        'appointment_duration' => $appointment->duration,
                        'user_id' => $appointment->user_id,
                        'user_name' => optional($appointment->user)->first_name ?? default_user_name(),
                        'doctor_id' => $appointment->doctor_id,
                        'doctor_name' => optional($appointment->doctor)->first_name,
                        'clinic_name' => optional($appointment->cliniccenter)->name,
                        'clinic_id' => optional($appointment->cliniccenter)->id,
                        'appointment_date' => Carbon::parse($appointment->appointment_date)->format('d/m/Y'),
                        'appointment_time' => Carbon::parse($appointment->appointment_time)->format('h:i A'),
                        'appointment_services_names' => ClinicsService::with('systemservice')->find($appointment->service_id)->systemservice->name ?? '--',
                        'appointment_services_image' => optional($appointment->clinicservice)->file_url,
                        'appointment_date_and_time' => $startDate->format('Y-m-d H:i'),
                        'latitude' => null,
                        'longitude' => null,
                        'clinic_name' => $clinic_data->name,
                        'clinic_id' => $clinic_data->id,
                        'vendor_id' => $clinic_data->vendor_id,
                        'receptionist_id' => $clinic_data->receptionist->receptionist_id ?? $receptionist->receptionist_id ?? null,
                        'receptionist_name' => isset($receptionist) ? $receptionist->users->first_name.' '.$receptionist->users->last_name : 'unknown',

                    ];
                    $this->sendNotificationOnBookingUpdate('checkout_appointment', $notification_data);
                }
            }
        }


        // Get the final encounter status after all updates (verify it was actually closed)
        $encounterAfterUpdate = PatientEncounter::find($data['encount_id']);
        $encounterClosedStatus = $encounterClosed || ($encounterAfterUpdate && $encounterAfterUpdate->status == 0);
        
        \Log::info('SaveBillingData - Success Response', [
            'encounter_id' => $data['encount_id'],
            'payment_status' => $paymentStatus,
            'encounter_closed' => $encounterClosedStatus,
            'encounter_closed_variable' => $encounterClosed,
            'encounter_status' => $encounterAfterUpdate ? $encounterAfterUpdate->status : null,
            'final_total_amount' => $billingData->final_total_amount,
        ]);
        
        return response()->json([
            'message' => 'Billing details saved successfully',
            'status' => true,
            'encounter_closed' => $encounterClosedStatus,
            'payment_status' => $paymentStatus,
            'data' => [
                'payment_status' => $paymentStatus,
                'encounter_closed' => $encounterClosedStatus,
                'final_total_amount' => $billingData->final_total_amount,
            ]
        ]);
    }
}

