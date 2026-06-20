<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Setting;
use Carbon\Carbon;

class PatientEncounterResource extends JsonResource
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
            $paymentStatus = match(optional($transaction)->payment_status) {
                1 => 'paid',
                0 => 'pending',
                default => 'Unknown',
            };
        }
        $timezone   = Setting::where('name', 'default_time_zone')->value('val') ?? 'UTC';
        $dateFormat = Setting::where('name', 'date_formate')->value('val') ?? 'Y-m-d';
        $timeFormat = Setting::where('name', 'time_formate')->value('val') ?? 'h:i A';

        $encounterDate = $this->encounter_date
            ? Carbon::parse($this->encounter_date)->timezone($timezone)
            : null;

        // prescription_date
        $prescriptionCreatedAt = optional($this->encounterPrescription->first())->created_at;
        $prescriptionDateFormatted = $prescriptionCreatedAt
            ? Carbon::parse($prescriptionCreatedAt)->timezone($timezone)->format("$dateFormat $timeFormat")
            : null;

        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'encounter_id' => $this->id,
            'encounter_date' => $encounterDate
                ? $encounterDate->format($dateFormat) . ' At ' . $encounterDate->format($timeFormat)
                : null,            // 'total_amount' => round((float) $this->encounterPrescription->sum('total_amount'), 2),
            // 'total_amount' => round((float) $this->billingDetail->total_amount ?? 0, 2),
            'total_amount' => round((float) (optional($this->billingDetail)->total_amount ?? 0), 2),
            'appointment_status' => optional($appointment)->status,
            'appointment_payment_status' => $paymentStatus,
            'prescription_status' => $this->prescription_status == 1 ? 'completed' : 'pending',
            'prescription_payment_status' => $this->prescription_payment_status == 1 ? 'paid' : 'pending',
            'user_name' => optional($this->user)->full_name,
            'user_email' => optional($this->user)->email,
            'user_image' => optional($this->user)->getFirstMediaUrl('profile_image'),
            'prescription_date' => $prescriptionDateFormatted,
            'medicine_ids' => $this->encounterPrescription->pluck('medicine_id') ?? null,
        ];
    }

}
