<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Commission\Models\Commission;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $commissions = [];
        if (
            $this->user_type == 'pharma' ||
            ($this->getRoleNames() && $this->getRoleNames()->contains('pharma'))
        ) {
            $commissionModels = \Modules\Commission\Models\Commission::where('type', 'pharma_commission')->get();
            foreach ($commissionModels as $commissionModel) {
                $commissions[] = [
                    'name' => $commissionModel->title ?? '',
                    'type' => $commissionModel->commission_type ?? '',
                    'value' => (float)$commissionModel->commission_value,
                ];
            }
        }

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'date_of_birth'=>$this->date_of_birth ?? null,
            'gender' => $this->gender,
            'user_role' => $this->getRoleNames() ?? [],
            'api_token' => $this->api_token,
            'profile_image' => $this->avatar,
            'login_type' => $this->login_type,
            'profile_image' => $this->media->pluck('original_url')->first(),
            'address' => $this->address,
            'commissions' => $commissions,
            'country_id' => $this->country,
            'state_id' => $this->state,
            'city_id' => $this->city,
            'postal_code' => $this->pincode,
            'is_google_authentication' => $this->is_google_authentication,

        ];
    }
}
