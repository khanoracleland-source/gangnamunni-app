<div class="modal modal-lg fade" id="generate_invoice" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    @php

    @endphp

    <form id="billingForm" method="POST" onsubmit="return false;">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('clinic.lbl_generate_invoice') }}</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ph ph-x-circle"></i>
                    </button>
                </div>
                <div class="modal-body">

                    <input type="hidden" id="billing_encounter_id" value="{{ $data['id'] }}" />
                    <input type="hidden" id="final_total_amount" value="">
                    <input type="hidden" id="total_amount" value="">
                    <input type="hidden" id="total_tax_amount" value="">
                    <input type="hidden" id="total_bed_charges" value="">

                    <p class="d-inline-flex gap-1">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <h4>
                            {{ __('appointment.add_item_in_billing') }}
                        </h4>
                        <button class="btn btn-primary" type="button" id="toggleButton" data-bs-toggle="collapse"
                            data-bs-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">
                            {{ __('appointment.add_item') }}
                        </button>
                    </div>
                    </p>
                    <div class="collapse" id="collapseExample">
                        <div class="card card-body" id="extra-service-list">

                            @include('appointment::backend.patient_encounter.component.add_service', [
                                'encounter_id' => $data['id'],
                                'billing_id' => $data['billingrecord']['id'],
                                'service_id' => $data['billingrecord']['service_id'],
                            ])

                        </div>
                    </div>

                    <div id="Service_list">

                        @include('appointment::backend.patient_encounter.component.service_list', [
                            'data' => $data['billingrecord'],
                            'status' => $data['status'],
                        ])
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="bg-gray-900 p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label m-0"
                                        for="category-discount">{{ __('service.discount') }}</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" name="final_discount" id="category-discount"
                                            type="checkbox"
                                            {{ old('final_discount', $data['billingrecord']['final_discount'] ?? 0) ? 'checked' : '' }}
                                            onchange="toggleDiscountSection()" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="final-discount-box mt-3 d-none" id="final_discount_section">
                            <div class="d-flex flex-column flex-md-row gap-3 mt-3">
                                <div class="w-100">
                                    <label class="form-label mb-0">{{ __('service.lbl_discount_value') }} <span
                                            class="text-danger">*</span> </label>
                                    <input type="number" name="final_discount_value" id="final_discount_value"
                                        class="form-control" placeholder="{{ __('service.lbl_discount_value') }}"
                                        step="0.01"
                                        value="{{ old('final_discount_value', $data['billingrecord']['final_discount_value'] ?? 0) }}"
                                        oninput="validateDiscount(this)" onchange="updateDiscount()" required />

                                    <span id="discount_amount_error" class="text-danger"></span>
                                </div>
                                <div class="w-100 flex flex-col">
                                    <label class="form-label m-0 d-block"
                                        for="category-discount">{{ __('service.lbl_discount_type') }}
                                        <span class="text-danger">*</span></label>
                                    <select id="final_discount_type" name="final_discount_type"
                                        class="select2 form-select" placeholder="{{ __('service.lbl_discount_type') }}"
                                        data-filter="select" onchange="updateDiscount()">
                                        <option value="percentage"
                                            {{ old('final_discount_type', $data['billingrecord']['final_discount_type'] ?? '') === 'percentage' ? 'selected' : '' }}>
                                            {{ __('appointment.percentage') }}
                                        </option>
                                        <option value="fixed"
                                            {{ old('final_discount_type', $data['billingrecord']['final_discount_type'] ?? '') === 'fixed' ? 'selected' : '' }}>
                                            {{ __('appointment.fixed') }}
                                        </option>
                                    </select>
                                </div>
                                {{-- Second Discount Value Field - Commented Out
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('service.lbl_discount_value') }} <span
                                            class="text-danger">*</span> </label>
                                            <input type="number" name="final_discount_value" id="final_discount_value"
                            class="form-control" placeholder="{{ __('service.lbl_discount_value') }}" step="1.00"
                            value="{{ old('final_discount_value', $data['final_discount_value'] ?? 1) }}"

                            oninput="validateDiscount(this)"
                             required />

                             <span id="discount_amount_error" class="text-danger" ></span>
                                </div>
                                --}}
                            </div>
                        </div>

                        {{-- Bed Allocation Section --}}
                        <div class="row align-items-center mb-2 mt-4">
                            <div class="col">
                                <h5 class="mb-0">Bed Allocation</h5>
                            </div>
                            <div class="col-auto">
                                {{-- <button class="btn btn-primary" id="showAddBedFormBtn">Edit Bed Allocation</button> --}}
                            </div>
                        </div>
                        <div id="bed-allocation-table" class="mb-3">
                            @include(
                                'appointment::backend.patient_encounter.component.bed_allocation_table',
                                [
                                    'data' => $data,
                                    'bedAllocations' => $bedAllocations ?? [],
                                    'hideActions' => true,
                                ]
                            )
                        </div>
                    </div>

                    <div id="tax_list">

                        @include('appointment::backend.patient_encounter.component.tax_list', [
                            'data' => $data['billingrecord'],
                        ])

                    </div>

                    {{-- {{ dd($data['billingrecord'])  }} --}}

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                @php
                                    // Calculate sum of all service totals: Service Price only (without tax)
                                    $totalServiceAmount = 0;
                                    $totalServiceDiscount = 0;

                                    if (!empty($data['billingrecord']['billingItem'])) {
                                        foreach ($data['billingrecord']['billingItem'] as $item) {
                                            $quantity = $item->quantity ?? 1;
                                            $unitPrice = $item->service_amount ?? 0;
                                            $inclusiveTax = $item->inclusive_tax_amount ?? 0;

                                            // Recalculate inclusive tax if needed (if billing item has 0 but service has inclusive tax)
                                            if (
                                                ($inclusiveTax == 0 || $inclusiveTax == null) &&
                                                !empty($item->item_id)
                                            ) {
                                                $service = \Modules\Clinic\Models\ClinicsService::where(
                                                    'id',
                                                    $item->item_id,
                                                )->first();
                                                if (
                                                    $service &&
                                                    $service->is_inclusive_tax == 1 &&
                                                    !empty($service->inclusive_tax)
                                                ) {
                                                    $inclusiveTaxJson = json_decode($service->inclusive_tax, true);
                                                    if (is_array($inclusiveTaxJson)) {
                                                        $recalculatedTax = 0;
                                                        foreach ($inclusiveTaxJson as $tax) {
                                                            if (isset($tax['status']) && $tax['status'] == 1) {
                                                                if (isset($tax['type']) && $tax['type'] == 'fixed') {
                                                                    $recalculatedTax += $tax['value'] ?? 0;
                                                                } elseif (
                                                                    isset($tax['type']) &&
                                                                    $tax['type'] == 'percent'
                                                                ) {
                                                                    $recalculatedTax +=
                                                                        ($unitPrice * ($tax['value'] ?? 0)) / 100;
                                                                }
                                                            }
                                                        }
                                                        $inclusiveTax = $recalculatedTax;
                                                    }
                                                }
                                            }

                                            // Calculate service price total (without inclusive tax): Price * Quantity
                                            $servicePriceTotal = $unitPrice * $quantity;

                                            // Get discount information
                                            $itemDiscountValue = $item->discount_value ?? null;
                                            $itemDiscountType = $item->discount_type ?? null;
                                            $itemDiscountStatus = $item->discount_status ?? null;

                                            // If billing item doesn't have discount, check the service
        if (empty($itemDiscountValue) || $itemDiscountValue == 0) {
            if (!empty($item->item_id)) {
                $service = \Modules\Clinic\Models\ClinicsService::where(
                    'id',
                    $item->item_id,
                )->first();
                if (
                    $service &&
                    !empty($service->discount_value) &&
                    $service->discount_value > 0
                ) {
                    $itemDiscountValue = $service->discount_value;
                    $itemDiscountType = $service->discount_type;
                    $itemDiscountStatus = 1;
                }
            }
        }

        // Calculate service discount amount (applied to service price only, not inclusive tax)
        $itemDiscountAmount = 0;
        if (!empty($itemDiscountValue) && $itemDiscountValue > 0) {
            // If discount_status doesn't exist, default to 1 if discount exists
                                                if ($itemDiscountStatus === null) {
                                                    $itemDiscountStatus = 1;
                                                }

                                                // Apply discount only if status is 1 (active)
                                                if ($itemDiscountStatus == 1) {
                                                    if ($itemDiscountType == 'percentage') {
                                                        // Percentage discount on service price total
                                                        $itemDiscountAmount =
                                                            ($servicePriceTotal * $itemDiscountValue) / 100;
                                                    } else {
                                                        // Fixed discount per quantity
                                                        $itemDiscountAmount = $itemDiscountValue * $quantity;
                                                    }
                                                }
                                            }

                                            // Add to total service amount (service price only, without tax)
                                            $totalServiceAmount += $servicePriceTotal;

                                            // Add to total service discount
                                            $totalServiceDiscount += $itemDiscountAmount;
                                        }
                                    } else {
                                        $totalServiceAmount = $data['billingrecord']['service_amount'] ?? 0;
                                    }

                                    // Overall discount (final_discount) - Apply only to service amount
                                    $overallDiscountAmount = 0;
                                    if (
                                        isset($data['billingrecord']['final_discount']) &&
                                        $data['billingrecord']['final_discount'] == 1
                                    ) {
                                        $discountType = $data['billingrecord']['final_discount_type'] ?? 'percentage';
                                        $discountValue = $data['billingrecord']['final_discount_value'] ?? 0;
                                        // Apply discount only to service amount
                                        $amountForDiscount = $totalServiceAmount;

                                        if ($discountType === 'percentage') {
                                            $overallDiscountAmount = ($amountForDiscount * $discountValue) / 100;
                                        } else {
                                            $overallDiscountAmount = $discountValue;
                                        }
                                    }

                                    // Total discount = Service discounts + Overall discount
                                    $totalDiscountAmount = $totalServiceDiscount + $overallDiscountAmount;

                                    // Calculate amount after discount: Service Amount - Total Discount
                                    $amountAfterDiscount = $totalServiceAmount - $totalDiscountAmount;

                                    // Get tax_data from billing record if available
                                    $taxData = null;
                                    if (
                                        isset($data['billingrecord']['tax_data']) &&
                                        !empty($data['billingrecord']['tax_data'])
                                    ) {
                                        $taxData = $data['billingrecord']['tax_data'];
                                    }

                                    // Tax (Exclusive) is calculated on (Service Amount - Total Discount)
                                    $taxDetails = getBookingTaxamount($amountAfterDiscount, $taxData);
                                    $taxAmount = $taxDetails['total_tax_amount'] ?? 0;
                                    $taxBreakdown = $taxDetails['tax_details'] ?? [];
                                @endphp

                                <!-- Service Amount - Sum of all service totals -->
                                <div class="d-flex justify-content-between align-items-center form-control">
                                    <label class="form-label m-0">{{ __('appointment.service_amount') }}</label>
                                    <div class="form-check" id="service_amount">
                                        <input type="hidden" id="total_service_amount"
                                            value="{{ $totalServiceAmount }}">
                                        <input type="hidden" id="total_inclusive_tax" value="0">
                                        <input type="hidden" id="subtotal" value="{{ $totalServiceAmount }}">
                                        <input type="hidden" id="service_discount" value="0">
                                        {{ Currency::format($totalServiceAmount) }}
                                    </div>
                                </div>

                                <!-- Discount (Final Discount) - Only show when discount toggle is ON AND discount > 0 -->
                                <div class="d-flex justify-content-between align-items-center form-control"
                                    id="overall_discount_section" style="display: none !important;">
                                    <label class="form-label m-0">{{ __('service.discount') }}</label>
                                    <div class="form-check" id="overall_discount_amount">
                                        <input type="hidden" id="overall_discount"
                                            value="{{ $overallDiscountAmount }}">
                                        <span
                                            id="overall_discount_display">{{ Currency::format($overallDiscountAmount) }}</span>
                                    </div>
                                </div>

                                <!-- Tax with Expandable Breakdown -->
                                <div class="form-control"
                                    style="padding: 0.375rem 0.75rem; min-height: auto; height: auto;">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                                        <span>{{ __('appointment.tax') }}</span>
                                        <span id="tax_amount_display">{{ Currency::format($taxAmount) }}</span>
                                        <input type="hidden" id="total_tax_amount" value="{{ $taxAmount }}">
                                    </div>
                                    @if (!empty($taxBreakdown) && is_array($taxBreakdown) && count($taxBreakdown) > 0)
                                        <div id="encounter-tax-breakdown" class="tax-breakdown-details"
                                            style="display: none; margin-top: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; width: 100%; box-sizing: border-box;">
                                            @foreach ($taxBreakdown as $taxItem)
                                                @php
                                                    $tax_name =
                                                        $taxItem['tax_name'] ??
                                                        ($taxItem['title'] ?? ($taxItem['name'] ?? 'Tax'));
                                                    $tax_value = $taxItem['tax_value'] ?? ($taxItem['value'] ?? 0);
                                                    $tax_type = $taxItem['tax_type'] ?? ($taxItem['type'] ?? 'percent');
                                                    // Tax amount is already calculated on subtotal by getBookingTaxamount
                                                    $individual_tax_amount = $taxItem['tax_amount'] ?? 0;
                                                @endphp
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-muted">{{ $tax_name }} @if ($tax_type == 'percent')
                                                            ({{ $tax_value }}%)
                                                        @endif
                                                    </span>
                                                    <span>{{ Currency::format($individual_tax_amount) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @php
                                    // Calculate total payable amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                                    $totalPayableAmount = $amountAfterDiscount + $taxAmount;

                                    $totalBedCharges = 0;
                                    if (isset($bedAllocations) && $bedAllocations->isNotEmpty()) {
                                        $totalBedCharges = $bedAllocations->sum('charge') ?? 0;
                                    } elseif (isset($data['billingrecord']['bed_charges'])) {
                                        $totalBedCharges = $data['billingrecord']['bed_charges'] ?? 0;
                                    }

                                    // Final total including bed charges (for hidden input only)
                                    $finalTotal = $totalPayableAmount + $totalBedCharges;

                                    // Debug logging for PHP calculation
                                    \Log::info('PHP Billing Calculation', [
                                        'service_amount' => $totalServiceAmount,
                                        'discount_amount' => $overallDiscountAmount,
                                        'amount_after_discount' => $amountAfterDiscount,
                                        'tax_amount' => $taxAmount,
                                        'bed_charges' => $totalBedCharges,
                                        'total_payable_amount' => $totalPayableAmount,
                                        'final_total' => $finalTotal,
                                        'discount_toggle' => $data['billingrecord']['final_discount'] ?? 0,
                                    ]);
                                @endphp

                                <!-- Total Payable Amount (Service Amount - Discount + Tax, WITHOUT bed charges) -->
                                <div class="d-flex justify-content-between align-items-center form-control">
                                    <label class="form-label m-0">{{ __('appointment.total_payable_amount') }}</label>
                                    <div class="form-check" id="total_payable_amount">
                                        <input type="hidden" id="total_amount" value="{{ $finalTotal }}">
                                        {{ Currency::format($totalPayableAmount) }}
                                    </div>
                                </div>

                                @if ($totalBedCharges > 0)
                                    <div class="d-flex justify-content-between align-items-center form-control">
                                        <label class="form-label m-0">Bed Total</label>
                                        <div class="form-check" id="bed_total_amount">
                                            <input type="hidden" id="total_bed_charges"
                                                value="{{ $totalBedCharges }}">
                                            {{ Currency::format($totalBedCharges) }}
                                        </div>
                                    </div>
                                @endif

                                @if (optional(optional($data['appointmentdetail'])->appointmenttransaction)->advance_payment_status == 1)
                                    <div class="d-flex justify-content-between align-items-center form-control">
                                        <label class="form-label m-0">{{ __('service.advance_payment_amount') }}
                                            ({{ optional($data['appointmentdetail'])->advance_payment_amount ?? 0 }}%)</label>
                                        <div class="form-check" id="advance_payment_amount">
                                            <input type="hidden" id="advance_paid_amount"
                                                value="{{ optional($data['appointmentdetail'])->advance_paid_amount ?? 0 }}">
                                            {{ Currency::format(optional($data['appointmentdetail'])->advance_paid_amount ?? 0) }}
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Duplicate Tax and Total Amount fields - commented out
                                <div class="d-flex justify-content-between align-items-center form-control">
                                    <label class="form-label m-0">{{ __('appointment.tax') }}</label>
                                    <div class="form-check" id="tax_amount">
                                        {{ Currency::format($data['billingrecord']['final_tax_amount']) ?? Currency::format(0) }}
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center form-control">
                                    <label class="form-label m-0">Total Amount</label>
                                    <div class="form-check" id="total_payable_amount">
                                        <input type="hidden" id="total_amount" value="">
                                        {{ Currency::format($data['billingrecord']['total_amount']) ?? Currency::format(0) }}
                                    </div>
                                </div>
                                --}}


                            <div class="d-flex justify-content-between align-items-center form-control"
                                id="final_total_amount_section">
                                <label class="form-label m-0">Final Total Amount</label>
                                <div class="form-check" id="final_total_amounts">
                                    <input type="hidden" id="final_total_amount" value="{{ $finalTotal }}">
                                    {{ Currency::format($finalTotal) }}
                                </div>
                            </div>

                            {{-- <div class="d-flex justify-content-between align-items-center form-control">
                                    <label class="form-label m-0">Total Amount</label>
                                    <div class="form-check" id="final_total_amount">
                                        @php
                                            $totalBedCharge = 0; // Calculate total bed charges from bedAllocations
                                            if (isset($bedAllocations) && $bedAllocations->isNotEmpty()) {
                                                $totalBedCharge = $bedAllocations->sum('charge') ?? 0;
                                            }
                                            $grandTotal = null;
                                            if (isset($data['billingrecord']['final_total_amount']) && $data['billingrecord']['final_total_amount'] !== null) {
                                                $grandTotal = $data['billingrecord']['final_total_amount'] + $totalBedCharge;
                                            } else {
                                                $mainTotal = $data['billingrecord']['service_amount'] ?? 0;
                                                $grandTotal = ($mainTotal ? $mainTotal : 0) + $totalBedCharge;
                                            }
                                        @endphp
                                        {{ Currency::format($grandTotal) ?? '--' }}
                                    </div>
                                </div> --}}

                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="d-flex justify-content-between align-items-center form-control">
                                    <label class="form-label m-0">{{ __('clinic.lbl_payment_status') }}</label>
                                    <div class="form-check billing-detail-select">

                                        <select id="payment_status" name="payment_status" class="select2 form-select"
                                            placeholder="{{ __('service.lbl_discount_type') }}" data-filter="select">
                                            <option value="0"
                                                {{ old('payment_status', $data['payment_status'] ?? '') === '0' ? 'selected' : '' }}>
                                                {{ __('appointment.pending') }}
                                            </option>
                                            <option value="1"
                                                {{ old('payment_status', $data['payment_status'] ?? '') === '1' ? 'selected' : '' }}>
                                                {{ __('appointment.paid') }}
                                            </option>
                                        </select>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-md-row gap-3 mt-3">
                        <div class="col-md-12">
                            <div class="card mb-0 p-3">
                                <div class="row justify-content-lg-between align-items-center gy-2">
                                    <div class="col-lg-6">
                                        <label class="form-label m-0">{{ __('clinic.select_payment_method') }}</label>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-check custom-select-input-white p-0">
                                            <select name="payment_method" id="payment_method"
                                                class="form-control select2">
                                                <option value="">{{ __('messages.select_payment_method') }}
                                                </option>
                                                @foreach ($paymentMethod as $method)
                                                    <option value="{{ $method->value }}"
                                                        @if ($method->value === $billingDetail->payment_method ?? '') selected @endif>
                                                        {{ ucfirst($method->label) }}
                                                    </option>
                                                @endforeach
                                            </select>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-4 mt-4">
                        <div class="d-grid d-sm-flex justify-content-sm-end gap-3">
                            <button type="button" id="save-button"
                                onclick="console.log('=== INLINE ONCLICK FIRED ==='); if(window.submitBillingFormHandler) { window.submitBillingFormHandler(); } else { console.error('submitBillingFormHandler not defined!'); } return false;"
                                class="btn btn-secondary">{{ __('appointment.save') }}</button>
                            <button type="button" class="btn btn-white d-block"
                                data-bs-dismiss="modal">{{ __('appointment.close') }}</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

