<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EncounterBillingDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'exclusive_tax' => json_decode($this->exclusive_tax, true),
            'exclusive_tax_amount' => $this->exclusive_tax_amount,
            'total_amount' => $this->total_amount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
