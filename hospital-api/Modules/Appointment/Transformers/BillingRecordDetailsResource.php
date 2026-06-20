<?php

namespace Modules\Appointment\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Modules\Appointment\Trait\AppointmentTrait;
use Modules\Appointment\Transformers\BillingItemResource;
use Modules\Bed\Models\BedAllocation;

class BillingRecordDetailsResource extends JsonResource
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
        $discount_type = $this->final_discount_type ?? '';
        $enable_discount = $this->final_discount ?? 0;
        $total_discount = $this->final_discount_value ?? 0;
        
        $amount_data = $this->billingItem ?? collect();
        $bed_allocation_raw = $this->bed_allocation_charges ?? BedAllocation::with('bedMaster','bedType')->where('encounter_id', optional($this->patientEncounter)->id)->first();
        
        $bed_allocation_details = collect(
                is_string($bed_allocation_raw)
                    ? json_decode($bed_allocation_raw, true)
                    : $bed_allocation_raw
            );
        $bed_charges = $this->bed_charges > 0 ? $this->bed_charges : $bed_allocation_details['charge'] ?? 0;
        
        // STEP 1: Calculate Service Amount (from billing items ONLY, NOT including bed charges)
        $serviceAmount = $amount_data->sum('total_amount') ?? 0;
        
        // STEP 2: Calculate Discount on Service Amount ONLY (not on bed charges)
        $billing_discount_total_amount = 0;
        if ($enable_discount == 1 && $total_discount > 0) {
            if ($discount_type == 'percentage') {
                $billing_discount_total_amount = $serviceAmount * $total_discount / 100;
            } else {
                $billing_discount_total_amount = $total_discount ?? 0;
            }
        }
        
        // Calculate amount after discount: Service Amount - Discount
        $amountAfterDiscount = $serviceAmount - $billing_discount_total_amount;
        
        // STEP 3: Calculate Tax on (Service Amount - Discount) - NOT including bed charges
        $tax_data = $this->calculateTaxAmounts($this->tax_data ?? null, $amountAfterDiscount);
        $total_tax = array_sum(array_column($tax_data, 'amount'));

        // STEP 4: Calculate Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
        $totalPayableAmount = $amountAfterDiscount + $total_tax;
        
        // STEP 5: Calculate Final Total: Total Payable Amount + Bed Charges (bed charges added without tax)
        $final_total = $totalPayableAmount + $bed_charges;
        
        // Sub total for display purposes (Service Amount + Bed Charges, before tax and discount)
        $sub_total = $serviceAmount + $bed_charges;

        $advance_payment_status = 0;
        $advance_paid_amount = 0;
        $remaining_payable_amount = 0;
        if (optional(optional(optional($this->patientencounter)->appointmentdetail)->appointmenttransaction)->advance_payment_status == 1) {
            $advance_payment_status = optional(optional(optional($this->patientencounter)->appointmentdetail)->appointmenttransaction)->advance_payment_status;
            $advance_paid_amount = optional(optional($this->patientencounter)->appointmentdetail)->advance_paid_amount;
            $remaining_payable_amount = $final_total - $advance_paid_amount;
        }

        $total_amount = $final_total;
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'user_id' => $this->user_id,
            'user_name' => optional($this->user)->first_name . ' ' . optional($this->user)->last_name,
            'user_address' => optional($this->user)->address,
            'user_gender' => optional($this->user)->gender,
            'user_dob' => optional($this->user)->date_of_birth,
            'clinic_id' => $this->clinic_id,
            'clinic_name' => optional($this->clinic)->name,
            'clinic_email' => optional($this->clinic)->email,
            'clinic_address' => optional($this->clinic)->address,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => optional($this->doctor)->first_name . ' ' . optional($this->doctor)->last_name,
            'doctor_email' => optional($this->doctor)->email,
            'doctor_mobile' => optional($this->doctor)->mobile,
            'service_id' => $this->service_id,
            'service_name' => optional($this->clinicservice)->name,
            'service_amount' => $serviceAmount,
            'total_amount' => $total_amount,
            'discount_amount' => $this->discount_amount,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'is_enable_advance_payment' => optional($this->clinicservice)->is_enable_advance_payment,
            'advance_payment_amount' => optional(optional($this->patientencounter)->appointmentdetail)->advance_payment_amount,
            'advance_payment_status' => $advance_payment_status,
            'advance_paid_amount' => $advance_paid_amount,
            'remaining_payable_amount' => $remaining_payable_amount,
            'billing_final_discount_type' => $discount_type,
            'enable_final_billing_discount' => $enable_discount,
            'billing_final_discount_value' => $total_discount,
            'billing_final_discount_amount' => $billing_discount_total_amount ?? 0,
            'sub_total' => $sub_total,
            'tax' => $tax_data,
            'billing_items' => BillingItemResource::collection($this->billingItem) ?? collect(),
            'total_tax' => $total_tax,
            'final_total' => $final_total,
            'date' => $this->date,
            'payment_status' => $this->payment_status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'bed_details' => $bed_allocation_details ? [
                'bed_allocation_id' => $bed_allocation_details['id'] ?? null,
                'bed_id' => $bed_allocation_details['bed_master_id'] ?? null,
                'bed_type_id' => $bed_allocation_details['bed_type_id'] ?? null,
                'bed_name' => $bed_allocation_details['bed_master']['bed'] ?? null,
                'bed_type' => $bed_allocation_details['bed_type']['type'] ?? null,
                'charge' => $bed_allocation_details['charge'] ?? 0,
                'assign_date' => isset($bed_allocation_details['assign_date']) && $bed_allocation_details['assign_date'] ? Carbon::parse($bed_allocation_details['assign_date'])->format('d/m/Y') : null,
                'discharge_date' => isset($bed_allocation_details['discharge_date']) && $bed_allocation_details['discharge_date'] ? Carbon::parse($bed_allocation_details['discharge_date'])->format('d/m/Y') : null,
                'per_day_charge' => (double) ($bed_allocation_details['bed_master']['charges'] ?? 0),
                'bed_payment_status' => $bed_allocation_details['bed_payment_status'] ?? null,
            ] : null,
        ];
    }
}
