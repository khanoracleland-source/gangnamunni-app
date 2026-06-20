<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pharma\Transformers\MedicineCategoryResource;
use App\Models\Setting;
use Carbon\Carbon;

class PatientEncounterDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $appointment = $this->appointmentdetail;
        $transaction = optional($appointment)->appointmenttransaction;

        if ($appointment && $appointment->status === 'cancelled' && $appointment->advance_paid_amount != 0) {
            $paymentStatus = 'Advance Refund';
        } elseif ($appointment && $appointment->status === 'cancelled' && $transaction && $transaction->payment_status == 1) {
            $paymentStatus = 'Refunded';
        } elseif ($appointment && $appointment->status === 'cancelled') {
            $paymentStatus = '--';
        } else {
            $paymentStatus = match (optional($transaction)->payment_status) {
                1 => 'paid',
                0 => 'pending',
                default => 'Unknown',
            };
        }

        $timezone   = Setting::where('name', 'default_time_zone')->value('val') ?? 'UTC';
        $dateFormat = Setting::where('name', 'date_formate')->value('val') ?? 'Y-m-d';
        $timeFormat = Setting::where('name', 'time_formate')->value('val') ?? 'h:i A';

        // appointment_date_time
        $appointmentDateTime = optional($this->appointmentdetail)->start_date_time;
        $appointmentDateTimeFormatted = $appointmentDateTime
            ? Carbon::parse($appointmentDateTime)->timezone($timezone)->format("$dateFormat $timeFormat")
            : null;

        // prescription_date
        $prescriptionCreatedAt = optional($this->encounterPrescription->first())->created_at;
        $prescriptionDateFormatted = $prescriptionCreatedAt
            ? Carbon::parse($prescriptionCreatedAt)->timezone($timezone)->format("$dateFormat $timeFormat")
            : null;

        return [
            'id' => $this->id,
            'appointment_status' => optional($appointment)->status,
            'appointment_payment_status' => $paymentStatus,
            'prescription_status' => $this->prescription_status == 1 ? 'completed' : 'pending',
            'prescription_payment_status' => $this->prescription_payment_status == 1 ? 'paid' : 'pending',
            'patient_info' => [
                'name' => optional($this->user)->full_name,
                'phone' => optional($this->user)->mobile,
                'email' => optional($this->user)->email,
                'image' => optional($this->user)->profile_image ?: default_user_avatar(),
            ],
            'doctor_info' => [
                'name' => optional($this->doctor)->full_name,
                'phone' => optional($this->doctor)->mobile,
                'email' => optional($this->doctor)->email,
                'image' => optional($this->doctor)->profile_image ?: default_user_avatar(),
            ],
            'booking_info' => [
                'appointment_date_time' => $appointmentDateTimeFormatted,
                'service_name' => optional($this->appointmentdetail)->clinicservice->name,
                'prescription_date' => $prescriptionDateFormatted,
                'appointment_type' => optional($this->appointmentdetail)->clinicservice->type,
                'clinic_name' => optional($this->appointmentdetail)->cliniccenter->name,
                'encounter_status' => optional($this)->status,
                'payment_status' => optional($this->appointmentdetail->appointmenttransaction)->payment_status == 1 ? 'paid' : 'pending',
                'price' => optional($this->appointmentdetail)->total_amount,
                'encounter_id' => optional($this->encounterPrescription->first())->encounter_id
            ],
            'medicine_info' => $this->encounterPrescription->map(function ($prescription) {
                $quantity = $prescription->quantity ?? 1;
                $unitPrice = $quantity > 0 ? $prescription->medicine_price / $quantity : 0;

                $inclusiveTaxes = collect(json_decode($prescription->inclusive_tax, true) ?? [])->map(function ($tax) use ($unitPrice, $quantity) {
                    $amount = $tax['type'] === 'percent'
                        ? round(($unitPrice * $tax['value'] / 100) * $quantity, 2)
                        : round($tax['value'] * $quantity, 2);
                    return array_merge($tax, ['amount' => $amount]);
                });
                $exclusiveTaxes = collect(json_decode($prescription->billingDetail->exclusive_tax ?? '[]', true))->map(function ($tax) use ($unitPrice, $quantity) {
                    $amount = $tax['type'] === 'percent'
                        ? round(($unitPrice * $tax['value'] / 100) * $quantity, 2)
                        : round($tax['value'] * $quantity, 2);
                    return array_merge($tax, ['amount' => $amount]);
                });

                return [
                    'id' => $prescription->id,
                    'medicine_id' => optional($prescription->medicine)->id ?? null,
                    'name' => optional($prescription->medicine)->name . ' - ' . optional($prescription->medicine)->dosage,
                    'form' => optional($prescription->medicine?->form)->name,
                    'category' => new MedicineCategoryResource(optional(optional($prescription->medicine)->category)),
                    'dosage' => optional($prescription->medicine)->dosage,
                    'frequency' => $prescription->frequency,
                    'days' => $prescription->duration,
                    'quantity' => $prescription->quantity,
                    'instruction' => $prescription->instruction,
                    'expiry_date' => optional($prescription->medicine)->expiry_date,
                    'inclusive_tax' => $inclusiveTaxes,
                    'exclusive_tax' => $exclusiveTaxes,
                    'inclusive_tax_amount' => format_decimal($prescription->inclusive_tax_amount),
                    'medicine_price' => format_decimal(optional($prescription->medicine)->selling_price),
                    'total_amount' => format_decimal($prescription->total_amount),
                    'avilable_stock' => optional($prescription->medicine)->quntity,
                ];
            }),
            'payment_info' => $this->billingDetail ? (function () {
                $medicineTotal = optional($this->encounterPrescription)->sum('medicine_price');
                $inclusiveTaxTotals = [];

                foreach ($this->encounterPrescription as $prescription) {
                    $quantity = $prescription->quantity ?? 1;
                    $unitPrice = $quantity > 0 ? $prescription->medicine_price / $quantity : 0;
                    $taxes = json_decode($prescription->inclusive_tax, true) ?? [];

                    foreach ($taxes as $tax) {
                        $taxId = $tax['id'];
                        $amount = $tax['type'] === 'percent'
                            ? round(($unitPrice * $tax['value'] / 100) * $quantity, 2)
                            : round($tax['value'] * $quantity, 2);

                        if (!isset($inclusiveTaxTotals[$taxId])) {
                            $inclusiveTaxTotals[$taxId] = [
                                'id' => $tax['id'],
                                'type' => $tax['type'],
                                'title' => $tax['title'],
                                'value' => $tax['value'],
                                'amount' => 0,
                                'status' => $tax['status'],
                                'category' => $tax['category'],
                                'tax_type' => $tax['tax_type'],
                                'module_type' => $tax['module_type'],
                            ];
                        }

                        $inclusiveTaxTotals[$taxId]['amount'] += $amount;
                    }
                }

                return [
                    'medicine_total' => format_decimal(($medicineTotal ?? 0) + (array_sum(array_column($inclusiveTaxTotals, 'amount')) ?? 0)),
                    'inclusive_tax' => array_values($inclusiveTaxTotals),
                    'exclusive_tax' => json_decode($this->billingDetail->exclusive_tax, true) ?? [],
                    'exclusive_tax_amount' => format_decimal($this->billingDetail->exclusive_tax_amount),
                    'inclusive_tax_amount' => format_decimal(array_sum(array_column($inclusiveTaxTotals, 'amount'))),
                    'total_amount' => format_decimal($this->billingDetail->total_amount),
                ];
            })() : [
                'medicine_total' => format_decimal(0),
                'inclusive_tax' => [],
                'exclusive_tax' => [],
                'exclusive_tax_amount' => format_decimal(0),
                'inclusive_tax_amount' => format_decimal(0),
                'total_amount' => format_decimal(0),
            ],
        ];
    }
}
