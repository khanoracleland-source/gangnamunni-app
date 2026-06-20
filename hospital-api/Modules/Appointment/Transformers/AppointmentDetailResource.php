<?php

namespace Modules\Appointment\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointment\Trait\AppointmentTrait;
use Modules\Clinic\Transformers\DoctorReviewResource;
use Modules\Appointment\Transformers\BodyChartResource;
use Modules\Appointment\Transformers\BillingItemResource;
use Modules\Appointment\Models\BillingRecord;
use Carbon\Carbon;

use Modules\Bed\Models\BedAllocation;
use Modules\Bed\Models\BedMaster;
use Modules\Bed\Models\BedType;

class AppointmentDetailResource extends JsonResource
{
    use AppointmentTrait;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {

        $remaining_payable_amount = 0;
        $cacellationcharges = 0;
        $tax_data = $this->calculateTaxAmounts($this->appointmenttransaction ? $this->appointmenttransaction->tax_percentage : null, ($this->service_amount));
        $total_inclusive_tax = 0;
        $service_inclusive_tax=[];


        $total_inclusive_tax = $this->appointmenttransaction ? $this->appointmenttransaction->inclusive_tax_price : 0;

        $service_inclusive_tax = $this->appointmenttransaction && $this->appointmenttransaction->inclusive_tax
            ? $this->calculateTaxAmounts($this->appointmenttransaction->inclusive_tax, $this->service_price - $this->appointmenttransaction->discount_amount)
            : [];

        $total_tax = array_sum(array_column($tax_data, 'amount'));

        $totalAmount = $this->total_amount;


        $billingItems = optional(optional($this->patientEncounter)->billingrecord)->billingItem ?? collect();
        $billing_record = optional($this->patientEncounter)->billingrecord ?? collect();

        if ($billing_record instanceof BillingRecord && $billingItems && !empty($billingItems)) {
            $service_price = $billingItems->sum('total_amount');
        } else {
            $service_price = $this->service_amount;
        }
        $bed_allocation_details = [];
        if ($billing_record instanceof BillingRecord) {
            $discount_type = $billing_record->final_discount_type ?? '';
            $enable_discount = $billing_record->final_discount ?? 0;
            $total_discount = $billing_record->final_discount_value ?? 0;
            $final_total_Amount = $billing_record->final_total_amount ?? 0;
            $total_amount = $billing_record->total_amount ?? 0;

            // Get all bed allocations for this encounter (return as array)
            // Note: bedAllocations relationship is hasOne, so we need to query directly to get ALL beds
            $bed_allocations = [];
            
            // Always query the database for ALL bed allocations for this encounter
            // Don't rely on the hasOne relationship as it only returns one bed
            if ($this->patientEncounter && $this->patientEncounter->id) {
                $bed_allocations = BedAllocation::with('bedMaster', 'bedType')
                    ->where('encounter_id', $this->patientEncounter->id)
                    ->whereNull('deleted_at')
                    ->get();
                    
                if ($bed_allocations->isNotEmpty()) {
                    \Log::info('AppointmentDetailResource: Queried bed allocations from database', [
                        'count' => $bed_allocations->count(),
                        'encounter_id' => $this->patientEncounter->id,
                        'bed_allocation_ids' => $bed_allocations->pluck('id')->toArray(),
                    ]);
                }
            }
            
            // Fallback: try to get from billing record's bed_allocation_charges (JSON string)
            if (empty($bed_allocations) || (is_countable($bed_allocations) && count($bed_allocations) === 0)) {
                $bed_allocation_raw = $billing_record->bed_allocation_charges;
                if ($bed_allocation_raw) {
                    $decoded = is_string($bed_allocation_raw) ? json_decode($bed_allocation_raw, true) : $bed_allocation_raw;
                    if ($decoded) {
                        // Ensure it's an array
                        $bed_allocations = is_array($decoded) && isset($decoded[0]) ? $decoded : [$decoded];
                        \Log::info('AppointmentDetailResource: Using bed_allocation_charges from billing record', [
                            'count' => count($bed_allocations),
                        ]);
                    }
                }
            }
            
            // Convert to array format for processing
            $bed_allocation_details = $bed_allocations;
            
            \Log::info('AppointmentDetailResource: Bed allocation details', [
                'has_bed_allocation' => !empty($bed_allocation_details),
                'bed_allocation_type' => gettype($bed_allocation_details),
                'encounter_id' => optional($this->patientEncounter)->id,
            ]);
            $bed_charges = $billing_record->bed_charges ?? 0;

            if ($discount_type === 'percentage') {
                // $billing_discount_total_amount = $billingItems->sum('total_amount') * $total_discount / 100;
                $billing_discount_total_amount = ($total_amount * $total_discount) / 100;
            } else {
                $billing_discount_total_amount = $total_discount;
            }
            $totl_amount = $billingItems->sum('total_amount') + $bed_charges;
            // $totl_amount = $total_amount - $billing_discount_total_amount;
        }
        if ($billing_record instanceof BillingRecord) {
            $total_amount = $totl_amount;
            $subtotal = $total_amount;
            $tax_data = json_encode($tax_data);
            $tax_data = $this->calculateTaxAmounts($tax_data ?? null,   $subtotal);
            $total_tax = array_sum(array_column($tax_data, 'amount'));

            $totalAmount = $total_amount + $total_tax;

        } else {
            $subtotal = $this->service_amount;
        }
        if (optional($this->appointmenttransaction)->advance_payment_status == 1) {
            $remaining_payable_amount = $totalAmount - $this->advance_paid_amount;
        }

        if($this->status == 'cancelled'){
            $cacellationcharges = $this->cancellation_charge_amount ?? 0;
        }elseif($this->status == 'pending' && $this->appointmenttransaction && $this->appointmenttransaction->transaction_type == 'cash' ){
            $cacellationcharges =  0;
        }else{
            $cacellationcharges = $this->getCancellationCharges();
        }
        $payment_status = optional($this->appointmenttransaction)->payment_status;
        $advance_paid_amount = $this->advance_paid_amount ?? 0;
        $total_paid_amount = $totalAmount;
        $cancellation_charge_amount = $cacellationcharges ?? 0;

        if ($payment_status == 0) { // Unpaid
            $refund_amount = $advance_paid_amount - $cancellation_charge_amount;
        } else { // Paid
            $refund_amount = $total_paid_amount - $cancellation_charge_amount;
        }

        $refund_amount = max(0, $refund_amount);

        // Calculate inclusive tax for quick booking
        if ($this->is_inclusive_tax == 1) {
            $inclusive_tax = $this->calculate_inclusive_tax($this->service_price, $this->inclusive_tax);
            $calculated_inclusive_tax = $inclusive_tax['total_inclusive_tax'] ?? 0;
            $price = $inclusive_tax['taxes'] ?? [];
        } else {
            $calculated_inclusive_tax = 0;
            $price = [];
        }

        // Override total_inclusive_tax with calculated value if available
        if ($calculated_inclusive_tax > 0) {
            $total_inclusive_tax = $calculated_inclusive_tax;
        }

        $serviceAmount = $this->service_amount;
        $service_price = $service_price ;
        $price_before_discount = $price ?? [];
       // dd($this->user_id);
        
 
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date_time' => $this->start_date_time,
            'user_id' => $this->user_id,
            'user_name' => optional($this->user)->first_name . ' ' . optional($this->user)->last_name,
            'user_image' => optional($this->user)->profile_image,
            'user_email' => optional($this->user)->email,
            'user_phone' => optional($this->user)->mobile,
            'user_dob' => optional($this->user)->date_of_birth,
            'user_gender' => optional($this->user)->gender,
            'country_id' => optional($this->user)->country,
            'state_id' => optional($this->user)->state,
            'city_id' => optional($this->user)->city,
            'country_name' => optional(optional($this->user)->countries)->name,
            'state_name' => optional(optional($this->user)->states)->name,
            'city_name' => optional(optional($this->user)->cities)->name,
            'address' => optional($this->user)->address,
            'pincode' => optional($this->user)->pincode,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => optional($this->doctor)->first_name . ' ' . optional($this->doctor)->last_name,
            'doctor_image' => optional($this->doctor)->profile_image,
            'doctor_email' => optional($this->doctor)->email,
            'doctor_phone' => optional($this->doctor)->mobile,
            'clinic_id' => $this->clinic_id,
            'clinic_name' => optional($this->cliniccenter)->name,
            'clinic_image' => optional($this->cliniccenter)->file_url,
            'clinic_address' => optional($this->cliniccenter)->address,
            'clinic_phone' => optional($this->cliniccenter)->contact_number,
            'appointment_date'=> $this->appointment_date,
            'appointment_time'=> $this->appointment_time,
            'duration'=> $this->duration,
            'service_id'=> $this->service_id,
            'service_name'=> optional($this->clinicservice)->name,
            'category_name' => optional(optional($this->clinicservice)->category)->name,
            'service_image' => optional($this->clinicservice)->file_url,
            'service_type' => optional($this->clinicservice)->type,
            'is_video_consultancy' => optional($this->clinicservice)->is_video_consultancy,
            'appointment_extra_info' => $this->appointment_extra_info,
            'total_amount' => $totalAmount,
            'service_amount' => $this->service_amount,
            'service_price' => $this->service_price,
            'service_total' => $service_price ?? 0,
            'discount_type' => optional($this->appointmenttransaction)->discount_type,
            'discount_value' => optional($this->appointmenttransaction)->discount_value,
            'discount_amount' => optional($this->appointmenttransaction)->discount_amount,
            'subtotal' => $subtotal,
            'final_total_amount' => round($final_total_Amount ?? $totalAmount, 2),
            'billing_final_discount_type' => $discount_type ?? '',
            'enable_final_billing_discount' => $enable_discount ?? 0,
            'billing_final_discount_value' => $total_discount ?? 0,
            'billing_final_discount_amount' => $billing_discount_total_amount ?? 0,
            'total_tax' => $total_tax,
            'total_inclusive_tax' => $total_inclusive_tax,
            'service_inclusive_tax' => $service_inclusive_tax,
            'price' => $price,
            'tax_data' => $tax_data,
            'billing_items' => ($billingItems && $billingItems->isNotEmpty()) ? BillingItemResource::collection($billingItems) : [],
            'payment_status' => optional($this->appointmenttransaction)->payment_status,
            'is_enable_advance_payment' => optional($this->clinicservice)->is_enable_advance_payment,
            'advance_payment_amount' => $this->advance_payment_amount,
            'advance_payment_status' => optional($this->appointmenttransaction)->advance_payment_status,
            'advance_paid_amount' => $this->advance_paid_amount,
            'remaining_payable_amount' => $remaining_payable_amount,
            'medical_report' => getAttachmentArray($this->getMedia('file_url'), null),
            'encounter_id' => optional($this->patientEncounter)->id,
            'encounter_description' => optional($this->patientEncounter)->description,
            'encounter_status' => optional($this->patientEncounter)->status,
            'tax' => json_decode(optional($this->appointmenttransaction)->tax_percentage),
            'book_for_image'=> optional($this->otherPatient)->profile_image ?: default_user_avatar(),
            'book_for_name'=> optional($this->otherPatient)->full_name??null,

            'reviews' => ($review = $this->serviceRatingUnique($this->doctor_id)->first()) ? new DoctorReviewResource($review) : null,
            'cancellation_charge_amount' => $cacellationcharges,
            'reason' => $this->reason,
            'cancellation_charge' => $this->cancellation_charge ?? (int) setting('cancellation_charge'),
            'cancellation_type' => $this->cancellation_type ?? setting('cancellation_type'),
            'refund_amount' => $refund_amount,
            'refund_status' => $refund_amount > 0 ? 'completed' : null,
            'prescription_date_time' => optional(optional(optional($this->patientEncounter)->prescriptions)->first())->created_at
                ? optional(optional(optional($this->patientEncounter)->prescriptions)->first()->created_at)->format('Y-m-d H:i:s')
                : null, 
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'bed_details' => $this->formatBedDetails($bed_allocation_details), // Returns array of bed details
        ];
    }

    /**
     * Format bed details safely - returns an array of bed details
     */
    private function formatBedDetails($bed_allocation_details)
    {
        \Log::info('AppointmentDetailResource: formatBedDetails called', [
            'bed_allocation_details_type' => gettype($bed_allocation_details),
            'is_empty' => empty($bed_allocation_details),
        ]);
        
        if (!$bed_allocation_details || empty($bed_allocation_details)) {
            \Log::info('AppointmentDetailResource: formatBedDetails returning empty array - no bed allocation details');
            return [];
        }
        
        // Ensure it's an array
        $allocations = [];
        if (is_object($bed_allocation_details) && method_exists($bed_allocation_details, 'toArray')) {
            $allocations = $bed_allocation_details->toArray();
        } elseif (is_array($bed_allocation_details)) {
            $allocations = $bed_allocation_details;
        } else {
            // Single item, wrap in array
            $allocations = [$bed_allocation_details];
        }
        
        // Ensure allocations is an array of items
        if (!is_array($allocations) || empty($allocations)) {
            return [];
        }
        
        // If first item is not an array, it's a single item - wrap it
        if (!is_array($allocations[0]) && !is_object($allocations[0])) {
            $allocations = [$allocations];
        }
        
        $formatted_array = [];
        
        foreach ($allocations as $bed_allocation_details) {
            // Handle if it's an object
            if (is_object($bed_allocation_details) && method_exists($bed_allocation_details, 'toArray')) {
                $bed_allocation_details = $bed_allocation_details->toArray();
            }
            
            if (!is_array($bed_allocation_details) || empty($bed_allocation_details)) {
                continue;
            }
            
            // Safely access nested arrays
            $bed_master = $bed_allocation_details['bed_master'] ?? $bed_allocation_details['bedMaster'] ?? [];
            $bed_type = $bed_allocation_details['bed_type'] ?? $bed_allocation_details['bedType'] ?? [];
            
            // Handle if bed_master or bed_type are objects
            if (is_object($bed_master) && method_exists($bed_master, 'toArray')) {
                $bed_master = $bed_master->toArray();
            }
            if (is_object($bed_type) && method_exists($bed_type, 'toArray')) {
                $bed_type = $bed_type->toArray();
            }
            
            $formatted_array[] = [
                'bed_allocation_id' => $bed_allocation_details['id'] ?? null,
                'bed_id' => $bed_allocation_details['bed_master_id'] ?? null,
                'bed_type_id' => $bed_allocation_details['bed_type_id'] ?? null,
                'bed_name' => is_array($bed_master) ? ($bed_master['bed'] ?? null) : null,
                'bed_type' => is_array($bed_type) ? ($bed_type['type'] ?? null) : null,
                'charge' => $bed_allocation_details['charge'] ?? 0,
                'assign_date' => isset($bed_allocation_details['assign_date']) && $bed_allocation_details['assign_date'] ? Carbon::parse($bed_allocation_details['assign_date'])->format('d/m/Y') : null,
                'discharge_date' => isset($bed_allocation_details['discharge_date']) && $bed_allocation_details['discharge_date'] ? Carbon::parse($bed_allocation_details['discharge_date'])->format('d/m/Y') : null,
                'per_day_charge' => (double) (is_array($bed_master) ? ($bed_master['charges'] ?? 0) : 0),
                'bed_payment_status' => $bed_allocation_details['bed_payment_status'] ?? null,
            ];
        }
        
        \Log::info('AppointmentDetailResource: formatBedDetails returning formatted array', [
            'count' => count($formatted_array),
            'formatted_bed_details' => $formatted_array,
        ]);
        
        return $formatted_array;
    }
}
