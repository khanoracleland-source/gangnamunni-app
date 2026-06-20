@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection
@section('content')
    <div class="row">
        <x-backend.section-header>
            <x-slot name="toolbar">
                <a href="{{ route('backend.appointments.index') }}" class="btn btn-primary ms-auto" data-type="ajax"
                    data-bs-toggle="tooltip">
                    {{ __('appointment.back') }}
                </a>
            </x-slot>
        </x-backend.section-header>

                @foreach ($data as $info)
                                    @php
                                        $setting = App\Models\Setting::where('name', 'date_formate')->first();
                                        $dateformate = $setting ? $setting->val : 'Y-m-d';
                                        $setting = App\Models\Setting::where('name', 'time_formate')->first();
                                        $timeformate = $setting ? $setting->val : 'h:i A';
                $setting = App\Models\Setting::where('name', 'default_time_zone')->first();
                $timeZone = $setting ? $setting->val : 'UTC';
                                        $createdDate = date($dateformate, strtotime($info['appointment_date'] ?? '--'));
                                        $createdTime = date($timeformate, strtotime($info['appointment_time'] ?? '--'));
                                    @endphp

            <div class="col-lg-12">
                <div class="card card-block card-stretch card-height">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="col-md-3">
                                <span class="d-block mb-1">{{ __('appointment.lbl_patient_name') }}</span>
                                <div class="d-flex gap-3 align-items-center">
                                    <img src="{{ $info['user']['profile_image'] ?? default_user_avatar() }}"
                                        alt="avatar" class="avatar avatar-70 rounded-pill">
                                    <div class="text-start">
                                        <h5 class="m-0">{{ ($info['user']['first_name'] ?? '') . ' ' . ($info['user']['last_name'] ?? '') ?: default_user_name() }}
                                        </h5>
                                        <span>{{ $info['user']['email'] ?? '--' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-3">
                                <div>
                                    <span class="d-block mb-2">{{ __('clinic.lbl_clinic_name') }}</span>
                                    <img src="{{ $info['cliniccenter']['file_url'] ?? default_file_url() }}"
                                        alt="avatar" class="avatar avatar-30 rounded-pill me-2">
                                    <h6 class="m-0">
                                        {{ $info['cliniccenter']['name'] ?? '--' }}
                                    </h6>
                                </div>

                                <div>
                                    <span class="d-block mb-2">{{ __('messages.payment_status') }}</span>
                                    @if ($info['appointmenttransaction']['payment_status'] == 1)
                                        <h6 class="m-0 text-success">{{ __('messages.paid') }}</h6>
                                    @else
                                        <h6 class="m-0 text-secondary">{{ __('messages.unpaid') }}</h6>
                                    @endif
                                </div>
                            </div>
                                </div>
                            </div>
                        </div>
                    </div>

            <div class="col-lg-12">
                <div class="card card-block card-stretch card-height">
                                <div class="card-body">
                        <div class="row gy-3 mb-5">
                            <div class="col-md-3">
                                <span class="d-block mb-1">{{ __('messages.invoice_id') }}</span>
                                <h6 class="m-0">{{ setting('inv_prefix') }}{{ $info['id'] ?? '--' }}</h6>
                                        </div>
                            <div class="col-md-3">
                                <span class="d-block mb-1">{{ __('appointment.lbl_appointment_date') }}</span>
                                <h6 class="m-0">{{ $createdDate }}</h6>
                                            </div>
                            <div class="col-md-3">
                                <span class="d-block mb-1">{{ __('appointment.lbl_appointment_time') }}</span>
                                <h6 class="m-0">{{ __('appointment.at') }} {{ $createdTime }}</h6>
                                            </div>
                            <div class="col-md-3">
                                <span class="d-block mb-1">{{ __('appointment.lbl_doctor') }} {{ __('appointment.lbl_name') }}</span>
                                @if (empty($info['doctor']))
                                    <h6 class="m-0">--</h6>
                                @else
                                    <div class="d-flex gap-3 align-items-center">
                                        <img src="{{ $info['doctor']['profile_image'] ?? default_user_avatar() }}"
                                            alt="avatar" class="avatar avatar-50 rounded-pill">
                                        <div class="text-start">
                                            <h6 class="m-0">
                                                {{ ($info['doctor']['first_name'] ?? '') . ' ' . ($info['doctor']['last_name'] ?? '') }}
                                            </h6>
                                            <span>{{ $info['doctor']['email'] ?? '--' }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="border-top"></div>
                        <div class="row gy-3 pt-5">
                            <div class="col-md-3">
                                <span class="d-block mb-1">{{ __('appointment.lbl_contact_number') }}</span>
                                <h6 class="m-0">{{ $info['user']['mobile'] ?? '--' }}</h6>
                                        </div>
                            @if (!empty($info['user']['gender']))
                                <div class="col-md-3">
                                    <span class="d-block mb-1">{{ __('messages.gender') }}</span>
                                    <h6 class="m-0">{{ $info['user']['gender'] ?? '--' }}</h6>
                                                    </div>
                                                @endif
                            @if (!empty($info['user']['date_of_birth']))
                                <div class="col-md-3">
                                    <span class="d-block mb-1">{{ __('messages.date_of_birth') }}</span>
                                    <h6 class="m-0">{{ date($dateformate, strtotime($info['user']['date_of_birth'])) ?? '--' }}</h6>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
            
            <div class="col-lg-12">
                    <div class="row gy-3">
                        <div class="col-md-12 col-lg-12">
                            <h5 class="mb-3 mt-3">{{ __('messages.service') }}</h5>
                            <div class="card card-block card-stretch card-height mb-0">
                                <div class="card-body">

                                    @if (!isset($info['patient_encounter']))
                                        <div class="content-detail">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                                <span>{{ __('messages.item_name') }}</span>
                                                <span class="heading-color">{{ $info['clinicservice']['name'] ?? '--' }}</span>
                                            </div>
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                                <span>{{ __('messages.price') }}</span>
                                                <span
                                                    class="heading-color">{{ Currency::format($info['service_price']) ?? '--' }}</span>
                                            </div>
                                            <!-- <div class="d-flex flex-wrap align-items-center justify-content-between">
                                                                                                                                                                                                <span>{{ __('messages.total') }}</span>
                                                                                                                                                                                                <span class="heading-color">{{ Currency::format($info['service_amount']) ?? '--' }}</span>
                                                                                                                                                                                            </div> -->
                                        </div>
                                    @endif

                                    @if (isset($info['patient_encounter']) &&
                                            !empty($info['patient_encounter']['billingrecord']) &&
                                            !empty($info['patient_encounter']['billingrecord']['billing_item']))
                                        @foreach ($info['patient_encounter']['billingrecord']['billing_item'] as $billingItem)
                                            <div class="d-flex align-items-center bg-body p-4 rounded">
                                                <div class="detail-box bg-white rounded">
                                                    <img src="{{ $billingItem['clinicservice']['file_url'] ?? default_file_url() }}"
                                                        alt="avatar" class="avatar avatar-80 rounded-pill">
                                                </div>

                                                <div
                                                    class="ms-3 w-100 d-flex align-items-center justify-content-between flex-wrap">
                                                    <div class="d-flex flex-column flex-grow-1">
                                                        <div class="fs-5">
                                                            <b>{{ $billingItem['clinicservice']['name'] ?? 'N/A' }}</b>
                                                        </div>
                                                        @if(!empty($billingItem['clinicservice']['description']))
                                                            <div class="text-muted mt-1" style="font-size: 1rem;">
                                                                {{ $billingItem['clinicservice']['description'] }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @php
                                                        // Calculation order: Base Service - Discount + Inclusive Tax (on discounted amount)
                                                        // Get base service price from service charges (actual base price)
                                                        $baseServicePrice = (float) ($billingItem['service_amount'] ?? 0);
                                                        $itemId = $billingItem['item_id'] ?? null;
                                                        $quantity = (int) ($billingItem['quantity'] ?? 1);
                                                        
                                                        // If we have item_id, get the base price from the service itself
                                                        $service = null;
                                                        if (!empty($itemId)) {
                                                            $service = \Modules\Clinic\Models\ClinicsService::where('id', $itemId)->first();
                                                            if ($service) {
                                                                // Get base price from service charges (this is the actual base price)
                                                                $baseServicePrice = $service->charges ?? $baseServicePrice;
                                                            }
                                                        }
                                                        
                                                        // Service Price Total (base price * quantity)
                                                        $servicePriceTotal = $baseServicePrice * $quantity;
                                                        
                                                        // Get discount information
                                                        $discountValue = (float) ($billingItem['discount_value'] ?? 0);
                                                        $discountType = $billingItem['discount_type'] ?? 'percentage';
                                                        $discountStatus = $billingItem['discount_status'] ?? null;
                                                        
                                                        // If billing item doesn't have discount, check the service for discount
                                                        if (empty($discountValue) || $discountValue == 0) {
                                                            if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                                                $discountValue = $service->discount_value;
                                                                $discountType = $service->discount_type ?? 'percentage';
                                                                $discountStatus = 1;
                                                            }
                                                        }
                                                        
                                                        // Calculate service discount amount (applied to base service price only)
                                                        $serviceDiscountAmount = 0;
                                                        if (!empty($discountValue) && $discountValue > 0) {
                                                            if ($discountStatus === null) {
                                                                $discountStatus = 1;
                                                            }
                                                            
                                                            if ($discountStatus == 1) {
                                                                if ($discountType == 'percentage') {
                                                                    $serviceDiscountAmount = ($servicePriceTotal * $discountValue) / 100;
                                                                } else {
                                                                    $serviceDiscountAmount = $discountValue * $quantity;
                                                                }
                                                            }
                                                        }
                                                        
                                                        // Calculate service price after discount: Base Service Price - Discount
                                                        $servicePriceAfterDiscount = $servicePriceTotal - $serviceDiscountAmount;
                                                        
                                                        // Calculate Inclusive Tax on the discounted amount (per unit)
                                                        $unitPriceAfterDiscount = $servicePriceAfterDiscount / $quantity;
                                                        $unitInclusiveTax = 0;
                                                        
                                                        // Recalculate inclusive tax on discounted amount if service has inclusive tax enabled
                                                        if (!empty($itemId)) {
                                                            if (!$service) {
                                                                $service = \Modules\Clinic\Models\ClinicsService::where('id', $itemId)->first();
                                                            }
                                                            
                                                            if ($service && $service->is_inclusive_tax == 1 && !empty($service->inclusive_tax)) {
                                                                $inclusiveTaxJson = json_decode($service->inclusive_tax, true);
                                                                
                                                                if (is_array($inclusiveTaxJson)) {
                                                                    $calculatedInclusiveTax = 0;
                                                                    
                                                                    // Calculate inclusive tax on discounted price (not base price)
                                                                    foreach ($inclusiveTaxJson as $tax) {
                                                                        if (isset($tax['status']) && $tax['status'] == 1) {
                                                                            if (isset($tax['type']) && $tax['type'] == 'fixed') {
                                                                                $calculatedInclusiveTax += $tax['value'] ?? 0;
                                                                            } elseif (isset($tax['type']) && $tax['type'] == 'percent') {
                                                                                // Calculate tax on discounted amount
                                                                                $calculatedInclusiveTax += ($unitPriceAfterDiscount * ($tax['value'] ?? 0)) / 100;
                                                                            }
                                                                        }
                                                                    }
                                                                    
                                                                    $unitInclusiveTax = $calculatedInclusiveTax;
                                                                }
                                                            } else {
                                                                // Use existing inclusive tax amount if service doesn't have inclusive tax enabled
                                                                $unitInclusiveTax = (float) ($billingItem['inclusive_tax_amount'] ?? 0);
                                                            }
                                                        } else {
                                                            // Use existing inclusive tax amount if no item_id
                                                            $unitInclusiveTax = (float) ($billingItem['inclusive_tax_amount'] ?? 0);
                                                        }
                                                        
                                                        // Calculate total inclusive tax
                                                        $inclusiveTaxTotal = $unitInclusiveTax * $quantity;
                                                        
                                                        // Final total: (Base Service Price - Discount) + Inclusive Tax (calculated on discounted amount)
                                                        $finalAmount = $servicePriceAfterDiscount + $inclusiveTaxTotal;
                                                        
                                                        // Calculate original inclusive tax on base price (for display of original price)
                                                        $unitOriginalInclusiveTax = 0;
                                                        if (!empty($itemId)) {
                                                            if (!$service) {
                                                                $service = \Modules\Clinic\Models\ClinicsService::where('id', $itemId)->first();
                                                            }
                                                            
                                                            if ($service && $service->is_inclusive_tax == 1 && !empty($service->inclusive_tax)) {
                                                                $inclusiveTaxJson = json_decode($service->inclusive_tax, true);
                                                                
                                                                if (is_array($inclusiveTaxJson)) {
                                                                    foreach ($inclusiveTaxJson as $tax) {
                                                                        if (isset($tax['status']) && $tax['status'] == 1) {
                                                                            if (isset($tax['type']) && $tax['type'] == 'fixed') {
                                                                                $unitOriginalInclusiveTax += $tax['value'] ?? 0;
                                                                            } elseif (isset($tax['type']) && $tax['type'] == 'percent') {
                                                                                // Calculate tax on base price (for original price display)
                                                                                $unitOriginalInclusiveTax += ($baseServicePrice * ($tax['value'] ?? 0)) / 100;
                                                                            }

                                                                        }

                                                                    }

                                                                }
                                                            } else {
                                                                $unitOriginalInclusiveTax = (float) ($billingItem['inclusive_tax_amount'] ?? 0);
                                                            }
                                                        } else {
                                                            $unitOriginalInclusiveTax = (float) ($billingItem['inclusive_tax_amount'] ?? 0);
                                                        }
                                                        
                                                        // Original total (base + inclusive tax on base, before discount) for display
                                                        $originalTotal = ($baseServicePrice + $unitOriginalInclusiveTax) * $quantity;
                                                    @endphp

                                                    <div class="d-flex flex-column align-items-end text-end">
                                                        <span class="fw-bold fs-5">{{ Currency::format($finalAmount) }}</span>

                                                        @if ($serviceDiscountAmount > 0)
                                                            <span class="text-muted text-decoration-line-through mt-1">
                                                                {{ Currency::format($originalTotal) }}
                                                            </span>
                                                            <small class="text-success mt-1">
                                                                @if ($discountType === 'percentage')
                                                                    {{ $discountValue ?? '--' }}% {{ __('frontend.off') }}
                                                                @else
                                                                    {{ Currency::format($discountValue) ?? '--' }} {{ __('frontend.off') }}
                                                                @endif
                                                            </small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                            </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @php
                        // Get bed allocations for this appointment
                        $currentBedAllocations = collect();
                        if (isset($info['patient_encounter']['id']) && isset($bedAllocationsByEncounter[$info['patient_encounter']['id']])) {
                            $currentBedAllocations = $bedAllocationsByEncounter[$info['patient_encounter']['id']];
                        } elseif (isset($info['user_id']) && isset($bedAllocationsByEncounter['patient_' . $info['user_id']])) {
                            // Fallback to patient_id key
                            $currentBedAllocations = $bedAllocationsByEncounter['patient_' . $info['user_id']];
                        }
                    @endphp
                    @if ($currentBedAllocations->isNotEmpty())
                <div class="col-lg-12">
                        <div class="row gy-3 mt-4">
                            <div class="col-sm-12">
                                <h5 class="mb-3">Bed Allocation</h5>
                                <div class="card">
                                    <div class="card-body">
                                        @include('appointment::backend.patient_encounter.component.bed_allocation_table', [
                                            'data' => $info['patient_encounter']['billingrecord'] ?? null,
                                            'bedAllocations' => $currentBedAllocations,
                                            'hideActions' => true
                                        ])
                                </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @php
                // Calculate Service Total from billing items - Matching appointment_detail.blade.php logic
                // Service Amount = BASE PRICE ONLY (without inclusive tax, without discounts)
                $totalServiceAmount = 0; // Base service price only (for display and tax calculation)
                $totalServiceDiscount = 0; // Service-level discounts (tracked separately)
                        
                        $billingItems = $info['patient_encounter']['billingrecord']['billing_item'] ?? null;
                        if (!empty($billingItems) && is_array($billingItems)) {
                            foreach ($billingItems as $item) {
                                $quantity = $item['quantity'] ?? 1;
                        $item_id = $item['item_id'] ?? null;
                        
                        // Get base service price - matching appointment_detail: use service_amount directly
                        $unitPrice = $item['service_amount'] ?? 0; // Base price per unit
                        
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
                    // Fallback: For appointments without encounter, use service_price (base price only)
                    $totalServiceAmount = $info['service_price'] ?? 0;
                    $totalServiceDiscount = 0;
                        }
                        
                        $transaction = optional(optional($info['patient_encounter'])['billingrecord'])
                            ? optional(optional($info['patient_encounter'])['billingrecord'])
                            : null;
                        
                // Overall discount (final_discount) - Apply only to base service amount (matching appointment_detail logic)
                $overallDiscountAmount = 0;
                if ($transaction != null && isset($transaction['final_discount']) && $transaction['final_discount'] == 1) {
                    $discountType = $transaction['final_discount_type'] ?? 'percentage';
                    $discountValue = $transaction['final_discount_value'] ?? 0;
                    
                    if ($discountType === 'percentage') {
                        $overallDiscountAmount = ($totalServiceAmount * $discountValue) / 100;
                            } else {
                        $overallDiscountAmount = $discountValue;
                    }
                }
                
                // Total discount = Service discounts + Overall discount
                $totalDiscountAmount = $totalServiceDiscount + $overallDiscountAmount;
                
                // Calculate amount after discount: Service Amount - Total Discount
                $amountAfterDiscount = $totalServiceAmount - $totalDiscountAmount;
                
                // Tax calculation logic (matching appointment_detail):
                // - If there's NO overall discount, calculate tax on BASE service amount (ignoring service-level discounts)
                // - If there IS an overall discount, calculate tax on (Base Service Amount - Overall Discount)
                $amountForTaxCalculation = $totalServiceAmount;
                if ($overallDiscountAmount > 0) {
                    // When overall discount exists, calculate tax on (Base Service Amount - Overall Discount)
                    $amountForTaxCalculation = $totalServiceAmount - $overallDiscountAmount;
                }
                // Note: Service-level discounts are NOT included in tax calculation base
                
                // Get tax_data from transaction if available
                $taxData = null;
                if ($transaction && isset($transaction['tax_data'])) {
                    $taxData = $transaction['tax_data'];
                }
                
                // Tax (Exclusive) is calculated using getBookingTaxamount function
                $taxDetails = getBookingTaxamount($amountForTaxCalculation, $taxData);
                $taxAmount = $taxDetails['total_tax_amount'] ?? 0;
                $taxBreakdown = $taxDetails['tax_details'] ?? [];
                
                // Calculate total payable amount: (Base Service Amount - Overall Discount) + Tax (WITHOUT bed charges)
                // Note: Service-level discounts are separate and don't affect tax or total payable calculation
                $totalPayableAmount = $amountForTaxCalculation + $taxAmount;
                
                // Calculate bed charges
                        $bed_charges = $info['calculated_bed_charges'] ?? 0;
                        if ($bed_charges == 0 && isset($currentBedAllocations) && $currentBedAllocations->isNotEmpty()) {
                            $bed_charges = $currentBedAllocations->sum('charge') ?? 0;
                        }
                        if ($bed_charges == 0 && isset($transaction['bed_charges'])) {
                            $bed_charges = $transaction['bed_charges'] ?? 0;
                        }
                        
                        // Final Total: Total Payable Amount + Bed Charges
                        $final_total_amount = $totalPayableAmount + $bed_charges;
                
                // For display: Use totalServiceAmount as service_total_amount
                $service_total_amount = $totalServiceAmount;
                $encounter_discount_amount = $overallDiscountAmount;
                    @endphp
                    
            <div class="col-lg-12">
                        <div class="row gy-3 mt-4">
                            <div class="col-sm-12">
                                <h5 class="mb-3">{{ __('report.lbl_taxes') }}</h5>
                                <div class="card">
                                    <div class="card-body">
                                @if (isset($info['patient_encounter']) && !empty($info['patient_encounter']['billingrecord']))
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
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.tax') }}</span>
                                            <span class="heading-color">{{ Currency::format($taxAmount) ?? '--' }}</span>
                                        </div>

                                        <!-- STEP 4: Total Payable Amount (Service + Tax - Discount, WITHOUT bed charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color">{{ __('appointment.total_payable_amount') }}</span>
                                            <span class="text-dark">{{ Currency::format($totalPayableAmount) ?? '--' }}</span>
                                        </div>
                                        <hr class="border-top border-gray">

                                        <!-- STEP 5: Bed Total (if bed charges exist) -->
                                        @if($bed_charges > 0)
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                        <span>Bed Total</span>
                                        <span class="heading-color">{{ Currency::format($bed_charges) ?? '--' }}</span>
                                        </div>
                                        @endif

                                        <!-- STEP 6: Final Total Amount (Total Payable Amount + Bed Charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color fw-bold">Final Total Amount</span>
                                            <span class="text-dark fw-bold">{{ Currency::format($final_total_amount) ?? '--' }}</span>
                                        </div>
                                        
                                        @php
                                            $remaining_payable_amount = $final_total_amount - ($info['advance_paid_amount'] ?? 0);
                                        @endphp

                                        @if ($info['appointmenttransaction']['advance_payment_status'] == 1)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                                <span>{{ __('service.advance_payment_amount') }}({{ $info['advance_payment_amount'] }}%)</span>
                                                <span>{{ Currency::format($info['advance_paid_amount']) ?? '--' }}</span>
                                            </div>
                                        @endif

                                        @if ($info['appointmenttransaction']['advance_payment_status'] == 1 && $info['status'] == 'checkout')
                                        <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 pb-2 mb-2">
                                                <span>{{ __('service.remaining_amount') }}<span
                                                        class="text-capitalize badge bg-success p-2">{{ __('appointment.paid') }}</span></span>
                                                <span
                                                    class="heading-color">{{ Currency::format($remaining_payable_amount) }}</span>
                                        </div>
                                        @endif
                    @else
                                    {{-- For appointments without encounter --}}
                                    @php
                                        // Service Total = Service Price + Inclusive Tax
                                        $directServiceTotal = ($info['service_price'] ?? 0) + (optional($info['appointmenttransaction'])['inclusive_tax_price'] ?? 0);
                                        
                                        // Encounter-Level Discount (final_discount) - Apply to Service Amount ONLY
                                        $directDiscountAmount = 0;
                                        if ($info['appointmenttransaction'] && isset($info['appointmenttransaction']['final_discount']) && $info['appointmenttransaction']['final_discount'] == 1) {
                                            $directDiscountType = $info['appointmenttransaction']['final_discount_type'] ?? 'percentage';
                                            $directDiscountValue = $info['appointmenttransaction']['final_discount_value'] ?? 0;
                                            
                                            if ($directDiscountType === 'percentage') {
                                                $directDiscountAmount = ($directServiceTotal * $directDiscountValue) / 100;
                                            } else {
                                                $directDiscountAmount = $directDiscountValue;
                                            }
                                        }
                                        
                                        // Calculate amount after discount: Service Amount - Discount
                                        $directAmountAfterDiscount = $directServiceTotal - $directDiscountAmount;
                                        
                                        // Tax (Exclusive) is calculated on (Service Amount - Discount)
                                        $directTaxDetails = getBookingTaxamount($directAmountAfterDiscount, null);
                                        $directTaxAmount = $directTaxDetails['total_tax_amount'] ?? 0;
                                        
                                        // Calculate bed charges for appointments without encounter
                                        $bedcharges_direct = 0;
                                        if (isset($currentBedAllocations) && $currentBedAllocations->isNotEmpty()) {
                                            $bedcharges_direct = $currentBedAllocations->sum('charge') ?? 0;
                                        }
                                        
                                        // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                                        $directTotalPayableAmount = $directAmountAfterDiscount + $directTaxAmount;
                                        
                                        // Final Total: Total Payable Amount + Bed Charges
                                        $directFinalTotal = $directTotalPayableAmount + $bedcharges_direct;
                                    @endphp
                                
                                        <!-- STEP 1: Service Amount -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                        <span>{{ __('appointment.service_amount') }}</span>
                                        <span class="heading-color">{{ Currency::format($directServiceTotal) ?? '--' }}</span>
                                        </div>

                                        <!-- STEP 2: Discount Amount (if any) -->
                                    @if($directDiscountAmount > 0)
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.discount_amount') }}</span>
                                        <span class="heading-color">{{ Currency::format($directDiscountAmount) ?? '--' }}</span>
                                        </div>
                                        @endif

                                        <!-- STEP 3: Tax -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <span>{{ __('appointment.tax') }}</span>
                                        <span class="heading-color">{{ Currency::format($directTaxAmount) ?? '--' }}</span>
                                        </div>

                                        <!-- STEP 4: Total Payable Amount (Service + Tax - Discount, WITHOUT bed charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color">{{ __('appointment.total_payable_amount') }}</span>
                                        <span class="text-dark">{{ Currency::format($directTotalPayableAmount) ?? '--' }}</span>
                                        </div>
                                        <hr class="border-top border-gray">

                                        <!-- STEP 5: Bed Total (if bed charges exist) -->
                                    @if($bedcharges_direct > 0)
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                        <span>Bed Total</span>
                                        <span class="heading-color">{{ Currency::format($bedcharges_direct) ?? '--' }}</span>
                                        </div>
                                        @endif

                                        <!-- STEP 6: Final Total Amount (Total Payable Amount + Bed Charges) -->
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <span class="heading-color fw-bold">Final Total Amount</span>
                                        <span class="text-dark fw-bold">{{ Currency::format($directFinalTotal) ?? '--' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
            </div>


                    <hr class="my-3" />
                @endforeach
            </div>

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
        function toggleTaxBreakdown(id) {
            const breakdown = document.getElementById(id);
            const icon = document.getElementById(id + '-icon');
            
            if (breakdown) {
                if (breakdown.style.display === 'none' || breakdown.style.display === '') {
                    breakdown.style.display = 'block';
                    if (icon) {
                        icon.classList.remove('ph-caret-down');
                        icon.classList.add('ph-caret-up');
                    }
                } else {
                    breakdown.style.display = 'none';
                    if (icon) {
                        icon.classList.remove('ph-caret-up');
                        icon.classList.add('ph-caret-down');
                    }
                }
            }
        }
    </script>
@endpush