</div>

</div>

</form>

</div>

@push('after-scripts')
    <script>
        // Define submitBillingFormHandler IMMEDIATELY and GLOBALLY before anything else
        // This ensures it's available when the inline onclick fires
        (function() {
            // Define the main submit function first
            window.submitBillingForm = function() {

                if (typeof $ === 'undefined' || typeof $.ajax === 'undefined') {

                    return false;
                }

                const baseUrl = '{{ url('/') }}';

                let paymentStatus = $('#payment_status').val();

                if (!$('#payment_status').val() && $('#payment_status').hasClass('select2-hidden-accessible')) {
                    paymentStatus = $('#payment_status').select2('val') || '0';
                    console.log('Payment status from Select2:', paymentStatus);
                }
                paymentStatus = paymentStatus || '0';

                const formData = {
                    encount_id: $('#billing_encounter_id').val(),
                    final_discount_value: $('#final_discount_value').val() || 0,
                    final_discount_type: $('#final_discount_type').val() || 'percentage',
                    payment_status: paymentStatus,
                    payment_method: $('#payment_method').val(),
                    final_discount: $('#category-discount').is(':checked') ? 1 : 0,
                    final_total_amount: $('#final_total_amount').val() || 0,
                    total_tax_amount: $('#total_tax_amount').val() || 0,
                    total_amount: $('#total_amount').val() || 0,
                    bed_charges: $('#total_bed_charges').val() || 0,
                    _token: '{{ csrf_token() }}'
                };

                console.log('=== FORM DATA COLLECTED ===');
                console.log('Form submission data:', formData);
                console.log('Payment status value:', paymentStatus);
                console.log('Encounter ID:', formData.encount_id);
                console.log('Final total amount:', formData.final_total_amount);

                // Disable save button to prevent double submission
                console.log('Disabling save button...');
                $('#save-button').prop('disabled', true).text('Saving...');
                console.log('Save button disabled:', $('#save-button').prop('disabled'));

                // Make AJAX request - use Laravel route helper or construct URL properly
                const ajaxUrl = baseUrl + '/app/billing-record/save-billing-detail-data';
                console.log('=== MAKING AJAX REQUEST ===');
                console.log('URL:', ajaxUrl);
                console.log('Method: POST');
                console.log('Data:', formData);

                $.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: formData,
                    beforeSend: function(xhr) {
                        console.log('=== AJAX REQUEST SENT ===');
                        console.log('Request headers:', xhr);
                    },
                    success: function(response) {
                        console.log('=== AJAX SUCCESS ===');
                        console.log('Timestamp:', new Date().toISOString());
                        console.log('Response:', response);
                        console.log('Response type:', typeof response);
                        console.log('Response status:', response.status);
                        console.log('Payment Status:', formData.payment_status);
                        console.log('Encounter Closed:', response.encounter_closed);

                        // Show appropriate success message based on payment status and encounter closure
                        if (typeof window.successSnackbar === 'function') {
                            if (formData.payment_status == 1) {
                                if (response.encounter_closed) {
                                    window.successSnackbar(
                                        'Billing details saved successfully and encounter closed');
                                } else {
                                    window.successSnackbar('Billing details saved successfully');
                                    console.warn(
                                        'Encounter was not closed despite payment status being paid'
                                    );
                                }
                            } else {
                                window.successSnackbar('Billing details saved successfully');
                            }
                        }

                        $('#generate_invoice').modal('hide');
                        console.log('Reloading page in 500ms...');
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    },
                    error: function(xhr, status, error) {
                        console.error('=== AJAX ERROR ===');
                        console.error('Timestamp:', new Date().toISOString());
                        console.error('Status:', status);
                        console.error('Error:', error);
                        console.error('Response:', xhr.responseJSON || xhr.responseText);
                        console.error('Status Code:', xhr.status);
                        console.error('Response Headers:', xhr.getAllResponseHeaders());

                        // Re-enable save button
                        $('#save-button').prop('disabled', false).text('{{ __('appointment.save') }}');

                        let errorMessage = 'Failed to save billing details.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            errorMessage = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        }

                        if (typeof window.errorSnackbar === 'function') {
                            window.errorSnackbar(errorMessage);
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Error',
                                text: errorMessage,
                                icon: 'error'
                            });
                        } else {
                            alert(errorMessage);
                        }
                    }
                });

                return false;
            };

            // Define the handler that will be called from inline onclick
            window.submitBillingFormHandler = function() {
                console.log('=== SAVE BUTTON CLICKED (Inline Handler) ===');
                console.log('Timestamp:', new Date().toISOString());
                console.log('jQuery available:', typeof $ !== 'undefined');
                console.log('submitBillingForm function available:', typeof window.submitBillingForm !==
                    'undefined');

                if (typeof window.submitBillingForm === 'function') {
                    window.submitBillingForm();
                } else {
                    console.error('submitBillingForm function not available!');
                    alert('Error: Form submission function not loaded. Please refresh the page.');
                }
            };


        })();

        // Define toggleDiscountSection function globally and early so it's accessible from inline handlers
        window.toggleDiscountSection = function() {
            console.log('toggleDiscountSection called');
            const isChecked = document.getElementById('category-discount') ? document.getElementById(
                'category-discount').checked : false;
            const discountSection = document.getElementById('final_discount_section');

            if (!discountSection) {
                console.error('Discount section element not found');
                return;
            }

            if (isChecked) {
                discountSection.classList.remove('d-none'); // Remove Bootstrap's d-none class
                // Get current discount values and update calculation instantly
                const discountValueInput = document.getElementById('final_discount_value');
                const discountTypeInput = document.getElementById('final_discount_type');
                const discountValue = discountValueInput ? discountValueInput.value || 0 : 0;
                const discountType = discountTypeInput ? discountTypeInput.value || 'percentage' : 'percentage';
                // Update calculation immediately when discount is enabled
                if (typeof updateDiscount === 'function') {
                    updateDiscount();
                }
                // Show overall discount section only if there's a discount value > 0
                if (parseFloat(discountValue) > 0) {
                    $('#overall_discount_section').show();
                } else {
                    $('#overall_discount_section').hide();
                }
            } else {
                discountSection.classList.add('d-none'); // Add Bootstrap's d-none class

                if (typeof removeDiscountValue === 'function') {
                    removeDiscountValue();
                } else {
                    // Fallback if function doesn't exist yet
                    $('#final_discount_value').val(0);
                    $('#final_discount_type').val('percentage');
                }
                // Hide overall discount section when discount is disabled
                $('#overall_discount_section').hide();
                // Recalculate totals with 0 discount when toggle is off
                if (typeof updateDiscount === 'function') {
                    updateDiscount();
                }
            }
        };

        // Define toggleTaxBreakdown function globally and early so it's accessible from inline handlers
        window.toggleTaxBreakdown = function(id) {
            console.log('toggleTaxBreakdown called with id:', id);
            const breakdown = document.getElementById(id);
            const icon = document.getElementById(id + '-icon');

            if (breakdown) {
                // Check if element is currently visible by checking computed style
                const computedStyle = window.getComputedStyle(breakdown);
                const inlineDisplay = breakdown.style.display;
                const computedDisplay = computedStyle.display;
                const offsetHeight = breakdown.offsetHeight;

                const isCurrentlyVisible = computedDisplay !== 'none' && offsetHeight > 0;

                // Also check icon state as backup
                const iconHasUpClass = icon && icon.classList.contains('ph-caret-up');
                const iconHasDownClass = icon && icon.classList.contains('ph-caret-down');

                // If icon shows up (expanded) or element is visible, then hide it
                // Otherwise show it
                if (isCurrentlyVisible || iconHasUpClass) {
                    // Hide the breakdown
                    breakdown.style.display = 'none';
                    if (icon) {
                        icon.classList.remove('ph-caret-up');
                        icon.classList.add('ph-caret-down');
                    }
                } else {
                    // Show the breakdown
                    breakdown.style.display = 'block';
                    if (icon) {
                        icon.classList.remove('ph-caret-down');
                        icon.classList.add('ph-caret-up');
                    }

                    // Force parent to expand if needed
                    setTimeout(() => {
                        if (breakdown.offsetHeight > 0 && breakdown.parentElement) {
                            breakdown.parentElement.style.height = 'auto';
                            breakdown.parentElement.style.maxHeight = 'none';
                            breakdown.parentElement.style.overflow = 'visible';
                        }
                    }, 100);
                }
            } else {
                console.error('Breakdown element not found with id:', id);
            }
        };

        // Define validateDiscount function globally and early so it's accessible from inline handlers
        window.validateDiscount = function(input) {
            console.log('validateDiscount called');
            let maxAmount = 100;
            const discountTypeSelect = document.querySelector('#final_discount_type');
            if (!discountTypeSelect) {
                console.warn('Discount type select not found');
                return;
            }
            const discountType_value = discountTypeSelect.value;
            // Get numeric value from service_amount and tax_amount text content
            // Tax amount might be in a span, so get text from the element
            const serviceAmountElement = document.querySelector('#service_amount');
            const serviceAmount = serviceAmountElement ? parseFloat(serviceAmountElement.innerText.replace(/[^0-9.-]+/g,
                '')) || 0 : 0;
            const taxAmountElement = document.querySelector('#tax_amount');
            const taxAmount = taxAmountElement ? parseFloat((taxAmountElement.innerText || taxAmountElement
                .textContent || '').replace(/[^0-9.-]+/g, '')) || 0 : 0;
            const totalAmountWithTax = serviceAmount + taxAmount; // Service + Tax for discount calculation
            const discountValue = parseFloat(input.value) || 0;

            if (discountType_value === 'percentage' && input.value > maxAmount) {
                $('#discount_amount_error').text('{{ __('appointment.discount_value_less_than_100') }}');
                input.value = 0;
                if (typeof window.discountUpdateTimeout !== 'undefined') {
                    clearTimeout(window.discountUpdateTimeout);
                }
                if (typeof window.updateDiscount === 'function') {
                    window.updateDiscount();
                }
            } else if (discountType_value === 'fixed' && discountValue > totalAmountWithTax) {
                $('#discount_amount_error').text('{{ __('appointment.discount_amount_exceed_service_amount') }}');
                input.value = 0;
                if (typeof window.discountUpdateTimeout !== 'undefined') {
                    clearTimeout(window.discountUpdateTimeout);
                }
                if (typeof window.updateDiscount === 'function') {
                    window.updateDiscount();
                }
            } else {
                $('#discount_amount_error').text('');
                // Debounce the update to avoid too many API calls while typing
                if (typeof window.discountUpdateTimeout !== 'undefined') {
                    clearTimeout(window.discountUpdateTimeout);
                }
                window.discountUpdateTimeout = setTimeout(function() {
                    if (typeof window.updateDiscount === 'function') {
                        window.updateDiscount();
                    }
                }, 500); // Wait 500ms after user stops typing
            }
        };

        // Define updateDiscount function globally and early so it's accessible from inline handlers
        window.updateDiscount = function() {
            console.log('=== updateDiscount START ===');
            const discountInput = document.getElementById('final_discount_value');
            const discountTypeSelect = document.getElementById('final_discount_type');

            if (!discountInput || !discountTypeSelect) {
                console.warn('Discount input or type select not found');
                return;
            }

            const discountValue = discountInput.value || 0;
            const discountType = discountTypeSelect.value || 'percentage';
            const isToggleEnabled = $('#category-discount').is(':checked');

            console.log('Discount Input Value:', discountValue);
            console.log('Discount Type:', discountType);
            console.log('Discount Toggle Enabled:', isToggleEnabled);

            // Always call calculateFinalAmount to update the totals
            // calculateFinalAmount is defined at the top of this script, so it should always be available
            if (typeof window.calculateFinalAmount === 'function') {
                console.log('Calling calculateFinalAmount with:', discountValue, discountType);
                window.calculateFinalAmount(discountValue, discountType);
            } else {
                console.error('calculateFinalAmount function not found! This should not happen.');
                console.error('window.calculateFinalAmount type:', typeof window.calculateFinalAmount);
            }
            console.log('=== updateDiscount END ===');
        };

        // Define calculateFinalAmount function directly here so it's available immediately

        window.calculateFinalAmount = function(discountValue, discountType) {

            const billingId = "{{ $data['billingrecord']['id'] }}";
            const baseUrl = '{{ url('/') }}';
            const discountValueNum = parseFloat(discountValue) || 0;


            const data = {
                discount_value: discountValueNum,
                discount_type: discountType || 'percentage',
                billing_id: billingId
            };


            fetch(`${baseUrl}/app/billing-record/calculate-discount-record`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data),
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {

                    // CRITICAL: Preserve the service amount - it should NOT change when discount is toggled
                    // Service amount is the sum of all service totals and should remain constant
                    let totalServiceAmount = parseFloat($('#total_service_amount').val()) || 0;

                    // Only update from backend if we don't have a value yet (initial load)
                    if (totalServiceAmount === 0 && data.service_details.service_total) {
                        totalServiceAmount = parseFloat(data.service_details.service_total) || 0;
                        $('#total_service_amount').val(totalServiceAmount);
                    }

                    // DO NOT overwrite service amount from backend response when discount is updated
                    // The service amount should remain the same regardless of discount changes

                    const taxAmount = parseFloat(data.service_details.total_tax) || 0;
                    const bedCharges = parseFloat(data.service_details.total_bed_charges) || 0;

                    // Keep service amount display unchanged - it should not be recalculated
                    // Only update if it's empty (initial load)
                    if ($('#service_amount').text().trim() === '' || $('#service_amount').text().trim() === '0' ||
                        $('#service_amount').text().trim() === '$0.00') {
                        $('#service_amount').text(currencyFormat(totalServiceAmount));
                    }

                    // Ensure hidden inputs are set correctly
                    $('#total_service_amount').val(totalServiceAmount);
                    $('#total_inclusive_tax').val(0);
                    $('#subtotal').val(totalServiceAmount);
                    $('#service_discount').val(0);

                    $('#total_tax_amount').val(taxAmount);
                    $('#tax_amount_display').html(currencyFormat(taxAmount));


                    if (data.service_details.tax_breakdown && data.service_details.tax_breakdown.length > 0) {
                        let breakdownHtml = '';
                        data.service_details.tax_breakdown.forEach(function(taxItem) {
                            const taxName = taxItem.tax_name || taxItem.title || taxItem.name || 'Tax';
                            const taxValue = taxItem.tax_value || taxItem.value || 0;
                            const taxType = taxItem.tax_type || taxItem.type || 'percent';
                            const individualTaxAmount = taxItem.tax_amount || 0;
                            const percentLabel = taxType === 'percent' ? '(' + taxValue + '%)' : '';

                            breakdownHtml +=
                                '<div class="d-flex justify-content-between align-items-center mb-2">';
                            breakdownHtml += '<span class="text-muted">' + taxName + ' ' + percentLabel +
                                '</span>';
                            breakdownHtml += '<span>' + currencyFormat(
                                individualTaxAmount) + '</span>';
                            breakdownHtml += '</div>';
                        });

                        const breakdownDiv = $('#encounter-tax-breakdown');
                        if (breakdownDiv.length) {
                            breakdownDiv.html(breakdownHtml);
                        }
                    }

                    if (bedCharges > 0) {
                        $('#total_bed_charges').val(bedCharges);
                        $('#bed_total_amount').text(currencyFormat(bedCharges));
                        $('#bed_total_amount').closest('.form-control').show();
                    } else {
                        $('#bed_total_amount').closest('.form-control').hide();
                    }

                    // Apply discount only to service amount
                    const amountForOverallDiscount = totalServiceAmount;
                    let overallDiscountAmount = 0;

                    if (discountValueNum > 0) {
                        if (discountType === 'percentage') {
                            overallDiscountAmount = (amountForOverallDiscount * discountValueNum) / 100;
                        } else {
                            overallDiscountAmount = discountValueNum;
                        }
                    }

                    $('#overall_discount_display').text(currencyFormat(overallDiscountAmount));
                    $('#overall_discount').val(overallDiscountAmount);

                    const isDiscountEnabled = $('#category-discount').is(':checked');
                    // Only show discount section if toggle is ON AND discount amount is greater than 0
                    if (isDiscountEnabled && discountValueNum > 0 && overallDiscountAmount > 0) {
                        $('#overall_discount_section').show();
                    } else {
                        $('#overall_discount_section').hide();
                    }

                    const discountAmount = overallDiscountAmount;
                    $('#final_discount_amount').val(discountAmount);

                    // Calculate amount after discount: Service Amount - Discount
                    const amountAfterDiscount = totalServiceAmount - overallDiscountAmount;

                    // Tax is calculated on (Service Amount - Discount) by the backend
                    // Use the tax amount from backend response (it's already calculated correctly)
                    // The backend recalculates tax on amountAfterDiscount

                    // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                    const totalPayableAmount = amountAfterDiscount + taxAmount;

                    // Final total including bed charges (for hidden input only)
                    const grandTotal = totalPayableAmount + bedCharges;
                    const backendTotal = parseFloat(data.service_details.final_total_amount) || parseFloat(data
                        .service_details.total_amount) || 0;

                    // Use calculated grand total as it's more accurate (calculated from correct tax amount)
                    // Only use backend total if calculated total is 0 or invalid
                    const finalTotal = (grandTotal > 0) ? grandTotal : backendTotal;

                    console.log('=== calculateFinalAmount - Final Total Calculation ===');
                    console.log('Service Amount:', totalServiceAmount);
                    console.log('Discount Amount:', overallDiscountAmount);
                    console.log('Amount After Discount:', amountAfterDiscount);
                    console.log('Tax Amount:', taxAmount);
                    console.log('Bed Charges:', bedCharges);
                    console.log('Total Payable Amount (Service + Tax):', totalPayableAmount);
                    console.log('Grand Total (Service + Tax + Bed):', grandTotal);
                    console.log('Backend Total:', backendTotal);
                    console.log('Final Total Selected:', finalTotal);
                    console.log('Discount Value:', discountValueNum);
                    console.log('Discount Type:', discountType);

                    // Set total amount (including bed charges for form submission)
                    $('#total_amount').val(finalTotal);
                    // Display total payable amount WITHOUT bed charges
                    $('#total_payable_amount').text(currencyFormat(totalPayableAmount));
                    $('#final_total_amount').val(finalTotal);
                    $('#final_total_amounts').text(currencyFormat(finalTotal));

                    console.log('Updated DOM elements:');
                    console.log('- #total_amount:', finalTotal);
                    console.log('- #total_payable_amount:', currencyFormat(totalPayableAmount));
                    console.log('- #final_total_amount:', finalTotal);
                    console.log('- #final_total_amounts:', currencyFormat(finalTotal));
                    console.log('=== calculateFinalAmount - Final Total Calculation END ===');

                    // Always show final total amount section (no condition based on discount)
                    $('#final_total_amount_section').removeClass('d-none');

                    const finalToggleCheck = $('#category-discount').is(':checked');
                    if (!finalToggleCheck) {
                        $('#overall_discount_section').hide();
                    }

                    // Ensure total payable amount is always visible
                    $('#total_payable_amount').closest('.form-control').show();
                })
                .catch(error => {
                    console.error('Error fetching billing data:', error);
                    const phpCalculatedTotal = parseFloat($('#total_amount').val()) || 0;
                    if (phpCalculatedTotal > 0) {
                        console.log('Using PHP-calculated total from hidden input:', phpCalculatedTotal);
                        $('#total_payable_amount').text(currencyFormat(phpCalculatedTotal));
                    }
                });
        };


        $(document).on('shown.bs.modal', '#generate_invoice', function() {
            console.log('Modal shown - attaching event listeners');

            // Ensure total payable amount is always visible
            $('#total_payable_amount').closest('.form-control').show();

            // Initially hide discount section if discount is 0
            const initialDiscount = parseFloat($('#overall_discount').val()) || 0;
            const isDiscountEnabled = $('#category-discount').is(':checked');
            if (initialDiscount <= 0 || !isDiscountEnabled) {
                $('#overall_discount_section').hide();
            }

            // Remove inline onchange and use event listener instead
            $('#category-discount').off('change').on('change', function() {
                console.log('Checkbox changed via event listener');
                if (typeof window.toggleDiscountSection === 'function') {
                    window.toggleDiscountSection();
                } else {
                    console.error('toggleDiscountSection function not available');
                }
            });

            // Also attach for tax breakdown
            $(document).off('click', '[onclick*="toggleTaxBreakdown"]').on('click',
                '[onclick*="toggleTaxBreakdown"]',
                function(e) {
                    const onclick = $(this).attr('onclick');
                    if (onclick && onclick.includes('toggleTaxBreakdown')) {
                        e.preventDefault();
                        const match = onclick.match(/toggleTaxBreakdown\(['"]([^'"]+)['"]\)/);
                        if (match && match[1] && typeof window.toggleTaxBreakdown === 'function') {
                            window.toggleTaxBreakdown(match[1]);
                        }
                    }
                });

            console.log('Event listeners attached to modal elements');
        });

        // Also try on document ready as backup
        $(document).ready(function() {
            // Initially hide discount section if discount is 0
            const initialDiscount = parseFloat($('#overall_discount').val()) || 0;
            if (initialDiscount <= 0) {
                $('#overall_discount_section').hide();
            }

            setTimeout(function() {
                if ($('#generate_invoice').length && $('#category-discount').length) {
                    $('#category-discount').off('change').on('change', function() {
                        if (typeof window.toggleDiscountSection === 'function') {
                            window.toggleDiscountSection();
                        }
                    });
                }
            }, 500);
        });
    </script>
    <script>
        // Debounce variable for discount updates - make it global
        window.discountUpdateTimeout = window.discountUpdateTimeout || null;

        // Define toggleDiscountSection function globally and early so it's accessible from inline handlers
        window.toggleDiscountSection = function() {
            const isChecked = document.getElementById('category-discount') ? document.getElementById(
                'category-discount').checked : false;
            const discountSection = document.getElementById('final_discount_section');

            if (!discountSection) {
                console.error('Discount section element not found');
                return;
            }

            if (isChecked) {
                discountSection.classList.remove('d-none'); // Remove Bootstrap's d-none class
                // Get current discount values and update calculation instantly
                const discountValueInput = document.getElementById('final_discount_value');
                const discountTypeInput = document.getElementById('final_discount_type');
                const discountValue = discountValueInput ? discountValueInput.value || 0 : 0;
                const discountType = discountTypeInput ? discountTypeInput.value || 'percentage' : 'percentage';
                // Update calculation immediately when discount is enabled
                if (typeof updateDiscount === 'function') {
                    updateDiscount();
                }
                // Show overall discount section only if there's a discount value > 0
                if (parseFloat(discountValue) > 0) {
                    $('#overall_discount_section').show();
                } else {
                    $('#overall_discount_section').hide();
                }
            } else {
                discountSection.classList.add('d-none'); // Add Bootstrap's d-none class

                if (typeof removeDiscountValue === 'function') {
                    removeDiscountValue();
                } else {
                    // Fallback if function doesn't exist yet
                    $('#final_discount_value').val(0);
                    $('#final_discount_type').val('percentage');
                }
                // Hide overall discount section when discount is disabled
                $('#overall_discount_section').hide();
                // Recalculate totals with 0 discount when toggle is off
                if (typeof updateDiscount === 'function') {
                    updateDiscount();
                }
            }
        };

        // Define toggleTaxBreakdown function globally and early so it's accessible from inline handlers
        window.toggleTaxBreakdown = function(id) {
            console.log('=== toggleTaxBreakdown START ===');
            console.log('ID:', id);

            const breakdown = document.getElementById(id);
            const icon = document.getElementById(id + '-icon');

            console.log('Breakdown element:', breakdown);
            console.log('Icon element:', icon);

            if (breakdown) {
                // Check if element is currently visible by checking computed style
                const computedStyle = window.getComputedStyle(breakdown);
                const inlineDisplay = breakdown.style.display;
                const computedDisplay = computedStyle.display;
                const offsetHeight = breakdown.offsetHeight;

                console.log('Inline display:', inlineDisplay);
                console.log('Computed display:', computedDisplay);
                console.log('Offset height:', offsetHeight);

                const isCurrentlyVisible = computedDisplay !== 'none' && offsetHeight > 0;

                // Also check icon state as backup
                const iconHasUpClass = icon && icon.classList.contains('ph-caret-up');
                const iconHasDownClass = icon && icon.classList.contains('ph-caret-down');

                console.log('Is currently visible:', isCurrentlyVisible);
                console.log('Icon has up class:', iconHasUpClass);
                console.log('Icon has down class:', iconHasDownClass);

                // Check parent container
                const parentContainer = breakdown.parentElement;
                console.log('Parent container:', parentContainer);
                console.log('Parent classes:', parentContainer ? parentContainer.className : 'N/A');

                // If icon shows up (expanded) or element is visible, then hide it
                // Otherwise show it
                if (isCurrentlyVisible || iconHasUpClass) {
                    console.log('HIDING breakdown');
                    // Hide the breakdown
                    breakdown.style.display = 'none';
                    if (icon) {
                        icon.classList.remove('ph-caret-up');
                        icon.classList.add('ph-caret-down');
                    }
                } else {
                    console.log('SHOWING breakdown');
                    // Show the breakdown
                    breakdown.style.display = 'block';
                    if (icon) {
                        icon.classList.remove('ph-caret-down');
                        icon.classList.add('ph-caret-up');
                    }

                    // Log after showing
                    setTimeout(() => {
                        const afterComputed = window.getComputedStyle(breakdown);
                        const parentComputed = window.getComputedStyle(breakdown.parentElement);
                        console.log('After show - Computed display:', afterComputed.display);
                        console.log('After show - Offset height:', breakdown.offsetHeight);
                        console.log('After show - Parent:', breakdown.parentElement);
                        console.log('After show - Parent overflow:', parentComputed.overflow);
                        console.log('After show - Parent height:', parentComputed.height);
                        console.log('After show - Parent max-height:', parentComputed.maxHeight);
                        console.log('After show - Breakdown is visible:', breakdown.offsetParent !== null);

                        // Force parent to expand if needed
                        if (breakdown.offsetHeight > 0 && breakdown.parentElement) {
                            breakdown.parentElement.style.height = 'auto';
                            breakdown.parentElement.style.maxHeight = 'none';
                            breakdown.parentElement.style.overflow = 'visible';
                        }
                    }, 100);
                }
            } else {
                console.error('Breakdown element not found with id:', id);
                console.log('Available elements with similar IDs:');
                const allElements = document.querySelectorAll('[id*="tax"]');
                allElements.forEach(el => console.log('  -', el.id, el));
            }

            console.log('=== toggleTaxBreakdown END ===');
        };

        document.addEventListener('DOMContentLoaded', () => {

            $('#total_payable_amount').closest('.form-control').show();
            const initialDiscount = parseFloat($('#overall_discount').val()) || 0;
            const isDiscountEnabled = $('#category-discount').is(':checked');
            if (initialDiscount <= 0 || !isDiscountEnabled) {
                $('#overall_discount_section').hide();
            }
            if (typeof $.fn.select2 !== 'undefined') {
                $('.select2').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2({
                            width: '100%'
                        });
                    }
                });

                $('#final_discount_value, #final_discount_type').css({

                    'width': '100%'
                });

                // Also set height for Select2 container
                $('#final_discount_type').on('select2:open', function() {
                    $(this).next('.select2-container').find('.select2-selection').css('height', '38px');
                });


                // setTimeout(function() {
                //     $('#final_discount_type').next('.select2-container').find('.select2-selection').css(
                //         'height', '38px');
                //     $('#final_discount_value').css('height', '38px');
                // }, 100);


                $('#final_discount_type').on('change', function() {
                    clearTimeout(discountUpdateTimeout);
                    updateDiscount();
                });
            }

            const button = document.getElementById('toggleButton');
            const collapse = document.getElementById('collapseExample');

            if (button && collapse) {
                collapse.addEventListener('shown.bs.collapse', () => {
                    button.textContent = '{{ __('appointment.close') }}';
                });

                collapse.addEventListener('hidden.bs.collapse', () => {
                    button.textContent = '{{ __('appointment.add_item') }}';
                });


                const initialToggleState = $('#category-discount').is(':checked');

                window.toggleDiscountSection();

                const billingId = "{{ $data['billingrecord']['id'] }}";

                initializeBillingCalculations(billingId);

            }
        });

        function initializeBillingCalculations(billingId) {
            console.log('=== initializeBillingCalculations START ===');
            console.log('Billing ID:', billingId);

            // Always get billing data first, then update discount calculation
            // This ensures we have all the data before recalculating
            const isDiscountEnabled = $('#category-discount').is(':checked');
            console.log('Discount toggle enabled:', isDiscountEnabled);
            console.log('Initial PHP values from hidden inputs:');
            console.log('- #total_amount:', $('#total_amount').val());
            console.log('- #total_service_amount:', $('#total_service_amount').val());
            console.log('- #total_tax_amount:', $('#total_tax_amount').val());
            console.log('- #total_bed_charges:', $('#total_bed_charges').val());
            console.log('- #final_total_amount:', $('#final_total_amount').val());

            // Always call getTotalAmount first to get latest data from backend
            console.log('Calling getTotalAmount first...');
            getTotalAmount(billingId).then((data) => {
                console.log('getTotalAmount completed, data received:', data);
                console.log('After getTotalAmount - Current values:');
                console.log('- #total_amount:', $('#total_amount').val());
                console.log('- #total_payable_amount text:', $('#total_payable_amount').text());
                console.log('- #final_total_amounts text:', $('#final_total_amounts').text());

                // Then update discount calculation based on toggle state
                if (isDiscountEnabled) {
                    console.log('Toggle ON - Calling updateDiscount');
                    updateDiscount();
                } else {
                    // When toggle is off, set discount to 0 and recalculate totals
                    console.log('Toggle OFF - Setting discount to 0 and recalculating');
                    $('#final_discount_value').val(0);
                    $('#final_discount_type').val('percentage');
                    // Ensure overall discount is hidden if toggle is off
                    $('#overall_discount_section').hide();
                    // Call updateDiscount to recalculate totals with 0 discount via API
                    // This ensures tax is recalculated correctly on full service amount
                    console.log('Calling updateDiscount with discount=0');
                    updateDiscount();
                }
            }).catch((error) => {
                console.error('Error in getTotalAmount:', error);
                // Fallback: still try to update discount
                if (isDiscountEnabled) {
                    updateDiscount();
                } else {
                    $('#final_discount_value').val(0);
                    $('#final_discount_type').val('percentage');
                    $('#overall_discount_section').hide();
                    updateDiscount();
                }
            });

            console.log('=== initializeBillingCalculations END ===');
        }

        function removeDiscountValue() {

            $('#final_discount_value').val(0);
            $('#final_discount_type').val('percentage');

            // Hide overall discount section when discount is removed
            $('#overall_discount_section').hide();

            updateDiscount()

        }

        function getTotalAmount(billingId) {
            console.log('=== getTotalAmount START ===');
            console.log('Billing ID:', billingId);
            const isToggleEnabled = $('#category-discount').is(':checked');
            console.log('Discount Toggle State (in getTotalAmount):', isToggleEnabled);

            var baseUrl = '{{ url('/') }}';
            var url = `${baseUrl}/app/billing-record/get-billing-record/${billingId}`;
            console.log('Fetching from URL:', url);

            return fetch(url, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {

                    const phpCalculatedTotal = parseFloat($('#total_amount').val()) || 0;

                    if (phpCalculatedTotal > 0) {

                        const bedChargesFromPHP = parseFloat($('#total_bed_charges').val()) || 0;
                        const totalPayableAmountFromPHP = phpCalculatedTotal - bedChargesFromPHP;
                        $('#total_payable_amount').text(currencyFormat(totalPayableAmountFromPHP));
                        $('#final_total_amount').val(phpCalculatedTotal);
                        $('#final_total_amounts').text(currencyFormat(phpCalculatedTotal));
                    }


                    let totalServiceAmount = parseFloat($('#total_service_amount').val()) || 0;

                    if (totalServiceAmount === 0 && data.service_details.service_total) {
                        totalServiceAmount = parseFloat(data.service_details.service_total) || 0;
                        $('#total_service_amount').val(totalServiceAmount);
                        console.log('Updated totalServiceAmount from backend:', totalServiceAmount);
                    }

                    if ($('#service_amount').text().trim() === '' || $('#service_amount').text().trim() ===
                        '0' || $('#service_amount').text().trim() === '$0.00') {
                        $('#service_amount').text(currencyFormat(totalServiceAmount));
                        console.log('Updated service_amount display:', currencyFormat(totalServiceAmount));
                    }


                    $('#total_service_amount').val(totalServiceAmount);
                    $('#total_inclusive_tax').val(0);
                    $('#subtotal').val(totalServiceAmount);
                    $('#service_discount').val(0);
                    const taxAmount = parseFloat(data.service_details.total_tax) || 0;

                    $('#tax_amount').html(currencyFormat(taxAmount));
                    $('#tax_amount_display').html(currencyFormat(taxAmount));
                    $('#total_tax_amount').val(taxAmount);

                    // Update tax breakdown if available
                    if (data.service_details.tax_breakdown && data.service_details.tax_breakdown.length > 0) {
                        let breakdownHtml = '';
                        let breakdownTotal = 0;
                        data.service_details.tax_breakdown.forEach(function(taxItem) {
                            const taxName = taxItem.tax_name || taxItem.title || taxItem.name || 'Tax';
                            const taxValue = taxItem.tax_value || taxItem.value || 0;
                            const taxType = taxItem.tax_type || taxItem.type || 'percent';
                            const individualTaxAmount = taxItem.tax_amount || 0;
                            breakdownTotal += individualTaxAmount;
                            const percentLabel = taxType === 'percent' ? '(' + taxValue + '%)' : '';

                            breakdownHtml +=
                                '<div class="d-flex justify-content-between align-items-center mb-2">';
                            breakdownHtml += '<span class="text-muted">' + taxName + ' ' +
                                percentLabel +
                                '</span>';
                            breakdownHtml += '<span>' + currencyFormat(
                                individualTaxAmount) + '</span>';
                            breakdownHtml += '</div>';
                        });

                        const breakdownDiv = $('#encounter-tax-breakdown');
                        if (breakdownDiv.length) {
                            breakdownDiv.html(breakdownHtml);
                        }
                    }

                    // Set bed total amount
                    const bedCharges = parseFloat(data.service_details.total_bed_charges) || 0;
                    if (bedCharges > 0) {
                        $('#total_bed_charges').val(bedCharges);
                        $('#bed_total_amount').text(currencyFormat(bedCharges));
                        // Show bed total field if it exists
                        const bedTotalField = $('#bed_total_amount').closest('.form-control');
                        if (bedTotalField.length) {
                            bedTotalField.show();
                        }
                    } else {
                        // Hide bed total field if no charges
                        const bedTotalField = $('#bed_total_amount').closest('.form-control');
                        if (bedTotalField.length) {
                            bedTotalField.hide();
                        }
                    }

                    // Calculate overall discount (final_discount) - Apply only to service amount
                    const amountForOverallDiscount = totalServiceAmount;
                    let overallDiscountAmount = 0;

                    // Check if discount toggle is enabled
                    const isDiscountToggleEnabled = $('#category-discount').is(':checked');

                    // Only apply discount if toggle is ON AND discount is enabled in backend
                    if (isDiscountToggleEnabled && data.service_details.final_discount == 1 && data
                        .service_details.final_discount_value > 0) {
                        if (data.service_details.final_discount_type === 'percentage') {
                            overallDiscountAmount = (amountForOverallDiscount * data.service_details
                                .final_discount_value) / 100;
                        } else {
                            overallDiscountAmount = data.service_details.final_discount_value;
                        }
                        console.log('Discount Applied - Amount:', overallDiscountAmount);
                    } else {
                        // If toggle is off, discount is 0 - force recalculation via API
                        overallDiscountAmount = 0;
                        console.log('Discount NOT Applied - Toggle OFF or no discount in backend');
                        console.log('Will be recalculated by updateDiscount() API call');
                        // If toggle is off but backend has discount, we need to recalculate
                        // This will be handled by updateDiscount() call in initializeBillingCalculations
                    }

                    // Calculate amount after discount: Service Amount - Discount
                    const amountAfterDiscount = totalServiceAmount - overallDiscountAmount;

                    // Tax is calculated on (Service Amount - Discount) by the backend
                    // IMPORTANT: If discount toggle is off, tax from backend might be calculated with discount
                    // We need to ensure tax is recalculated correctly
                    let taxAmountToUse = taxAmount;

                    // If toggle is off but backend has discount, tax might be wrong
                    // In this case, updateDiscount() will recalculate it correctly
                    // For now, use the tax from backend, but updateDiscount() will fix it
                    if (!isDiscountToggleEnabled && overallDiscountAmount === 0) {
                        // Toggle is off, so tax should be on full service amount
                        // If backend tax seems wrong (calculated with discount), we'll wait for updateDiscount()
                        // But for now, calculate what tax should be if it's on full amount
                        // Note: This is a fallback - updateDiscount() API call will provide correct tax
                    }

                    // Set overall discount - always update the display (but don't show if toggle is off)
                    $('#overall_discount_display').text(currencyFormat(overallDiscountAmount));
                    $('#overall_discount').val(overallDiscountAmount);

                    // Show/hide overall discount section based on discount toggle and amount
                    const isDiscountEnabled = $('#category-discount').is(':checked');
                    // CRITICAL: Only show if toggle is ON AND discount amount is greater than 0
                    // If toggle is OFF or discount is 0, always hide
                    if (isDiscountEnabled && overallDiscountAmount > 0) {
                        $('#overall_discount_section').show();
                    } else {
                        // Hide if toggle is OFF or discount amount is 0
                        $('#overall_discount_section').hide();
                    }

                    // Store discount amount for calculations (not displayed - only Overall Discount is shown)
                    const discountAmount = overallDiscountAmount;
                    $('#final_discount_amount').val(discountAmount);



                    // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                    const totalPayableAmount = amountAfterDiscount + taxAmountToUse;

                    // Final total including bed charges (for hidden input only)
                    const grandTotal = totalPayableAmount + bedCharges;

                    // Use PHP-calculated total if available and different (PHP is source of truth)
                    const finalTotal = (phpCalculatedTotal > 0 && Math.abs(grandTotal - phpCalculatedTotal) >
                            0.01) ?
                        phpCalculatedTotal : grandTotal;



                    // Set total amount (including bed charges for form submission)
                    $('#total_amount').val(finalTotal);
                    // Display total payable amount WITHOUT bed charges
                    $('#total_payable_amount').text(currencyFormat(totalPayableAmount));
                    $('#final_total_amount').val(finalTotal);
                    $('#final_total_amounts').text(currencyFormat(finalTotal));


                    // Ensure final total amount section is visible
                    $('#final_total_amount_section').removeClass('d-none');


                    if (data.service_details.final_discount_amount > 0) {

                        $('#final_discount_section').removeClass('d-none');
                        document.getElementById('category-discount').checked = true;
                        $('#final_discount_value').val(data.service_details.final_discount_value);
                        $('#final_discount_type').val(data.service_details.final_discount_type);
                        // Show overall discount section when discount exists and update its value
                        $('#overall_discount_display').text(currencyFormat(data.service_details
                            .final_discount_amount));
                        $('#overall_discount').val(data.service_details.final_discount_amount);
                        // Only show if toggle is ON (which it is, since we just checked it)
                        $('#overall_discount_section').show();

                        // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                        const totalPayableAmount = amountAfterDiscount + taxAmount;

                        // Final total including bed charges (for hidden input only)
                        const finalTotal = parseFloat(data.service_details.final_total_amount) || (
                            totalPayableAmount + bedCharges);
                        $('#final_total_amount').val(finalTotal);
                        $('#total_amount').val(finalTotal);
                        // Display total payable amount WITHOUT bed charges
                        $('#total_payable_amount').text(currencyFormat(totalPayableAmount));
                        $('#final_total_amounts').text(currencyFormat(finalTotal));
                    } else {
                        // Hide overall discount section if no discount exists
                        $('#overall_discount_section').hide();
                    }

                    // CRITICAL: After all calculations, check toggle state again
                    // If toggle is OFF, hide overall discount section regardless of any calculations
                    const finalToggleCheck = $('#category-discount').is(':checked');
                    if (!finalToggleCheck) {
                        $('#overall_discount_section').hide();
                    }

                    return data;

                })
                .catch(error => {

                    const phpCalculatedTotal = parseFloat($('#total_amount').val()) || 0;
                    console.log('PHP Calculated Total (fallback):', phpCalculatedTotal);
                    if (phpCalculatedTotal > 0) {
                        console.log('Using PHP-calculated total from hidden input:', phpCalculatedTotal);
                        // Extract total payable amount (without bed charges)
                        const bedChargesFromPHP = parseFloat($('#total_bed_charges').val()) || 0;
                        const totalPayableAmountFromPHP = phpCalculatedTotal - bedChargesFromPHP;
                        console.log('Bed Charges (from PHP):', bedChargesFromPHP);
                        console.log('Total Payable Amount (calculated):', totalPayableAmountFromPHP);
                        $('#total_payable_amount').text(currencyFormat(totalPayableAmountFromPHP));
                    }
                    console.log('=== getTotalAmount ERROR END ===');
                });

        }



        function validateDiscount(input) {
            let maxAmount = 100;
            const discountType_value = document.querySelector('#final_discount_type').value;
            // Get numeric value from service_amount and tax_amount text content
            // Tax amount might be in a span, so get text from the element
            const serviceAmount = parseFloat(document.querySelector('#service_amount').innerText.replace(/[^0-9.-]+/g,
                '')) || 0;
            const taxAmountElement = document.querySelector('#tax_amount');
            const taxAmount = parseFloat((taxAmountElement.innerText || taxAmountElement.textContent || '').replace(
                /[^0-9.-]+/g, '')) || 0;
            const totalAmountWithTax = serviceAmount + taxAmount; // Service + Tax for discount calculation
            const discountValue = parseFloat(input.value) || 0;

            if (discountType_value === 'percentage' && input.value > maxAmount) {
                $('#discount_amount_error').text('{{ __('appointment.discount_value_less_than_100') }}');
                input.value = 0;
                clearTimeout(discountUpdateTimeout);
                updateDiscount();
            } else if (discountType_value === 'fixed' && discountValue > totalAmountWithTax) {
                $('#discount_amount_error').text('{{ __('appointment.discount_amount_exceed_service_amount') }}');
                input.value = 0;
                clearTimeout(discountUpdateTimeout);
                updateDiscount();
            } else {
                $('#discount_amount_error').text('');
                // Debounce the update to avoid too many API calls while typing
                clearTimeout(discountUpdateTimeout);
                discountUpdateTimeout = setTimeout(function() {
                    updateDiscount();
                }, 500); // Wait 500ms after user stops typing
            }
        }

        // Make calculateFinalAmount globally accessible
        // Store the real implementation separately first, then replace the stub
        console.log('Defining _realCalculateFinalAmount function...');
        window._realCalculateFinalAmount = function(discountValue, discountType) {
            console.log('calculateFinalAmount (real function) called with:', {
                discountValue,
                discountType
            });

            const billingId = "{{ $data['billingrecord']['id'] }}"; // Replace with dynamic billing ID as needed
            const baseUrl = '{{ url('/') }}'; // Base URL of your application

            // Ensure discount value is a number
            const discountValueNum = parseFloat(discountValue) || 0;

            console.log('Calculating final amount with discount:', {
                discountValue: discountValueNum,
                discountType: discountType,
                billingId: billingId
            });

            // Prepare the data to send in the POST request
            const data = {
                discount_value: discountValueNum,
                discount_type: discountType || 'percentage',
                billing_id: billingId
            };

            fetch(`${baseUrl}/app/billing-record/calculate-discount-record`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}', // Include CSRF token for Laravel
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data), // Send the data as JSON
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // CRITICAL: Preserve the service amount - it should NOT change when discount is toggled
                    // Service amount is the sum of all service totals and should remain constant
                    let totalServiceAmount = parseFloat($('#total_service_amount').val()) || 0;

                    // Only update from backend if we don't have a value yet (initial load)
                    if (totalServiceAmount === 0 && data.service_details.service_total) {
                        totalServiceAmount = parseFloat(data.service_details.service_total) || 0;
                        $('#total_service_amount').val(totalServiceAmount);
                    }

                    // DO NOT overwrite service amount from backend response when discount is updated
                    // The service amount should remain the same regardless of discount changes

                    const taxAmount = parseFloat(data.service_details.total_tax) || 0;
                    const bedCharges = parseFloat(data.service_details.total_bed_charges) || 0;

                    // Keep service amount display unchanged - it should not be recalculated
                    // Only update if it's empty (initial load)
                    if ($('#service_amount').text().trim() === '' || $('#service_amount').text().trim() ===
                        '0' ||
                        $('#service_amount').text().trim() === '$0.00') {
                        $('#service_amount').text(currencyFormat(totalServiceAmount));
                    }

                    // Ensure hidden inputs are set correctly
                    $('#total_service_amount').val(totalServiceAmount);
                    $('#total_inclusive_tax').val(0);
                    $('#subtotal').val(totalServiceAmount);
                    $('#service_discount').val(0);

                    // Service amount is already set above and should not change
                    // No need to recalculate subtotal or service discount - they're already included in service amount

                    // Set tax amount with danger color (Tax Exclusive)
                    $('#total_tax_amount').val(taxAmount);
                    $('#tax_amount_display').html(currencyFormat(taxAmount));

                    // Update tax breakdown if available
                    if (data.service_details.tax_breakdown && data.service_details.tax_breakdown.length > 0) {
                        let breakdownHtml = '';
                        data.service_details.tax_breakdown.forEach(function(taxItem) {
                            const taxName = taxItem.tax_name || taxItem.title || taxItem.name || 'Tax';
                            const taxValue = taxItem.tax_value || taxItem.value || 0;
                            const taxType = taxItem.tax_type || taxItem.type || 'percent';
                            const taxAmount = taxItem.tax_amount || 0;
                            const percentLabel = taxType === 'percent' ? '(' + taxValue + '%)' : '';

                            breakdownHtml +=
                                '<div class="d-flex justify-content-between align-items-center mb-2">';
                            breakdownHtml += '<span class="text-muted">' + taxName + ' ' +
                                percentLabel +
                                '</span>';
                            breakdownHtml += '<span>' + currencyFormat(taxAmount) +
                                '</span>';
                            breakdownHtml += '</div>';
                        });

                        const breakdownDiv = $('#encounter-tax-breakdown');
                        if (breakdownDiv.length) {
                            breakdownDiv.html(breakdownHtml);
                        } else {
                            // Create breakdown div if it doesn't exist
                            const taxContainer = $('#tax_amount_display').closest('.mb-3');
                            if (taxContainer.length) {
                                taxContainer.append(
                                    '<div id="encounter-tax-breakdown" class="tax-breakdown-details" style="display: none; margin-top: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">' +
                                    breakdownHtml + '</div>');
                            }
                        }
                    }

                    // Set bed total amount
                    if (bedCharges > 0) {
                        $('#total_bed_charges').val(bedCharges);
                        $('#bed_total_amount').text(currencyFormat(bedCharges));
                        // Show bed total field if it exists
                        const bedTotalField = $('#bed_total_amount').closest('.form-control');
                        if (bedTotalField.length) {
                            bedTotalField.show();
                        }
                    } else {
                        // Hide bed total field if no charges
                        const bedTotalField = $('#bed_total_amount').closest('.form-control');
                        if (bedTotalField.length) {
                            bedTotalField.hide();
                        }
                    }

                    // Calculate overall discount (final_discount) - Apply only to service amount
                    const amountForOverallDiscount = totalServiceAmount;
                    let overallDiscountAmount = 0;

                    if (discountValueNum > 0) {
                        if (discountType === 'percentage') {
                            overallDiscountAmount = (amountForOverallDiscount * discountValueNum) / 100;
                        } else {
                            overallDiscountAmount = discountValueNum;
                        }
                    }

                    // Calculate amount after discount: Service Amount - Discount
                    const amountAfterDiscount = totalServiceAmount - overallDiscountAmount;

                    // Tax is calculated on (Service Amount - Discount) by the backend
                    // Use the tax amount from backend response (it's already calculated correctly)

                    // Set overall discount - always update the display
                    $('#overall_discount_display').text(currencyFormat(overallDiscountAmount));
                    $('#overall_discount').val(overallDiscountAmount);

                    // Show/hide overall discount section based on discount toggle and amount
                    const isDiscountEnabled = $('#category-discount').is(':checked');
                    // Only show if toggle is ON AND discount amount is greater than 0
                    // Hide if toggle is OFF or discount amount is 0
                    if (isDiscountEnabled && discountValueNum > 0 && overallDiscountAmount > 0) {
                        $('#overall_discount_section').show();
                    } else {
                        $('#overall_discount_section').hide();
                    }

                    console.log('Overall Discount Calculation:', {
                        discountValueNum: discountValueNum,
                        discountType: discountType,
                        amountForOverallDiscount: amountForOverallDiscount,
                        overallDiscountAmount: overallDiscountAmount,
                        isDiscountEnabled: isDiscountEnabled,
                        sectionVisible: $('#overall_discount_section').is(':visible')
                    });

                    // Store discount amount for calculations (not displayed - only Overall Discount is shown)
                    const discountAmount = overallDiscountAmount;
                    $('#final_discount_amount').val(discountAmount);

                    // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                    const totalPayableAmount = amountAfterDiscount + taxAmount;

                    // Final total including bed charges (for hidden input only)
                    const grandTotal = totalPayableAmount + bedCharges;

                    // Use backend-calculated total as source of truth (it's already correct)
                    const backendTotal = parseFloat(data.service_details.final_total_amount) || parseFloat(data
                        .service_details.total_amount) || 0;

                    console.log('Grand Total Calculation:', {
                        totalServiceAmount,
                        overallDiscountAmount,
                        amountAfterDiscount,
                        taxAmount,
                        bedCharges,
                        totalPayableAmount: totalPayableAmount,
                        calculatedGrandTotal: grandTotal,
                        backendTotal: backendTotal,
                        'Using calculated total': true
                    });

                    // Use calculated grand total as it's more accurate (calculated from correct tax amount)
                    // Only use backend total if calculated total is 0 or invalid
                    const finalTotal = (grandTotal > 0) ? grandTotal : backendTotal;

                    // Set total amount (including bed charges for form submission)
                    $('#total_amount').val(finalTotal);
                    // Display total payable amount WITHOUT bed charges
                    $('#total_payable_amount').text(currencyFormat(totalPayableAmount));
                    $('#final_total_amount').val(finalTotal);
                    $('#final_total_amounts').text(currencyFormat(finalTotal));

                    // Always show final total amount section (no condition based on discount)
                    $('#final_total_amount_section').removeClass('d-none');

                    // CRITICAL: Final check - if toggle is OFF, hide overall discount section
                    const finalToggleCheck = $('#category-discount').is(':checked');
                    if (!finalToggleCheck) {
                        $('#overall_discount_section').hide();
                    }

                    // Ensure total payable amount is always visible
                    $('#total_payable_amount').closest('.form-control').show();
                })
                .catch(error => {
                    console.error('Error fetching billing data:', error);
                    // If fetch fails, try to use PHP-rendered values from hidden inputs
                    const phpCalculatedTotal = parseFloat($('#total_amount').val()) || 0;
                    if (phpCalculatedTotal > 0) {
                        console.log('Using PHP-calculated total from hidden input:', phpCalculatedTotal);
                        // Extract total payable amount (without bed charges)
                        const bedChargesFromPHP = parseFloat($('#total_bed_charges').val()) || 0;
                        const totalPayableAmountFromPHP = phpCalculatedTotal - bedChargesFromPHP;
                        $('#total_payable_amount').text(currencyFormat(totalPayableAmountFromPHP));
                    }
                });
        };

        // Now replace the stub with the real function
        console.log('About to replace stub - _realCalculateFinalAmount exists?', !!window._realCalculateFinalAmount);
        console.log('_realCalculateFinalAmount type:', typeof window._realCalculateFinalAmount);
        if (window._realCalculateFinalAmount && typeof window._realCalculateFinalAmount === 'function') {
            window.calculateFinalAmount = window._realCalculateFinalAmount;
            console.log('Real calculateFinalAmount function loaded and stub replaced successfully');
        } else {
            console.error('ERROR: _realCalculateFinalAmount is not a function!', typeof window
                ._realCalculateFinalAmount);
        }

        // Note: submitBillingForm and submitBillingFormHandler are already defined above in the IIFE
        // They are available as window.submitBillingForm and window.submitBillingFormHandler
        console.log('=== Billing Form Handler Setup (Second Check) ===');
        console.log('window.submitBillingFormHandler defined:', typeof window.submitBillingFormHandler !== 'undefined');
        console.log('window.submitBillingForm defined:', typeof window.submitBillingForm !== 'undefined');

        // Test if button exists and is clickable
        setTimeout(function() {
            if (typeof $ !== 'undefined') {
                const saveButton = $('#save-button');
                console.log('=== SAVE BUTTON CHECK ===');
                console.log('Save button exists:', saveButton.length > 0);
                if (saveButton.length > 0) {
                    // Check if handlers are attached
                    const clickEvents = $._data(saveButton[0], 'events');
                    console.log('Attached click events:', clickEvents ? clickEvents.click : 'none');
                } else {
                    console.warn('⚠️ Save button not found in DOM!');
                    console.warn('Searching for button with different selector...');
                    console.log('Buttons with id containing save:', $('[id*="save"]'));
                    console.log('All buttons in modal:', $('#generate_invoice').find('button'));
                }
            }
        }, 2000);

        // Prevent form submission and handle via AJAX
        function attachBillingFormHandler() {
            $('#billingForm').off('submit').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                return submitBillingForm();
            });
        }

        // Handle save button click directly - this is the primary method
        // Use both delegated and direct event handlers
        $(document).on('click', '#save-button', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('=== SAVE BUTTON CLICKED (Delegated Handler) ===');
            console.log('Timestamp:', new Date().toISOString());
            console.log('Button element:', this);
            console.log('Event:', e);
            console.log('submitBillingForm function available:', typeof submitBillingForm !== 'undefined');
            submitBillingForm();
            return false;
        });

        // Also attach directly when modal is shown
        $(document).on('shown.bs.modal', '#generate_invoice', function() {
            console.log('=== MODAL SHOWN - Attaching Handlers ===');
            console.log('Save button exists:', $('#save-button').length > 0);
            console.log('Save button element:', $('#save-button')[0]);

            // Remove any existing handlers and attach fresh
            $('#save-button').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('=== SAVE BUTTON CLICKED (Direct Handler) ===');
                console.log('Timestamp:', new Date().toISOString());
                console.log('Button element:', this);
                console.log('Event:', e);
                console.log('submitBillingForm function available:', typeof submitBillingForm !==
                    'undefined');
                submitBillingForm();
                return false;
            });
            console.log('Save button handler attached on modal shown');

            // Also ensure the global handler is available
            if (typeof window.submitBillingFormHandler === 'undefined') {
                window.submitBillingFormHandler = function() {
                    console.log('=== SAVE BUTTON CLICKED (Inline Handler from Modal) ===');
                    console.log('Timestamp:', new Date().toISOString());
                    submitBillingForm();
                };
            }
            console.log('Global handler available:', typeof window.submitBillingFormHandler !== 'undefined');
        });

        // Wait for jQuery to be available before attaching handlers
        function initializeBillingFormHandlers() {
            console.log('=== INITIALIZING BILLING FORM HANDLERS ===');
            console.log('jQuery available:', typeof $ !== 'undefined');
            console.log('jQuery alias available:', typeof jQuery !== 'undefined');

            if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
                console.log('⏳ jQuery not available yet, retrying in 100ms...');
                setTimeout(initializeBillingFormHandlers, 100);
                return;
            }

            console.log('✅ jQuery available, attaching billing form handlers');
            console.log('Save button exists:', $('#save-button').length > 0);

            // Attach form submit handler
            attachBillingFormHandler();
            console.log('Form submit handler attached');

            // Also attach on document ready as backup
            $(document).ready(function() {
                console.log('=== DOCUMENT READY ===');
                attachBillingFormHandler();
                console.log('Billing form handler attached on document ready');
                console.log('Save button in DOM:', $('#save-button').length > 0);
            });
        }

        // Start initialization
        console.log('=== STARTING BILLING FORM HANDLER INITIALIZATION ===');
        initializeBillingFormHandlers();

        // Also attach when modal is shown (in case form is dynamically loaded)
        // Use setTimeout to ensure jQuery is available
        setTimeout(function() {
            if (typeof $ !== 'undefined') {
                $(document).on('shown.bs.modal', '#generate_invoice', function() {
                    attachBillingFormHandler();
                    console.log('Billing form handler attached on modal shown');
                });
            }
        }, 500);

        // Bed Allocation Form Handling
        document.addEventListener('DOMContentLoaded', function() {
            const showAddBedFormBtn = document.getElementById('showAddBedFormBtn');
            const bedAllocationForm = document.getElementById('bedAllocationForm');
            const cancelBedForm = document.getElementById('cancelBedForm');
            const addBedForm = document.getElementById('addBedForm');

            // Only add event listeners if elements exist
            if (showAddBedFormBtn) {
                // Show form and fetch existing data
                showAddBedFormBtn.addEventListener('click', function() {
                    if (bedAllocationForm) {
                        bedAllocationForm.classList.remove('d-none');
                        fetchExistingBedAllocation();
                    }
                });
            }

            // Hide form
            if (cancelBedForm && bedAllocationForm && addBedForm) {
                cancelBedForm.addEventListener('click', function() {
                    bedAllocationForm.classList.add('d-none');
                    addBedForm.reset();
                    $('#bed_type_id').val('').trigger('change');
                    $('#room_no').val('').trigger('change');
                    $('#bed_price').val('');
                    $('#bed_allocation_id').val('');
                });
            }

            // Function to fetch existing bed allocation data
            function fetchExistingBedAllocation() {
                const baseUrl = '{{ url('/') }}';
                const encounterId = '{{ $data['id'] }}';

                $.ajax({
                    url: baseUrl + '/app/bed-allocation/encounter/' + encounterId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success && response.data && response.data.length > 0) {
                            if (response.data.length === 1) {
                                // Only one bed allocation, edit it directly
                                populateBedAllocationForm(response.data[0]);
                            } else {
                                // Multiple bed allocations, show selection dialog
                                showBedAllocationSelectionDialog(response.data);
                            }
                        } else {
                            // No bed allocation found, show message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'No Bed Allocation',
                                    text: 'No bed allocation found for this encounter. Please add a new bed allocation first.',
                                    icon: 'info'
                                });
                            }
                            bedAllocationForm.classList.add('d-none');
                        }
                    },
                    error: function() {
                        console.error('Failed to fetch bed allocation data');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Error',
                                text: 'Failed to fetch bed allocation data',
                                icon: 'error'
                            });
                        }
                        bedAllocationForm.classList.add('d-none');
                    }
                });
            }

            // Function to show bed allocation selection dialog
            function showBedAllocationSelectionDialog(bedAllocations) {
                const options = bedAllocations.map((allocation, index) => {
                    const bedType = allocation.bed_master && allocation.bed_master.bed_type ? allocation
                        .bed_master.bed_type.type : 'Unknown';
                    const roomBed = allocation.bed_master ? allocation.bed_master.bed : 'Unknown';
                    const assignDate = allocation.assign_date ? allocation.assign_date.split('T')[0] :
                        'N/A';
                    return {
                        value: index,
                        text: `${bedType} - ${roomBed} (Assigned: ${assignDate})`
                    };
                    console.log(formData);
                    // Make AJAX request
                    $.ajax({
                        url: `${baseUrl}/app/billing-record/save-billing-detail-data`,
                        method: 'POST',
                        data: formData,
                        success: function(response) {
                            $('#generate_invoice').modal('hide');

                            window.location.reload();

                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', xhr.responseJSON || xhr.responseText ||
                                error);
                        }
                    });
                });
            }
        });


        // Bed Allocation Form Handling
        document.addEventListener('DOMContentLoaded', function() {
            const showAddBedFormBtn = document.getElementById('showAddBedFormBtn');
            const bedAllocationForm = document.getElementById('bedAllocationForm');
            const cancelBedForm = document.getElementById('cancelBedForm');
            const addBedForm = document.getElementById('addBedForm');

            // Show form and fetch existing data
            showAddBedFormBtn.addEventListener('click', function() {
                bedAllocationForm.classList.remove('d-none');
                fetchExistingBedAllocation();
            });

            // Hide form
            cancelBedForm.addEventListener('click', function() {
                bedAllocationForm.classList.add('d-none');
                addBedForm.reset();
                $('#bed_type_id').val('').trigger('change');
                $('#room_no').val('').trigger('change');
                $('#bed_price').val('');
                $('#bed_allocation_id').val('');
            });

            // Function to fetch existing bed allocation data
            function fetchExistingBedAllocation() {
                const baseUrl = '{{ url('/') }}';
                const encounterId = '{{ $data['id'] }}';

                $.ajax({
                    url: baseUrl + '/app/bed-allocation/encounter/' + encounterId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success && response.data && response.data
                            .length >
                            0) {
                            if (response.data.length === 1) {
                                // Only one bed allocation, edit it directly
                                populateBedAllocationForm(response.data[0]);
                            } else {
                                // Multiple bed allocations, show selection dialog
                                showBedAllocationSelectionDialog(response.data);
                            }
                        } else {
                            // No bed allocation found, show message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'No Bed Allocation',
                                    text: 'No bed allocation found for this encounter. Please add a new bed allocation first.',
                                    icon: 'info'
                                });
                            }
                            bedAllocationForm.classList.add('d-none');
                        }
                    },
                    error: function() {
                        console.error('Failed to fetch bed allocation data');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Error',
                                text: 'Failed to fetch bed allocation data',
                                icon: 'error'
                            });
                        }
                        bedAllocationForm.classList.add('d-none');
                    }
                });
            }

            // Function to show bed allocation selection dialog
            function showBedAllocationSelectionDialog(bedAllocations) {
                const options = bedAllocations.map((allocation, index) => {
                    const bedType = allocation.bed_master && allocation.bed_master
                        .bed_type ?
                        allocation
                        .bed_master.bed_type.type : 'Unknown';
                    const roomBed = allocation.bed_master ? allocation.bed_master.bed :
                        'Unknown';
                    const assignDate = allocation.assign_date ? allocation.assign_date
                        .split('T')[
                            0] :
                        'N/A';
                    return {
                        value: index,
                        text: `${bedType} - ${roomBed} (Assigned: ${assignDate})`
                    };
                });

                const selectOptions = options.map(option =>
                    `<option value="${option.value}">${option.text}</option>`
                ).join('');

                const dialogHtml = `
                                        <div class="mb-3">
                                            <label class="form-label">Select Bed Allocation to Edit:</label>
                                            <select id="bedAllocationSelect" class="form-control">
                                                ${selectOptions}
                                            </select>
                                        </div>
                                    `;

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Select Bed Allocation',
                        html: dialogHtml,
                        showCancelButton: true,
                        confirmButtonText: 'Edit',
                        cancelButtonText: 'Cancel',
                        preConfirm: () => {
                            const selectedIndex = document.getElementById(
                                    'bedAllocationSelect')
                                .value;
                            return selectedIndex;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const selectedIndex = result.value;
                            const selectedAllocation = bedAllocations[selectedIndex];
                            populateBedAllocationForm(selectedAllocation);
                        } else {
                            bedAllocationForm.classList.add('d-none');
                        }
                    });
                } else {
                    // Fallback if SweetAlert is not available
                    const selectedIndex = prompt(
                        'Enter the number of the bed allocation to edit (0-' + (
                            bedAllocations.length - 1) + '):');
                    if (selectedIndex !== null && !isNaN(selectedIndex) && selectedIndex >= 0 &&
                        selectedIndex <
                        bedAllocations.length) {
                        populateBedAllocationForm(bedAllocations[selectedIndex]);
                    } else {
                        bedAllocationForm.classList.add('d-none');
                    }
                }
            }

            // Handle form submission
            if (addBedForm) {
                addBedForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const bedAllocationId = $('#bed_allocation_id').val();
                    if (!bedAllocationId) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Error',
                                text: 'No bed allocation selected for editing',
                                icon: 'error'
                            });
                        }
                        return;
                    }

                    const formData = new FormData(this);
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('_method', 'PUT');

                    const baseUrl = '{{ url('/') }}';

                    $.ajax({
                        url: baseUrl + '/app/bed-allocation/' + bedAllocationId,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                // Refresh the bed allocation table
                                refreshBedAllocationTable();

                                // Hide form and reset
                                bedAllocationForm.classList.add('d-none');
                                addBedForm.reset();
                                $('#bed_type_id').val('').trigger('change');
                                $('#room_no').val('').trigger('change');
                                $('#bed_price').val('');
                                $('#bed_allocation_id').val('');

                                // Show success message
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: 'Success',
                                        text: response.message ||
                                            'Bed allocation updated successfully',
                                        icon: 'success'
                                    });
                                }
                            } else {
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: 'Error',
                                        text: response.message ||
                                            'Failed to update bed allocation',
                                        icon: 'error'
                                    });
                                }
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error(error);
                            let errorMessage =
                                'An unexpected error occurred.';

                            if (xhr.responseJSON && xhr.responseJSON
                                .message) {
                                errorMessage = xhr.responseJSON.message;
                            } else if (xhr.responseJSON && xhr.responseJSON
                                .errors) {
                                errorMessage = Object.values(xhr
                                        .responseJSON
                                        .errors).flat()
                                    .join(
                                        ', ');
                            }

                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'Error',
                                    text: errorMessage,
                                    icon: 'error'
                                });
                            }
                        }
                    });
                });
            }

            const baseUrl = '{{ url('/') }}';

            $.ajax({
                url: baseUrl + '/app/bed-allocation/' + bedAllocationId,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Refresh the bed allocation table
                        refreshBedAllocationTable();

                        // Hide form and reset
                        bedAllocationForm.classList.add('d-none');
                        addBedForm.reset();
                        $('#bed_type_id').val('').trigger('change');
                        $('#room_no').val('').trigger('change');
                        $('#bed_price').val('');
                        $('#bed_allocation_id').val('');

                        // Show success message
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Success',
                                text: response.message ||
                                    'Bed allocation updated successfully',
                                icon: 'success'
                            });
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Error',
                                text: response.message ||
                                    'Failed to update bed allocation',
                                icon: 'error'
                            });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error);
                    let errorMessage = 'An unexpected error occurred.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMessage = Object.values(xhr.responseJSON.errors).flat()
                            .join(
                                ', ');
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Error',
                            text: errorMessage,
                            icon: 'error'
                        });
                    }
                }
            });
        });

        // Function to refresh bed allocation table
        function refreshBedAllocationTable() {
            const baseUrl = '{{ url('/') }}';
            const encounterId = '{{ $data['id'] }}';

            $.ajax({
                url: baseUrl + '/app/bed-allocation/encounter/' + encounterId,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#bed-allocation-table').html(response.html);
                        // Recalculate bed charges after refreshing the table
                        calculateBedCharges();
                    }
                },
                error: function() {
                    console.error('Failed to refresh bed allocation table');
                }
            });
        }

        // Initialize select2 for bed type and room - only if not already initialized
        if (typeof $.fn.select2 !== 'undefined') {
            $('#bed_type_id, #room_no').each(function() {
                if ($(this).length && !$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        width: '100%'
                    });
                }
            });
        }

        // Handle bed type change to load rooms and set price
        $('#bed_type_id').on('change', function() {
            const bedTypeId = $(this).val();
            if (bedTypeId) {
                loadRoomsForBedType(bedTypeId);

                // Get bed type price
                const baseUrl = '{{ url('/') }}';
                $.ajax({
                    url: baseUrl + '/app/bed-type/' + bedTypeId + '/price',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            $('#bed_price').val(response.price);
                        }
                    }
                });
            } else {
                $('#room_no').empty().append('<option value="">Select Room/Bed</option>');
                $('#bed_price').val('');
            }
        });


        function calculateBedCharges() {
            let totalBedCharges = 0;

            // Get all bed allocation rows from the table
            const bedRows = document.querySelectorAll('#bed-allocation-table tbody tr');

            bedRows.forEach(row => {
                const chargeCell = row.querySelector('td:nth-child(6)'); // Charge column
                if (chargeCell) {
                    const chargeText = chargeCell.textContent.trim();
                    // Extract numeric value from currency format (e.g., "₹1,000.00" -> 1000.00)
                    const chargeValue = parseFloat(chargeText.replace(/[^0-9.-]+/g, '')) || 0;
                    totalBedCharges += chargeValue;
                }
            });

            // Update the bed charges display
            $('#total_bed_charges').val(totalBedCharges);

            // Update bed charges amount display if element exists
            if ($('#bed_charges_amount').length) {
                $('#bed_charges_amount').text(currencyFormat(totalBedCharges));
            }

            // Show/hide bed charges section based on whether there are charges
            if ($('#bed_charges_section').length) {
                if (totalBedCharges > 0) {
                    $('#bed_charges_section').removeClass('d-none');
                } else {
                    $('#bed_charges_section').addClass('d-none');
                }
            }

            // Update total amount including bed charges
            updateTotalWithBedCharges(totalBedCharges);

            console.log('Bed charges calculated:', totalBedCharges);
        }

        function updateTotalWithBedCharges(bedCharges) {
            const serviceAmount = parseFloat($('#total_service_amount').val()) || 0;
            const taxAmount = parseFloat($('#total_tax_amount').val()) || 0;
            const discountAmount = parseFloat($('#final_discount_amount').val()) || 0;

            const totalAmount = serviceAmount + taxAmount + bedCharges - discountAmount;

            $('#total_amount').val(totalAmount);

            // Update total payable amount display if element exists
            if ($('#total_payable_amount').length) {
                $('#total_payable_amount').text(currencyFormat(totalAmount));
            }

            $('#final_total_amount').val(totalAmount);

            //     // Update the bed charges display
            //     $('#total_bed_charges').val(totalBedCharges);
            //     $('#bed_charges_amount').text(currencyFormat(totalBedCharges));

            //     // Show/hide bed charges section based on whether there are charges
            //     if (totalBedCharges > 0) {
            //         $('#bed_charges_section').removeClass('d-none');
            //     } else {
            //         $('#bed_charges_section').addClass('d-none');
            //     }

            //     // Update total amount including bed charges
            //     updateTotalWithBedCharges(totalBedCharges);

            //     console.log('Bed charges calculated:', totalBedCharges);
            // }

            // function updateTotalWithBedCharges(bedCharges) {
            //     const serviceAmount = parseFloat($('#total_service_amount').val()) || 0;
            //     const taxAmount = parseFloat($('#total_tax_amount').val()) || 0;
            //     const discountAmount = parseFloat($('#final_discount_amount').val()) || 0;

            //     const totalAmount = serviceAmount + taxAmount + bedCharges - discountAmount;

            //     $('#total_amount').val(totalAmount);
            //     $('#total_payable_amount').text(currencyFormat(totalAmount));
            //     $('#final_total_amount').val(totalAmount);

            //     console.log('Total updated:', {
            //         serviceAmount,
            //         taxAmount,
            //         bedCharges,
            //         discountAmount,
            //         totalAmount
            //     });
            // }

        }


        // Debug: Log on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DOM Loaded - Checking Tax Breakdown ===');
            const breakdown = document.getElementById('encounter-tax-breakdown');
            const icon = document.getElementById('encounter-tax-breakdown-icon');
            console.log('Breakdown on load:', breakdown);
            console.log('Icon on load:', icon);
            if (breakdown) {
                console.log('Breakdown parent:', breakdown.parentElement);
                console.log('Breakdown initial display:', window.getComputedStyle(breakdown)
                    .display);
            }
        });
    </script>
@endpush



<!-- Select2 is already loaded in main backend layout (app.blade.php) -->
