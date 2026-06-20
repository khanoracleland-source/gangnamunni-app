<?php

namespace Modules\Appointment\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Imports\PrescriptionImport;
use App\Mail\MedicalReportEmail;
use App\Mail\PrescriptionListMail;
use App\Models\Setting;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use League\Csv\Reader;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Appointment\Models\Appointment;
use Modules\Appointment\Models\BillingRecord;
use Modules\Appointment\Models\EncounterMedicalReport;
use Modules\Appointment\Models\EncounterOtherDetails;
use Modules\Appointment\Models\EncounterPrescription;
use Modules\Appointment\Models\EncounterPrescriptionBillingDetail;
use Modules\Appointment\Models\EncounterTemplate;
use Modules\Appointment\Models\EncouterMedicalHistroy;
use Modules\Appointment\Models\PatientEncounter;
use Modules\Appointment\Models\TemplateMedicalHistory;
use Modules\Appointment\Models\TemplateOtherDetails;
use Modules\Appointment\Models\TemplatePrescription;
use Modules\Appointment\Trait\EncounterTrait;
use Modules\Appointment\Transformers\MedicalReportRescource;
use Modules\Appointment\Transformers\PrescriptionRescource;
use Modules\Constant\Models\Constant;
use Modules\CustomField\Models\CustomField;
use Modules\CustomField\Models\CustomFieldGroup;
use Modules\CustomForm\Models\CustomForm;
use Modules\Tax\Models\Tax;
use PDF;
use Yajra\DataTables\DataTables;
use Modules\Clinic\Models\DoctorServiceMapping;
use Modules\Bed\Models\BedMaster;
use Modules\Bed\Models\BedType;
use Modules\Bed\Models\BedAllocation;


class PatientEncounterController extends Controller
{
    use EncounterTrait;

    protected string $exportClass = '\App\Exports\EncounterExport';

    //protected string $exportClassdata = '\App\Exports\EncounterPrescriptionExport';

    public function __construct()
    {
        // Page Title
        $this->module_title = 'appointment.encounter';
        // module name
        $this->module_name = 'encounter';

        // module icon
        $this->module_icon = 'fa-solid fa-clipboard-list';

        view()->share([
            'module_title' => $this->module_title,
            'module_icon'  => $this->module_icon,
            'module_name'  => $this->module_name,
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
        $columns       = CustomFieldGroup::columnJsonValues(new Appointment());
        $customefield  = CustomField::exportCustomFields(new Appointment());
        $patients      = User::role('user')->where('status', 1)->get();

        $export_import  = true;
        $export_columns = [
            [
                'value' => 'user_id',
                'text'  => __('appointment.lbl_patient_name'),
            ],
            [
                'value' => 'clinic_id',
                'text'  => __('appointment.lbl_clinic'),
            ],
            [
                'value' => 'doctor_id',
                'text'  => __('appointment.lbl_doctor'),
            ],

            [
                'value' => 'encounter_date',
                'text'  => __('appointment.lbl_date'),
            ],

            [
                'value' => 'status',
                'text'  => __('service.lbl_status'),
            ],

        ];
        $export_url = route('backend.encounter.export');

        return view('appointment::backend.patient_encounter.index_datatable', compact('module_action', 'filter', 'columns', 'customefield', 'export_import', 'export_columns', 'export_url', 'patients'));
    }

    /**
     * Select Options for Select 2 Request/ Response.
     *
     * @return Response
     */
    public function index_list(Request $request)
    {
        $query_data = PatientEncounter::SetRole(auth()->user())->with('appointment');

        $query_data = $query_data->where('status',1)->orderBy('appointment_id', 'desc')->get();

        $data = [];

        foreach ($query_data as $row) {
            $data[] = [
                'id'             => $row->id,
                'text'           => 'Encounters#' . $row->id,
                'clinic_id'      => $row->clinic_id,
                'user_id'        => $row->user_id,
                'doctor_id'      => $row->doctor_id,
                'appointment_id' => $row->appointment_id,
                'service_id'     => optional($row->appointment)->service_id,
                'date'           => date('d-m-Y', strtotime(customDate($row->encounter_date))),
            ];
        }
        return response()->json($data);
    }

    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);

        $actionType = $request->action_type;

        $message = __('messages.bulk_update');

        switch ($actionType) {
            case 'change-status':
                $clinic  = PatientEncounter::whereIn('id', $ids)->update(['status' => $request->status]);
                $message = __('appointment.encounter_status');
                break;

            case 'delete':
                if (env('IS_DEMO')) {
                    return response()->json(['message' => __('messages.permission_denied'), 'status' => false], 200);
                }
                PatientEncounter::whereIn('id', $ids)->delete();
                $message = __('appointment.encounter_delete');
                break;

            default:
                return response()->json(['status' => false, 'message' => __('service_providers.invalid_action')]);
                break;
        }

        return response()->json(['status' => true, 'message' => $message]);
    }

