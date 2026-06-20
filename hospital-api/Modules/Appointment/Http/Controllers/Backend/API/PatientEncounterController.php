<?php

namespace Modules\Appointment\Http\Controllers\Backend\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Appointment\Models\PatientEncounter;
use Modules\Appointment\Transformers\EncounterResource;
use Modules\Appointment\Transformers\EncounterDetailsResource;
use PDF;
use Illuminate\Support\Facades\File;
use App\Models\Setting;
use Carbon\Carbon;

class PatientEncounterController extends Controller
{
    public function encounterList(Request $request)
    {
        // dd($request->all());        // Handle single encounter detail request
        if ($request->filled('id')) {
            $encounter_data = PatientEncounter::SetRole(auth()->user())->with('user', 'clinic', 'doctor', 'appointment','soap');
            
            // Filter by clinic_id if provided
            if($request->filled('clinic_id')){
                $encounter_data->where('clinic_id', $request->clinic_id);
            }

            // Filter by patient_id if provided (patient_id maps to user_id in encounters)
            if($request->filled('patient_id')){
                $encounter_data->where('user_id', $request->patient_id);
            }

            $encounter = $encounter_data->where('id', $request->id)->first();
            $responseData = new EncounterDetailsResource($encounter);

            return response()->json([
                'status' => true,
                'data' => $responseData,
                'message' => __('appointment.encounter_details'),
            ], 200);
        }

        // Handle encounter list request
        $perPage = $request->input('per_page', 10);

        $encounter_data = PatientEncounter::SetRole(auth()->user())->with('user', 'clinic', 'doctor', 'appointment','soap');

        // Filter by clinic_id if provided
        if($request->filled('clinic_id')){
            $encounter_data->where('clinic_id', $request->clinic_id);
        }

        // Filter by patient_id if provided (patient_id maps to user_id in encounters)
        if($request->filled('patient_id')){
            $encounter_data->where('user_id', $request->patient_id);
        }

        // Filter out completed encounters (status = 0 means completed, status = 1 means active/not completed)
        // $encounter_data->where('status', 1);

        $encounter = $encounter_data->paginate($perPage);
        $encounterCollection = EncounterResource::collection($encounter);

        return response()->json([
            'status' => true,
            'data' => $encounterCollection,
            'message' => __('appointment.encounter_list'),
        ], 200);
    }

    public function encounterInvoice(Request $request)
    {
        $id = $request->id;
        $data = PatientEncounter::where('id', $id)->with('user', 'clinic', 'doctor', 'medicalHistroy', 'prescriptions', 'EncounterOtherDetails', 'medicalReport', 'appointmentdetail', 'billingrecord')->first();

        $data['selectedProblemList'] =  $data->medicalHistroy()->where('type', 'encounter_problem')->get();
        $data['selectedObservationList'] = $data->medicalHistroy()->where('type', 'encounter_observations')->get();
        $data['notesList'] = $data->medicalHistroy()->where('type', 'encounter_notes')->get();
        $data['prescriptions'] = $data->prescriptions()->get();
        $data['other_details'] = $data->EncounterOtherDetails()->value('other_details') ?? null;
        $data['signature'] = optional(optional($data->doctor)->doctor)->Signature ?? null;
        $pdf = PDF::loadHTML(view("appointment::backend.encounter_template.invoice", ['data' => $data])->render())
            ->setOptions(['defaultFont' => 'sans-serif']);
        $baseDirectory = storage_path('app/public');
        $highestDirectory = collect(File::directories($baseDirectory))->map(function ($directory) {
            return basename($directory);
        })->max() ?? 0;
        $nextDirectory = intval($highestDirectory) + 1;
        while (File::exists($baseDirectory . '/' . $nextDirectory)) {
            $nextDirectory++;
        }
        $newDirectory = $baseDirectory . '/' . $nextDirectory;
        File::makeDirectory($newDirectory, 0777, true);

        $filename = 'invoice_' . $id . '.pdf';
        $filePath = $newDirectory . '/' . $filename;

        $pdf->save($filePath);

        $url = url('storage/' . $nextDirectory . '/' . $filename);
        return response()->json(['status' => true, 'link' => $url], 200);
    }

    public function downloadPrescription(Request $request)
    {
        $id = $request->id;

        $data = PatientEncounter::where('id', $id)->with('pharma', 'user', 'clinic', 'doctor', 'medicalHistroy', 'prescriptions', 'EncounterOtherDetails', 'medicalReport', 'appointmentdetail', 'billingrecord')->first();


        $data['prescriptions'] = $data->prescriptions()->get();
        $data['signature'] = optional(optional($data->doctor)->doctor)->Signature ?? null;
        $data['dateformate'] = Setting::where('name', 'date_formate')->value('val') ?? 'Y-m-d';
        $data['timeformate'] = Setting::where('name', 'time_formate')->value('val') ?? 'h:i A';
        $data['timezone'] = Setting::where('name', 'default_time_zone')->value('val') ?? 'UTC';
        $logo = null;
        $logoSetting = setting('logo'); // this should be "img/logo/logo.webp"

        $urlPath = parse_url($logoSetting, PHP_URL_PATH); 
        // "/storage/303/0LSvgrEYQdzqKfcZ1FnXErSL7uU6bxt1mOaGRbLB.png"

        // Map to actual local path
        $logoPath = public_path(ltrim($urlPath, '/')); 

        if (file_exists($logoPath) && is_readable($logoPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $logoPath);
            finfo_close($finfo);

            $extensions = [
                'image/jpeg' => 'jpeg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/svg+xml' => 'svg+xml',
                'image/webp' => 'webp'
            ];

            $extension = $extensions[$mimeType] ?? 'jpeg';

            $logoData = file_get_contents($logoPath);
            $logo = 'data:image/' . $extension . ';base64,' . base64_encode($logoData);
        }

        // If no logo from settings or failed to load, try default logo
        if (!$logo) {
            $defaultLogoPath = public_path('images/default.png');
            // dd($defaultLogoPath);
            if (file_exists($defaultLogoPath)) {
                try {
                    $logoData = file_get_contents($defaultLogoPath);
                    $logo = 'data:image/png;base64,' . base64_encode($logoData);
                } catch (\Exception $e) {
                    \Log::error('Default logo processing error: ' . $e->getMessage());
                }
            }
        }
        $data['logo'] = $logo;
        // $pdf = PDF::loadHTML(view("appointment::backend.encounter_template.prescription", ['data' => $data])->render())
        //     ->setOptions(['defaultFont' => 'sans-serif']);
        $pdf = PDF::loadHTML(view("appointment::backend.encounter_template.pharma_prescription", ['data' => $data])->render())
            ->setOptions(['defaultFont' => 'sans-serif']);
        $baseDirectory = storage_path('app/public');
        $highestDirectory = collect(File::directories($baseDirectory))->map(function ($directory) {
            return basename($directory);
        })->max() ?? 0;
        $nextDirectory = intval($highestDirectory) + 1;
        while (File::exists($baseDirectory . '/' . $nextDirectory)) {
            $nextDirectory++;
        }
        $newDirectory = $baseDirectory . '/' . $nextDirectory;
        File::makeDirectory($newDirectory, 0777, true);

        $filename = 'prescription_' . $id . '.pdf';
        $filePath = $newDirectory . '/' . $filename;

        $pdf->save($filePath);

        $url = url('storage/' . $nextDirectory . '/' . $filename);

        return response()->json(['status' => true, 'link' => $url], 200);

    }
}
