<?php

/**
 * Encounter Dashboard Detail API
 *
 * POST /api/appointment/encounter-dashboard-detail
 *
 * Request Body:
 *   - encounter_id: integer (required)
 *
 * Example cURL:
 * curl -X POST \
 *   -H "Authorization: Bearer <token>" \
 *   -H "Content-Type: application/json" \
 *   -d '{"encounter_id": 123}' \
 *   https://your-domain.com/api/appointment/encounter-dashboard-detail
 *
 * Response: JSON with encounter details, including 'bed_allocations' array
 */

namespace Modules\Appointment\Http\Controllers\Backend\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Constant\Models\Constant;
use Modules\Appointment\Transformers\ConstantResource;
use Modules\Appointment\Models\EncounterMedicalReport;
use Modules\Appointment\Transformers\MedicalReportRescource;
use Modules\Appointment\Models\EncounterPrescription;
use Modules\Appointment\Transformers\PrescriptionRescource;
use Modules\Appointment\Models\EncouterMedicalHistroy;
use Modules\Appointment\Models\EncounterOtherDetails;
use Modules\Appointment\Models\PatientEncounter;
use Modules\Appointment\Transformers\EncounterDashboardDetailsResource;
use Modules\Appointment\Models\BillingRecord;
use Modules\Appointment\Transformers\BillingRecordResource;
use Modules\Appointment\Transformers\BillingRecordDetailsResource;
use Modules\Appointment\Models\AppointmentPatientBodychart;
use Modules\Appointment\Transformers\BodyChartResource;
use Modules\Appointment\Transformers\EncounterServiceResource;

