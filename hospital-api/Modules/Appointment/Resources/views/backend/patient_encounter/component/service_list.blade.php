<div class="card-body">
    <div class="table-responsive rounded">
        <table class="table table-lg m-0" id="service_list_table">
            <thead>

                <tr class="text-white">
                    <th>{{ __('appointment.sr_no') }}</th>
                    <th>{{ __('appointment.lbl_services') }}</th>
                    <th>{{ __('service.discount') }}</th>
                    <th>{{ __('product.quantity') }}</th>
                    <th>{{ __('appointment.price') }}</th>
                    <th>{{ __('service.inclusive_tax') }}</th>
                    <th>{{ __('appointment.total') }}</th>
                    @if ($status == 1)
                        <th>{{ __('appointment.lbl_action') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>

                @foreach ($data['billingItem'] as $index => $iteam)
                @php
                    // Recalculate inclusive tax if service has it enabled but amount is 0
                    $displayInclusiveTax = $iteam['inclusive_tax_amount'] ?? 0;
                    
                    // If inclusive tax is 0, check if service has inclusive tax enabled
                    if ($displayInclusiveTax == 0 && !empty($iteam['item_id'])) {
                        $service = \Modules\Clinic\Models\ClinicsService::where('id', $iteam['item_id'])->first();
                        
                        if ($service && $service->is_inclusive_tax == 1 && !empty($service->inclusive_tax)) {
                            $inclusiveTaxJson = json_decode($service->inclusive_tax, true);
                            
                            if (is_array($inclusiveTaxJson)) {
                                $unitPrice = $iteam['service_amount'] ?? 0;
                                $calculatedInclusiveTax = 0;
                                
                                // Filter and calculate only taxes with status == 1
                                foreach ($inclusiveTaxJson as $tax) {
                                    if (isset($tax['status']) && $tax['status'] == 1) {
                                        if (isset($tax['type']) && $tax['type'] == 'fixed') {
                                            $calculatedInclusiveTax += $tax['value'] ?? 0;
                                        } elseif (isset($tax['type']) && $tax['type'] == 'percent') {
                                            $calculatedInclusiveTax += ($unitPrice * ($tax['value'] ?? 0)) / 100;
                                        }
                                    }
                                }
                                
                                $displayInclusiveTax = $calculatedInclusiveTax;
                            }
                        }
                    }
                    
                    // Get base service price from service charges (not from calculated service_amount)
                    $baseServicePrice = $iteam['service_amount'] ?? 0;
                    $itemId = is_array($iteam) ? ($iteam['item_id'] ?? null) : ($iteam->item_id ?? null);
                    
                    // If we have item_id, get the base price from the service itself
                    if (!empty($itemId)) {
                        $service = \Modules\Clinic\Models\ClinicsService::where('id', $itemId)->first();
                        if ($service) {
                            // Get base price from service charges (this is the actual base price)
                            $baseServicePrice = $service->charges ?? $baseServicePrice;
                        }
                    }
                    
                    // Calculate: Base Service Price → Discount → Add Inclusive Tax → Total
                    $unitPrice = $baseServicePrice; // Use base service price
                    $quantity = $iteam['quantity'] ?? 1;
                    
                    // Service Price Total (base price * quantity)
                    $servicePriceTotal = $unitPrice * $quantity;
                    
                    // Get discount information
                    $discountValue = is_array($iteam) ? ($iteam['discount_value'] ?? null) : ($iteam->discount_value ?? null);
                    $discountType = is_array($iteam) ? ($iteam['discount_type'] ?? null) : ($iteam->discount_type ?? null);
                    $discountStatus = is_array($iteam) ? ($iteam['discount_status'] ?? null) : ($iteam->discount_status ?? null);
                    
                    // If billing item doesn't have discount, check the service for discount
                    if (empty($discountValue) || $discountValue == 0) {
                        if (!empty($itemId) && isset($service)) {
                            if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                $discountValue = $service->discount_value;
                                $discountType = $service->discount_type;
                                $discountStatus = 1;
                            }
                        } elseif (!empty($itemId)) {
                            // If service wasn't loaded above, load it now
                            $service = \Modules\Clinic\Models\ClinicsService::where('id', $itemId)->first();
                            if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                $discountValue = $service->discount_value;
                                $discountType = $service->discount_type;
                                $discountStatus = 1;
                            }
                        }
                    }
                    
                    // Calculate service discount amount (applied to base service price only)
                    $serviceDiscountAmount = 0;
                    if (!empty($discountValue) && $discountValue > 0) {
                        // If discount_status doesn't exist, default to 1 if discount exists
                        if ($discountStatus === null) {
                            $discountStatus = 1;
                        }
                        
                        // Apply discount only if status is 1 (active)
                        if ($discountStatus == 1) {
                            if ($discountType == 'percentage') {
                                // Percentage discount on service price total
                                $serviceDiscountAmount = ($servicePriceTotal * $discountValue) / 100;
                            } else {
                                // Fixed discount per quantity
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
                        if (!isset($service)) {
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
                            $unitInclusiveTax = $displayInclusiveTax;
                        }
                    } else {
                        // Use existing inclusive tax amount if no item_id
                        $unitInclusiveTax = $displayInclusiveTax;
                    }
                    
                    // Calculate total inclusive tax
                    $inclusiveTaxTotal = $unitInclusiveTax * $quantity;
                    
                    // Final total: (Base Service Price - Discount) + Inclusive Tax (calculated on discounted amount)
                    $finalTotal = $servicePriceAfterDiscount + $inclusiveTaxTotal;
                @endphp
                <tr data-service-id="{{ $iteam['id'] }}">

                        <td>
                            <h6 class="text-primary">
                                {{ $index + 1 }}
                            </h6>

                        </td>
                        <td>
                            <h6 class="text-primary">
                                {{ $iteam['item_name'] }}
                            </h6>

                        </td>
                        <td>
                            <p class="m-0">
                                @php
                                    // Handle both array and object access
                                    $discountValue = is_array($iteam) ? ($iteam['discount_value'] ?? null) : ($iteam->discount_value ?? null);
                                    $discountType = is_array($iteam) ? ($iteam['discount_type'] ?? null) : ($iteam->discount_type ?? null);
                                    $discountStatus = is_array($iteam) ? ($iteam['discount_status'] ?? null) : ($iteam->discount_status ?? null);
                                    $itemId = is_array($iteam) ? ($iteam['item_id'] ?? null) : ($iteam->item_id ?? null);
                                    
                                    // If billing item doesn't have discount, check the service for discount
                                    if (empty($discountValue) || $discountValue == 0) {
                                        if (!empty($itemId)) {
                                            $service = \Modules\Clinic\Models\ClinicsService::where('id', $itemId)->first();
                                            if ($service && !empty($service->discount_value) && $service->discount_value > 0) {
                                                $discountValue = $service->discount_value;
                                                $discountType = $service->discount_type;
                                                // Service discount is active by default (status = 1)
                                                $discountStatus = 1;
                                            }
                                        }
                                    }
                                    
                                    // Check if discount exists and is active
                                    $hasDiscount = !empty($discountValue) && $discountValue > 0;
                                    
                                    // If discount_status doesn't exist in data, default to 1 if discount exists (active by default)
                                    if ($discountStatus === null) {
                                        // discount_status field doesn't exist - default to 1 if discount exists
                                        $discountStatus = $hasDiscount ? 1 : 0;
                                    }
                                    
                                    // Show discount only if it exists AND status is 1
                                    $shouldShowDiscount = $hasDiscount && $discountStatus == 1;
                                @endphp
                                @if (!$shouldShowDiscount)
                                    -
                                @else
                                    @if ($discountType == 'fixed')
                                        <span>{{ Currency::format($discountValue) }}</span>
                                    @else
                                        <span>({{ $discountValue }}%) </span>
                                    @endif
                                @endif
                            </p>
                        </td>
                        <td>
                            {{ $iteam['quantity'] }}
                        </td>
                        <td>
                            {{ Currency::format($unitPrice) }}
                        </td>
                        <td>
                            {{ Currency::format($unitInclusiveTax) }}
                        </td>
                        <td>
                            {{ Currency::format($finalTotal) }}
                        </td>
                        @if ($status == 1)
                            <td class="action">
                                <div class="d-flex align-items-center gap-3">

                                    @if ($index !== 0)
                                        <button type="button" class="btn text-danger p-0 fs-5"
                                            onclick="destroyServiceData({{ $iteam['id'] }}, 'Are you sure you want to delete it?')"
                                            data-bs-toggle="tooltip">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach


                @if (count($data['billingItem']) <= 0)
                    <tr>
                        <td colspan="7">
                            <div class="my-1 text-danger text-center">{{ __('appointment.no_service_found') }}
                            </div>
                        </td>
                    </tr>
                @endif
            </tbody>

        </table>
        <div id="service-error-message" class="alert alert-danger mt-2 d-none"></div>

    </div>
</div>

@push('after-scripts')
    <script>
        // Add this function to check service count
        function checkServiceCount() {
            // Get all billing items that have actual services (those with an ID)
            const serviceRows = document.querySelectorAll('#service_list_table tbody tr[data-service-id]');
            return serviceRows.length;
        }

        function showError(message) {
            const errorDiv = document.getElementById('service-error-message');
            errorDiv.textContent = message;
            errorDiv.classList.remove('d-none');
            setTimeout(() => {
                errorDiv.classList.add('d-none');
            }, 3000);


        }

        function destroyServiceData(id) {
            const serviceCount = checkServiceCount();
            console.log(serviceCount)
            if (serviceCount <= 1) {
                showError('At least one service is required. Cannot delete the All service.');
                return;
            }


            var baseUrl = '{{ url('/') }}';

            $.ajax({
                url: baseUrl + '/app/billing-record/delete-billing-item/' + id,
                method: 'GET',

                success: function(response) {

                    if (response) {


                        document.getElementById('Service_list').innerHTML = ''

                        document.getElementById('Service_list').innerHTML = response.html;

                        $('#service_id').val(null).trigger('change');
                        $('#charges').val('');
                        $('#quantity').val('');
                        $('#total').val('');
                        $('#discount_value').val('');
                        $('#discount_type').val('');
                        $('#service_amount').text(currencyFormat(response.service_details.service_total));
                        $('#tax_amount').text(currencyFormat(response.service_details.total_tax));
                        $('#total_payable_amount').text(currencyFormat(response.service_details.total_amount));
                        $('#total_service_amount').val(response.service_details.service_total);
                        $('#total_tax_amount').val(response.service_details.total_tax);
                        $('#total_amount').val(response.service_details.total_amount);
                        $('#discount_amount').text(currencyFormat(response.service_details
                            .final_discount_amount));
                        const isChecked = document.getElementById('category-discount').checked;
                        console.log('isChecked', isChecked);
                        console.log('final_discount_amount', response.service_details.final_discount_amount);
                        if (isChecked && response.service_details.final_discount_amount <= 0) {
                            updateDiscount();
                        }
                        // After updating the service list, recheck the count
                        const updatedCount = checkServiceCount();


                        deleteButtons.forEach(btn => {
                            if (updatedCount <= 1) {
                                btn.setAttribute('disabled', 'disabled');
                                btn.setAttribute('title',
                                    '{{ __('appointment.cannot_delete_last_service') }}');
                            } else {
                                btn.removeAttribute('disabled');
                                btn.removeAttribute('title');
                            }
                        });

                    } else {

                        console.error('Failed to fetch services.');
                    }
                },
                error: function(error) {
                    console.error('Error fetching services:', error);
                }
            });

        }
    </script>
@endpush
