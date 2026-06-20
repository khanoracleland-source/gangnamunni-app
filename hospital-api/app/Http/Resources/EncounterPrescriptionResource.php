<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EncounterPrescriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'user_id' => $this->user_id,
            'medicine_id' => $this->medicine_id,
            'quantity' => $this->quantity,
            'name' => $this->name,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'instruction' => $this->instruction,
            'inclusive_tax' => json_decode($this->inclusive_tax, true),
            'inclusive_tax_amount' => $this->inclusive_tax_amount,
            'medicine_price' => $this->medicine_price,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'medicine' => $this->medicine,
        ];
    }

}
