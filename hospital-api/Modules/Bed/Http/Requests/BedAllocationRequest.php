<?php

namespace Modules\Bed\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Bed\Models\BedAllocation; // Adjust if needed
use Carbon\Carbon;

class BedAllocationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = request()->id ?? null;
        $rules = [
            'encounter_id' => 'required|exists:patient_encounters,id',
            'bed_type_id' => 'required|exists:bed_type,id',
            'room_no' => 'required|exists:bed_master,id',
            'assign_date' => 'required|date',
            'discharge_date' => 'nullable|date|after:assign_date',
            'description' => 'nullable|string|max:250',
            'temperature' => 'nullable|regex:/^\d{1,3}(\.\d{1,2})?$/',
            'weight' => 'nullable|regex:/^\d{1,3}(\.\d{1,2})?$/',
            'height' => 'nullable|regex:/^\d{1,3}(\.\d{1,2})?$/',
            'blood_pressure' => 'nullable',
            'heart_rate' => 'nullable|regex:/^\d{1,3}$/',
            'blood_group' => ['nullable', 'regex:/^(A|B|AB|O)[\+\-]$/'],
            'symptoms' => 'nullable|string',
            'notes' => 'nullable|string',
        ];

        if (multiVendor() == 1) {
            $rules['clinic_id'] = 'required|exists:clinic,id';
            $rules['clinic_admin_id'] = 'required|exists:users,id';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->has('room_no') || !$this->has('assign_date')) {
                return;
            }

            $roomId = $this->input('room_no');
            $assignDate = Carbon::parse($this->input('assign_date'))->format('Y-m-d');
            $dischargeDate = $this->has('discharge_date') && $this->input('discharge_date') 
                ? Carbon::parse($this->input('discharge_date'))->format('Y-m-d') 
                : null;
            // Get ID from route parameter (for edit) - Laravel resource routes use the resource name as parameter
            // Try to get from route parameters or request
            $id = null;
            if ($this->route()) {
                $routeParams = $this->route()->parameters();
                $id = $routeParams['bed-allocation'] 
                    ?? $routeParams['bed_allocation'] 
                    ?? $routeParams['id'] 
                    ?? $this->route('bed-allocation')
                    ?? $this->route('bed_allocation')
                    ?? $this->route('id');
            }
            // Fallback to request input if not found in route
            $id = $id ?? request()->id ?? null; // For update case

            // Check if bed is already occupied on the selected date range
            // Exclude allocations where encounter is closed (status = 0)
            // Two date ranges overlap if: start1 <= end2 AND start2 <= end1
            $conflict = BedAllocation::where('bed_master_id', $roomId)
                ->whereNull('deleted_at')
                ->when($id, fn($q) => $q->where('id', '!=', $id))
                ->with('patientEncounter') // Load encounter relationship to check status
                ->where(function($query) use ($assignDate, $dischargeDate) {
                    $query->where(function($q) use ($assignDate, $dischargeDate) {
                        // Case 1: Existing allocation has no discharge date (ongoing)
                        // If existing allocation has no discharge date, it's ongoing
                        // Check if new assign_date is on or after existing assign_date
                        $q->whereNull('discharge_date')
                          ->whereDate('assign_date', '<=', $dischargeDate ?? $assignDate);
                    })->orWhere(function($q) use ($assignDate, $dischargeDate) {
                        // Case 2: Both allocations have discharge dates
                        // Check if date ranges overlap
                        $q->whereNotNull('discharge_date')
                          ->whereDate('assign_date', '<=', $dischargeDate ?? $assignDate)
                          ->whereDate('discharge_date', '>=', $assignDate);
                    });
                })
                ->get()
                ->filter(function($allocation) {
                    // Exclude allocations where encounter is closed (status = 0)
                    // If encounter is closed, bed is available for new allocation
                    if ($allocation->patientEncounter && $allocation->patientEncounter->status == 0) {
                        return false; // Encounter is closed, so this allocation doesn't block the bed
                    }
                    return true; // Encounter is active, so this allocation blocks the bed
                })
                ->isNotEmpty();

            if ($conflict) {
                $validator->errors()->add('assign_date', 'This bed is already occupied on the selected date. Please choose a different date or bed.');
            }
        });
    }

    public function messages()
    {
        return [
            'encounter_id.required' => 'Patient encounter is required',
            'encounter_id.exists' => 'Invalid patient encounter selected',
            'bed_type_id.required' => 'Please select a bed type',
            'bed_type_id.exists' => 'Selected bed type is invalid',
            'room_no.required' => 'Please select a room',
            'room_no.exists' => 'Selected room is invalid',
            'assign_date.required' => 'Please select admission date',
            'assign_date.date' => 'Please enter a valid admission date',
            'discharge_date.date' => 'Please enter a valid discharge date',
            'discharge_date.after' => 'Discharge date must be after admission date',
            'description.max' => 'Description cannot exceed 250 characters',
            'temperature.regex' => 'Please enter a valid temperature (e.g., 98.6)',
            'weight.regex' => 'Please enter a valid weight (e.g., 70.5)',
            'height.regex' => 'Please enter a valid height (e.g., 170.5)',
            'heart_rate.regex' => 'Please enter a valid heart rate',
            'blood_group.regex' => 'Please enter a valid blood group (e.g., A+, B-, AB+, O-)',
            'clinic_id.required' => 'Clinic is required',
            'clinic_id.exists' => 'Invalid clinic selected',
            'clinic_admin_id.required' => 'Clinic admin is required',
            'clinic_admin_id.exists' => 'Invalid clinic admin selected',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            // Get the first error message as the main message, or use default
            $firstError = $validator->errors()->first();
            $message = $firstError ?: 'Validation failed';
            
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => $validator->errors()
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }
}