    public function index_data(Datatables $datatable, Request $request)
    {
        $query = PatientEncounter::SetRole(auth()->user());

        $customform = CustomForm::where('module_type', 'patient_encounter_module')
            ->where('status', 1)
            ->get();

        $filter = $request->filter;

        if (isset($filter)) {
            if (isset($filter['column_status'])) {
                $query->where('status', $filter['column_status']);
            }

            if (isset($filter['patient_name'])) {
                $query->where('user_id', $filter['patient_name']);
            }
            if (isset($filter['clinic_name'])) {
                $query->where('clinic_id', $filter['clinic_name']);
            }
            if (isset($filter['doctor_name'])) {
                $query->where('doctor_id', $filter['doctor_name']);
            }
            // Add encounter_id filter if present
            if (isset($filter['encounter_id'])) {
                $query->where('id', $filter['encounter_id']);
            }
        }

        $datatable = $datatable->eloquent($query)
            ->addColumn('check', function ($data) {
                return '<input type="checkbox" class="form-check-input select-table-row"  id="datatable-row-' . $data->id . '"  name="datatable_ids[]" value="' . $data->id . '" onclick="dataTableRowCheck(' . $data->id . ')">';
            })
            ->addColumn('action', function ($data) use ($customform) {
                return view('appointment::backend.patient_encounter.datatable.action_column', compact('data', 'customform'));
            })

            ->editColumn('clinic_id', function ($data) {
                return view('appointment::backend.patient_encounter.clinic_id', compact('data'));
            })
            // Show encounter_id as "Encounter#ID" if encounter_id exists, else show '--'
            ->addColumn('encounter_id', function ($data) {
                if (!empty($data->id)) {
                    return '#' . $data->id;
                }
                return '--';
            })
            ->editColumn('appointment_id', function ($data) {
                if (!empty($data->appointment_id)) {
                    return '#' . $data->appointment_id;
                }
                return '--';
            })

            ->editColumn('user_id', function ($data) {
                return view('appointment::backend.clinic_appointment.user_id', compact('data'));
            })

            ->editColumn('encounter_date', function ($data) {
                return formatDate($data->encounter_date) ?? '--';
            })

            ->editColumn('doctor_id', function ($data) {
                return view('appointment::backend.clinic_appointment.doctor_id', compact('data'));
            })

            ->editColumn('status', function ($data) {
                return view('appointment::backend.patient_encounter.verify_action', compact('data'));
            })

            ->filterColumn('doctor_id', function ($query, $keyword) {
                if (! empty($keyword)) {
                    $query->whereHas('doctor', function ($query) use ($keyword) {
                        $query->where('first_name', 'like', '%' . $keyword . '%')
                            ->orWhere('last_name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    });
                }
            })

            ->filterColumn('user_id', function ($query, $keyword) {
                if (! empty($keyword)) {
                    $query->whereHas('user', function ($query) use ($keyword) {
                        $query->where('first_name', 'like', '%' . $keyword . '%')
                            ->orWhere('last_name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    });
                }
            })

            ->filterColumn('clinic_id', function ($query, $keyword) {
                if (! empty($keyword)) {
                    $query->whereHas('clinic', function ($query) use ($keyword) {
                        $query->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    });
                }
            })

            // Add filterColumn for encounter_id (which is actually the "id" column)
            ->filterColumn('encounter_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    // Remove any leading "#" if present
                    $keyword = ltrim($keyword, '#');
                    $query->where('id', $keyword);
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
            // Order by appointment_id in descending order
            ->orderColumns(['appointment_id'], '-:column $1');

        // Custom Fields For export
        $customFieldColumns = CustomField::customFieldData($datatable, User::CUSTOM_FIELD_MODEL, null);

        // Add 'encounter_id' to rawColumns so it is not escaped
        return $datatable->rawColumns(array_merge(['action', 'status', 'is_banned', 'email_verified_at', 'check', 'image', 'encounter_id'], $customFieldColumns))
            ->toJson();
    }

    public function store(Request $request)
    {
        $data = $request->all();
// dd($data);
        $data['vendor_id'] = isset($data['vendor_id']) ? $data['vendor_id'] : Auth::id();

        $encounter_details = PatientEncounter::create($data);
        $billing_record    = [
            'encounter_id' => $encounter_details->id,
            'user_id'      => $encounter_details->user_id,
            'clinic_id'    => $encounter_details->clinic_id,
            'doctor_id'    => $encounter_details->doctor_id,
        ];
        $billingrecord = BillingRecord::create($billing_record);

        if ($request->is('api/*')) {
            $message = __('appointment.save_encounter');
            return response()->json(['message' => $message, 'data' => $data, 'status' => true], 200);
        } else {
            $message = __('appointment.save_encounter');
            return response()->json(['message' => $message, 'data' => $data, 'status' => true], 200);
        }
    }

    public function edit($id)
    {
        $module_action = 'Edit';

        $data = PatientEncounter::findOrFail($id);

        return response()->json(['data' => $data, 'status' => true]);
    }

    public function update(Request $request, $id)
    {
        $data = PatientEncounter::findOrFail($id);

        $request_data = $request->all();

        $data->update($request_data);

        $message = __('messages.update_form', ['form' => __('appointment.patient_encounter')]);

        if ($request->is('api/*')) {
            return response()->json(['message' => $message, 'data' => $data, 'status' => true], 200);
        } else {
            return response()->json(['message' => $message, 'status' => true], 200);
        }
    }

    public function encounterDetail($id)
    {

        $data = PatientEncounter::where('id', $id)->with('user', 'user.cities', 'user.countries', 'clinic', 'doctor', 'medicalHistroy', 'prescriptions', 'EncounterOtherDetails', 'medicalReport', 'appointmentdetail', 'billingrecord')->first();

        $data['selectedProblemList']     = $data->medicalHistroy()->where('type', 'encounter_problem')->get();
        $data['selectedObservationList'] = $data->medicalHistroy()->where('type', 'encounter_observations')->get();
        $data['notesList']               = $data->medicalHistroy()->where('type', 'encounter_notes')->get();
        $data['prescriptions']           = $data->prescriptions()->get();
        $data['other_details']           = $data->EncounterOtherDetails()->value('other_details') ?? null;
        $data['medicalReport']           = $data->medicalReport()->get();
        $data['signature']               = optional(optional($data->doctor)->doctor)->Signature ?? null;
        $data['appointment_status']      = $data->appointmentdetail->status ?? null;
        $data['payment_status']          = $data->appointmentdetail->appointmenttransaction->payment_status ?? null;
        $data['billingrecord']           = $data->billingrecord ?? null;
        $data['billingrecord_payment']   = $data->billingrecord->payment_status ?? null;
        $data['encounter_date']          = formatDate($data['encounter_date']);
        $data['customform']              = CustomForm::where('module_type', 'appointment_module')
            ->where('status', 1)
            ->get()
            ->filter(function ($item) {
                $showInArray = json_decode($item->show_in, true);
                return in_array('encounter', $showInArray);
            });

        // Get clinic admin based on the encounter's clinic
        $clinicAdmin = null;
        if ($data->clinic) {
            // Get the clinic admin for this specific clinic
            $clinicAdmin = \App\Models\User::where('id', $data->clinic->vendor_id)
                ->where('user_type', 'vendor')
                ->where('status', 1)
                ->first();

            // If the clinic's vendor_id points to a super admin, find the actual clinic admin for this clinic
            if (!$clinicAdmin) {
                // Look for users who are vendors and have this clinic assigned to them
                // Since there's no direct relationship, we'll check if any vendor has this clinic
                $clinicAdmin = \App\Models\User::where('user_type', 'vendor')
                    ->where('status', 1)
                    ->whereHas('doctor', function($query) use ($data) {
                        $query->whereHas('doctorclinic', function($q) use ($data) {
                            $q->where('clinic_id', $data->clinic->id);
                        });
                    })
                    ->first();

                // If still not found, check receptionist relationship
                if (!$clinicAdmin) {
                    $clinicAdmin = \App\Models\User::where('user_type', 'vendor')
                        ->where('status', 1)
                        ->whereHas('receptionist', function($query) use ($data) {
                            $query->where('clinic_id', $data->clinic->id);
                        })
                        ->first();
                }
            }
        }

        // Debug: Log the clinic admin info
        \Log::info('Clinic Admin Debug', [
            'clinic_id' => $data->clinic ? $data->clinic->id : null,
            'clinic_vendor_id' => $data->clinic ? $data->clinic->vendor_id : null,
            'clinic_admin_found' => $clinicAdmin ? true : false,
            'clinic_admin_id' => $clinicAdmin ? $clinicAdmin->id : null,
            'clinic_admin_name' => $clinicAdmin ? $clinicAdmin->first_name . ' ' . $clinicAdmin->last_name : null,
        ]);

        return response()->json(['data' => $data, 'status' => true]);
    }

    public function saveEncouterDetails(Request $request)
    {

        $encounter = PatientEncounter::where('id', $request->encounter_id)->first();

        $user_id      = $request->user_id;
        $encounter_id = $request->encounter_id;

        if ($encounter) {

            if ($request->filled('template_id') && $request->template_id != null) {

                $encounter->update(['encounter_template_id' => $request->template_id]);
            }

            if ($request->filled('other_details') && $request->other_details != null) {

                $other_details = [

                    'encounter_id'  => $encounter_id,
                    'user_id'       => $user_id,
                    'other_details' => $request->other_details,

                ];

                EncounterOtherDetails::updateOrCreate(
                    ['encounter_id' => $encounter_id, 'user_id' => $user_id],
                    $other_details
                );
            }

            EncouterMedicalHistroy::where('encounter_id', $encounter_id)->where('user_id', $user_id)->forceDelete();

            if ($request->filled('notesList') && $request->notesList != null) {

                foreach ($request->notesList as $notes) {

                    $notes_details = [

                        'encounter_id' => $encounter_id,
                        'user_id'      => $user_id,
                        'type'         => 'encounter_notes',
                        'title'        => $notes['title'],

                    ];

                    EncouterMedicalHistroy::create($notes_details);
                }
            }

            if ($request->filled('selectedObservationList') && $request->selectedObservationList != null) {

                foreach ($request->selectedObservationList as $observation) {

                    $observation_details = [

                        'encounter_id' => $encounter_id,
                        'user_id'      => $user_id,
                        'type'         => 'encounter_observations',
                        'title'        => $observation['title'],

                    ];

                    EncouterMedicalHistroy::create($observation_details);
                }
            }

            if ($request->filled('selectedproblemList') && $request->selectedproblemList != null) {

                foreach ($request->selectedproblemList as $problem) {

                    $problem_details = [

                        'encounter_id' => $encounter_id,
                        'user_id'      => $user_id,
                        'type'         => 'encounter_problem',
                        'title'        => $problem['title'],

                    ];

                    EncouterMedicalHistroy::create($problem_details);
                }
            }

            EncounterPrescription::where('encounter_id', $encounter_id)->where('user_id', $user_id)->forceDelete();

            if ($request->filled('prescriptionList') && $request->prescriptionList != null) {

                foreach ($request->prescriptionList as $prescription) {

                    $prescription = [

                        'encounter_id' => $encounter_id,
                        'user_id'      => $user_id,
                        'name'         => $prescription['name'],
                        'frequency'    => $prescription['frequency'],
                        'duration'     => $prescription['duration'],
                        'instruction'  => $prescription['instruction'],

                    ];

                    EncounterPrescription::create($prescription);
                }
            }

            $message = __('appointment.encounter_detail_save');

            return response()->json(['message' => $message, 'status' => true], 200);
        }
    }

    public function saveEncouter(Request $request)
    {
        $encounter    = PatientEncounter::where('id', $request->encounter_id)->first();
        $user_id      = $request->user_id;
        $encounter_id = $request->encounter_id;

        if ($encounter) {
            if ($request->filled('template_id') && $request->template_id != null) {
                $encounter->update(['encounter_template_id' => $request->template_id]);
            }

            if ($request->filled('other_details') && $request->other_details != null) {
                $other_details = [
                    'encounter_id'  => $encounter_id,
                    'user_id'       => $user_id,
                    'other_details' => $request->other_details,
                ];

                EncounterOtherDetails::updateOrCreate(
                    ['encounter_id' => $encounter_id, 'user_id' => $user_id],
                    $other_details
                );
            }

            if ($request->filled('template_id') && $request->template_id != null) {
                $templateId = $request->template_id;

                EncouterMedicalHistroy::where('encounter_id', $encounter_id)
                    ->where('user_id', $user_id)
                    ->forceDelete();

                $selectedEncouterMedicalHistroy = TemplateMedicalHistory::where('template_id', $templateId)->get();

                $selectedTemplatePrescription = TemplatePrescription::where('template_id', $templateId)->get();
                $selectedTemplateOtherDetails = TemplateOtherDetails::where('template_id', $templateId)->get();

                foreach ($selectedEncouterMedicalHistroy as $medicalHistory) {
                    if ($medicalHistory->type === 'encounter_problem') {
                        EncouterMedicalHistroy::create([
                            'encounter_id' => $encounter_id,
                            'user_id'      => $user_id,
                            'type'         => 'encounter_problem',
                            'title'        => $medicalHistory->title,
                        ]);
                    }

                    if ($medicalHistory->type === 'encounter_observations') {
                        EncouterMedicalHistroy::create([
                            'encounter_id' => $encounter_id,
                            'user_id'      => $user_id,
                            'type'         => 'encounter_observations',
                            'title'        => $medicalHistory->title,
                        ]);
                    }

                    if ($medicalHistory->type === 'encounter_notes') {
                        EncouterMedicalHistroy::create([
                            'encounter_id' => $encounter_id,
                            'user_id'      => $user_id,
                            'type'         => 'encounter_notes',
                            'title'        => $medicalHistory->title,
                        ]);
                    }
                }

                EncounterPrescription::where('encounter_id', $encounter_id)
                    ->where('user_id', $user_id)
                    ->forceDelete();

                foreach ($selectedTemplatePrescription as $prescription) {
                    EncounterPrescription::create([
                        'encounter_id' => $encounter_id,
                        'user_id'      => $user_id,
                        'name'         => $prescription->name,
                        'frequency'    => $prescription->frequency,
                        'duration'     => $prescription->duration,
                        'instruction'  => $prescription->instruction,
                    ]);
                }

                foreach ($selectedTemplateOtherDetails as $detail) {
                    EncounterOtherDetails::updateOrCreate(
                        ['encounter_id' => $encounter_id, 'user_id' => $user_id],
                        ['other_details' => $detail->other_details]
                    );
                }
            }

            $message = __('appointment.encounter_detail_save');
            return response()->json(['message' => $message, 'status' => true], 200);
        }

        return response()->json(['message' => __('appointment.encounter_not_found'), 'status' => false], 404);
    }

    public function saveSelectOption(Request $request)
    {

        if ($request->type == 'encounter_problem' || $request->type == 'encounter_observations') {

            $data = [

                'name'  => $request->name,
                'type'  => $request->type,
                'value' => str_replace(' ', '_', strtolower($request->name)),
            ];

            $constant = Constant::updateOrCreate(
                ['value' => $data['value'], 'type' => $data['type']],
                $data
            );
        }

        //    $query=Constant::create($data);

        // /return response()->json(['data' => $constant_data, 'last_selected_id'=>$query->id,'status'=>true]);

        $medical_histroy = [

            'encounter_id' => $request->encounter_id,
            'user_id'      => $request->user_id,
            'type'         => $request->type,
            'title'        => $request->name,
        ];

        $encounter_detail = EncouterMedicalHistroy::updateOrCreate(
            [
                'title'        => $request->name,
                'user_id'      => $request->user_id,
                'type'         => $request->type,
                'encounter_id' => $request->encounter_id,
            ],
            $medical_histroy
        );

        $constant_data = Constant::where('type', $request->type)->get();

        $medical_histroy = EncouterMedicalHistroy::where('encounter_id', $encounter_detail->encounter_id)->where('type', $encounter_detail->type)->get();

        return response()->json(['data' => $constant_data, 'medical_histroy' => $medical_histroy, 'status' => true]);
    }

    public function removeHistroyData(Request $request)
    {

        $id = $request->id;

        if ($id) {

            $encounter_details = EncouterMedicalHistroy::where('id', $id)->first();

            $encounter_id = $encounter_details->encounter_id;

            $encounter_details->forceDelete();

            $medical_histroy = EncouterMedicalHistroy::where('encounter_id', $encounter_id)->where('type', $request->type)->get();

            return response()->json(['medical_histroy' => $medical_histroy, 'status' => true]);
        }
    }

    public function destroy($id)
    {
        $data = PatientEncounter::findOrFail($id);

        $data->delete();

        $message = __('appointment.encounter_delete_successfully');

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    public function saveMultiplePrescriptions(Request $request)
    {
        $request_data = $request->only(['encounter_id', 'user_id', 'type']);
        $medicines = $request->input('medicines', []);

        $prescriptions = [];
        $pharma_id = $request->pharma ?? null; // just like SaveBillingData()
        foreach ($medicines as $medicineItem) {
            $data = $request_data; // base data
            $data = array_merge($data, $medicineItem); // add medicine-specific data
            $data['pharma_id'] = $pharma_id;


            if (checkPlugin('pharma') == 'active' && class_exists('Modules\Pharma\Models\Medicine')) {
                $medicine = \Modules\Pharma\Models\Medicine::find($medicineItem['medicine_id']);

                $data['name'] = $medicine ? $medicine->name . ' - ' . $medicine->dosage : null;

                $quantity = $medicineItem['quantity'] ?? 1;
                $selling_price = $medicine ? ($medicine->selling_price ?? 0) : 0;

                $medicine_price = $quantity * $selling_price;
                $data['medicine_price'] = $medicine_price;

                // Inclusive Tax Logic
                $inclusive_tax_amount = 0;
                $inclusiveTaxesArray = [];

                if ($medicine && $medicine->is_inclusive_tax == 1) {
                    $inclusiveTaxes = Tax::where([
                        'category'    => 'medicine',
                        'tax_type'    => 'inclusive',
                        'module_type' => 'medicine',
                        'status'      => 1
                    ])->get();

                    foreach ($inclusiveTaxes as $tax) {
                        $taxPerUnit = $tax->type === 'percent'
                            ? ($selling_price * $tax->value) / 100
                            : $tax->value;

                        $taxTotal = $taxPerUnit * $quantity;
                        $inclusive_tax_amount += $taxTotal;

                        $taxData = $tax->toArray();
                        $taxData['amount'] = round($taxTotal, 2);
                        $inclusiveTaxesArray[] = $taxData;
                    }

                    $data['inclusive_tax'] = json_encode($inclusiveTaxesArray);
                }

                $data['inclusive_tax_amount'] = $inclusive_tax_amount;
                $data['total_amount'] = $medicine_price + $inclusive_tax_amount;
                if ($medicine) {
                    $medicineNames[] =  $data['name'];
                }
            }

            $prescriptions[] = EncounterPrescription::create($data);
        }

        if ($pharma_id && count($medicineNames) > 0) {
            sendNotification([
                'notification_type' => 'add_prescription',
                'pharma_id'         => $pharma_id,
                'encounter_id'      => $request_data['encounter_id'], // or parent prescription id if exists
                'medicine_name'     => implode(', ', $medicineNames), // all medicine names
                'user_id'           => $request_data['user_id'],
                'prescription'      => $prescriptions, // pass all if needed
                'prescription_id'   => $prescriptions[0]->id,
            ]);
        }
        $encounterId = $request_data['encounter_id'];
        $subtotal = EncounterPrescription::where('encounter_id', $encounterId)
            ->sum(\DB::raw('medicine_price + inclusive_tax_amount'));

        // Exclusive Tax
        $exclusiveTaxAmount = 0;
        $exclusiveTaxesArray = [];
        $exclusiveTaxes = Tax::where([
            'category' => 'medicine',
            'status'   => 1,
            'tax_type' => 'exclusive'
        ])->get();

        foreach ($exclusiveTaxes as $tax) {
            $taxAmount = $tax->type === 'percent'
                ? round(($subtotal * $tax->value) / 100, 2)
                : round($tax->value, 2);

            $exclusiveTaxAmount += $taxAmount;

            $taxData = $tax->toArray();
            $taxData['amount'] = $taxAmount;
            $exclusiveTaxesArray[] = $taxData;
        }

        $grandTotal = $subtotal + $exclusiveTaxAmount;

        if ($pharma_id && $encounterId) {
            BillingRecord::updateOrCreate(
                ['encounter_id' => $encounterId],
                ['pharma_id' => $pharma_id]
            );
        }
        $encounter = PatientEncounter::where('id', $encounterId)->first();
        if ($encounter) {
            $encounter->pharma_id = $pharma_id;
            $encounter->save();
        }
        // dd($encounter);

        // Update billing
        EncounterPrescriptionBillingDetail::updateOrCreate(
            ['encounter_id' => $encounterId],
            [
                'exclusive_tax'        => json_encode($exclusiveTaxesArray),
                'exclusive_tax_amount' => $exclusiveTaxAmount,
                'total_amount'         => $grandTotal,
            ]
        );

        // Response
        if ($request->is('api/*')) {
            return response()->json([
                'message' => __('appointment.encounter_prescription_save'),
                'data'    => PrescriptionRescource::collection($prescriptions),
                'status'  => true
            ], 200);
        }

        $data = PatientEncounter::with('user', 'prescriptions')->find($encounterId);

        // Only use prescription_table view if pharma plugin is active
        if (checkPlugin('pharma') == 'active') {
            $html = $data
                ? view('appointment::backend.patient_encounter.component.prescription_table', compact('data'))->render()
                : '';
        } else {
            $html = $data
                ? view('appointment::backend.patient_encounter.component.prescription_without_pharma_table', compact('data'))->render()
                : '';
        }

        return response()->json(['html' => $html, 'count' => count($prescriptions)]);
    }


    // public function editPrescription($id)
    // {
    //     $prescription = EncounterPrescription::with('medicine')->find($id);

    //     $response = $prescription->toArray();
    //     $medicineName = $prescription->medicine->name ?? '';
    //     $dosage = $prescription->medicine->dosage ?? ''; // Adjust field name if needed

    //     $response['name'] = trim($medicineName . ' - ' . $dosage);

    //     return response()->json(['data' => $response, 'status' => true]);
    // }

    public function editPrescription($encounterId)
    {
        // dd($request->all());
        $prescriptionsQuery = EncounterPrescription::where('encounter_id', $encounterId);

        // Only eager load medicine relationship if Medicine class exists
        if (class_exists('Modules\Pharma\Models\Medicine')) {
            $prescriptionsQuery->with('medicine');
        }

        $prescriptions = $prescriptionsQuery->get();

        if ($prescriptions->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No medicine data found for this prescription']);
        }


        $response = [];

        foreach ($prescriptions as $prescription) {
            $medicineName = checkPlugin('pharma') == 'active' ? ($prescription->medicine->name ?? '') : $prescription->name;
            $dosage       = $prescription->medicine->dosage ?? '';

            $item = $prescription->toArray();
            $item['name'] = checkPlugin('pharma') == 'active' ? trim($medicineName . ' - ' . $dosage) : $medicineName;
            $item['pharma_id'] = $prescription->pharma_id;
            $response[] = $item;
        }

        // dd($item['pharma_id']);

        return response()->json(['data' => $response, 'status' => true]);
    }


    //     public function updatePrescription(Request $request, $id)
    //     {
    //         dd($request->all());
    //         try {
    //             $prescription = EncounterPrescription::findOrFail($id);
    //             // Validate that medicines array exists and has data
    //             if (!$request->has('medicines') || empty($request->medicines)) {
    //                 return response()->json(['error' => 'No medicine data provided'], 400);
    //             }

    //             $medicineData = $request->medicines[$prescription->id] ?? null;

    // if (!$medicineData) {
    //     return response()->json(['error' => 'No medicine data found for prescription ID: ' . $prescription->id], 400);
    // }

    //             $medicine = \Modules\Pharma\Models\Medicine::findOrFail($medicineData['medicine_id']);

    //             $prescriptionData = [
    //                 'medicine_id' => $medicineData['medicine_id'],
    //                 'name' => $medicine->name . ' - ' . ($medicine->dosage ?? ''),
    //                 'quantity' => $medicineData['quantity'] ?? 1,
    //                 'frequency' => $medicineData['frequency'] ?? null,
    //                 'duration' => $medicineData['duration'] ?? null,
    //                 'instruction' => $medicineData['instruction'] ?? null,
    //             ];

    //             $quantity = $prescriptionData['quantity'];
    //             $selling_price = $medicine->selling_price ?? 0;
    //             $medicine_price = $quantity * $selling_price;
    //             $prescriptionData['medicine_price'] = $medicine_price;

    //             // Inclusive Tax Calculation
    //             $inclusive_tax_amount = 0;
    //             $inclusiveTaxesArray = [];

    //             if ($medicine->is_inclusive_tax == 1) {
    //                 $inclusiveTaxes = Tax::where([
    //                     'category' => 'medicine',
    //                     'tax_type' => 'inclusive',
    //                     'module_type' => 'medicine',
    //                     'status' => 1,
    //                 ])->get();

    //                 foreach ($inclusiveTaxes as $tax) {
    //                     $taxPerUnit = $tax->type === 'percent'
    //                         ? ($selling_price * $tax->value) / 100
    //                         : $tax->value;

    //                     $taxTotal = $taxPerUnit * $quantity;
    //                     $inclusive_tax_amount += $taxTotal;

    //                     $taxData = $tax->toArray();
    //                     $taxData['amount'] = round($taxTotal, 2);
    //                     $inclusiveTaxesArray[] = $taxData;
    //                 }
    //             }

    //             $prescriptionData['inclusive_tax'] = json_encode($inclusiveTaxesArray);
    //             $prescriptionData['inclusive_tax_amount'] = $inclusive_tax_amount;
    //             $prescriptionData['total_amount'] = $medicine_price + $inclusive_tax_amount;

    //             // Debug: Log the data being updated
    //             // \Log::info('Updating prescription with data:', $prescriptionData);

    //             // Update the prescription
    //             $updated = $prescription->update($prescriptionData);

    //             // Debug: Check if update was successful
    //             if (!$updated) {
    //                 \Log::error('Failed to update prescription with ID: ' . $id);
    //                 return response()->json(['error' => 'Failed to update prescription'], 500);
    //             }

    //             // Refresh the model to get updated data
    //             $prescription->refresh();

    //             // \Log::info('Prescription updated successfully:', $prescription->toArray());

    //             // Constant Save (if needed for your form fields)
    //             if ($request->has('name') && $request->has('type')) {
    //                 $data_value = [
    //                     'name' => $request->name,
    //                     'type' => $request->type,
    //                     'value' => str_replace(' ', '_', strtolower($request->name)),
    //                 ];

    //                 Constant::updateOrCreate(
    //                     ['value' => $data_value['value'], 'type' => $data_value['type']],
    //                     $data_value
    //                 );
    //             }

    //             $encounterId = $prescription->encounter_id;

    //             // Recalculate subtotal
    //             $totalMedicines = EncounterPrescription::where('encounter_id', $encounterId)
    //                 ->selectRaw('SUM(medicine_price + inclusive_tax_amount) as subtotal')
    //                 ->first();

    //             $subtotal = $totalMedicines->subtotal ?? 0;

    //             // Exclusive Tax Calculation
    //             $exclusiveTaxAmount = 0;
    //             $exclusiveTaxesArray = [];

    //             $exclusiveTaxes = Tax::where([
    //                 'category' => 'medicine',
    //                 'status' => 1,
    //                 'tax_type' => 'exclusive',
    //             ])->get();

    //             foreach ($exclusiveTaxes as $tax) {
    //                 $taxAmount = $tax->type === 'percent'
    //                     ? ($subtotal * $tax->value) / 100
    //                     : $tax->value;

    //                 $exclusiveTaxAmount += $taxAmount;

    //                 $taxData = $tax->toArray();
    //                 $taxData['amount'] = round($taxAmount, 2);
    //                 $exclusiveTaxesArray[] = $taxData;
    //             }

    //             $grandTotal = $subtotal + $exclusiveTaxAmount;

    //             // Save billing
    //             EncounterPrescriptionBillingDetail::updateOrCreate(
    //                 ['encounter_id' => $encounterId],
    //                 [
    //                     'exclusive_tax' => json_encode($exclusiveTaxesArray),
    //                     'exclusive_tax_amount' => $exclusiveTaxAmount,
    //                     'total_amount' => $grandTotal,
    //                 ]
    //             );

    //             // Response
    //             if ($request->is('api/*')) {
    //                 $message = __('appointment.encounter_prescription_update');
    //                 return response()->json([
    //                     'message' => $message,
    //                     'data' => new PrescriptionRescource($prescription),
    //                     'status' => true,
    //                 ], 200);
    //             } else {
    //                 $data = PatientEncounter::with('user', 'prescriptions')->find($encounterId);
    //                 $html = !empty($data)
    //                     ? view('appointment::backend.patient_encounter.component.prescription_table', ['data' => $data])->render()
    //                     : '';

    //                 return response()->json(['html' => $html]);
    //             }
    //         } catch (\Exception $e) {
    //             // \Log::error('Error updating prescription: ' . $e->getMessage());
    //             return response()->json(['error' => 'An error occurred while updating prescription'], 500);
    //         }
    //     }

    public function updatePrescription(Request $request, $encounterId)
    {
        try {
            $pharma_id = $request->pharma ?? null;

            // Validate that medicines array exists and has data
            if (!$request->has('medicines') || empty($request->medicines)) {
                return response()->json(['error' => 'No medicine data provided'], 400);
            }

            $existingIds = []; // Store existing updated/newly inserted prescription IDs

            foreach ($request->medicines as $medicineData) {
                if (!isset($medicineData['medicine_id'])) {
                    continue;
                }

                if (!class_exists('Modules\Pharma\Models\Medicine')) {
                    return response()->json(['error' => 'Pharma module is not available'], 400);
                }

                $medicine = \Modules\Pharma\Models\Medicine::findOrFail($medicineData['medicine_id']);

                $quantity = $medicineData['quantity'] ?? 1;
                $selling_price = $medicine->selling_price ?? 0;
                $medicine_price = $quantity * $selling_price;

                // Inclusive Tax Calculation
                $inclusive_tax_amount = 0;
                $inclusiveTaxesArray = [];
                $encounter = PatientEncounter::findOrFail($encounterId);
                $patientUserId = $encounter->user_id;

                if ($medicine->is_inclusive_tax == 1) {
                    $inclusiveTaxes = Tax::where([
                        'category' => 'medicine',
                        'tax_type' => 'inclusive',
                        'module_type' => 'medicine',
                        'status' => 1,
                    ])->get();
                    foreach ($inclusiveTaxes as $tax) {
                        $taxPerUnit = $tax->type === 'percent'
                            ? ($selling_price * $tax->value) / 100
                            : $tax->value;

                        $taxTotal = $taxPerUnit * $quantity;
                        $inclusive_tax_amount += $taxTotal;

                        $taxData = $tax->toArray();
                        $taxData['amount'] = round($taxTotal, 2);
                        $inclusiveTaxesArray[] = $taxData;
                    }
                }

                $prescriptionData = [
                    'encounter_id' => $encounterId,
                    'medicine_id' => $medicineData['medicine_id'],
                    'name' => $medicine->name . ' - ' . ($medicine->dosage ?? ''),
                    'quantity' => $quantity,
                    'frequency' => $medicineData['frequency'] ?? null,
                    'duration' => $medicineData['duration'] ?? null,
                    'instruction' => $medicineData['instruction'] ?? null,
                    'medicine_price' => $medicine_price,
                    'inclusive_tax' => json_encode($inclusiveTaxesArray),
                    'inclusive_tax_amount' => $inclusive_tax_amount,
                    'total_amount' => $medicine_price + $inclusive_tax_amount,
                ];

                // Update if id exists, else create
                if (!empty($medicineData['id'])) {
                    $prescription = EncounterPrescription::find($medicineData['id']);
                    if ($prescription) {
                        $prescriptionData['updated_by'] = auth()->id(); // ✅ Add updated_by if needed
                        $prescriptionData['user_id'] = $prescription->user_id ?? auth()->id(); // ✅ Retain existing or fallback
                        $prescription->update($prescriptionData);
                        $existingIds[] = $prescription->id;
                    }
                } else {
                    $prescriptionData['user_id'] = $patientUserId; // ✅ Correctly assign patient
                    $prescriptionData['created_by'] = auth()->id(); // Optional tracking
                    $created = EncounterPrescription::create($prescriptionData);
                    $existingIds[] = $created->id;
                }
                if ($medicine) {
                    $medicineNames[] = $prescriptionData['name'];
                }
            }
            if ($pharma_id && count($medicineNames) > 0) {
                sendNotification([
                    'notification_type' => 'add_prescription',
                    'pharma_id'         => $pharma_id,
                    'encounter_id'   => $encounterId, // or parent prescription id if exists
                    'medicine_name'     => implode(', ', $medicineNames), // all medicine names
                    'user_id'           => $prescriptionData['user_id'],
                    'prescription'     => $prescriptionData, // pass all if needed
                    'prescription_id'  => reset($existingIds),
                ]);
            }

            // Delete prescriptions not in submitted list
            EncounterPrescription::where('encounter_id', $encounterId)
                ->whereNotIn('id', $existingIds)
                ->delete();

            $billingRecord = BillingRecord::where('encounter_id', $encounterId)->first();

            if ($billingRecord) {
                $billingRecord->pharma_id = $pharma_id;
                $billingRecord->save();
            }
            // dd($billingRecord);

            if ($pharma_id && $encounterId) {
                $encounter = PatientEncounter::where('id', $encounterId)->first();

                if ($encounter) {
                    $encounter->pharma_id = $pharma_id;
                    $encounter->save();
                }
            }

            // dd($encounter);


            $totalMedicines = EncounterPrescription::where('encounter_id', $encounterId)
                ->selectRaw('SUM(medicine_price + inclusive_tax_amount) as subtotal')
                ->first();

            $subtotal = $totalMedicines->subtotal ?? 0;

            $exclusiveTaxAmount = 0;
            $exclusiveTaxesArray = [];

            $exclusiveTaxes = Tax::where([
                'category' => 'medicine',
                'status' => 1,
                'tax_type' => 'exclusive',
            ])->get();

            foreach ($exclusiveTaxes as $tax) {
                $taxAmount = $tax->type === 'percent'
                    ? ($subtotal * $tax->value) / 100
                    : $tax->value;

                $exclusiveTaxAmount += $taxAmount;

                $taxData = $tax->toArray();
                $taxData['amount'] = round($taxAmount, 2);
                $exclusiveTaxesArray[] = $taxData;
            }

            $grandTotal = $subtotal + $exclusiveTaxAmount;

            EncounterPrescriptionBillingDetail::updateOrCreate(
                ['encounter_id' => $encounterId],
                [
                    'exclusive_tax' => json_encode($exclusiveTaxesArray),
                    'exclusive_tax_amount' => $exclusiveTaxAmount,
                    'total_amount' => $grandTotal,
                ]
            );

            // Final Response
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => __('appointment.encounter_prescription_update'),
                    'status' => true,
                ]);
            } else {
                $data = PatientEncounter::with('user', 'prescriptions')->find($encounterId);
                $html = !empty($data)
                    ? view('appointment::backend.patient_encounter.component.prescription_table', ['data' => $data])->render()
                    : '';

                return response()->json(['html' => $html]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating prescription: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while updating prescription'], 500);
        }
    }



    public function deletePrescription(Request $request, $id)
    {

        $prescription = EncounterPrescription::where('id', $id)->first();

        $encounter_id = $prescription->encounter_id;

        $prescription->forceDelete();

        if ($request->is('api/*')) {

            $message = __('appointment.prescription_delete');

            return response()->json(['message' => $message, 'status' => true], 200);
        } else {

            $data = PatientEncounter::where('id', $encounter_id)->with('user', 'prescriptions')->first();

            if (! empty($data)) {

                $html = view('appointment::backend.patient_encounter.component.prescription_table', ['data' => $data])->render();
            }

            return response()->json(['html' => $html]);
        }
    }
    public function bulkDeletePrescriptions(Request $request)
    {
        $ids = $request->input('prescription_ids');
        $prescriptionIds = $request->input('prescription_ids', []);
        // dd($request->all());
        $prescription = EncounterPrescription::where('id', $ids[0])->first();
        $encounter_id = $prescription->encounter_id;
        EncounterPrescription::whereIn('id', $prescriptionIds)->forceDelete();

        if ($request->is('api/*')) {

            $message = __('appointment.prescription_delete');

            return response()->json(['message' => $message, 'status' => true], 200);
        } else {

            $data = PatientEncounter::where('id', $encounter_id)
                ->with('user', 'prescriptions')
                ->first();
            // dd($data);
            $html = view('appointment::backend.patient_encounter.component.prescription_table', ['data' => $data])->render();

            return response()->json(['status' => true, 'html' => $html]);
        }
    }



    public function saveOtherDetails(Request $request)
    {

        $request_data = $request->all();

        $data = [

            'other_details' => $request->other_details,
            'encounter_id'  => $request->encounter_id,
            'user_id'       => $request->user_id,
        ];

        $otherDetails = EncounterOtherDetails::updateOrCreate(
            ['encounter_id' => $data['encounter_id'], 'user_id' => $data['user_id']],
            $data
        );

        return response()->json(['otherDetails' => $otherDetails, 'status' => true]);
    }

    public function saveMedicalReport(Request $request)
    {

        $data = $request->all();

        $html = '';

        $medical_report = EncounterMedicalReport::create($data);

        if ($request->hasFile('file_url')) {
            storeMediaFile($medical_report, $request->file('file_url'));
        }

        if ($request->is('api/*')) {

            $medical_report = new MedicalReportRescource($medical_report);

            $message = __('appointment.medical_report_save');
            return response()->json(['message' => $message, 'data' => $medical_report, 'status' => true], 200);
        } else {

            $data = PatientEncounter::where('id', $data['encounter_id'])->with('user', 'medicalReport')->first();

            if (! empty($data)) {

                $html = view('appointment::backend.patient_encounter.component.medical_report_table', ['data' => $data])->render();
            }

            return response()->json(['html' => $html]);
        }
    }

    public function editMedicalReport(Request $request, $id)
    {

        $medical_report = EncounterMedicalReport::where('id', $id)->first();

        return response()->json(['data' => $medical_report, 'status' => true]);
    }

    public function updateMedicalReport(Request $request, $id)
    {

        $data = $request->all();

        $html = '';

        $medical_report = EncounterMedicalReport::where('id', $id)->first();

        $medical_report->update($data);

        if ($request->hasFile('file_url')) {

            storeMediaFile($medical_report, $request->file('file_url'));
        }

        if ($request->is('api/*')) {

            $medical_report = new MedicalReportRescource($medical_report);

            $message = __('appointment.medical_report_update');

            return response()->json(['message' => $message, 'data' => $medical_report, 'status' => true], 200);
        } else {

            $data = PatientEncounter::where('id', $data['encounter_id'])->with('user', 'medicalReport')->first();

            if (! empty($data)) {

                $html = view('appointment::backend.patient_encounter.component.medical_report_table', ['data' => $data])->render();
            }

            return response()->json(['html' => $html]);
        }
    }

    public function deleteMedicalReport(Request $request, $id)
    {

        $medical_report = EncounterMedicalReport::where('id', $id)->first();

        $encounter_id = $medical_report->encounter_id;
        $html         = '';

        $medical_report->forceDelete();

        if ($request->is('api/*')) {

            $message = __('appointment.medical_report_delete');

            return response()->json(['message' => $message, 'status' => true], 200);
        } else {

            $data = PatientEncounter::where('id', $encounter_id)->with('user', 'medicalReport')->first();

            if (! empty($data)) {

                $html = view('appointment::backend.patient_encounter.component.medical_report_table', ['data' => $data])->render();
            }

            return response()->json(['html' => $html]);
        }
    }

    public function GetReportData(Request $request)
    {

        $encounter_id = $request->encounter_id;

        $medical_report = EncounterMedicalReport::where('encounter_id', $encounter_id)->get();

        return response()->json(['medical_report' => $medical_report, 'status' => true]);
    }

    public function SendMedicalReport(Request $request)
    {

        $encounter_id = $request->id;

        $data = PatientEncounter::where('id', $encounter_id)->first();

        $user_id = $data->user_id;

        $user = User::where('id', $user_id)->first();

        $medicalReport = EncounterMedicalReport::where('id', $data['report_id'])->first();

        if ($user && $medicalReport) {

            $filePath = $medicalReport->file_url;

            Mail::to($user->email)->send(new MedicalReportEmail($medicalReport, $filePath));
            $message = __('appointment.medical_report_send');
            return response()->json(['message' => $message, 'status' => true]);
        } else {
            $message = __('appointment.something_wrong');
            return response()->json(['message' => $message, 'status' => false]);
        }
    }

    public function sendPrescription(Request $request)
    {

        $encounter_id = $request->id;

        $data = PatientEncounter::where('id', $encounter_id)->first();

        $user_id = $data->user_id;

        $user = User::where('id', $user_id)->first();

        $prescriptionList = EncounterPrescription::where('encounter_id', $encounter_id)->get();

        if ($user && $prescriptionList) {

            Mail::to($user->email)->send(new PrescriptionListMail($prescriptionList));
            $message = __('appointment.prescription_send');
            return response()->json(['message' => $message, 'status' => true]);
        } else {
            $message = __('appointment.something_wrong');
            return response()->json(['message' => $message, 'status' => false]);
        }
    }

    public function importPrescription(Request $request)
    {
        $file = $request->file('file_url');

        if (! $file->isValid()) {
            return response()->json(['error' => 'Invalid file', 'status' => false]);
        }

        if (! in_array($file->getClientOriginalExtension(), ['csv', 'xlsx'])) {
            return response()->json(['error' => 'Invalid file type', 'status' => false], 400);
        }

        if ($file->getClientOriginalExtension() === 'csv') {
            $records = $this->importCsv($file, $request->user_id, $request->encounter_id);
        } elseif ($file->getClientOriginalExtension() === 'xlsx') {

            Excel::import(new PrescriptionImport($request->user_id, $request->encounter_id), $file);
        }

        $prescription = EncounterPrescription::where('encounter_id', $request->encounter_id)->get();

        return response()->json(['prescription' => $prescription, 'status' => true]);
    }

    public function exportPrescriptionData(Request $request)
    {

        $data = $request->all();

        $type = $data['type'];

        $encounter_id = $data['encounter_id'];

        $prescriptionList = EncounterPrescription::where('encounter_id', $encounter_id)->get(['id', 'name', 'frequency', 'duration', 'instruction']);

        switch ($type) {
            case 'pdf':

                $pdf = PDF::loadView('pdf.prescripcription', ['prescriptionList' => $prescriptionList]);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->download('prescripcription.pdf');

                break;
            case 'csv':

                $csvContent = "ID,Name,Frequency,Duration,Instruction\n";
                $csvContent .= "2,test1,34,1,efergr\n";

                $headers = [
                    'Content-Disposition' => 'attachment; filename="test.csv"',
                    'Content-Type'        => 'application/json',
                    'Accept'              => 'application/json',
                ];

                return Response::make($csvContent, 200, $headers);

                return Response::make($csvContent, 200, $headers);

                break;
            case 'xlsx':
                return $this->exportXLSX($encounter_id);
                break;
            default:
                return redirect()->back()->with('error', 'Unsupported file format.');
        }
    }

    protected function importCsv($file, $user_id, $encounter_id)
    {
        $filePath = $file->getRealPath();
        $csv      = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        $records = [];

        foreach ($csv->getRecords() as $record) {
            $file_record = [
                'user_id'      => $user_id,
                'encounter_id' => $encounter_id,
                'name'         => $record['name'],
                'frequency'    => $record['frequency'],
                'duration'     => $record['duration'],
                'instruction'  => $record['instruction'],
            ];

            EncounterPrescription::create($file_record);

            $constant_record = [

                'type'  => 'encounter_prescription',
                'name'  => $record['name'],
                'value' => str_replace(' ', '_', $record['name']),

            ];

            $constant = Constant::updateOrCreate(
                ['value' => $constant_record['value'], 'type' => $constant_record['type']],
                $constant_record
            );

            $records[] = $file_record;
        }

        return $records;
    }

    public function EncouterDetailPage(Request $request, $id)
    {

        $module_title = "Encounter Dashboard";

        $data = PatientEncounter::where('id', $id)->with('user', 'user.cities', 'user.countries', 'clinic', 'doctor', 'medicalHistroy', 'prescriptions', 'EncounterOtherDetails', 'medicalReport', 'appointmentdetail', 'billingrecord')->first();
            if (!$data) {
            return redirect()->route('backend.appointments.index')
                ->with('error', 'Encounter not found or has been deleted.');
        }

        $data['selectedProblemList']     = $data->medicalHistroy()->where('type', 'encounter_problem')->get();
        $data['selectedObservationList'] = $data->medicalHistroy()->where('type', 'encounter_observations')->get();
        $data['notesList']               = $data->medicalHistroy()->where('type', 'encounter_notes')->get();
        $data['prescriptions']           = $data->prescriptions()->get();
        $data['other_details']           = $data->EncounterOtherDetails()->value('other_details') ?? null;
        $data['medicalReport']           = $data->medicalReport()->get();
        $data['signature']               = optional(optional($data->doctor)->doctor)->Signature ?? null;
        $data['appointment_status']      = $data->appointmentdetail->status ?? null;
        $data['payment_status']          = $data->appointmentdetail->appointmenttransaction->payment_status ?? null;
        $data['billingrecord']           = $data->billingrecord ?? null;
        $data['billingrecord_payment']   = $data->billingrecord->payment_status ?? null;
        $data['customform']              = CustomForm::where('module_type', 'appointment_module')
            ->where('status', 1)
            ->get()
            ->filter(function ($item) {
                $showInArray = json_decode($item->show_in, true);
                return in_array('encounter', $showInArray);
            });
        $template_data = EncounterTemplate::with('templateMedicalHistroy', 'templatePrescriptions', 'TemplateOtherDetails')->Where('status', 1)->get();

        $encounter_data = encounter();

        $problem_list      = Constant::where('type', 'encounter_problem')->get();
        $observation_list  = Constant::where('type', 'encounter_observations')->get();
        $prescription_list = EncounterPrescription::all()->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
            ];
        })->toArray();

        $labelMap = [
            'razor_payment_method'      => 'Razorpay',
            'str_payment_method'        => 'Stripe',
            'paystack_payment_method'   => 'Paystack',
            'paypal_payment_method'     => 'PayPal',
            'flutterwave_payment_method' => 'Flutterwave',
            'airtel_payment_method'     => 'Airtel Money',
            'phonepay_payment_method'   => 'PhonePe',
            'midtrans_payment_method'   => 'Midtrans',
            'cinet_payment_method'      => 'Cinet',
            'sadad_payment_method'      => 'Sadad',
        ];

        $paymentMethod = Setting::select('name as value')
            ->where('name', 'LIKE', '%_payment_method')
            ->where('val', 1)
            ->get()
            ->map(function ($item) use ($labelMap) {
                $item->label = $labelMap[$item->value] ?? ucfirst(str_replace('_payment_method', '', str_replace('_', ' ', $item->value)));
                return $item;
            });


        // dd($paymentMethod);
        $billingDetail = BillingRecord::where('encounter_id', $id)->first();
        // dd($billingDetail->payment_method);
     $bedTypes = BedType::pluck('type', 'id');

        // Get all beds grouped by bed_type_id
        $beds = BedMaster::pluck('bed', 'id');

        // Get clinic admin based on the encounter's clinic
        $clinicAdmin = null;
        if ($data->clinic) {
            // Get the clinic admin for this specific clinic
            $clinicAdmin = \App\Models\User::where('id', $data->clinic->vendor_id)
                ->where('user_type', 'vendor')
                ->where('status', 1)
                ->first();

            // If the clinic's vendor_id points to a super admin, find the actual clinic admin for this clinic
            if (!$clinicAdmin) {
                // Look for users who are vendors and have this clinic assigned to them
                // Since there's no direct relationship, we'll check if any vendor has this clinic
                $clinicAdmin = \App\Models\User::where('user_type', 'vendor')
                    ->where('status', 1)
                    ->whereHas('doctor', function($query) use ($data) {
                        $query->whereHas('doctorclinic', function($q) use ($data) {
                            $q->where('clinic_id', $data->clinic->id);
                        });
                    })
                    ->first();

                // If still not found, check receptionist relationship
                if (!$clinicAdmin) {
                    $clinicAdmin = \App\Models\User::where('user_type', 'vendor')
                        ->where('status', 1)
                        ->whereHas('receptionist', function($query) use ($data) {
                            $query->where('clinic_id', $data->clinic->id);
                        })
                        ->first();
                }
            }
        }

        // Debug: Log the clinic admin info
        \Log::info('Clinic Admin Debug', [
            'clinic_id' => $data->clinic ? $data->clinic->id : null,
            'clinic_vendor_id' => $data->clinic ? $data->clinic->vendor_id : null,
            'clinic_admin_found' => $clinicAdmin ? true : false,
            'clinic_admin_id' => $clinicAdmin ? $clinicAdmin->id : null,
            'clinic_admin_name' => $clinicAdmin ? $clinicAdmin->first_name . ' ' . $clinicAdmin->last_name : null,
        ]);

        // Get all users with user_type 'vendor' for clinic admin dropdown
        $clinicAdmins = \App\Models\User::where('user_type', 'vendor')
            ->where('status', 1)
            ->get()
            ->mapWithKeys(function ($user) {
                return [$user->id => $user->first_name . ' ' . $user->last_name];
            });

        // Patient encounters will be loaded dynamically based on selected clinic
        $patientEncounters = collect([]);

        // Fetch bed allocations ONLY for this specific encounter
        // Only show beds that are allocated to this encounter_id (NOT by patient_id)
        $bedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $data->id)
            ->whereNotNull('encounter_id') // Ensure encounter_id is not null
            ->whereNull('deleted_at') // Exclude soft-deleted allocations
            ->with(['patient', 'bedMaster.bedType', 'bedType'])
            ->orderBy('assign_date', 'desc')
            ->get();
        
        // Additional safety check: filter out any allocations that don't match the encounter_id
        $bedAllocations = $bedAllocations->filter(function($allocation) use ($data) {
            return $allocation->encounter_id == $data->id;
        })->values();

        // Debug logging
        \Log::info('Bed Allocations Debug for Encounter', [
            'encounter_id' => $data->id,
            'patient_id' => $data->user_id ?? null,
            'bed_allocations_count' => $bedAllocations->count(),
            'bed_allocations' => $bedAllocations->map(function($allocation) {
                return [
                    'id' => $allocation->id,
                    'encounter_id' => $allocation->encounter_id,
                    'patient_id' => $allocation->patient_id,
                    'bed_master_id' => $allocation->bed_master_id,
                    'assign_date' => $allocation->assign_date,
                    'discharge_date' => $allocation->discharge_date,
                ];
            })->toArray(),
        ]);


         return view('appointment::backend.patient_encounter.encounter_detail_page', compact('module_title','data','template_data','encounter_data','problem_list','observation_list','prescription_list','bedTypes','beds','bedAllocations','clinicAdmins','patientEncounters','clinicAdmin','paymentMethod','billingDetail'));

    }
    // public function getTemplateData($templateId, Request $request)
    // {
    //     $selectedEncouterMedicalHistroy = TemplateMedicalHistory::where('template_id', $templateId)->get();
    //     $selectedTemplatePrescription   = TemplatePrescription::where('template_id', $templateId)->get();
    //     $selectedTemplateOtherDetails   = TemplateOtherDetails::where('template_id', $templateId)->get();
    //     $problem_list                   = Constant::where('type', 'encounter_problem')->get();
    //     $observation_list               = Constant::where('type', 'encounter_observations')->get();

    //     if ($selectedEncouterMedicalHistroy->isNotEmpty() || $selectedTemplatePrescription->isNotEmpty()) {
    //         $problemHtml      = '';
    //         $observationHtml  = '';
    //         $noteHtml         = '';
    //         $PrescriptionHtml = '';
    //         $otherdetailHtml  = '';
    //         // Iterate through the collection
    //         foreach ($selectedEncouterMedicalHistroy as $medicalHistory) {
    //             if ($medicalHistory->type === 'encounter_problem') {
    //                 // Generate problem HTML using a Blade view
    //                 $problemHtml = view('appointment::backend.patient_encounter.component.encounter_problem', [
    //                     'data'         => [
    //                         'id'                  => $request->encounter_id ?? '',
    //                         'user_id'             => $request->user_id ?? '',
    //                         'status'              => $request->status ?? '0',
    //                         'selectedProblemList' => $selectedEncouterMedicalHistroy->where('type', 'encounter_problem'),
    //                     ],
    //                     'problem_list' => $problem_list,
    //                 ])->render();
    //             }

    //             if ($medicalHistory->type === 'encounter_observations') {
    //                 // Generate observation HTML using a Blade view

    //                 $observationHtml = view('appointment::backend.patient_encounter.component.encounter_observation', [
    //                     'data'             => [
    //                         'id'                      => $request->encounter_id ?? '',
    //                         'user_id'                 => $request->user_id ?? '',
    //                         'status'                  => $request->status ?? '0',
    //                         'selectedObservationList' => $selectedEncouterMedicalHistroy->where('type', 'encounter_observations'),
    //                     ],
    //                     'observation_list' => $observation_list,
    //                 ])->render();
    //             }

    //             if ($medicalHistory->type === 'encounter_notes') {
    //                 // Generate note HTML using a Blade view
    //                 $noteHtml = view('appointment::backend.patient_encounter.component.encounter_note', [
    //                     'data' => [
    //                         'id'        => $request->encounter_id ?? '',
    //                         'user_id'   => $request->user_id ?? '',
    //                         'status'    => $request->status ?? '0',
    //                         'notesList' => $selectedEncouterMedicalHistroy->where('type', 'encounter_notes'),
    //                     ],

    //                 ])->render();
    //             }
    //         }

    //         foreach ($selectedTemplatePrescription as $TemplatePrescription) {
    //             if ($TemplatePrescription !== null) {
    //                 // Generate problem HTML using a Blade view
    //                 $PrescriptionHtml = view('appointment::backend.patient_encounter.component.prescription_table', [
    //                     'data' => [
    //                         'id'            => $request->encounter_id ?? '',
    //                         'user_id'       => $request->user_id ?? '',
    //                         'status'        => $request->status ?? '0',
    //                         'prescriptions' => $selectedTemplatePrescription,
    //                     ],

    //                 ])->render();
    //             }
    //         }

    //         foreach ($selectedTemplateOtherDetails as $detail) {
    //             $otherdetailHtml = $detail->other_details;
    //         }

    //         // Return response as JSON
    //         return response()->json([
    //             'is_encounter_problem'     => ! empty($problemHtml),
    //             'problem_html'             => $problemHtml,
    //             'is_encounter_observation' => ! empty($observationHtml),
    //             'observation_html'         => $observationHtml,
    //             'is_encounter_note'        => ! empty($noteHtml),
    //             'note_html'                => $noteHtml,
    //             'is_encounter_precreption' => ! empty($PrescriptionHtml),
    //             'precreption_html'         => $PrescriptionHtml,
    //             'is_encounter_otherdetail' => ! empty($otherdetailHtml),
    //             'other_detail_html'        => $otherdetailHtml,
    //         ]);
    //     } else {
    //         return response()->json([
    //             'is_encounter_problem'     => false,
    //             'problem_html'             => '',
    //             'is_encounter_observation' => false,
    //             'observation_html'         => '',
    //             'is_encounter_note'        => false,
    //             'note_html'                => '',
    //         ]);
    //     }
    // }


    public function getTemplateData($templateId, Request $request)
    {
        $selectedEncouterMedicalHistroy   = TemplateMedicalHistory::where('template_id', $templateId)->get();
        $selectedTemplatePrescription     = TemplatePrescription::where('template_id', $templateId)->whereNull('deleted_at')->get();
        $selectedTemplateOtherDetails     = TemplateOtherDetails::where('template_id', $templateId)->get();

        $problem_list      = Constant::where('type', 'encounter_problem')->get();
        $observation_list  = Constant::where('type', 'encounter_observations')->get();

        $problemList       = $selectedEncouterMedicalHistroy->where('type', 'encounter_problem');
        $observationList   = $selectedEncouterMedicalHistroy->where('type', 'encounter_observations');
        $noteList          = $selectedEncouterMedicalHistroy->where('type', 'encounter_notes');

        // ✅ Initialize all HTML variables to avoid undefined errors
        $problemHtml        = '';
        $observationHtml    = '';
        $noteHtml           = '';
        $PrescriptionHtml   = '';
        $otherdetailHtml    = '';

        // ✅ Generate HTML views conditionally
        if ($problemList->isNotEmpty()) {
            $problemHtml = view('appointment::backend.patient_encounter.component.encounter_problem', [
                'data' => [
                    'id' => $request->encounter_id ?? '',
                    'user_id' => $request->user_id ?? '',
                    'status' => $request->status ?? '0',
                    'selectedProblemList' => $problemList,
                ],
                'problem_list' => $problem_list,
            ])->render();
        }

        if ($observationList->isNotEmpty()) {
            $observationHtml = view('appointment::backend.patient_encounter.component.encounter_observation', [
                'data' => [
                    'id' => $request->encounter_id ?? '',
                    'user_id' => $request->user_id ?? '',
                    'status' => $request->status ?? '0',
                    'selectedObservationList' => $observationList,
                ],
                'observation_list' => $observation_list,
            ])->render();
        }

        if ($noteList->isNotEmpty()) {
            $noteHtml = view('appointment::backend.patient_encounter.component.encounter_note', [
                'data' => [
                    'id' => $request->encounter_id ?? '',
                    'user_id' => $request->user_id ?? '',
                    'status' => $request->status ?? '0',
                    'notesList' => $noteList,
                ],
            ])->render();
        }

        // ✅ Remove old prescriptions before adding new ones
        EncounterPrescription::where('encounter_id', $request->encounter_id)->delete();

        // ✅ Add prescriptions from the template
        foreach ($selectedTemplatePrescription as $templatePrescription) {
            $medicine = Medicine::find($templatePrescription->medicine_id);

            if ($medicine) {
                $quantity         = $templatePrescription->quantity ?? 1;
                $selling_price    = $medicine->selling_price ?? 0;
                $medicine_price   = $quantity * $selling_price;
                $inclusive_tax_amount = 0;
                $inclusiveTaxesArray  = [];

                if ($medicine->is_inclusive_tax == 1) {
                    $inclusiveTaxes = Tax::where([
                        'category'    => 'medicine',
                        'tax_type'    => 'inclusive',
                        'module_type' => 'medicine',
                        'status'      => 1
                    ])->get();

                    foreach ($inclusiveTaxes as $tax) {
                        $taxPerUnit = $tax->type === 'percent'
                            ? ($selling_price * $tax->value) / 100
                            : $tax->value;

                        $taxTotal = $taxPerUnit * $quantity;
                        $inclusive_tax_amount += $taxTotal;

                        $taxData = $tax->toArray();
                        $taxData['amount'] = round($taxTotal, 2);
                        $inclusiveTaxesArray[] = $taxData;
                    }
                }

                EncounterPrescription::create([
                    'encounter_id'         => $request->encounter_id,
                    'user_id'              => $request->user_id,
                    'medicine_id'          => $medicine->id,
                    'quantity'             => $quantity,
                    'name'                 => $medicine->name,
                    'frequency'            => $templatePrescription->frequency,
                    'duration'             => $templatePrescription->duration,
                    'instruction'          => $templatePrescription->instruction,
                    'inclusive_tax'        => json_encode($inclusiveTaxesArray),
                    'inclusive_tax_amount' => $inclusive_tax_amount,
                    'medicine_price'       => $medicine_price,
                    'total_amount'         => $medicine_price + $inclusive_tax_amount,
                    'status'               => 0,
                    'created_by'           => auth()->id(),
                ]);
            }
        }

        // ✅ Now render updated prescription table
        if ($selectedTemplatePrescription->isNotEmpty()) {
            $PrescriptionHtml = view('appointment::backend.patient_encounter.component.prescription_table', [
                'data' => [
                    'id' => $request->encounter_id ?? '',
                    'user_id' => $request->user_id ?? '',
                    'status' => $request->status ?? '0',
                    'prescriptions' => EncounterPrescription::where('encounter_id', $request->encounter_id)->get(),
                ],
            ])->render();
        }

        // ✅ Handle other details
        foreach ($selectedTemplateOtherDetails as $detail) {
            $otherdetailHtml = $detail->other_details;
        }

        return response()->json([
            'is_encounter_problem'     => $problemList->isNotEmpty(),
            'problem_html'             => $problemHtml,
            'is_encounter_observation' => $observationList->isNotEmpty(),
            'observation_html'         => $observationHtml,
            'is_encounter_note'        => $noteList->isNotEmpty(),
            'note_html'                => $noteHtml,
            'is_encounter_precreption' => $selectedTemplatePrescription->isNotEmpty(),
            'precreption_html'         => $PrescriptionHtml,
            'is_encounter_otherdetail' => !empty($otherdetailHtml),
            'other_detail_html'        => $otherdetailHtml,
        ]);
    }

    public function saveWithoutPharmaPrescription(Request $request)
    {

        $request_data = $request->all();


        $data = [

            'name' => $request->name,
            'type' => $request->type,
            'value' => str_replace(' ', '_', strtolower($request->name)),
        ];


        // $constant = Constant::updateOrCreate(
        //     ['value' => $data['value'], 'type' => $data['type']],
        //     $data
        // );

        $prescription = EncounterPrescription::create($request_data);

        if ($request->is('api/*')) {

            $message = __('appointment.encounter_prescription_save');
            return response()->json(['message' => $message, 'data' => new PrescriptionRescource($prescription), 'status' => true], 200);

        } else {

            $data = PatientEncounter::where('id',$request_data['encounter_id'])->with('user','prescriptions')->first();

            if(!empty($data)){

                $html = view('appointment::backend.patient_encounter.component.prescription_without_pharma_table', ['data' => $data])->render();
              }

               return response()->json(['html' => $html]);

        }


    }

    public function editWithoutPharmaPrescription($id)
    {

        $prescription = EncounterPrescription::where('id', $id)->first();

        return response()->json(['data' => $prescription, 'status' => true]);

    }

    public function updateWithoutPharmaPrescription(Request $request, $id)
    {

        $data = $request->all();

        $prescription = EncounterPrescription::where('id', $id)->first();

        $data_value = [

            'name' => $request->name,
            'type' => $request->type,
            'value' => str_replace(' ', '_', strtolower($request->name)),
        ];


        $constant = Constant::updateOrCreate(
            ['value' => $data_value['value'], 'type' => $data_value['type']],
            $data_value
        );

        $prescription->update($data);

        if ($request->is('api/*')) {

            $message = __('appointment.encounter_prescription_update');
            return response()->json(['message' => $message, 'data' => new PrescriptionRescource($prescription), 'status' => true], 200);

        } else {

            $data = PatientEncounter::where('id',$data['encounter_id'])->with('user','prescriptions')->first();

            if(!empty($data)){

                $html = view('appointment::backend.patient_encounter.component.prescription_without_pharma_table', ['data' => $data])->render();
              }

               return response()->json(['html' => $html]);

        }


    }

    public function deleteWithoutPharmaPrescription(Request $request, $id)
    {

        $prescription = EncounterPrescription::where('id', $id)->first();

        $encounter_id = $prescription->encounter_id;

        $prescription->forceDelete();

        if ($request->is('api/*')) {

            $message = __('appointment.prescription_delete');

            return response()->json(['message' => $message, 'status' => true], 200);

        } else {

            $data = PatientEncounter::where('id',$encounter_id)->with('user','prescriptions')->first();

            if(!empty($data)){

                $html = view('appointment::backend.patient_encounter.component.prescription_without_pharma_table', ['data' => $data])->render();
              }

               return response()->json(['html' => $html]);
        }

    }


    // public function saveOtherDetails(Request $request)
    // {

    //     $request_data = $request->all();

    //     $data = [

    //         'other_details' => $request->other_details,
    //         'encounter_id' => $request->encounter_id,
    //         'user_id' => $request->user_id,
    //     ];

    //     $otherDetails = EncounterOtherDetails::updateOrCreate(
    //         ['encounter_id' => $data['encounter_id'], 'user_id' => $data['user_id']],
    //         $data
    //     );


    //     return response()->json(['otherDetails' => $otherDetails, 'status' => true]);

    // }

    // public function saveMedicalReport(Request $request)
    // {

    //     $data = $request->all();

    //     $html='';

    //     $medical_report=EncounterMedicalReport::create($data);

    //     if ($request->hasFile('file_url')) {
    //         storeMediaFile($medical_report, $request->file('file_url'));
    //     }

    //     if ($request->is('api/*')) {

    //         $medical_report = new MedicalReportRescource($medical_report);

    //         $message = __('appointment.medical_report_save');
    //         return response()->json(['message' => $message, 'data' => $medical_report, 'status' => true], 200);
    //     } else {


    //         $data = PatientEncounter::where('id',$data['encounter_id'])->with('user','medicalReport')->first();

    //         if(!empty($data)){

    //             $html = view('appointment::backend.patient_encounter.component.medical_report_table', ['data' => $data])->render();
    //           }

    //            return response()->json(['html' => $html]);

    //         }


    // }

    // public function editMedicalReport(Request $request, $id)
    // {

    //     $medical_report=EncounterMedicalReport::where('id',$id)->first();


    //     return response()->json(['data' => $medical_report, 'status' => true]);


    // }

    // public function updateMedicalReport(Request $request, $id)
    // {

    //     $data = $request->all();

    //     $html='';


    //     $medical_report=EncounterMedicalReport::where('id',$id)->first();

    //     $medical_report->update($data);


    //     if ($request->hasFile('file_url')) {

    //         storeMediaFile($medical_report, $request->file('file_url'));
    //     }


    //     if ($request->is('api/*')) {

    //         $medical_report = new MedicalReportRescource($medical_report);

    //         $message = __('appointment.medical_report_update');

    //         return response()->json(['message' => $message, 'data' => $medical_report, 'status' => true], 200);

    //     } else {

    //         $data = PatientEncounter::where('id',$data['encounter_id'])->with('user','medicalReport')->first();

    //         if(!empty($data)){

    //             $html = view('appointment::backend.patient_encounter.component.medical_report_table', ['data' => $data])->render();
    //           }

    //            return response()->json(['html' => $html]);

    //         }


    // }


    // public function deleteMedicalReport(Request $request, $id)
    // {

    //      $medical_report=EncounterMedicalReport::where('id',$id)->first();

    //      $encounter_id= $medical_report->encounter_id;
    //      $html='';


    //     $medical_report->forceDelete();

    //     if ($request->is('api/*')) {

    //         $message = __('appointment.medical_report_delete');

    //         return response()->json(['message' => $message, 'status' => true], 200);

    //     } else {

    //         $data = PatientEncounter::where('id',$encounter_id)->with('user','medicalReport')->first();

    //         if(!empty($data)){

    //             $html = view('appointment::backend.patient_encounter.component.medical_report_table', ['data' => $data])->render();
    //           }

    //            return response()->json(['html' => $html]);

    //     }



    //   }



    // public function GetReportData(Request $request)
    // {

    //     $encounter_id = $request->encounter_id;

    //     $medical_report = EncounterMedicalReport::where('encounter_id', $encounter_id)->get();

    //     return response()->json(['medical_report' => $medical_report, 'status' => true]);

    // }


    // public function SendMedicalReport(Request $request)
    // {

    //     $encounter_id=$request->id;

    //     $data = PatientEncounter::where('id',$encounter_id)->first();

    //     $user_id= $data->user_id;

    //     $user=User::where('id',$user_id)->first();

    //     $medicalReport = EncounterMedicalReport::where('id', $data['report_id'])->first();


    //     if ($user && $medicalReport) {

    //         $filePath = $medicalReport->file_url;

    //             Mail::to($user->email)->send(new MedicalReportEmail($medicalReport, $filePath));
    //             $message = __('appointment.medical_report_send');
    //             return response()->json(['message'=> $message,'status'=>true]);

    //        } else {
    //         $message  = __('appointment.something_wrong');
    //         return response()->json(['message'=> $message,'status'=>false]);
    //     }

    // }

    // public function sendPrescription(Request $request)
    // {


    //     $encounter_id=$request->id;

    //     $data = PatientEncounter::where('id',$encounter_id)->first();

    //     $user_id= $data->user_id;

    //     $user = User::where('id', $user_id)->first();

    //     $prescriptionList = EncounterPrescription::where('encounter_id', $encounter_id)->get();


    //     if ($user && $prescriptionList) {

    //             Mail::to($user->email)->send(new PrescriptionListMail($prescriptionList));
    //             $message = __('appointment.prescription_send');
    //             return response()->json(['message'=> $message,'status'=>true]);

    //        } else {
    //         $message  = __('appointment.something_wrong');
    //         return response()->json(['message'=> $message ,'status'=>false]);
    //     }

    // }


    // public function importPrescription(Request $request)
    // {
    //      $file = $request->file('file_url');

    //      if (!$file->isValid()) {
    //          return response()->json(['error' => 'Invalid file', 'status' => false]);
    //      }

    //      if (!in_array($file->getClientOriginalExtension(), ['csv', 'xlsx'])) {
    //          return response()->json(['error' => 'Invalid file type', 'status' => false], 400);
    //      }

    //      if ($file->getClientOriginalExtension() === 'csv') {
    //          $records = $this->importCsv($file, $request->user_id, $request->encounter_id);
    //      } elseif ($file->getClientOriginalExtension() === 'xlsx') {

    //         Excel::import(new PrescriptionImport($request->user_id, $request->encounter_id), $file);

    //     }

    //      $prescription=EncounterPrescription::where('encounter_id',$request->encounter_id)->get();

    //      return response()->json(['prescription'=>$prescription,'status'=>true]);
    // }

    // public function exportPrescriptionData(Request $request)
    // {

    //     $data = $request->all();

    //     $type = $data['type'];

    //     $encounter_id = $data['encounter_id'];

    //     $prescriptionList = EncounterPrescription::where('encounter_id', $encounter_id)->get(['id', 'name', 'frequency', 'duration', 'instruction']);

    //     switch ($type) {
    //         case 'pdf':

    //             $pdf = PDF::loadView('pdf.prescripcription', ['prescriptionList' => $prescriptionList]);
    //             $pdf->setPaper('A4', 'landscape');

    //                return $pdf->download('prescripcription.pdf');

    //              break;
    //         case 'csv':

    //             $csvContent = "ID,Name,Frequency,Duration,Instruction\n";
    //             $csvContent .= "2,test1,34,1,efergr\n";

    //             $headers = [
    //                 'Content-Disposition' => 'attachment; filename="test.csv"',
    //                 'Content-Type' => 'application/json',
    //                 'Accept' => 'application/json',
    //             ];


    //             return Response::make($csvContent, 200, $headers);


    //     return Response::make($csvContent, 200, $headers);

    //             break;
    //         case 'xlsx':
    //             return $this->exportXLSX($encounter_id);
    //             break;
    //         default:
    //             return redirect()->back()->with('error', 'Unsupported file format.');
    //     }

    // }


    //    protected function importCsv($file, $user_id, $encounter_id)
    //    {
    //        $filePath = $file->getRealPath();
    //        $csv = Reader::createFromPath($filePath, 'r');
    //        $csv->setHeaderOffset(0);
    //        $records = [];

    //        foreach ($csv->getRecords() as $record) {
    //            $file_record = [
    //                'user_id' => $user_id,
    //                'encounter_id' => $encounter_id,
    //                'name' => $record['name'],
    //                'frequency' => $record['frequency'],
    //                'duration' => $record['duration'],
    //                'instruction' => $record['instruction'],
    //            ];

    //            EncounterPrescription::create($file_record);

    //            $constant_record=[

    //                'type'=>'encounter_prescription',
    //                'name'=> $record['name'],
    //                'value'=>str_replace(' ', '_',$record['name']),

    //             ];

    //             $constant = Constant::updateOrCreate(
    //               ['value' => $constant_record['value'], 'type' => $constant_record['type']],
    //               $constant_record
    //           );

    //            $records[] = $file_record;
    //        }

    //        return $records;
    //    }



    //    public function EncouterDetailPage(Request $request , $id){

    //     $module_title ="Encounter Dashboard";

    //     $data = PatientEncounter::where('id',$id)->with('user','user.cities','user.countries','clinic','doctor','medicalHistroy','prescriptions','EncounterOtherDetails','medicalReport','appointmentdetail','billingrecord','bedAllocations')->first();
    //     $data['selectedProblemList'] =  $data->medicalHistroy()->where('type','encounter_problem')->get();
    //     $data['selectedObservationList'] = $data->medicalHistroy()->where('type', 'encounter_observations')->get();
    //     $data['notesList'] = $data->medicalHistroy()->where('type', 'encounter_notes')->get();
    //     $data['prescriptions'] = $data->prescriptions()->get();
    //     $data['other_details'] = $data->EncounterOtherDetails()->value('other_details') ?? null;
    //     $data['medicalReport'] = $data->medicalReport()->get();
    //     $data['signature'] = optional(optional($data->doctor)->doctor)->Signature ?? null;
    //     $data['appointment_status'] = $data->appointmentdetail->status ?? null;
    //     $data['payment_status'] = $data->appointmentdetail->appointmenttransaction->payment_status ?? null;
    //     $data['billingrecord']=$data->billingrecord ?? null;
    //     $data['billingrecord_payment']=$data->billingrecord->payment_status ?? null;
    //     $data['encounter_date'] = formatDate($data['encounter_date']);
    //     $data['customform'] = CustomForm::where('module_type', 'appointment_module')
    //     ->where('status', 1)
    //     ->get()
    //     ->filter(function($item) {
    //         $showInArray = json_decode($item->show_in, true);
    //         return in_array('encounter', $showInArray);
    //     });

    //     $template_data = EncounterTemplate::with('templateMedicalHistroy','templatePrescriptions','TemplateOtherDetails')->Where('status', 1)->get();

    //     $encounter_data=encounter();

    //     $problem_list= Constant::where('type', 'encounter_problem')->get();
    //     $observation_list= Constant::where('type', 'encounter_observations')->get();
    //     $prescription_list = EncounterPrescription::all()->map(function ($item) {
    //         return [
    //             'value' => $item->id,
    //             'label' => $item->name,
    //         ];
    //     })->toArray();

    //     $bedTypes = BedType::pluck('type', 'id');

    //     // Get all beds grouped by bed_type_id
    //     $beds = BedMaster::pluck('bed', 'id');

    //     // Get clinic admin based on the encounter's clinic
    //     $clinicAdmin = null;
    //     if ($data->clinic) {
    //         // Get the clinic admin for this specific clinic
    //         $clinicAdmin = \App\Models\User::where('id', $data->clinic->vendor_id)
    //             ->where('user_type', 'vendor')
    //             ->where('status', 1)
    //             ->first();

    //         // If the clinic's vendor_id points to a super admin, find the actual clinic admin for this clinic
    //         if (!$clinicAdmin) {
    //             // Look for users who are vendors and have this clinic assigned to them
    //             // Since there's no direct relationship, we'll check if any vendor has this clinic
    //             $clinicAdmin = \App\Models\User::where('user_type', 'vendor')
    //                 ->where('status', 1)
    //                 ->whereHas('doctor', function($query) use ($data) {
    //                     $query->whereHas('doctorclinic', function($q) use ($data) {
    //                         $q->where('clinic_id', $data->clinic->id);
    //                     });
    //                 })
    //                 ->first();

    //             // If still not found, check receptionist relationship
    //             if (!$clinicAdmin) {
    //                 $clinicAdmin = \App\Models\User::where('user_type', 'vendor')
    //                     ->where('status', 1)
    //                     ->whereHas('receptionist', function($query) use ($data) {
    //                         $query->where('clinic_id', $data->clinic->id);
    //                     })
    //                     ->first();
    //             }
    //         }
    //     }

    //     // Debug: Log the clinic admin info
    //     \Log::info('Clinic Admin Debug', [
    //         'clinic_id' => $data->clinic ? $data->clinic->id : null,
    //         'clinic_vendor_id' => $data->clinic ? $data->clinic->vendor_id : null,
    //         'clinic_admin_found' => $clinicAdmin ? true : false,
    //         'clinic_admin_id' => $clinicAdmin ? $clinicAdmin->id : null,
    //         'clinic_admin_name' => $clinicAdmin ? $clinicAdmin->first_name . ' ' . $clinicAdmin->last_name : null,
    //     ]);

    //     // Get all users with user_type 'vendor' for clinic admin dropdown
    //     $clinicAdmins = \App\Models\User::where('user_type', 'vendor')
    //         ->where('status', 1)
    //         ->get()
    //         ->mapWithKeys(function ($user) {
    //             return [$user->id => $user->first_name . ' ' . $user->last_name];
    //         });

    //     // Patient encounters will be loaded dynamically based on selected clinic
    //     $patientEncounters = collect([]);

    //     // Fetch bed allocations for this encounter
    //     $bedAllocations = \Modules\Bed\Models\BedAllocation::where('encounter_id', $data->id)
    //         ->with(['patient', 'bedMaster','bedType'])
    //         ->get();

    //     return view('appointment::backend.patient_encounter.encounter_detail_page', compact('module_title','data','template_data','encounter_data','problem_list','observation_list','prescription_list','bedTypes','beds','bedAllocations','clinicAdmins','patientEncounters','clinicAdmin'));

    //    }
    //    public function getTemplateData($templateId, Request $request)
    //    {
    //     $selectedEncouterMedicalHistroy = TemplateMedicalHistory::where('template_id', $templateId)->get();
    //     $selectedTemplatePrescription = TemplatePrescription::where('template_id', $templateId)->get();
    //     $selectedTemplateOtherDetails = TemplateOtherDetails::where('template_id', $templateId)->get();
    //     $problem_list= Constant::where('type', 'encounter_problem')->get();
    //     $observation_list= Constant::where('type', 'encounter_observations')->get();

    //     if ($selectedEncouterMedicalHistroy->isNotEmpty() || $selectedTemplatePrescription->isNotEmpty()) {
    //         $problemHtml = '';
    //         $observationHtml = '';
    //         $noteHtml = '';
    //         $PrescriptionHtml = '';
    //         $otherdetailHtml = '';
    //         // Iterate through the collection
    //         foreach ($selectedEncouterMedicalHistroy as $medicalHistory) {
    //             if ($medicalHistory->type === 'encounter_problem') {
    //                 // Generate problem HTML using a Blade view
    //                 $problemHtml = view('appointment::backend.patient_encounter.component.encounter_problem', [
    //                     'data' => [
    //                         'id' => $request->encounter_id ?? '',
    //                         'user_id' => $request->user_id ?? '',
    //                         'status' => $request->status ?? '0',
    //                         'selectedProblemList' => $selectedEncouterMedicalHistroy->where('type', 'encounter_problem'),
    //                     ],
    //                     'problem_list' => $problem_list,
    //                 ])->render();
    //             }

    //             if ($medicalHistory->type === 'encounter_observations') {
    //                 // Generate observation HTML using a Blade view

    //                 $observationHtml = view('appointment::backend.patient_encounter.component.encounter_observation', [
    //                     'data' => [
    //                         'id' => $request->encounter_id ?? '',
    //                         'user_id' => $request->user_id ?? '',
    //                         'status' => $request->status ?? '0',
    //                         'selectedObservationList' => $selectedEncouterMedicalHistroy->where('type', 'encounter_observations'),
    //                     ],
    //                     'observation_list' => $observation_list,
    //                 ])->render();


    //             }

    //             if ($medicalHistory->type === 'encounter_notes') {
    //                 // Generate note HTML using a Blade view
    //                 $noteHtml = view('appointment::backend.patient_encounter.component.encounter_note', [
    //                     'data' => [
    //                         'id' => $request->encounter_id ?? '',
    //                         'user_id' => $request->user_id ?? '',
    //                         'status' => $request->status ?? '0',
    //                         'notesList' => $selectedEncouterMedicalHistroy->where('type', 'encounter_notes'),
    //                     ],

    //                 ])->render();
    //             }
    //         }

    //         foreach ($selectedTemplatePrescription as $TemplatePrescription) {
    //             if ($TemplatePrescription !== null) {
    //                 // Generate problem HTML using a Blade view
    //                 $PrescriptionHtml = view('appointment::backend.patient_encounter.component.prescription_table', [
    //                     'data' => [
    //                         'id' => $request->encounter_id ?? '',
    //                         'user_id' => $request->user_id ?? '',
    //                         'status' => $request->status ?? '0',
    //                         'prescriptions' => $selectedTemplatePrescription,
    //                     ],

    //                 ])->render();
    //             }


    //         }

    //         foreach ($selectedTemplateOtherDetails as $detail) {
    //             $otherdetailHtml = $detail->other_details ;
    //         }





    //         // Return response as JSON
    //         return response()->json([
    //             'is_encounter_problem' => !empty($problemHtml),
    //             'problem_html' => $problemHtml,
    //             'is_encounter_observation' => !empty($observationHtml),
    //             'observation_html' => $observationHtml,
    //             'is_encounter_note' => !empty($noteHtml),
    //             'note_html' => $noteHtml,
    //             'is_encounter_precreption' => !empty($PrescriptionHtml),
    //             'precreption_html' => $PrescriptionHtml,
    //             'is_encounter_otherdetail' => !empty($otherdetailHtml),
    //             'other_detail_html' => $otherdetailHtml,
    //         ]);
    //     }  else {
    //         return response()->json([
    //             'is_encounter_problem' => false,
    //             'problem_html' => '',
    //             'is_encounter_observation' => false,
    //             'observation_html' => '',
    //             'is_encounter_note' => false,
    //             'note_html' => '',
    //         ]);
    //     }


    //  }

     // Bed Allocation Methods
     public function editBedAllocation($id)
     {
         try {
             $bedAllocation = \Modules\Bed\Models\BedAllocation::with(['patient', 'bedMaster.bedType'])->findOrFail($id);

             // Format the data for the edit form
             $formData = [
                 'id' => $bedAllocation->id,
                 'patient_id' => $bedAllocation->patient_id,
                 'bed_type_id' => $bedAllocation->bed_type_id,
                 'bed_master_id' => $bedAllocation->bed_master_id,
                 'assign_date' => $bedAllocation->assign_date ? $bedAllocation->assign_date->format('Y-m-d') : null,
                 'discharge_date' => $bedAllocation->discharge_date ? $bedAllocation->discharge_date->format('Y-m-d') : null,
                 'status' => $bedAllocation->status,
                 'description' => $bedAllocation->description,
                 'temperature' => $bedAllocation->temperature,
                 'symptoms' => $bedAllocation->symptoms,
                 'notes' => $bedAllocation->notes,
                 'charge' => $bedAllocation->charge,
             ];

             return response()->json([
                 'status' => true,
                 'data' => $formData,
                 'message' => 'Bed allocation details loaded successfully'
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'status' => false,
                 'message' => 'Failed to load bed allocation details: ' . $e->getMessage()
             ]);
         }
     }

     public function updateBedAllocation(Request $request, $id)
     {
         try {
             $bedAllocation = \Modules\Bed\Models\BedAllocation::findOrFail($id);

             $validated = $request->validate([
                 'bed_type_id' => 'required|exists:bed_type,id',
                 'room_no' => 'required|exists:bed_master,id',
                 'assign_date' => 'required|date',
                 'discharge_date' => 'nullable|date|after_or_equal:assign_date',
                 'status' => 'nullable|boolean',
                 'description' => 'nullable|string|max:250',
                 'temperature' => 'nullable|string',
                 'symptoms' => 'nullable|string',
                 'notes' => 'nullable|string',
             ]);

             // Get bed info for charge
             $bedMaster = \Modules\Bed\Models\BedMaster::find($request->room_no);
             $charge = $bedMaster ? $bedMaster->charges : 0;

             $bedAllocation->update([
                 'bed_type_id' => $request->bed_type_id,
                 'bed_master_id' => $request->room_no,
                 'assign_date' => $request->assign_date,
                 'discharge_date' => $request->discharge_date,
                 'status' => $request->status ? 1 : 0,
                 'description' => $request->description,
                 'temperature' => $request->temperature,
                 'symptoms' => $request->symptoms,
                 'notes' => $request->notes,
                 'charge' => $charge,
             ]);

             // Get updated bed allocations for the patient
             $bedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $bedAllocation->patient_id)
                 ->with(['patient', 'bedMaster.bedType'])
                 ->get();

             $html = view('appointment::backend.patient_encounter.component.bed_allocation_table', [
                 'data' => ['status' => 1], // Assuming encounter is still active
                 'bedAllocations' => $bedAllocations
             ])->render();

             return response()->json([
                 'status' => true,
                 'message' => 'Bed allocation updated successfully',
                 'html' => $html
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'status' => false,
                 'message' => 'Failed to update bed allocation: ' . $e->getMessage()
             ]);
         }
     }

     public function deleteBedAllocation($id)
     {
         try {
             $bedAllocation = \Modules\Bed\Models\BedAllocation::findOrFail($id);
             $patientId = $bedAllocation->patient_id;

             $bedAllocation->delete();

             // Get updated bed allocations for the patient
             $bedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $patientId)
                 ->with(['patient', 'bedMaster.bedType'])
                 ->get();

             $html = view('appointment::backend.patient_encounter.component.bed_allocation_table', [
                 'data' => ['status' => 1], // Assuming encounter is still active
                 'bedAllocations' => $bedAllocations
             ])->render();

             return response()->json([
                 'status' => true,
                 'message' => 'Bed allocation deleted successfully',
                 'html' => $html
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'status' => false,
                 'message' => 'Failed to delete bed allocation: ' . $e->getMessage()
             ]);
         }
     }

     public function viewBedAllocation($id)
     {
         try {
            \Log::info('View Bed Allocation - Request Received', [
                'bed_allocation_id' => $id,
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->roles->pluck('name')->toArray() ?? [],
                'timestamp' => now()->toDateTimeString()
            ]);

             $bedAllocation = \Modules\Bed\Models\BedAllocation::with(['patient', 'bedMaster.bedType'])->findOrFail($id);

            \Log::info('View Bed Allocation - Bed Allocation Found', [
                'bed_allocation_id' => $bedAllocation->id,
                'encounter_id' => $bedAllocation->encounter_id,
                'patient_id' => $bedAllocation->patient_id,
                'bed_master_id' => $bedAllocation->bed_master_id,
                'assign_date' => $bedAllocation->assign_date,
                'discharge_date' => $bedAllocation->discharge_date,
                'charge' => $bedAllocation->charge,
                'has_patient' => !is_null($bedAllocation->patient),
                'has_bed_master' => !is_null($bedAllocation->bedMaster),
                'has_bed_type' => !is_null($bedAllocation->bedMaster?->bedType)
            ]);

             $data = [
                 'patient_name' => optional($bedAllocation->patient)->full_name ?? '--',
                 'bed_type' => optional($bedAllocation->bedMaster->bedType)->type ?? '--',
                 'room_bed' => optional($bedAllocation->bedMaster)->bed ?? '--',
                 'assign_date' => $bedAllocation->assign_date ? \Carbon\Carbon::parse($bedAllocation->assign_date)->format('Y-m-d') : '--',
                 'discharge_date' => $bedAllocation->discharge_date ? \Carbon\Carbon::parse($bedAllocation->discharge_date)->format('Y-m-d') : '--',
                 'status' => $bedAllocation->status ? 'Active' : 'Inactive',
                 'charge' => $bedAllocation->charge ? '₹' . number_format($bedAllocation->charge, 2) : '--',
                 'description' => $bedAllocation->description ?? '--',
                 'temperature' => $bedAllocation->temperature ?? '--',
                 'symptoms' => $bedAllocation->symptoms ?? '--',
                 'notes' => $bedAllocation->notes ?? '--',
             ];

            \Log::info('View Bed Allocation - Response Data Prepared', [
                'bed_allocation_id' => $id,
                'data_keys' => array_keys($data),
                'patient_name' => $data['patient_name'],
                'bed_type' => $data['bed_type'],
                'room_bed' => $data['room_bed'],
                'charge' => $data['charge']
            ]);

             return response()->json([
                 'status' => true,
                 'data' => $data,
                 'message' => 'Bed allocation details loaded successfully'
             ]);
         } catch (\Exception $e) {
            \Log::error('View Bed Allocation - Error', [
                'bed_allocation_id' => $id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);

             return response()->json([
                 'status' => false,
                 'message' => 'Failed to load bed allocation details: ' . $e->getMessage()
             ]);
         }
     }

     public function storeBedAllocation(Request $request)
     {
         try {
             $validated = $request->validate([
                 'encounter_id' => 'required|exists:patient_encounters,id',
                 'bed_type_id' => 'required|exists:bed_type,id',
                 'room_no' => 'required|exists:bed_master,id',
                 'assign_date' => 'required|date',
                 'discharge_date' => 'nullable|date|after_or_equal:assign_date',
                 'status' => 'nullable|boolean',
                 'description' => 'nullable|string|max:250',
                 'temperature' => 'nullable|string',
                 'symptoms' => 'nullable|string',
                 'notes' => 'nullable|string',
             ]);

             // Get patient encounter information
             $patientEncounter = PatientEncounter::where('id', $request->encounter_id)->first();
             if (!$patientEncounter) {
                 return response()->json([
                     'status' => false,
                     'message' => 'Invalid encounter selected.'
                 ]);
             }

             // Check if the same encounter already has an active bed allocation
             // For the same encounter, you cannot assign a second bed at the same time
             // After the discharge date, you can assign a second bed
             $today = Carbon::today();
             $activeEncounterAllocation = \Modules\Bed\Models\BedAllocation::where('encounter_id', $patientEncounter->id)
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
                 return response()->json([
                     'status' => false,
                     'message' => 'This encounter already has an active bed allocation. You cannot assign a second bed for the same encounter until the previous bed is discharged. Current discharge date: ' . $dischargeDateStr . '. Please discharge the previous bed first or assign a new bed after the discharge date.'
                 ]);
             }

             // Get bed info for charge
             $bedMaster = \Modules\Bed\Models\BedMaster::find($request->room_no);
             if (!$bedMaster) {
                 return response()->json([
                     'status' => false,
                     'message' => 'Invalid bed selected.'
                 ]);
             }
             $charge = $bedMaster->charges ?? 0;

             // Calculate nights and total charge
             $assignDate = Carbon::parse($request->assign_date);
             $dischargeDate = $request->discharge_date ? Carbon::parse($request->discharge_date) : $assignDate;
             $nights = max($assignDate->diffInDays($dischargeDate), 1);
             $totalCharge = $nights * $charge;

             // Create bed allocation
             \Modules\Bed\Models\BedAllocation::create([
                 'patient_id' => $patientEncounter->user_id,
                 'encounter_id' => $patientEncounter->id,
                 'clinic_id' => $patientEncounter->clinic_id,
                 'clinic_admin_id' => $patientEncounter->vendor_id,
                 'bed_type_id' => $request->bed_type_id,
                 'bed_master_id' => $request->room_no,
                 'assign_date' => $request->assign_date,
                 'discharge_date' => $request->discharge_date,
                 'status' => $request->status ? 1 : 0,
                 'description' => $request->description,
                 'temperature' => $request->temperature,
                 'symptoms' => $request->symptoms,
                 'notes' => $request->notes,
                 'charge' => $totalCharge,
                 'per_bed_charge' => $charge,
             ]);

             // Get updated bed allocations for the patient
             $bedAllocations = \Modules\Bed\Models\BedAllocation::where('patient_id', $patientEncounter->user_id)
                 ->with(['patient', 'bedMaster.bedType'])
                 ->get();

             $html = view('appointment::backend.patient_encounter.component.bed_allocation_table', [
                 'data' => ['status' => 1], // Assuming encounter is still active
                 'bedAllocations' => $bedAllocations
             ])->render();

             return response()->json([
                 'status' => true,
                 'message' => 'Bed allocation created successfully',
                 'html' => $html
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'status' => false,
                 'message' => 'Failed to create bed allocation: ' . $e->getMessage()
             ]);
         }
     }

     public function getRoomsByBedType($bedTypeId)
     {
         try {
             $rooms = BedMaster::SetRole(auth()->user())->where('bed_type_id', $bedTypeId)
                 ->where('status', true)
                 ->where('is_under_maintenance', false)
                 ->select('id', 'bed')
                 ->get();

             return response()->json([
                 'status' => true,
                 'rooms' => $rooms
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'status' => false,
                 'message' => 'Failed to load rooms: ' . $e->getMessage()
             ]);
         }
     }

}