class EncounterDashboardController extends Controller
{
    public function encounterDropdownList(Request $request){
        $perPage = $request->input('per_page', 10);

        $type = $request->type;
        $data = Constant::where('type', $type);

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $data->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%");

            });
        }

        if($perPage=='all'){
            $data = $data->get();
        }
        else{
            $data = $data->paginate($perPage);
        }

        $constantCollection = ConstantResource::collection($data);

        return response()->json([
            'status' => true,
            'data' => $constantCollection,
            'message' => __('appointment.encounter_dropdown_list'),
        ], 200);
    }


    public function  GetMedicalReport(Request $request){

        $medical_report=[];

        $perPage = $request->input('per_page', 10);

         $data= EncounterMedicalReport::where('encounter_id',$request->encounter_id);

        if($perPage=='all'){
            $data = $data->get();
        }
        else{
            $data = $data->paginate($perPage);
        }

        $reportCollection = MedicalReportRescource::collection($data);

        return response()->json([
            'status' => true,
            'data' =>  $reportCollection,
            'message' => __('appointment.medical_report'),
        ], 200);

    }

    public function  GetPrescription(Request $request){

        $prescription=[];

        $perPage = $request->input('per_page', 10);

         $data= EncounterPrescription::where('encounter_id',$request->encounter_id);

        if($perPage=='all'){
            $data = $data->get();
        }
        else{
            $data = $data->paginate($perPage);
        }

        $prescriptionCollection = PrescriptionRescource::collection($data);

        return response()->json([
            'status' => true,
            'data' =>  $prescriptionCollection,
            'message' => __('appointment.prescription_list'),
        ], 200);

    }

    public function saveEncounterDashboard(Request $request){
        $user_id = $request->user_id;
        $encounter_id = $request->encounter_id;
        $problems = $request->problems;
        $observations = $request->observations;
        $notes = $request->notes;
        $prescriptions = $request->prescriptions;
        $encounter = PatientEncounter::where('id',$encounter_id)->first();
        $pharma_id = $request->pharma_id ?? $encounter->pharma_id;
        $prescriptionname = [];
        $medicineNames = [];

        EncouterMedicalHistroy::where('encounter_id', $encounter_id)->delete();
        foreach($problems as $problem){
            $encounter_problem = new EncouterMedicalHistroy;
            $encounter_problem->encounter_id = $encounter_id;
            $encounter_problem->user_id = $user_id;
            $encounter_problem->type = 'encounter_problem';
            $encounter_problem->title = $problem['problem_name'];
            $encounter_problem->save();

            if(empty($problem['problem_id'])){
                $constant = new Constant;
                $constant->name = $problem['problem_name'];
                $constant->type = 'encounter_problem';
                $constant->value = $problem['problem_name'];
                $constant->save();
            }
        }
        foreach($observations as $observation){
            $encounter_observation = new EncouterMedicalHistroy;
            $encounter_observation->encounter_id = $encounter_id;
            $encounter_observation->user_id = $user_id;
            $encounter_observation->type = 'encounter_observations';
            $encounter_observation->title = $observation['observation_name'];
            $encounter_observation->save();

            if(empty($observation['observation_id'])){
                $constant = new Constant;
                $constant->name = $observation['observation_name'];
                $constant->type = 'encounter_observations';
                $constant->value = $observation['observation_name'];
                $constant->save();
            }
        }
        foreach($notes as $note){
            $encounter_notes = new EncouterMedicalHistroy;
            $encounter_notes->encounter_id = $encounter_id;
            $encounter_notes->user_id = $user_id;
            $encounter_notes->type = 'encounter_notes';
            $encounter_notes->title = $note;
            $encounter_notes->save();
        }

        EncounterPrescription::where('encounter_id', $encounter_id)->delete();

        foreach ($prescriptions as $prescription) {
            $data = [
                'encounter_id' => $encounter_id,
                'user_id'      => $user_id,
                'quantity'     => $prescription['quantity'] ?? 1,
                'medicine_id'  => $prescription['medicine_id'] ?? null,
                'frequency'    => $prescription['frequency'] ?? null,
                'duration'     => $prescription['duration'] ?? null,
                'instruction'  => $prescription['instruction'] ?? null,
            ];

            if (checkPlugin('pharma') == 'active' && !empty($prescription['medicine_id'])) {
                $medicine = \Modules\Pharma\Models\Medicine::find($prescription['medicine_id']);

                $data['name'] = $medicine ? $medicine->name . ' - ' . $medicine->dosage : $prescription['name'];
                $selling_price = $medicine->selling_price ?? 0;
                $medicine_price = $data['quantity'] * $selling_price;
                $data['medicine_price'] = $medicine_price;

                // Inclusive Tax Logic
                $inclusive_tax_amount = 0;
                $inclusiveTaxesArray = [];

                if ($medicine && $medicine->is_inclusive_tax == 1) {
                    $inclusiveTaxes = \Modules\Tax\Models\Tax::where([
                        'category'    => 'medicine',
                        'tax_type'    => 'inclusive',
                        'module_type' => 'medicine',
                        'status'      => 1
                    ])->get();

                    foreach ($inclusiveTaxes as $tax) {
                        $taxPerUnit = $tax->type === 'percent'
                            ? ($selling_price * $tax->value) / 100
                            : $tax->value;

                        $taxTotal = $taxPerUnit * $data['quantity'];
                        $inclusive_tax_amount += $taxTotal;

                        $taxData = $tax->toArray();
                        $taxData['amount'] = round($taxTotal, 2);
                        $inclusiveTaxesArray[] = $taxData;
                    }

                    $data['inclusive_tax'] = json_encode($inclusiveTaxesArray);
                }

                $data['inclusive_tax_amount'] = $inclusive_tax_amount;
                $data['total_amount'] = $medicine_price + $inclusive_tax_amount;
                if ($data['name']) {
                    $medicineNames[] =  $data['name'];
                }
            } else {
                $data['name'] = $prescription['name'];
            }

            $prescriptionname[] = EncounterPrescription::create($data);
        }
            // dd($pharma_id);
        if ($pharma_id && count($medicineNames) > 0) {

            sendNotification([
                'notification_type' => 'add_prescription',
                'pharma_id'         => $pharma_id,
                'encounter_id'      => $encounter_id, // or parent prescription id if exists
                'medicine_name'     => implode(', ', $medicineNames), // all medicine names
                'user_id'           => $user_id,
                'prescription'      => $prescriptionname, // pass all if needed
                'prescription_id'   => $prescriptionname[0]->id,
            ]);
        }

        // Billing calculation (after saving all prescriptions)
        $subtotal = \Modules\Appointment\Models\EncounterPrescription::where('encounter_id', $encounter_id)
            ->sum(\DB::raw('medicine_price + inclusive_tax_amount'));

        // Exclusive Tax Logic
        $exclusiveTaxAmount = 0;
        $exclusiveTaxesArray = [];

        $exclusiveTaxes = \Modules\Tax\Models\Tax::where([
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

        // Update billing record
        \Modules\Appointment\Models\EncounterPrescriptionBillingDetail::updateOrCreate(
            ['encounter_id' => $encounter_id],
            [
                'exclusive_tax'        => json_encode($exclusiveTaxesArray),
                'exclusive_tax_amount' => $exclusiveTaxAmount,
                'total_amount'         => $grandTotal,
            ]
        );


        $other_info = new EncounterOtherDetails;
        $other_info->encounter_id = $encounter_id;
        $other_info->user_id = $user_id;
        $other_info->other_details = $request->other_information;
        $other_info->save();


        return response()->json([
            'status' => true,
            'message' => __('appointment.encounter_dashboard_save'),
        ], 200);
    }
public function encounterDashboardDetail(Request $request)
{
    $encounter_id = $request->encounter_id;
    $encounterQuery = PatientEncounter::where('id',$encounter_id)
        ->with('user','clinic','doctor','medicalHistroy','prescriptions','EncounterOtherDetails','medicalReport','bodyChart','soap','bedAllocations.bedMaster.bedType');
    
    // Only eager load medicine relationship if Medicine class exists
    if (class_exists('Modules\Pharma\Models\Medicine')) {
        $encounterQuery->with('prescriptions.medicine');
    }
    
    $encounter_data = $encounterQuery->first();

    $encounter_data['bed_type_name'] = '';

    if ($encounter_data && $encounter_data->bedAllocations) {

        $encounter_data->bedAllocations->each(function($allocation) {
            $allocation->bed_type_name = optional($allocation->bedMaster->bedType)->type ?? null;
               $allocation->bed = $allocation->bedMaster->bed ?? null;

            unset($allocation->bedMaster);
            unset($allocation->bed_master);
            $allocation->setRelation('bedMaster', null);
            $allocation->offsetUnset('bedMaster');
            $allocation->offsetUnset('bed_master');
        });

        $encounter_data->makeHidden(['bedMaster', 'bed_master']);
    }

    if ($request->is('api/*') || $request->ajax()) {
        return response()->json([
            'success' => true,
            'data' => $encounter_data,
        ]);
    }

    $responseData = New EncounterDashboardDetailsResource($encounter_data);

    return response()->json([
        'status' => true,
        'data' => $responseData,
        'message' => __('appointment.encounter_dashboard_detail'),
    ], 200);
}

    public function billingList(Request $request){
        $perPage = $request->input('per_page', 10);

        $billing_records = BillingRecord::with('user','clinic','doctor','clinicservice','patientencounter');

        $billing_records = $billing_records->orderBy('updated_at', 'desc');

        $billing_records = $billing_records->paginate($perPage);

        $responseData = BillingRecordResource::collection($billing_records);

        if($request->filled('encounter_id')){

            $billing_data = BillingRecord::where('encounter_id',$request->encounter_id)->with(['user','clinic','doctor','clinicservice.doctor_service','billingItem'])->first();
            // dd($billing_data);
            if (!$billing_data) {
                return response()->json([
                    'status' => false,
                    'message' => __('appointment.billing_record_not_found'),
                ], 404);
            }

            $responseData = New BillingRecordDetailsResource($billing_data);

            return response()->json([
                'status' => true,
                'data' => $responseData,
                'message' => __('appointment.billing_record_details'),
            ], 200);
        }

        return response()->json([
            'status' => true,
            'data' => $responseData,
            'message' => __('appointment.billing_record_list'),
        ], 200);
    }

    public function saveBodychart(Request $request){

        $data = $request->except('file_url');
        $bodychart = AppointmentPatientBodychart::create($data);
        if ($request->hasFile('file_url')) {
            storeMediaFile($bodychart, $request->file('file_url'));
        }

        return response()->json([
            'status' => true,
            'message' => __('appointment.bodychart_save'),
        ], 200);
    }

    public function updateBodychart(Request $request, $id)
    {
        $image_handling = setting('image_handling');
        $record = AppointmentPatientBodychart::findOrFail($id);
        $data = $request->except('file_url');

        if ($record && $image_handling === 'Saved_image') {
            $record->update($data);
            if ($request->hasFile('file_url')) {
                storeMediaFile($record, $request->file('file_url'));
            }
        } else {
            $record = AppointmentPatientBodychart::create($data);
            if ($request->hasFile('file_url')) {
                storeMediaFile($record, $request->file('file_url'));
            }
        }
        return response()->json([
            'status' => true,
            'message' => __('appointment.bodychart_update'),
        ], 200);
    }

    public function deleteBodychart(Request $request, $id)
    {
        $data = AppointmentPatientBodychart::findOrFail($id);
        $data->delete();

        return response()->json([
            'status' => true,
            'message' => __('appointment.bodychart_delete'),
        ], 200);
    }

    public function bodyChartList(Request $request){
        $perPage = $request->input('per_page', 10);
        $encounter_id = $request->encounter_id;

        $bodychart_data = AppointmentPatientBodychart::with('patient_encounter')->where('encounter_id', $encounter_id);

        $bodychart_data = $bodychart_data->orderBy('updated_at', 'desc');

        $bodychart_data = $bodychart_data->paginate($perPage);

        $responseData = BodyChartResource::collection($bodychart_data);

        return response()->json([
            'status' => true,
            'data' => $responseData,
            'message' => __('appointment.bodychart_list'),
        ], 200);
    }

    public function encounterServiceDetails(Request $request){
        $encounter_id = $request->encounter_id;
        $encounter_data = PatientEncounter::where('id',$encounter_id)->with('user','clinic','doctor','appointment','medicalHistroy','prescriptions','EncounterOtherDetails','medicalReport')->first();

        $responseData = New EncounterServiceResource($encounter_data);

        return response()->json([
            'status' => true,
            'data' => $responseData,
            'message' => __('appointment.encounter_service_detail'),
        ], 200);
    }
}
