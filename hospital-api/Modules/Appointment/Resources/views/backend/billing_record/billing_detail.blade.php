@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection
@section('content')


    <style type="text/css" media="print">
        @page :footer {
            display: none !important;
        }

        @page :header {
            display: none !important;
        }

        @page {
            size: landscape;
        }

        /* @page { margin: 0; } */

        .pr-hide {
            display: none;
        }


        .order_table tr td div {
            white-space: normal;
        }


        * {
            -webkit-print-color-adjust: none !important;
            /* Chrome, Safari 6 – 15.3, Edge */
            color-adjust: none !important;
            /* Firefox 48 – 96 */
            print-color-adjust: none !important;
            /* Firefox 97+, Safari 15.4+ */
        }
    </style>

    <b-row>
        <b-col sm="12">
            <div id="bill">


                <div class="row pr-hide mb-4">


                    <div class="d-flex justify-content-end align-items-center ">
                        <a class="btn btn-primary" onclick="invoicePrint(this)">
                            <i class="fa-solid fa-download"></i>
                            {{ __('messages.print') }}
                        </a>
                    </div>
                </div>
                @php
                    use Carbon\Carbon;
                    $bedAllocations = $bedAllocations ?? ($billing['bed_allocation_charges'] ?? []);
                    $formatValue = function ($value, $default = '--') {
                        return display_text($value, $default);
                    };
                @endphp

                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                    <p class="mb-0">{{ __('messages.invoice_id') }} :<span class="text-secondary">
                                            {{ $formatValue(setting('inv_prefix'), '') }}{{ $formatValue($billing['encounter_id'] ?? null) }}</span> </h3>
                                    <p class="mb-0">
                                        {{ __('messages.payment_status') }}
                                        @if ($billing['payment_status'] == 1)
                                            <span
                                                class="badge booking-status bg-success-subtle p-2">{{ __('messages.paid') }}</span>
                                        @elseif(optional(optional(optional($billing->patientencounter)->appointmentdetail)->appointmenttransaction)->advance_payment_status)
                                            <span
                                                class="badge booking-status bg-success-subtle py-2 px-3">{{ __('appointment.advance_paid') }}
                                            </span>
                                        @elseif(optional(optional(optional($billing->patientencounter)->appointmentdetail)->appointmenttransaction) == null)
                                            <span
                                                class="badge booking-status bg-danger-subtle py-2 px-3">{{ __('appointment.failed') }}
                                            </span>
                                        @else
                                            <span
                                                class="badge booking-status bg-danger-subtle p-2">{{ __('messages.unpaid') }}</span>
                                        @endif
                                    </p>
                                </div>
                                <p class="mt-1 mb-0">{{ __('messages.date') }}: <span
                                        class="font-weight-bold heading-color">
                                        {{ isset($billing['created_at'])
                                            ? Carbon::parse($billing['created_at'])->timezone($timezone)->format($dateformate) .
                                                ' At ' .
                                                Carbon::parse($billing['created_at'])->timezone($timezone)->format($timeformate)
                                            : '-- At --' }}</span>
                                </p>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="row gy-3">
                    <div class="col-md-12 col-lg-12">
                        <h5 class="mb-3">{{ __('messages.clinic_info') }}</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="image-block">
                                        <img src="{{ $formatValue($billing['clinic']['file_url'] ?? null, '--') }}"
                                            class="img-fluid avatar avatar-50 rounded-circle" alt="image">
                                    </div>
                                    <div class="content-detail">
                                        <h5 class="mb-2">{{ $formatValue($billing['clinic']['name'] ?? null) }}</h5>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <i class="ph ph-envelope heading-color"></i>
                                                <u class="text-secondary">{{ $formatValue($billing['clinic']['email'] ?? null) }}</u>
                                            </div>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <i class="ph ph-map-pin heading-color"></i>
                                                <span>{{ $formatValue($billing['clinic']['address'] ?? null) }}</span>
                                            </div>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <i class="ph ph-phone-call heading-color"></i>
                                                <span>{{ $formatValue($billing['clinic']['contact_number'] ?? null) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <h5 class="mb-3">{{ __('messages.doctor_details') }}</h5>
                        <div class="card card-block card-stretch card-height mb-0">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center h-100 gap-3">
                                    <div class="image-block">
                                        <img src="{{ $formatValue($billing['doctor']['profile_image'] ?? null, '--') }}"
                                            class="img-fluid avatar avatar-50 rounded-circle" alt="image">
                                    </div>
                                    <div class="content-detail">
                                        <h5 class="mb-2">Dr. {{ $formatValue($billing['doctor']['full_name'] ?? null) }}</h5>
                                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <i class="ph ph-envelope heading-color"></i>
                                                <u class="text-secondary">{{ $formatValue($billing['doctor']['email'] ?? null) }}</u>
                                            </div>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <i class="ph ph-phone-call heading-color"></i>
                                                <span>{{ $formatValue($billing['doctor']['mobile'] ?? null) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <h5 class="mb-3">{{ __('messages.patient_detail') }}</h5>
                        <div class="card card-block card-stretch card-height mb-0">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center h-100 gap-3">
                                    <div class="image-block">
                                        <img src="{{ $formatValue($billing['user']['profile_image'] ?? null, '--') }}"
                                            class="img-fluid avatar avatar-50 rounded-circle" alt="image">
                                    </div>
                                    <div class="content-detail">
                                        <h5 class="mb-2">
                                            {{ $formatValue(($billing['user']['first_name'] ?? '') . ' ' . ($billing['user']['last_name'] ?? '')) }}
                                        </h5>
                                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                            @if ($billing['user']['gender'] !== null)
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="ph ph-user heading-color"></i>
                                                    <span class="">{{ $formatValue($billing['user']['gender'] ?? null) }}</span>
                                                </div>
                                            @endif
                                            @if ($billing['user']['date_of_birth'] !== null)
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="ph ph-cake heading-color"></i>
                                                    <span
                                                        class="">{{ $formatValue(isset($billing['user']['date_of_birth']) && $billing['user']['date_of_birth'] !== null ? date($dateformate, strtotime($billing['user']['date_of_birth'])) : null) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- @dd($pharma) --}}
                    @if (!empty($pharma))
                        <div class="col-md-6 col-lg-4">
                            <h5 class="mb-3">{{ __('pharma::messages.pharma_details') }}</h5>
                            <div class="card card-block card-stretch card-height mb-0">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center h-100 gap-3">
                                        <div class="image-block">
                                            <img src="{{ $formatValue($pharma['profile_image'] ?? null, asset('path/to/default/avatar.webp')) }}"
                                                class="img-fluid avatar avatar-50 rounded-circle" alt="image">
                                        </div>
                                        <div class="content-detail">
                                            <h5 class="mb-2">
                                                @if (!empty($pharma) && isset($pharma['first_name'], $pharma['last_name']))
                                                    {{ $formatValue(($pharma['first_name'] ?? '') . ' ' . ($pharma['last_name'] ?? '')) }}
                                                @else
                                                    --
                                                @endif
                                            </h5>
                                            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="ph ph-envelope text-dark"></i>
                                                    <u class="text-secondary">{{ $formatValue($pharma->email ?? null) }}</u>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="ph ph-phone-call text-dark"></i>
                                                    <span>{{ $formatValue($pharma->mobile ?? null) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="col-md-6 col-lg-4">
                        <h5 class="mb-3">{{ __('messages.service') }}</h5>
                        <div class="card card-block card-stretch card-height mb-0">
                            <div class="card-body">
                                <div class="content-detail">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                        <span>{{ __('messages.service_name') }}</span>
                                        <span class="heading-color">{{ $formatValue($billing['clinicservice']['name'] ?? null) }}</span>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                        <span>{{ __('messages.price') }}</span>
                                        <span
                                            class="heading-color">{{ $formatValue(Currency::format($billing['clinicservice']['charges'] ?? $billing['service_amount'])) }}</span>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3" />
                    @php
                        // Get billing items - use object notation for relationships
                        $billingItems = collect();
                        if (is_object($billing) && $billing->billingItem) {
                            $billingItems = $billing->billingItem;
                        } elseif (isset($billing['billingItem'])) {
                            $billingItems = is_array($billing['billingItem']) ? collect($billing['billingItem']) : $billing['billingItem'];
                        }
                        if (!($billingItems instanceof \Illuminate\Support\Collection)) {
                            $billingItems = collect($billingItems);
                        }
                    @endphp
                    @if ($billingItems->isNotEmpty())
                        <div class="">
                            <h5 class="mb-3">{{ __('messages.service') }}</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered border-top order_table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ __('messages.sr_no') }}</th>
                                            <th>{{ __('messages.service_name') }}</th>
                                            <th>{{ __('messages.price') }}</th>
                                            <th>{{ __('messages.discount') }}</th>
                                            <th>{{ __('appointment.inclusive_tax') }}</th>
                                            <th>{{ __('messages.total') }}</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        @php $index = 1 @endphp
                                        @foreach ($billingItems as $item)
                                            @php
                                                // Handle both object and array access
                                                $quantity = is_array($item) ? ($item['quantity'] ?? 1) : ($item->quantity ?? 1);
                                                $item_id = is_array($item) ? ($item['item_id'] ?? null) : ($item->item_id ?? null);
                                                $item_name = is_array($item) ? ($item['item_name'] ?? '--') : ($item->item_name ?? '--');
                                                
                                                // Get base service price from service charges (actual base price, not calculated amount)
                                                $service_price = is_array($item) ? ($item['service_amount'] ?? 0) : ($item->service_amount ?? 0); // Default fallback
                                                $service = null;
                                                if (!empty($item_id)) {
                                                    $service = \Modules\Clinic\Models\ClinicsService::where('id', $item_id)->first();
                                                    if ($service) {
                                                        // Get base price from service charges (this is the actual base price)
                                                        $service_price = $service->charges ?? $service_price;
                                                    }
                                                }
                                                
                                                // Get discount - check billing item first, then service
                                                $discount_value = is_array($item) ? ($item['discount_value'] ?? 0) : ($item->discount_value ?? 0);
                                                $discount_type = is_array($item) ? ($item['discount_type'] ?? null) : ($item->discount_type ?? null);
                                                $discount_status = is_array($item) ? ($item['discount_status'] ?? null) : ($item->discount_status ?? null);
                                                
                                                // If billing item doesn't have discount, check the service for discount
                                                if (empty($discount_value) || $discount_value == 0) {
                                                    if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                                        $discount_value = $service->discount_value;
                                                        $discount_type = $service->discount_type;
                                                        $discount_status = 1;
                                                    }
                                                }
                                                
                                                // Calculate service discount amount (applied to base price only)
                                                $serviceDiscountAmount = 0;
                                                $unitDiscountAmount = 0;
                                                $discount_display = '--';
                                                
                                                if (!empty($discount_value) && $discount_value > 0) {
                                                    // If discount_status doesn't exist, default to 1 if discount exists
                                                    if ($discount_status === null) {
                                                        $discount_status = 1;
                                                    }
                                                    
                                                    // Apply discount only if status is 1 (active)
                                                    if ($discount_status == 1) {
                                                        if ($discount_type == 'percentage') {
                                                            // Percentage discount on base price only (per unit)
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
                                                
                                                // Calculate: Base Service - Discount + Inclusive Tax
                                                // Base price total (per unit × quantity)
                                                $basePriceTotal = $service_price * $quantity;
                                                
                                                // Price after discount
                                                $priceAfterDiscount = $basePriceTotal - $serviceDiscountAmount;
                                                
                                                // Recalculate inclusive tax on discounted amount (matching invoice and service_list)
                                                // Tax should be calculated on (base - discount), not on base
                                                // Calculation: Base $100 - Discount $5 = $95, then tax 10% on $95 = $9.5
                                                $unitPriceAfterDiscount = $priceAfterDiscount / $quantity;
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
                                                    $inclusive_tax = is_array($item) ? ($item['inclusive_tax_amount'] ?? 0) : ($item->inclusive_tax_amount ?? 0);
                                                    $unitInclusiveTax = $inclusive_tax;
                                                }
                                                
                                                // Calculate inclusive tax total (per unit × quantity)
                                                $inclusive_tax_total = $unitInclusiveTax * $quantity;
                                                
                                                // Final total: (Base - Discount) + Inclusive Tax (calculated on discounted amount)
                                                $final_total = $priceAfterDiscount + $inclusive_tax_total;
                                            @endphp
                                            <tr>
                                                <td>{{ $index }}</td>
                                                <td>{{ $item_name }}</td>
                                                <td class="text-end">
                                                    {{ Currency::format($service_price) }} × {{ $quantity }}
                                                </td>
                                                <td class="text-end">
                                                    {{ $discount_display }}
                                                </td>
                                                <td class="text-end">
                                                    {{ $inclusive_tax_total > 0 ? Currency::format($inclusive_tax_total) : '--' }}
                                                </td>
                                                <td class="text-end">{{ Currency::format($final_total) ?? '--' }}
                                                </td>
                                            </tr>
                                            @php $index++ @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <h5>Bed Allocation</h5>
                        @include('appointment::backend.patient_encounter.component.bed_allocation_table', [
                            'data' => $billing,
                            'bedAllocations' => $billing['bed_allocation_charges'] ?? $bedAllocations,
                            'hideActions' => true,
                        ])
                    @else
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="text-primary mb-0">{{ __('messages.no_record_found') }}</h4>
                            </div>
                        </div>
                    @endif

                    @if ($billing['tax_data'] !== null)
                        @php
                            $tax = $billing['tax_data'];
                            $taxData = json_decode($tax, true);
                            $total_amount = 0;
                            // $total_amount = $billing['service_amount'] ?? 0;
                        @endphp


                        @php
                            // ============================================================================
                            // BILLING CALCULATION SYSTEM (Following appointment_detail.blade.php logic)
                            // ============================================================================

                            // STEP 1: Calculate Service Total from billing items
                            $service_total_amount = 0;
                            $hasInclusiveTax = false;

                            // Get billing items - handle both object and array access
                            $calcBillingItems = is_object($billing) ? ($billing->billingItem ?? collect()) : ($billing['billingItem'] ?? collect());
                            if (!($calcBillingItems instanceof \Illuminate\Support\Collection)) {
                                $calcBillingItems = collect($calcBillingItems);
                            }
                            
                            if ($calcBillingItems->isNotEmpty()) {
                                foreach ($calcBillingItems as $item) {
                                    $quantity = is_array($item) ? ($item['quantity'] ?? 1) : ($item->quantity ?? 1);
                                    $item_id = is_array($item) ? ($item['item_id'] ?? null) : ($item->item_id ?? null);
                                    
                                    // Get base service price from service charges (actual base price, not calculated amount)
                                    $service_price = is_array($item) ? ($item['service_amount'] ?? 0) : ($item->service_amount ?? 0); // Default fallback
                                    $service = null;
                                    if (!empty($item_id)) {
                                        $service = \Modules\Clinic\Models\ClinicsService::where('id', $item_id)->first();
                                        if ($service) {
                                            // Get base price from service charges (this is the actual base price)
                                            $service_price = $service->charges ?? $service_price;
                                        }
                                    }
                                    
                                    // Get discount - check billing item first, then service
                                    $discount_value = is_array($item) ? ($item['discount_value'] ?? 0) : ($item->discount_value ?? 0);
                                    $discount_type = is_array($item) ? ($item['discount_type'] ?? null) : ($item->discount_type ?? null);
                                    $discount_status = is_array($item) ? ($item['discount_status'] ?? null) : ($item->discount_status ?? null);
                                    
                                    // If billing item doesn't have discount, check the service for discount
                                    if (empty($discount_value) || $discount_value == 0) {
                                        if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                            $discount_value = $service->discount_value;
                                            $discount_type = $service->discount_type;
                                            $discount_status = 1;
                                        }
                                    }

                                    // Calculate service discount amount (applied to base price only)
                                    $item_discount = 0;
                                    $unitDiscountAmount = 0;
                                    if (!empty($discount_value) && $discount_value > 0) {
                                        // If discount_status doesn't exist, default to 1 if discount exists
                                        if ($discount_status === null) {
                                            $discount_status = 1;
                                        }
                                        
                                        // Apply discount only if status is 1 (active)
                                        if ($discount_status == 1) {
                                            if ($discount_type == 'percentage') {
                                                // Percentage discount on base price only (per unit)
                                                $unitDiscountAmount = ($service_price * $discount_value) / 100;
                                                $item_discount = $unitDiscountAmount * $quantity;
                                        } else {
                                                // Fixed discount per quantity
                                                $unitDiscountAmount = $discount_value;
                                                $item_discount = $discount_value * $quantity;
                                            }
                                        }
                                    }

                                    // Calculate: Base Service - Discount + Inclusive Tax
                                    // Base price total (per unit × quantity)
                                    $basePriceTotal = $service_price * $quantity;
                                    
                                    // Price after discount
                                    $priceAfterDiscount = $basePriceTotal - $item_discount;
                                    
                                    // Recalculate inclusive tax on discounted amount (matching invoice and service_list)
                                    // Tax should be calculated on (base - discount), not on base
                                    $unitPriceAfterDiscount = $priceAfterDiscount / $quantity;
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
                                        
                                        if ($unitInclusiveTax > 0) {
                                            $hasInclusiveTax = true;
                                        }
                                    } else {
                                        // Use existing inclusive tax amount if service doesn't have inclusive tax enabled
                                        $inclusive_tax = is_array($item) ? ($item['inclusive_tax_amount'] ?? 0) : ($item->inclusive_tax_amount ?? 0);
                                        $unitInclusiveTax = $inclusive_tax;
                                        
                                        if ($unitInclusiveTax > 0) {
                                            $hasInclusiveTax = true;
                                        }
                                    }
                                    
                                    // Calculate inclusive tax total (per unit × quantity)
                                    $inclusive_tax_total = $unitInclusiveTax * $quantity;
                                    
                                    // Final total for this item: (Base - Discount) + Inclusive Tax (calculated on discounted amount)
                                    $itemFinalTotal = $priceAfterDiscount + $inclusive_tax_total;
                                    
                                    // Add to total service amount
                                    $service_total_amount += $itemFinalTotal;
                                }
                            } else {
                                // Fallback to database value if no billing items
                                $service_total_amount = $billing['service_amount'] ?? 0;
                            }

                            // STEP 2: Calculate Encounter-Level Discount (Apply to Service Amount ONLY)
                            $encounter_discount_amount = 0;
                            $encounter_discount_percent = 0;
                            $encounter_discount_type = '';

                            if (isset($billing['final_discount']) && $billing['final_discount'] == 1) {
                                $encounter_discount_percent = $billing['final_discount_value'] ?? 0;
                                $encounter_discount_type = $billing['final_discount_type'] ?? 'percentage';

                                // Discount is applied to Service Amount ONLY (not Service + Tax)
                                if ($encounter_discount_type === 'percentage') {
                                    $encounter_discount_amount =
                                        $service_total_amount * ($encounter_discount_percent / 100);
                                } else {
                                    $encounter_discount_amount = $encounter_discount_percent;
                                }
                            }

                            // STEP 3: Calculate amount after discount: Service Amount - Discount
                            $amountAfterDiscount = $service_total_amount - $encounter_discount_amount;

                            // STEP 4: Calculate Bed Charges
                                            $bedcharges = $billing['bed_charges'] ?? 0;
                            
                            // If bed charges are 0, try to get from bed allocations
                            if ($bedcharges == 0 && isset($bedAllocations) && is_countable($bedAllocations) && $bedAllocations->isNotEmpty()) {
                                if (is_array($bedAllocations)) {
                                    $bedcharges = collect($bedAllocations)->sum('charge') ?? 0;
            } else {
                                    $bedcharges = $bedAllocations->sum('charge') ?? 0;
                                }
                            }
                            
                            // If still 0, try to get from bed_allocation_charges array
                            if ($bedcharges == 0 && isset($billing['bed_allocation_charges']) && !empty($billing['bed_allocation_charges'])) {
                                $bedAllocationCharges = is_string($billing['bed_allocation_charges']) 
                                    ? json_decode($billing['bed_allocation_charges'], true) 
                                    : $billing['bed_allocation_charges'];
                                if (is_array($bedAllocationCharges)) {
                                    $bedcharges = collect($bedAllocationCharges)->sum('charge') ?? 0;
                                }
                            }

                            // STEP 5: Calculate Tax on (Service Amount - Discount) - matching appointment_detail logic
        $taxBreakdown = [];
        $total_tax_amount = 0;

        if (is_array($taxData)) {
            foreach ($taxData as $tax) {
                $taxType = $tax['type'] ?? 'percent';
                $taxValue = $tax['value'] ?? 0;

                                    // Tax is calculated on amount after discount (NOT including bed charges)
                if ($taxType === 'percent') {
                                        $taxAmount = ($amountAfterDiscount * $taxValue) / 100;
                } else {
                    $taxAmount = $taxValue;
                }

                $total_tax_amount += $taxAmount;

                $taxBreakdown[] = [
                    'title' => $tax['title'] ?? ($tax['name'] ?? __('messages.tax')),
                    'type' => $taxType,
                    'value' => $taxValue,
                    'amount' => $taxAmount,
                ];
            }
        }

                            // STEP 6: Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                            $totalPayableAmount = $amountAfterDiscount + $total_tax_amount;

                            // STEP 7: Final Total: Total Payable Amount + Bed Charges
                            $final_total_amount = $totalPayableAmount + $bedcharges;
                                        @endphp

                        <div class="mt-4">
                            <div>
                                <h5 class="mb-3">{{ __('report.lbl_taxes') }}</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <!-- STEP 1: Service Amount -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.service_amount') }}</span>
                                            <span class="heading-color">{{ Currency::format($service_total_amount) ?? '--' }}</span>
                                        </div>

                                        <!-- STEP 2: Discount Amount (if any) -->
                                        @if($encounter_discount_amount > 0)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.discount_amount') }}</span>
                                            <span class="heading-color">{{ Currency::format($encounter_discount_amount) ?? '--' }}</span>
                                            </div>
                                        @endif

                                        <!-- STEP 3: Tax -->
                                        @if($total_tax_amount > 0)
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.tax') }}</span>
                                            <span class="heading-color">{{ Currency::format($total_tax_amount) ?? '--' }}</span>
                                            </div>
                                        @endif

                                        <!-- STEP 4: Total Payable Amount (Service + Tax - Discount, WITHOUT bed charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color">{{ __('appointment.total_payable_amount') }}</span>
                                            <span class="text-dark">{{ Currency::format($totalPayableAmount) ?? '--' }}</span>
                                        </div>
                                        <hr class="border-top border-gray">

                                        <!-- STEP 5: Bed Total (if bed charges exist) -->
                                        @if($bedcharges > 0)
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>Bed Total</span>
                                            <span class="heading-color">{{ Currency::format($bedcharges) ?? '--' }}</span>
                                        </div>
                                                    @endif

                                        <!-- STEP 6: Final Total Amount (Total Payable Amount + Bed Charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color fw-bold">Final Total Amount</span>
                                            <span class="text-dark fw-bold">{{ Currency::format($final_total_amount) ?? '--' }}</span>
                                        </div>

                                        {{-- Advance Payment --}}
                                        @php
                                            $appointment = optional(
                                                optional($billing->patientencounter)->appointmentdetail,
                                            );
                                            $advance_paid_amount = $appointment->advance_paid_amount ?? 0;
                                            $advance_percentage = $appointment->advance_payment_amount ?? 0;
                                            $remaining_payable_amount = max(0, $final_total_amount - $advance_paid_amount);
                                            $advance_status =
                                                optional($appointment->appointmenttransaction)
                                                    ->advance_payment_status ?? 0;
                                        @endphp

                                        @if ($advance_status == 1)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                                <span>{{ __('service.advance_payment_amount') }}
                                                    ({{ $advance_percentage }}%)</span>
                                                <div>{{ Currency::format($advance_paid_amount) }}</div>
                                            </div>

                                            @if ($appointment->status === 'checkout')
                                                <li class="d-flex align-items-center justify-content-between pb-2 mb-2">
                                                    <span>
                                                        {{ __('service.remaining_amount') }}
                                                        <span class="text-capitalize badge bg-success p-2">
                                                            {{ __('appointment.paid') }}
                                                        </span>
                                                    </span>
                                                    <span
                                                        class="text-dark">{{ Currency::format($remaining_payable_amount) }}</span>
                                                </li>
                                            @endif
                                        @endif
                                    </div>

                                </div>
                            </div>
                        </div>
                    @endif

                    @if (checkPlugin('pharma') == 'active')
                        @php
                            $prescriptions = $billing->patientencounter->prescriptions ?? collect();
                            $hasMedicine = $prescriptions
                                ->filter(function ($item) {
                                    return !empty($item->medicine);
                                })
                                ->isNotEmpty();
                        @endphp

                        @if ($hasMedicine)
                            <div class="">
                                <div>
                                    <h5 class="mb-3">{{ __('pharma::messages.medicine') }}</h5>
                                </div>
                                <div class="col-md-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered border-top order_table">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>{{ __('pharma::messages.medicine_name') }}</th>
                                                    <th>{{ __('pharma::messages.form') }}</th>
                                                    <th>{{ __('pharma::messages.dosage') }}</th>
                                                    <th>{{ __('pharma::messages.frequency') }}</th>
                                                    <th>{{ __('pharma::messages.days') }}</th>
                                                    <th>{{ __('pharma::messages.expiry_date') }}</th>
                                                    <th>{{ __('pharma::messages.price') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($prescriptions as $item)
                                                    @if (!empty($item->medicine))
                                                        <tr>
                                                            <td>{{ $item->name ?? '--' }}</td>
                                                            <td>{{ $item->medicine->form->name ?? '--' }}</td>
                                                            <td>{{ $item->medicine->dosage ?? '--' }}</td>
                                                            <td>{{ $item->frequency ?? '--' }}</td>
                                                            <td>{{ $item->duration ?? '--' }}</td>
                                                            <td>{{ \Carbon\Carbon::parse($item->medicine->expiry_date)->timezone($timezone)->format($dateformate) ?? '--' }}
                                                            </td>
                                                            <td>{{ Currency::format($item->total_amount) ?? '--' }}</td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- Payment Detail --}}
                            @php
                                $total_amount = 0;
                                foreach ($prescriptions as $item) {
                                    if (!empty($item->medicine)) {
                                        $total_amount += $item->total_amount;
                                    }
                                }

                                $taxLabel = __('pharma::messages.tax');
                                if (!empty($exclusiveTaxes)) {
                                    $firstTax = $exclusiveTaxes[0];
                                    if (!empty($firstTax['type']) && $firstTax['type'] === 'percentage') {
                                        $taxLabel .= ' (' . $firstTax['value'] . '%)';
                                    }
                                }
                            @endphp

                            <h6 class="fw-bold mt-5">{{ __('pharma::messages.payment_detail') }}</h6>
                            <div id="payment-detail-section">
                                {{-- Render payment breakdown here if needed --}}
                            </div>
                        @endif
                    @endif







                </div>

        </b-col>
    </b-row>

@endsection

@push('after-styles')
    <style>
        .detail-box {
            padding: 0.625rem 0.813rem;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script src="{{ mix('modules/appointment/script.js') }}"></script>
    <script src="{{ asset('js/form-offcanvas/index.js') }}" defer></script>
    <script>
        const prescriptionId = {{ $billing->encounter_id }};

        function invoicePrint() {
            window.print()
        }

        function updateStatusAjax(__this, url) {
            console.log(url);
            console.log($billing);
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: {
                    id: {{ $billing['id'] }},
                    status: __this.val(),
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    if (res.status) {
                        window.successSnackbar(res.message)
                        setTimeout(() => {
                            location.reload()
                        }, 100);
                    }
                }
            });
        }

        @if(checkPlugin('pharma') == 'active' && Route::has('backend.prescription.payment_detail'))
            $.ajax({
                url: "{{ route('backend.prescription.payment_detail', ['id' => '__ID__']) }}".replace('__ID__',
                    prescriptionId),
                type: 'GET',
                success: function(response) {
                    $('#payment-detail-section').html(response);
                }
            });
        @endif
    </script>
@endpush
