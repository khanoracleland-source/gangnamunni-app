<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.invoice_id') }}</title>
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
                @foreach($data as $info)

                <div class="row">
                    <div class="col-md-6">
                        <h2 class="mb-0">{{ $info['cliniccenter']['name'] ?? '--' }}</h2>
                        <h3 class="mb-0 font-weight-bold">{{ __('messages.invoice_id') }} <span class="text-primary">#{{ $info['id'] ?? '--' }}</span></h3>
                        @php
                        
                        $setting = App\Models\Setting::where('name', 'date_formate')->first();
                        $dateformate = $setting ? $setting->val : 'Y-m-d';
                        $setting = App\Models\Setting::where('name', 'time_formate')->first();
                        $timeformate = $setting ? $setting->val : 'h:i A';
                        $createdDate = date($dateformate, strtotime($info['appointment_date'] ?? '--' ));
                        $createdTime = date($timeformate, strtotime($info['appointment_time'] ?? '--' ));
                        @endphp
                        <h4 class="mb-0">
                            <span class="font-weight-bold"> {{ __('messages.appointment_at') }}: </span> {{ $createdDate }}
                        </h4>
                        <h4 class="mb-0">
                            <span class="font-weight-bold"> {{ __('messages.appointment_time') }}: </span> {{ $createdTime }}
                        </h4>
                    </div>
                    <div class="col-md-6 text-right">
                        <p class="mb-0">{{ $info['cliniccenter']['address'] ?? '--' }}</p>
                        <p class="mb-0">{{ $info['cliniccenter']['email'] ?? '--' }}</p>
                        <p class="mb-0 mt-2">
                            {{ __('messages.payment_status') }}
                            @if($info['appointmenttransaction']['payment_status'] == 1)
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
                                    <td>{{ $info['user']['first_name'] .''.$info['user']['last_name'] ?? '--' }}</td>
                                    <td>{{ $info['user']['gender'] ?? '--' }}</td>
                                    @if($info['user']['date_of_birth'] !== null)
                                    <td>{{ date($dateformate, strtotime($info['user']['date_of_birth']))  ?? '--'  }}</td>
                                    @else
                                    <td>-</td>
                                    @endif
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr class="my-3" />
                @if(isset($info['patient_encounter']))
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
                                        <th style="text-align: right;">{{ __('messages.price') }}</th>
                                        <th style="text-align: right;">{{ __('service.inclusive_tax') }}</th>
                                        <th style="text-align: right;">{{ __('messages.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $index = 1 @endphp
                                    @foreach ($info['patient_encounter']['billingrecord']['billing_item'] as $billingItem)
 
                                    <tr>
                                        <td>{{ $index }}</td>
                                        @if($billingItem['discount_value'] != 0)
                                            @if ($billingItem['discount_type'] === 'percentage')
                                                <td>{{ $billingItem['clinicservice']['name'] ?? '--' }} (<span>{{ $billingItem['discount_value'] ?? '--' }}%</span>)</td>
                                            @else
                                                <td>{{ $billingItem['clinicservice']['name'] ?? '--' }} (<span>{{ Currency::format($billingItem['discount_value']) ?? '--' }}</span>)</td>
                                            @endif

                                        @else
                                            <td>{{ $billingItem['clinicservice']['name'] ?? '--' }}</td>
                                        @endif
                                        <td style="text-align: right;">{{ Currency::format($billingItem['service_amount']) ?? '--' }}</td>
                                        <td style="text-align: right;">{{ Currency::format($billingItem['inclusive_tax_amount']) ?? '--' }}</td>
                                        <td style="text-align: right;">{{ Currency::format($billingItem['total_amount']) ?? '--' }}</td>
                                    </tr>
                                    @php $index++ @endphp
                                    @endforeach
                                </tbody>
                                @if($info['clinicservice'] == null)
                                <tbody>
                                    <tr>
                                        <td colspan="6">
                                            <h4 class="text-primary mb-0">{{ __('messages.no_record_found') }}</h4>
                                        </td>
                                    </tr>
                                </tbody>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                @php
                    $showMedicines = false;
                    $medicine_total_amount = 0;
                    if(isset($info['patient_encounter']) && !empty($info['patient_encounter']['prescriptions'])) {
                        $paymentStatus = $info['patient_encounter']['prescription_payment_status'] ?? 0;
                        // Check if payment status is 1 (paid) or 'paid' (string)
                        if($paymentStatus == 1 || $paymentStatus === 'paid') {
                            $showMedicines = true;
                            foreach($info['patient_encounter']['prescriptions'] as $prescription) {
                                $medicine_total_amount += $prescription['total_amount'] ?? 0;
                            }
                        }
                    }
                @endphp

              
                <hr class="my-3" />
                <div class="row">
                    <div class="col-md-12">
                        <h3>{{ __('messages.medicine') }}</h3>
                    </div>
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table custom-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('messages.sr_no') }}</th>
                                        <th>{{ __('messages.medicine_name') }}</th>
                                        <th style="text-align: right;">{{ __('messages.qty') }}</th>
                                        <th style="text-align: right;">{{ __('messages.price') }}</th>
                                        {{-- <th style="text-align: right;">{{ __('messages.total') }}</th> --}}
                                        <th style="text-align: center;">{{ __('messages.payment_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php 
                                        $index = 1;
                                        $prescriptionPaymentStatus = $info['patient_encounter']['prescription_payment_status'] ?? 0;
                                    @endphp
                                    @foreach ($info['patient_encounter']['prescriptions'] as $prescription)
                                    <tr>
                                        <td>{{ $index }}</td>
                                        <td>
                                            {{ $prescription['name'] ?? '--' }}
                                            @if (!empty($prescription['frequency']))
                                                <br><small class="text-muted">{{ __('messages.frequency') }}: {{ $prescription['frequency'] }}</small>
                                            @endif
                                            @if (!empty($prescription['duration']))
                                                <br><small class="text-muted">{{ __('messages.duration') }}: {{ $prescription['duration'] }} {{ __('messages.days') }}</small>
                                            @endif
                                            @if (!empty($prescription['instruction']))
                                                <br><small class="text-muted">{{ __('messages.instruction') }}: {{ $prescription['instruction'] }}</small>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">{{ $prescription['quantity'] ?? '--' }}</td>
                                        <td style="text-align: right;">{{ Currency::format($prescription['medicine_price'] ?? 0) }}</td>
                                        {{-- <td style="text-align: right;">{{ Currency::format($prescription['total_amount'] ?? 0) }}</td> --}}
                                        <td style="text-align: center;">
                                            @if($prescriptionPaymentStatus == 1 || $prescriptionPaymentStatus === 'paid')
                                                <span class="badge badge-success">{{ __('messages.paid') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ __('messages.unpaid') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @php $index++ @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                 
                <hr class="my-3" />

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
                <div class="row">
                    <div class="col-md-12">
                        <h3>{{ __('messages.bed_allocation') }}</h3>
                    </div>
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table custom-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('messages.patient_name') }}</th>
                                        <th>{{ __('messages.bed_type') }}</th>
                                        <th>{{ __('messages.room') }} / {{ __('messages.bed') }}</th>
                                        <th>{{ __('messages.assign_date') }}</th>
                                        <th>{{ __('messages.discharge_date') }}</th>
                                        <th style="text-align: right;">{{ __('messages.bed_price') }}</th>
                                        <th style="text-align: right;">{{ __('messages.Total_charge') }}</th>
                                        <th>{{ __('messages.payment_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($currentBedAllocations as $allocation)
                                        @php
                                            $allocationData = is_array($allocation) ? $allocation : $allocation->toArray();
                                            $assignDate = $allocationData['assign_date'] ?? null;
                                            $dischargeDate = $allocationData['discharge_date'] ?? null;
                                            
                                            // Calculate days
                                            if ($assignDate && $dischargeDate) {
                                                $days = \Carbon\Carbon::parse($assignDate)->diffInDays(\Carbon\Carbon::parse($dischargeDate));
                                                $days = $days > 0 ? $days : 1;
                                            } else {
                                                $days = 1;
                                            }
                                            
                                            // Get patient name
                                            $patient = $allocationData['patient'] ?? null;
                                            $patientName = '--';
                                            if ($patient) {
                                                if (is_array($patient)) {
                                                    $patientName = $patient['full_name'] ?? ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '');
                                                } else {
                                                    $patientName = $patient->full_name ?? ($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '');
                                                }
                                            }
                                            
                                            // Get bed type
                                            $bedType = $allocationData['bed_type'] ?? $allocationData['bedType'] ?? null;
                                            $bedTypeName = '--';
                                            if ($bedType) {
                                                if (is_array($bedType)) {
                                                    $bedTypeName = $bedType['type'] ?? $bedType['name'] ?? '--';
                                                } else {
                                                    $bedTypeName = $bedType->type ?? $bedType->name ?? '--';
                                                }
                                            }
                                            
                                            // Also try to get from bedMaster->bedType if bedType is not directly available
                                            if ($bedTypeName == '--') {
                                                $bedMaster = $allocationData['bed_master'] ?? $allocationData['bedMaster'] ?? null;
                                                if ($bedMaster) {
                                                    $bedMasterBedType = is_array($bedMaster) ? ($bedMaster['bed_type'] ?? $bedMaster['bedType'] ?? null) : ($bedMaster->bedType ?? null);
                                                    if ($bedMasterBedType) {
                                                        if (is_array($bedMasterBedType)) {
                                                            $bedTypeName = $bedMasterBedType['type'] ?? $bedMasterBedType['name'] ?? '--';
                                                        } else {
                                                            $bedTypeName = $bedMasterBedType->type ?? $bedMasterBedType->name ?? '--';
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            // Get bed master
                                            $bedMaster = $allocationData['bed_master'] ?? $allocationData['bedMaster'] ?? null;
                                            $bedName = '--';
                                            $bedCharges = 0;
                                            if ($bedMaster) {
                                                if (is_array($bedMaster)) {
                                                    $bedName = $bedMaster['bed'] ?? '--';
                                                    $bedCharges = $bedMaster['charges'] ?? 0;
                                                } else {
                                                    $bedName = $bedMaster->bed ?? '--';
                                                    $bedCharges = $bedMaster->charges ?? 0;
                                                }
                                            }
                                            
                                            // Get charge
                                            $charge = $allocationData['charge'] ?? 0;
                                            
                                            // Payment status - match bed_allocation_table logic: if appointment payment is paid OR bed_payment_status is paid, show as Paid
                                            $bedPaymentStatus = $allocationData['bed_payment_status'] ?? 0;
                                            
                                            // Get appointment payment status from the info array (same appointment being processed)
                                            $appointmentPaymentStatus = $info['appointmenttransaction']['payment_status'] ?? 0;
                                            
                                            // If appointment payment is paid OR bed payment status is paid, show as Paid
                                            if ($appointmentPaymentStatus == 1 || $bedPaymentStatus == 1) {
                                                $paymentStatusText = __('messages.paid');
                                            } else {
                                                $paymentStatusText = __('messages.unpaid');
                                            }
                    @endphp
                                        <tr>
                                            <td>{{ $patientName }}</td>
                                            <td>{{ $bedTypeName }}</td>
                                            <td>{{ $bedName }}</td>
                                            <td>{{ $assignDate ? \Carbon\Carbon::parse($assignDate)->format('Y-m-d') : '--' }}</td>
                                            <td>{{ $dischargeDate ? \Carbon\Carbon::parse($dischargeDate)->format('Y-m-d') : '--' }}</td>
                                            <td style="text-align: right;">{{ $bedCharges ? Currency::format($bedCharges) . ' * ' . $days : '--' }}</td>
                                            <td style="text-align: right;">{{ $charge ? Currency::format($charge) : '--' }}</td>
                                            <td>{{ $paymentStatusText }}</td>
                                        </tr>
                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <hr class="my-3" />
                @endif

                @php
                    // Calculate service total from billing items - matching appointment details page logic
                    $service_total_amount = 0;
                    $hasInclusiveTax = false;
                    
                    if (isset($info['patient_encounter']['billingrecord']['billing_item'])) {
                        // For encounters: Calculate from billing items with item-level discounts already applied
                        foreach ($info['patient_encounter']['billingrecord']['billing_item'] as $item) {
                            $quantity = $item['quantity'] ?? 1;
                            $service_price = $item['service_amount'] ?? 0;
                            
                            // Calculate inclusive tax
                            $inclusive_tax = $item['inclusive_tax_amount'] ?? 0;
                            if ($inclusive_tax > 0) {
                                $hasInclusiveTax = true;
                            }
                            
                            // Calculate subtotal per unit: Price + Inclusive Tax
                            $subtotalPerUnit = $service_price + $inclusive_tax;
                            
                            // Calculate total subtotal: (Price + Inclusive Tax) * Quantity
                            $item_total = $subtotalPerUnit * $quantity;
                            
                            // Get discount information
                            $itemDiscountValue = $item['discount_value'] ?? null;
                            $itemDiscountType = $item['discount_type'] ?? null;
                            $itemDiscountStatus = $item['discount_status'] ?? null;
                            
                            // Calculate service discount amount
                            $item_discount = 0;
                            if (!empty($itemDiscountValue) && $itemDiscountValue > 0) {
                                if ($itemDiscountStatus === null) {
                                    $itemDiscountStatus = 1;
                                }
                                
                                if ($itemDiscountStatus == 1) {
                                    if ($itemDiscountType === 'percentage') {
                                        $item_discount = ($item_total * $itemDiscountValue) / 100;
                                    } else {
                                        $item_discount = $itemDiscountValue * $quantity;
                                    }
                                }
                            }
                            
                            // Final total: Subtotal - Service Discount
                            $service_total_amount += ($item_total - $item_discount);
                        }
                    } else {
                        // For direct appointment without encounter
                        $service_total_amount = $info['service_price'] ?? 0;
                        if (isset($info['appointmenttransaction']['inclusive_tax_price']) && $info['appointmenttransaction']['inclusive_tax_price'] > 0) {
                            $service_total_amount += $info['appointmenttransaction']['inclusive_tax_price'];
                            $hasInclusiveTax = true;
                        }
                    }
                @endphp

                @php
                    // Get transaction and billing record
                    $transaction = $info['appointmenttransaction'] ?? null;
                    $billingRecord = null;
                    if (isset($info['patient_encounter']['billingrecord'])) {
                        $billingRecord = $info['patient_encounter']['billingrecord'];
                        $transaction = $billingRecord;
                    }
                    
                    // STEP 1: Service Amount (already calculated with item-level discounts)
                    $service_total = $service_total_amount;

                    // STEP 2: Calculate Encounter-Level Discount on Service Amount ONLY (matching appointment details)
                    $encounter_discount_amount = 0;
                    $encounter_discount_percent = 0;
                    $encounter_discount_type = '';
                    
                    if (isset($info['patient_encounter']['billingrecord']) && ($billingRecord['final_discount'] ?? null) == 1) {
                        $encounter_discount_percent = $billingRecord['final_discount_value'] ?? 0;
                        $encounter_discount_type = $billingRecord['final_discount_type'] ?? 'percentage';
                        
                        // Discount is applied to Service Amount ONLY (not Service + Tax)
                        if ($encounter_discount_type === 'percentage') {
                            $encounter_discount_amount = ($service_total * $encounter_discount_percent) / 100;
                        } else {
                            $encounter_discount_amount = $encounter_discount_percent;
                        }
                    } elseif (isset($info['appointmenttransaction']['discount_value']) && $info['appointmenttransaction']['discount_value'] > 0) {
                        // Direct appointment discount
                        $encounter_discount_percent = $info['appointmenttransaction']['discount_value'];
                        $encounter_discount_type = $info['appointmenttransaction']['discount_type'] ?? 'percentage';
                        
                        if ($encounter_discount_type === 'percentage') {
                            $encounter_discount_amount = ($service_total * $encounter_discount_percent) / 100;
                        } else {
                            $encounter_discount_amount = $encounter_discount_percent;
                        }
                    }

                    // Calculate amount after discount: Service Amount - Discount
                    $amountAfterDiscount = $service_total - $encounter_discount_amount;

                    // STEP 3: Calculate Tax on (Service Amount - Discount) - matching appointment details
                    // First try to get tax_data from billing record
                    $taxDataFromRecord = null;
                    if ($billingRecord && isset($billingRecord['tax_data']) && $billingRecord['tax_data'] !== null) {
                        $taxDataFromRecord = json_decode($billingRecord['tax_data'], true);
                    }
                    
                    // If no tax_data in billing record, try to get from appointmenttransaction tax_percentage
                    if (empty($taxDataFromRecord) && isset($info['appointmenttransaction']['tax_percentage']) && $info['appointmenttransaction']['tax_percentage'] !== null) {
                        $taxDataFromRecord = json_decode($info['appointmenttransaction']['tax_percentage'], true);
                    }
                    
                    // Calculate tax using getBookingTaxamount
                    $taxDetails = getBookingTaxamount($amountAfterDiscount, $taxDataFromRecord);
                    $tax_amount = $taxDetails['total_tax_amount'] ?? 0;
                    $taxData = $taxDetails['tax_details'] ?? [];
                    
                    // If tax_data exists in billing record, use it for breakdown (prefer billing record data)
                    if ($billingRecord && isset($billingRecord['tax_data']) && $billingRecord['tax_data'] !== null) {
                        $taxDataFromRecord = json_decode($billingRecord['tax_data'], true);
                        if (is_array($taxDataFromRecord) && !empty($taxDataFromRecord)) {
                            $taxData = $taxDataFromRecord;
                            // Recalculate tax_amount from taxData if needed
                            if ($tax_amount == 0 && !empty($taxData)) {
                                $tax_amount = 0;
                                foreach ($taxData as $tax) {
                                    if (isset($tax['status']) && $tax['status'] == 1) {
                                        $taxValue = $tax['value'] ?? 0;
                                        $taxType = $tax['type'] ?? 'percentage';
                                        if ($taxType == 'fixed') {
                                            $tax_amount += $taxValue;
                                        } else {
                                            $tax_amount += ($amountAfterDiscount * $taxValue) / 100;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // STEP 4: Calculate Bed Charges
                    $bed_charges = 0;
                    if ($currentBedAllocations->isNotEmpty()) {
                        $bed_charges = $currentBedAllocations->sum(function($allocation) {
                            $allocationData = is_array($allocation) ? $allocation : $allocation->toArray();
                            return $allocationData['charge'] ?? 0;
                        });
                    } elseif ($billingRecord && isset($billingRecord['bed_charges'])) {
                        $bed_charges = $billingRecord['bed_charges'] ?? 0;
                    }
                    
                    // Total Payable Amount: (Service Amount - Discount) + Tax (WITHOUT bed charges)
                    $totalPayableAmount = $amountAfterDiscount + $tax_amount;
                    
                    // Final Total: Total Payable Amount + Bed Charges
                    $final_total = $totalPayableAmount + $bed_charges;
                @endphp

                @if(!empty($taxData) && $tax_amount > 0)
                @php
                $totalTax = $tax_amount;
                @endphp

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
                                @foreach($taxData as $taxPercentage)
                                @php
                                // Convert to array if it's an object
                                $taxItem = is_array($taxPercentage) ? $taxPercentage : (is_object($taxPercentage) ? (array)$taxPercentage : []);
                                
                                // Handle different tax data formats
                                $taxTitle = $taxItem['title'] ?? $taxItem['tax_name'] ?? $taxItem['name'] ?? 'Tax';
                                $taxValue = $taxItem['value'] ?? $taxItem['tax_value'] ?? $taxItem['percent'] ?? 0;
                                $taxType = $taxItem['type'] ?? 'percentage';
                                
                                // Get tax amount if already calculated, otherwise calculate it
                                $tax_item_amount = isset($taxItem['tax_amount']) ? $taxItem['tax_amount'] : 0;
                                
                                // If tax_amount is not set, calculate it
                                if ($tax_item_amount == 0) {
                                    if ($taxType == 'fixed') {
                                        $tax_item_amount = $taxValue;
                                    } else {
                                        $tax_item_amount = ($amountAfterDiscount * $taxValue) / 100;
                                    }
                                }
                                
                                // Check if tax should be displayed (status check)
                                $shouldDisplay = true;
                                if (isset($taxItem['status'])) {
                                    $shouldDisplay = ($taxItem['status'] == 1);
                                }
                                @endphp
                                @if($shouldDisplay)
                                <tbody>
                                    <tr>
                                        <td colspan="3">{{ $index }}</td>

                                        <td colspan="3">
                                            @if($taxType == 'fixed')
                                            {{ $taxTitle }} ({{ Currency::format($taxValue) ?? '--' }})
                                            @else
                                            {{ $taxTitle }} ({{ $taxValue ?? '--' }}%)
                                            @endif
                                        </td>
                                       
                                        <td colspan="2" style="text-align: right;">
                                            {{ Currency::format($tax_item_amount) ?? '--' }}
                                        </td>
                                    </tr>
                                    @php $index++ @endphp
                                </tbody>
                                @endif
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
                                <thead class="thead-light">
                                    <tr>
                                        <th colspan="3"> </th>
                                       
                                        <th colspan="3"> </th>
                                        
                                        <th colspan="2">
                                            <div class="text-right">
                                                {{ __('messages.charges') }}
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                
                                
                                @php
                                    // Calculate remaining amount
                                    $remaining_payable_amount = $final_total - ($info['advance_paid_amount'] ?? 0);
                                @endphp

                                <tfoot>
                                    <!-- STEP 1: Service Amount -->
                                    <tr>
                                        <th colspan="6" class="text-right">
                                            {{ __('appointment.service_amount') }}
                                            @if ($hasInclusiveTax)
                                                <span class="text-danger small">({{ __('messages.lbl_with_inclusive_tax') }})</span>
                                            @endif
                                        </th>
                                        <th colspan="2" style="text-align: right;"><span>{{ Currency::format($service_total) }}</span></th>
                                    </tr>

                                    <!-- STEP 2: Discount Amount (if any) -->
                                    @if ($encounter_discount_amount > 0)
                                    <tr>
                                        <th colspan="6" class="text-right">{{ __('appointment.discount_amount') }}</th>
                                        <th colspan="2" style="text-align: right;">{{ Currency::format($encounter_discount_amount) ?? '--' }}</th>
                                    </tr>
                                    @endif

                                    <!-- STEP 3: Tax -->
                                    @if ($tax_amount > 0)
                                    <tr>
                                        <th colspan="6" class="text-right">{{ __('appointment.tax') }}</th>
                                        <th colspan="2" style="text-align: right;"><span>{{ Currency::format($tax_amount) }}</span></th>
                                    </tr>
                                    @endif

                                    <!-- STEP 4: Total Payable Amount (Service + Tax - Discount, WITHOUT bed charges) -->
                                    <tr>
                                        <th colspan="6" class="text-right">{{ __('appointment.total_payable_amount') }}</th>
                                        <th colspan="2" style="text-align: right;"><span>{{ Currency::format($totalPayableAmount) ?? '--' }}</span></th>
                                    </tr>

                                    <!-- STEP 5: Bed Total (if bed charges exist) -->
                                    @if ($bed_charges > 0)
                                    <tr>
                                        <th colspan="6" class="text-right">{{ __('messages.bed_price') }}</th>
                                        <th colspan="2" style="text-align: right;"><span>{{ Currency::format($bed_charges) }}</span></th>
                                    </tr>
                                    @endif

                                    <!-- STEP 6: Final Total Amount (Total Payable Amount + Bed Charges) -->
                                    <tr>
                                        <th colspan="6" class="text-right"><strong>{{ __('messages.grand_total') }}</strong></th>
                                        <th colspan="2" style="text-align: right;"><strong>{{ Currency::format($final_total) ?? '--' }}</strong></th>
                                    </tr>

                                    @if(isset($info['appointmenttransaction']['advance_payment_status']) && $info['appointmenttransaction']['advance_payment_status'] == 1)
                                        <tr>  
                                            <th colspan="6" class="text-right">{{ __('service.advance_payment_amount') }}({{ $info['advance_payment_amount'] ?? 0 }}%)</th>
                                            <th colspan="2" style="text-align: right;">{{ Currency::format($info['advance_paid_amount'] ?? 0) ?? '--' }}</th>
                                        </tr>
                                    @endif

                                    @if(isset($info['appointmenttransaction']['payment_status']) && $info['appointmenttransaction']['payment_status'] == 1)
                                        <tr>  
                                            <th colspan="6" class="text-right">{{ __('service.remaining_amount') }} <span class="badge badge-success">{{ __('messages.paid') }}</span></th>
                                            <th colspan="2" style="text-align: right;">{{ Currency::format($remaining_payable_amount) }}</th>
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
</body>

</html>