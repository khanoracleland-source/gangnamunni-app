<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('appointment.medical_certificate') }}</title>
    <style>
        /* Add CSS styles here */
        .custom-table {
            border-collapse: collapse;
            width: 100%;
        }

        .custom-table th,
        .custom-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .custom-table thead {
            background-color: #f0f0f0;
        }

        .text-capitalize {
            text-transform: capitalize;
        }

        .text-right {
            text-align: right;
        }

        .badge {
            font-size: 12px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .badge-success {
            background-color: #5cb85c;
            color: #fff;
        }

        .badge-danger {
            background-color: #d9534f;
            color: #fff;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .d-flex {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .btn {
            padding: 6px 12px;
            font-size: 14px;
            line-height: 1.42857143;
            border-radius: 4px;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 3px;
        }

        .btn-primary {
            background-color: #337ab7;
            color: #fff;
            border-color: #2e6da4;
        }

        .text-info {
            color: #31708f;
        }

        .fs-4 {
            font-size: 1.25rem;
        }

        body {
            font-family: 'DejaVu Sans', 'Arial Unicode MS', sans-serif;
        }
    </style>
</head>

<body style="font-size: 16px; color: #000;">
    <b-row>
        <b-col sm="12">
            <div id="bill">
                @foreach ($data as $info)
                    <div class="row">
                        <div class="col-md-6">
                            <h2 class="mb-0">{{ $info['cliniccenter']['name'] ?? '--' }}</h2>
                            <h3 class="mb-0 font-weight-bold"> {{ setting('inv_prefix') ?: __('messages.invoice_id') }} <span
                                    class="text-primary">{{ $info['id'] ?? '--' }}</span></h3>
                            @php

                                $setting = App\Models\Setting::where('name', 'date_formate')->first();
                                $dateformate = $setting ? $setting->val : 'Y-m-d';
                                $setting = App\Models\Setting::where('name', 'time_formate')->first();
                                $timeformate = $setting ? $setting->val : 'h:i A';
                                $createdDate = date($dateformate, strtotime($info['appointment_date'] ?? '--'));
                                $createdTime = date($timeformate, strtotime($info['appointment_time'] ?? '--'));
                            @endphp
                            <h4 class="mb-0">
                                <span class="font-weight-bold"> {{ __('messages.appointment_at') }}: </span>
                                {{ $createdDate }}
                            </h4>
                            <h4 class="mb-0">
                                <span class="font-weight-bold"> {{ __('messages.appointment_time') }}: </span>
                                {{ $createdTime }}
                            </h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <p class="mb-0">{{ $info['cliniccenter']['address'] ?? '--' }}</p>
                            <p class="mb-0">{{ $info['cliniccenter']['email'] ?? '--' }}</p>
                            <p class="mb-0 mt-2">
                                {{ __('messages.payment_status') }}
                                @if ($info['appointmenttransaction']['payment_status'] == 1)
                                    <span class="badge badge-success">{{ __('messages.paid') }}</span>
                                @else
                                    <span class="badge badge-danger">{{ __('messages.unpaid') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <hr class="my-3" />
                    <div class="row">
                        <div class="col-md-12">
                            <h3>{{ __('messages.patient_detail') }}</h3>
                        </div>
                        <div class="col-md-12">
                            <table class="table table-sm custom-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('messages.patient_name') }}</th>
                                        <th>{{ __('messages.patient_gender') }}</th>
                                        <th>{{ __('messages.patient_dob') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="text-capitalize">
                                    <tr>
                                        <td>{{ $info['user']['first_name'] . '' . $info['user']['last_name'] ?? '--' }}
                                        </td>
                                        <td>{{ $info['user']['gender'] ?? '--' }}</td>
                                        @if ($info['user']['date_of_birth'] !== null)
                                            <td>{{ date($dateformate, strtotime($info['user']['date_of_birth'])) ?? '--' }}
                                            </td>
                                        @else
                                            <td>-</td>
                                        @endif
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <hr class="my-3" />
                    @if (isset($info['patient_encounter']))
                        <div class="row">
                            <div class="col-md-12">
                                <h3>{{ __('messages.service') }}</h3>
                            </div>
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table custom-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>{{ __('messages.sr_no') }}</th>
                                                <th>{{ __('messages.item_name') }}</th>
                                                <th style="text-align: right;">
                                                    {{ __('messages.price') }}
                                                </th>
                                                @php
                                                    // Check if any billing item has discount or inclusive tax to show the fields dynamically
                                                    $showDiscount = false;
                                                    $showInclusiveTax = false;
                                                    if (!empty($info['patient_encounter']['billingrecord']['billing_item'])) {
                                                        foreach ($info['patient_encounter']['billingrecord']['billing_item'] as $billingItemCheck) {
                                                            // Check discount
                                                            $checkDiscountValue = $billingItemCheck['discount_value'] ?? 0;
                                                            $checkItemId = $billingItemCheck['item_id'] ?? null;
                                                            
                                                            // If billing item doesn't have discount, check the service
                                                            if (empty($checkDiscountValue) || $checkDiscountValue == 0) {
                                                                if (!empty($checkItemId)) {
                                                                    $checkService = \Modules\Clinic\Models\ClinicsService::where('id', $checkItemId)->first();
                                                                    if ($checkService && !empty($checkService->discount_value) && $checkService->discount_value > 0) {
                                                                        $checkDiscountValue = $checkService->discount_value;
                                                                    }
                                                                }
                                                            }
                                                            
                                                            if (!empty($checkDiscountValue) && $checkDiscountValue > 0) {
                                                                $showDiscount = true;
                                                            }
                                                            
                                                            // Check inclusive tax
                                                            $checkInclusiveTax = $billingItemCheck['inclusive_tax_amount'] ?? 0;
                                                            
                                                            // If billing item doesn't have inclusive tax, check the service
                                                            if ($checkInclusiveTax == 0 && !empty($checkItemId)) {
                                                                $checkService = \Modules\Clinic\Models\ClinicsService::where('id', $checkItemId)->first();
                                                                if ($checkService && $checkService->is_inclusive_tax == 1 && !empty($checkService->inclusive_tax)) {
                                                                    $checkInclusiveTaxJson = json_decode($checkService->inclusive_tax, true);
                                                                    if (is_array($checkInclusiveTaxJson)) {
                                                                        foreach ($checkInclusiveTaxJson as $tax) {
                                                                            if (isset($tax['status']) && $tax['status'] == 1) {
                                                                                $checkInclusiveTax = 1; // Just mark that it exists
                                                                                break;
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                            
                                                            if (!empty($checkInclusiveTax) && $checkInclusiveTax > 0) {
                                                                $showInclusiveTax = true;
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                @if($showDiscount)
                                                    <th style="text-align: right;">{{ __('service.discount') }}</th>
                                                @endif
                                                @if($showInclusiveTax)
                                                    <th style="text-align: right;">{{ __('service.inclusive_tax') }}</th>
                                                @endif
                                                <th style="text-align: right;">{{ __('messages.total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $index = 1; @endphp
                                            @foreach ($info['patient_encounter']['billingrecord']['billing_item'] as $billingItem)
                                                @php
                                                    $quantity = $billingItem['quantity'] ?? 1;
                                                    $item_id = $billingItem['item_id'] ?? null;
                                                    
                                                    // Get base service price from service charges (actual base price, not calculated amount)
                                                    $service_price = $billingItem['service_amount'] ?? 0; // Default fallback
                                                    $service = null;
                                                    if (!empty($item_id)) {
                                                        $service = \Modules\Clinic\Models\ClinicsService::where('id', $item_id)->first();
                                                        if ($service) {
                                                            // Get base price from service charges (this is the actual base price)
                                                            $service_price = $service->charges ?? $service_price;
                                                        }
                                                    }
                                                    
                                                    // Always recalculate inclusive tax on base price to ensure correct calculation
                                                    $inclusive_tax = 0;
                                                    if ($service && $service->is_inclusive_tax == 1 && !empty($service->inclusive_tax)) {
                                                        $inclusiveTaxJson = json_decode($service->inclusive_tax, true);
                                                        if (is_array($inclusiveTaxJson)) {
                                                            foreach ($inclusiveTaxJson as $tax) {
                                                                if (isset($tax['status']) && $tax['status'] == 1) {
                                                                    if (isset($tax['type']) && $tax['type'] == 'fixed') {
                                                                        $inclusive_tax += $tax['value'] ?? 0;
                                                                    } elseif (isset($tax['type']) && $tax['type'] == 'percent') {
                                                                        // Calculate inclusive tax on base price (per unit)
                                                                        $inclusive_tax += ($service_price * ($tax['value'] ?? 0)) / 100;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Only use stored value as fallback if we couldn't recalculate
                                                    if ($inclusive_tax == 0 && (empty($item_id) || !isset($service) || ($service && $service->is_inclusive_tax != 1))) {
                                                        $inclusive_tax = $billingItem['inclusive_tax_amount'] ?? 0;
                                                    }
                                                    
                                                    // Get discount - check billing item first, then service
                                                    $discount_value = $billingItem['discount_value'] ?? 0;
                                                    $discount_type = $billingItem['discount_type'] ?? null;
                                                    $discount_status = $billingItem['discount_status'] ?? null;
                                                    
                                                    // If billing item doesn't have discount, check the service for discount
                                                    if (empty($discount_value) || $discount_value == 0) {
                                                        if (!empty($item_id)) {
                                                            $service = \Modules\Clinic\Models\ClinicsService::where('id', $item_id)->first();
                                                            if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                                                $discount_value = $service->discount_value;
                                                                $discount_type = $service->discount_type;
                                                                $discount_status = 1;
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Calculation order: Base Service - Discount + Inclusive Tax
                                                    // Step 1: Calculate base service total (base price × quantity)
                                                    $baseServiceTotal = $service_price * $quantity;
                                                    
                                                    // Step 2: Calculate service discount amount (applied to base price only, matching frontend)
                                                    $serviceDiscountAmount = 0;
                                                    $unitDiscountAmount = 0; // Initialize for use in tax calculation
                                                    $discount_display = '--';
                                                    
                                                    if (!empty($discount_value) && $discount_value > 0) {
                                                        // If discount_status doesn't exist, default to 1 if discount exists
                                                        if ($discount_status === null) {
                                                            $discount_status = 1;
                                                        }
                                                        
                                                        // Apply discount only if status is 1 (active)
                                                        if ($discount_status == 1) {
                                                            if ($discount_type == 'percentage') {
                                                                // Percentage discount on base price per unit (matching frontend)
                                                                $unitDiscountAmount = ($service_price * $discount_value) / 100;
                                                                $serviceDiscountAmount = $unitDiscountAmount * $quantity;
                                                                $discount_display = '(' . $discount_value . '%)';
                                                            } else {
                                                                // Fixed discount per quantity
                                                                $unitDiscountAmount = $discount_value;
                                                                $serviceDiscountAmount = $discount_value * $quantity;
                                                                $discount_display = Currency::format($discount_value);
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Step 3: Calculate amount after discount
                                                    $amountAfterDiscount = $baseServiceTotal - $serviceDiscountAmount;
                                                    
                                                    // Step 4: Recalculate inclusive tax on discounted amount (matching service_list.blade.php)
                                                    // Tax should be calculated on (base - discount), not on base
                                                    // Calculation: Base $100 - Discount $5 = $95, then tax 10% on $95 = $9.5
                                                    $unitPriceAfterDiscount = $amountAfterDiscount / $quantity;
                                                    $unitInclusiveTax = 0;
                                                    
                                                    // Recalculate inclusive tax on discounted amount if service has inclusive tax enabled
                                                    if ($service && $service->is_inclusive_tax == 1 && !empty($service->inclusive_tax)) {
                                                        $inclusiveTaxJson = json_decode($service->inclusive_tax, true);
                                                        if (is_array($inclusiveTaxJson)) {
                                                            foreach ($inclusiveTaxJson as $tax) {
                                                                if (isset($tax['status']) && $tax['status'] == 1) {
                                                                    if (isset($tax['type']) && $tax['type'] == 'fixed') {
                                                                        $unitInclusiveTax += $tax['value'] ?? 0;
                                                                    } elseif (isset($tax['type']) && $tax['type'] == 'percent') {
                                                                        // Calculate tax on discounted amount (per unit)
                                                                        $unitInclusiveTax += ($unitPriceAfterDiscount * ($tax['value'] ?? 0)) / 100;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    } else {
                                                        // Use existing inclusive tax amount if service doesn't have inclusive tax enabled
                                                        $unitInclusiveTax = $inclusive_tax;
                                                    }
                                                    
                                                    // Calculate total inclusive tax (per unit × quantity)
                                                    $inclusive_tax_total = $unitInclusiveTax * $quantity;
                                                    
                                                    // Step 5: Final total: (Base - Discount) + Inclusive Tax (calculated on discounted amount)
                                                    $final_total = $amountAfterDiscount + $inclusive_tax_total;
                                                @endphp
                                                <tr>
                                                    <td>{{ $index }}</td>
                                                    <td>
                                                        {{ $billingItem['clinicservice']['name'] ?? ($billingItem['item_name'] ?? '--') }}
                                                    </td>
                                                    <td style="text-align: right;">
                                                        {{ Currency::format($service_price) }}
                                                        @if($quantity > 1)
                                                            × {{ $quantity }}
                                                        @endif
                                                    </td>
                                                    @if($showDiscount)
                                                        <td style="text-align: right;">
                                                            {{ $serviceDiscountAmount > 0 ? Currency::format($serviceDiscountAmount) : '--' }}
                                                        </td>
                                                    @endif
                                                    @if($showInclusiveTax)
                                                        <td style="text-align: right;">
                                                            {{ $inclusive_tax_total > 0 ? Currency::format($inclusive_tax_total) : '--' }}
                                                        </td>
                                                    @endif
                                                    <td style="text-align: right;">
                                                        {{ Currency::format($final_total) ?? '--' }}
                                                    </td>
                                                </tr>
                                                @php $index++ @endphp
                                            @endforeach
                                        </tbody>
                                        @if ($info['clinicservice'] == null)
                                            <tbody>
                                                <tr>
                                                    <td colspan="{{ 3 + ($showDiscount ? 1 : 0) + ($showInclusiveTax ? 1 : 0) + 1 }}">
                                                        <h4 class="text-primary mb-0">
                                                            {{ __('messages.no_record_found') }}
                                                        </h4>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    {{-- Bed Allocation Details --}}
                    @php
                        // Get bed allocations for this appointment
                        $currentBedAllocations = collect();
                        if (isset($info['patient_encounter']['id']) && isset($bedAllocationsByEncounter[$info['patient_encounter']['id']])) {
                            $currentBedAllocations = $bedAllocationsByEncounter[$info['patient_encounter']['id']];
                        }
                    @endphp
                    @if ($currentBedAllocations->isNotEmpty())
                        <hr class="my-3" />
                        <div class="row">
                            <div class="col-md-12">
                                <h3>Bed Allocation</h3>
                            </div>
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table custom-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>{{ __('messages.patient_name') }}</th>
                                                <th>Bed Type</th>
                                                <th>Room/Bed</th>
                                                <th>Assign Date</th>
                                                <th>Discharge Date</th>
                                                <th style="text-align: right;">Per Day Charge</th>
                                                <th style="text-align: right;">Total Charge</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($currentBedAllocations as $allocation)
                                                @php
                                                    $patientName = '--';
                                                    if (isset($allocation['patient'])) {
                                                        $patient = $allocation['patient'];
                                                        $patientName = ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '');
                                                    }
                                                    
                                                    $bedTypeName = '--';
                                                    if (isset($allocation['bed_type'])) {
                                                        $bedType = $allocation['bed_type'];
                                                        $bedTypeName = $bedType['type'] ?? '--';
                                                    } elseif (isset($allocation['bedType'])) {
                                                        $bedType = $allocation['bedType'];
                                                        $bedTypeName = is_array($bedType) ? ($bedType['type'] ?? '--') : ($bedType->type ?? '--');
                                                    }
                                                    
                                                    $bedName = '--';
                                                    $perBedCharge = 0;
                                                    if (isset($allocation['bed_master'])) {
                                                        $bedMaster = $allocation['bed_master'];
                                                        $bedName = is_array($bedMaster) ? ($bedMaster['bed'] ?? '--') : ($bedMaster->bed ?? '--');
                                                        $perBedCharge = is_array($bedMaster) ? ($bedMaster['charges'] ?? 0) : ($bedMaster->charges ?? 0);
                                                    } elseif (isset($allocation['bedMaster'])) {
                                                        $bedMaster = $allocation['bedMaster'];
                                                        $bedName = is_array($bedMaster) ? ($bedMaster['bed'] ?? '--') : ($bedMaster->bed ?? '--');
                                                        $perBedCharge = is_array($bedMaster) ? ($bedMaster['charges'] ?? 0) : ($bedMaster->charges ?? 0);
                                                    }
                                                    
                                                    $assignDate = isset($allocation['assign_date']) ? date($dateformate, strtotime($allocation['assign_date'])) : '--';
                                                    $dischargeDate = isset($allocation['discharge_date']) && $allocation['discharge_date'] ? date($dateformate, strtotime($allocation['discharge_date'])) : '--';
                                                    
                                                    $charge = $allocation['charge'] ?? 0;
                                                    
                                                    // Calculate days
                                                    $days = 1;
                                                    if (isset($allocation['assign_date']) && isset($allocation['discharge_date']) && $allocation['discharge_date']) {
                                                        $days = max(1, \Carbon\Carbon::parse($allocation['assign_date'])->diffInDays(\Carbon\Carbon::parse($allocation['discharge_date'])));
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>{{ $patientName }}</td>
                                                    <td>{{ $bedTypeName }}</td>
                                                    <td>{{ $bedName }}</td>
                                                    <td>{{ $assignDate }}</td>
                                                    <td>{{ $dischargeDate }}</td>
                                                    <td style="text-align: right;">{{ $perBedCharge ? Currency::format($perBedCharge) . ' × ' . $days : '--' }}</td>
                                                    <td style="text-align: right;">{{ Currency::format($charge) ?? '--' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <hr class="my-3" />

                    @php
                        // Calculate Service Total from billing items - Matching frontend appointment_detail.blade.php logic
                        // Service Amount = BASE PRICE ONLY (without inclusive tax, without discounts)
                        $totalServiceAmount = 0; // Base service price only (for display and tax calculation)
                        $totalServiceDiscount = 0; // Service-level discounts (tracked separately)
                        
                        if (!empty(optional(optional($info['patient_encounter'])['billingrecord'])['billing_item'])) {
                            foreach (optional(optional($info['patient_encounter'])['billingrecord'])['billing_item'] as $item) {
                                $quantity = $item['quantity'] ?? 1;
                                $item_id = $item['item_id'] ?? null;
                                
                                // Get base service price - matching frontend: use service_amount directly
                                $unitPrice = $item['service_amount'] ?? 0; // Base price per unit (matching frontend)
                                
                                // Service price total (base price only, without inclusive tax)
                                $itemBasePriceTotal = $unitPrice * $quantity;
                                
                                // Get discount information
                                $itemDiscountValue = $item['discount_value'] ?? null;
                                $itemDiscountType = $item['discount_type'] ?? 'percentage';
                                $itemDiscountStatus = $item['discount_status'] ?? null;
                                
                                // If billing item has no discount, check service for discount
                                if (empty($itemDiscountValue) || $itemDiscountValue == 0) {
                                    if (!empty($item_id)) {
                                        $service = \Modules\Clinic\Models\ClinicsService::where('id', $item_id)->first();
                                        if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                            $itemDiscountValue = $service->discount_value;
                                            $itemDiscountType = $service->discount_type ?? 'percentage';
                                            $itemDiscountStatus = 1;
                                        }
                                    }
                                }
                                
                                // Calculate service discount amount (applied to base price only)
                                $itemDiscountAmount = 0;
                                if (!empty($itemDiscountValue) && $itemDiscountValue > 0) {
                                    if ($itemDiscountStatus === null) {
                                        $itemDiscountStatus = 1;
                                    }
                                    
                                    if ($itemDiscountStatus == 1) {
                                        if ($itemDiscountType == 'percentage') {
                                            $itemDiscountAmount = ($itemBasePriceTotal * $itemDiscountValue) / 100;
                                        } else {
                                            $itemDiscountAmount = $itemDiscountValue * $quantity;
                                        }
                                    }
                                }
                                
                                // Add to total service amount (base price only)
                                $totalServiceAmount += $itemBasePriceTotal;
                                
                                // Add to total service discount
                                $totalServiceDiscount += $itemDiscountAmount;
                            }
                        } else {
                            // Fallback: For appointments without encounter, use service_amount (base price only)
                            $totalServiceAmount = $info['service_amount'] ?? 0;
                            $totalServiceDiscount = 0;
                        }
                        
                        $transaction = optional(optional($info['patient_encounter'])['billingrecord'])
                            ? optional(optional($info['patient_encounter'])['billingrecord'])
                            : null;
                        
                        // Get bed charges
                        $bed_charges = 0;
                        if ($transaction && isset($transaction['bed_charges']) && $transaction['bed_charges'] > 0) {
                            $bed_charges = $transaction['bed_charges'];
                        } elseif (isset($info['patient_encounter']['id'])) {
                            $bed_charges = \Modules\Bed\Models\BedAllocation::where('encounter_id', $info['patient_encounter']['id'])
                                ->whereNull('deleted_at')
                                ->sum('charge') ?? 0;
                        }
                        
                        // Overall discount (final_discount) - Apply only to base service amount (matching frontend logic)
                        $overallDiscountAmount = 0;
                        if ($transaction && isset($transaction['final_discount']) && $transaction['final_discount'] == 1) {
                            $discountType = $transaction['final_discount_type'] ?? 'percentage';
                            $discountValue = $transaction['final_discount_value'] ?? 0;
                            
                            if ($discountType === 'percentage') {
                                $overallDiscountAmount = ($totalServiceAmount * $discountValue) / 100;
                            } else {
                                $overallDiscountAmount = $discountValue;
                            }
                        }
                        
                        // Tax calculation logic:
                        // Tax should be calculated on (Service Amount - Overall Discount) only
                        // Do NOT include service-level discounts in tax calculation base
                        $amountForTaxCalculation = $totalServiceAmount - $overallDiscountAmount;
                        
                        // Ensure amount is not negative
                        $amountForTaxCalculation = max(0, $amountForTaxCalculation);
                        
                        // For display: Use totalServiceAmount as service_total_amount
                        $service_total_amount = $totalServiceAmount;
                        $encounter_discount_amount = $overallDiscountAmount;
                        
                        // Initialize tax
                        $totalTax = 0;
                    @endphp

                    @php
                        // Check for tax data from multiple sources
                        $taxDataForCalculation = null;
                        $taxData = null;
                        
                        // First, try to get tax_data from transaction (billing record)
                        if ($transaction && isset($transaction['tax_data']) && !empty($transaction['tax_data'])) {
                            $taxDataFromRecord = json_decode($transaction['tax_data'], true);
                            if (is_array($taxDataFromRecord) && !empty($taxDataFromRecord)) {
                                $taxDataForCalculation = $taxDataFromRecord;
                                $taxData = $taxDataFromRecord;
                            }
                        }
                        
                        // If not found, try from appointmenttransaction tax_percentage
                        if ($taxDataForCalculation === null && isset($info['appointmenttransaction']['tax_percentage']) && $info['appointmenttransaction']['tax_percentage'] !== null) {
                            $tax = $info['appointmenttransaction']['tax_percentage'];
                            $taxData = json_decode($tax, true);
                            if (is_array($taxData) && !empty($taxData)) {
                                $taxDataForCalculation = $taxData;
                            }
                        }
                        
                        // Calculate tax if we have tax data or amount for calculation
                        if ($taxDataForCalculation !== null || $amountForTaxCalculation > 0) {
                            // Tax (Exclusive) is calculated using getBookingTaxamount function (matching frontend)
                            $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxDataForCalculation);
                            $totalTax = $taxDetails['total_tax_amount'] ?? 0;
                            $taxBreakdown = $taxDetails['tax_details'] ?? [];
                            
                            // If tax_data exists but breakdown is empty, use taxData directly
                            if (is_array($taxData) && empty($taxBreakdown)) {
                                $taxBreakdown = $taxData;
                            }
                            
                            // Calculate total payable amount: (Base Service Amount - Overall Discount) + Tax (WITHOUT bed charges)
                            $totalPayableAmount = $amountForTaxCalculation + $totalTax;
                            
                            // Final Total: Total Payable Amount + Bed Charges
                            $final_total_amount = $totalPayableAmount + $bed_charges;
                            
                            $shouldCalculateTax = true;
                        } else {
                            // No tax data, set defaults
                            $totalTax = 0;
                            $taxBreakdown = [];
                            $totalPayableAmount = $amountForTaxCalculation;
                            $final_total_amount = $totalPayableAmount + $bed_charges;
                            $shouldCalculateTax = false;
                        }
                        @endphp
                    
                    @if ($shouldCalculateTax && !empty($taxBreakdown))

                        <div class="row">
                            <div class="col-md-12">
                                <h3>{{ __('report.lbl_tax_details') }}</h3>
                            </div>
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table custom-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th colspan="3">{{ __('messages.sr_no') }}</th>

                                                <th colspan="3">{{ __('messages.tax_name') }}</th>

                                                <th colspan="2">
                                                    <div class="text-right">
                                                        {{ __('messages.charges') }}
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        @php
                                            $index = 1;
                                        @endphp
                                        @foreach ($taxBreakdown as $indexKey => $taxPercentage)
                                            @php
                                                // Use tax details from getBookingTaxamount function
                                                $taxTitle = $taxPercentage['tax_name'] ?? ($taxPercentage['title'] ?? ($taxPercentage['name'] ?? __('messages.tax')));
                                                $taxType = $taxPercentage['tax_type'] ?? ($taxPercentage['type'] ?? 'percent');
                                                
                                                // Get tax value - check multiple possible keys
                                                $taxValue = 0;
                                                if (isset($taxPercentage['tax_value']) && $taxPercentage['tax_value'] > 0) {
                                                    $taxValue = $taxPercentage['tax_value'];
                                                } elseif (isset($taxPercentage['value']) && $taxPercentage['value'] > 0) {
                                                    $taxValue = $taxPercentage['value'];
                                                } elseif (isset($taxPercentage['percent']) && $taxPercentage['percent'] > 0) {
                                                    $taxValue = $taxPercentage['percent'];
                                                }
                                                
                                                // Check if tax is exclusive and status is on
                                                // tax_scope comes from getBookingTaxamount which maps tax_type/tax_scope from original data
                                                $taxScope = $taxPercentage['tax_scope'] ?? null;
                                                
                                                // Get status from original tax data if available
                                                $taxStatus = 1; // Default to 1 (on)
                                                if (is_array($taxDataForCalculation)) {
                                                    // Try to find matching tax in original data by name or title
                                                    foreach ($taxDataForCalculation as $originalTax) {
                                                        $originalTitle = $originalTax['title'] ?? ($originalTax['name'] ?? '');
                                                        if ($originalTitle == $taxTitle || 
                                                            (isset($originalTax['value']) && $originalTax['value'] == $taxValue)) {
                                                            $taxStatus = $originalTax['status'] ?? 1;
                                                            break;
                                                        }
                                                    }
                                                } elseif (isset($taxPercentage['status'])) {
                                                    $taxStatus = $taxPercentage['status'];
                                                }
                                                
                                                // Show percentage only for exclusive taxes with status on
                                                $showPercentage = false;
                                                if ($taxScope == 'exclusive' && $taxStatus == 1) {
                                                    $showPercentage = true;
                                                }
                                                
                                                // Use tax_amount from getBookingTaxamount if available, otherwise calculate
                                                if (isset($taxPercentage['tax_amount']) && $taxPercentage['tax_amount'] > 0) {
                                                    $tax_amount = $taxPercentage['tax_amount'];
                                                } else {
                                                    // Fallback calculation
                                                    if ($taxType == 'fixed') {
                                                        $tax_amount = $taxValue;
                                                    } else {
                                                        // Tax calculated on amountForTaxCalculation (base service amount - overall discount)
                                                        $tax_amount = ($amountForTaxCalculation * $taxValue) / 100;
                                                    }
                                                }
                                            @endphp
                                            <tbody>
                                                <tr>
                                                    <td colspan="3">{{ $index }}</td>

                                                    <td colspan="3">
                                                            {{ $taxTitle }}
                                                        @if ($showPercentage && $taxValue > 0)
                                                            @if ($taxType == 'fixed')
                                                                ({{ Currency::format($taxValue) }})
                                                        @else
                                                                ({{ number_format($taxValue, 2) }}%)
                                                            @endif
                                                        @endif
                                                    </td>

                                                    <td colspan="2" style="text-align: right;">
                                                        {{ Currency::format($tax_amount) ?? '--' }}
                                                    </td>
                                                </tr>
                                                @php $index++ @endphp
                                            </tbody>
                                        @endforeach

                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-12">
                            <h3>{{ __('report.lbl_taxes') }}</h3>
                        </div>
                        <div class="col-md-12">
                            <div class="table-responsive">
                                <table class="table custom-table">
                                    {{-- <thead class="thead-light">
                                        <tr>
                                            <th colspan="3"> </th>

                                            <th colspan="3"> </th>

                                            <th colspan="2">
                                                <div class="text-right">
                                                    {{ __('messages.charges') }}
                                                </div>
                                            </th>
                                        </tr>
                                    </thead> --}}

                                    <thead class="thead-light">
                                        <tr>
                                            <th colspan="6"> </th>
                                            <th colspan="2">
                                                <div class="text-right">
                                                    {{ __('messages.charges') }}
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>

                                    @php
                                        // Variables are already calculated above:
                                        // - $service_total_amount: Base service amount only
                                        // - $encounter_discount_amount: Overall discount amount
                                        // - $totalTax: Tax amount (calculated on amountForTaxCalculation)
                                        // - $totalPayableAmount: (Base Service Amount - Overall Discount) + Tax
                                        // - $final_total_amount: Total Payable Amount + Bed Charges
                                        
                                        // If totalPayableAmount is not set, calculate it
                                        if (!isset($totalPayableAmount)) {
                                            $totalPayableAmount = $amountForTaxCalculation + $totalTax;
                                        }
                                        
                                        // If final_total_amount is not set, calculate it
                                        if (!isset($final_total_amount)) {
                                        $final_total_amount = $totalPayableAmount + $bed_charges;
                                        }
                                        
                                        $remaining_payable_amount = $final_total_amount - ($info['advance_paid_amount'] ?? 0);
                                    @endphp

                                    <tfoot>
                                        <!-- Service Amount -->
                                        <tr>
                                            <th colspan="6" class="text-right">{{ __('appointment.service_amount') }}</th>
                                            <th colspan="2" style="text-align: right;">
                                                <span>{{ Currency::format($service_total_amount) }}</span>
                                            </th>
                                        </tr>
                                        
                                        <!-- Discount (if any) -->
                                        @if ($encounter_discount_amount > 0)
                                            <tr>
                                                <th colspan="6" class="text-right">{{ __('appointment.discount_amount') }}</th>
                                                <th colspan="2" style="text-align: right;">
                                                    <span>{{ Currency::format($encounter_discount_amount) ?? '--' }}</span>
                                                </th>
                                            </tr>
                                        @endif
                                        
                                        <!-- Tax -->
                                        <tr>
                                            <th colspan="6" class="text-right">{{ __('appointment.tax') }}</th>
                                            <th colspan="2" style="text-align: right;">
                                                <span>{{ Currency::format($totalTax) }}</span>
                                            </th>
                                        </tr>
                                        
                                        <!-- Total Payable Amount -->
                                        <tr>
                                            <th colspan="6" class="text-right">{{ __('appointment.total_payable_amount') }}</th>
                                            <th colspan="2" style="text-align: right;">
                                                <span>{{ Currency::format($totalPayableAmount) ?? '--' }}</span>
                                            </th>
                                        </tr>
                                        
                                        <!-- Bed Charges (if any) -->
                                        @if ($bed_charges > 0)
                                            <tr>
                                                <th colspan="6" class="text-right">{{ __('messages.bed_price') }}</th>
                                                <th colspan="2" style="text-align: right;">
                                                    <span>{{ Currency::format($bed_charges) }}</span>
                                                </th>
                                            </tr>
                                        @endif
                                        
                                        <!-- Final Total Amount -->
                                        <tr>
                                            <th colspan="6" class="text-right">{{ __('appointment.final_total_amount') ?? 'Final Total Amount' }}</th>
                                            <th colspan="2" style="text-align: right;">
                                                <span>{{ Currency::format($final_total_amount) ?? '--' }}</span>
                                            </th>
                                        </tr>
                                        @if ($info['appointmenttransaction']['advance_payment_status'] == 1)
                                            <tr>
                                                <th colspan="6" class="text-right">
                                                    {{ __('service.advance_payment_amount') }}({{ $info['advance_payment_amount'] }}%)
                                                </th>
                                                <th colspan="2" style="text-align: right;">
                                                    {{ Currency::format($info['advance_paid_amount']) ?? '--' }}</th>
                                            </tr>
                                        @endif

                                        @if ($info['appointmenttransaction']['advance_payment_status'] == 1 && $info['status'] == 'checkout')
                                            <tr>
                                                <th colspan="6" class="text-right">
                                                    {{ __('service.remaining_amount') }} <span
                                                        class="badge badge-success">{{ __('messages.paid') }}</span>
                                                </th>
                                                <th colspan="2" style="text-align: right;">
                                                    {{ Currency::format($remaining_payable_amount) }}</th>
                                            </tr>
                                        @endif
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </b-col>
    </b-row>
    <div style="margin-top: 40px; text-align: center;">
        {{ setting('spacial_note') ?? '' }}
    </div>
</body>

</html>

