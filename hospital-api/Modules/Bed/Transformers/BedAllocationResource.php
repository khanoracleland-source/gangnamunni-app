<?php

namespace Modules\Bed\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BedAllocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient->full_name ?? null,
            'encounter_id' => $this->encounter_id,
            'bed_type_id' => $this->bed_type_id,
            'bed_type_name' => $this->bedType->type ?? null,
            'bed_master_id' => $this->bed_master_id,
            'bed_master_name' => $this->bedMaster->bed ?? null,
            'assign_date' => $this->assign_date ? Carbon::parse($this->assign_date)->format('d/m/Y') : null,
            'discharge_date' => $this->discharge_date ? Carbon::parse($this->discharge_date)->format('d/m/Y') : null,
            'description' => $this->description,
            'charge' => $this->charge,
            'per_bed_charge' => $this->per_bed_charge,
            'bed_payment_status' => $this->bed_payment_status,
            'temperature' => $this->temperature,
            'symptoms' => $this->symptoms,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'status' => $this->status,
        ];
    }
}
