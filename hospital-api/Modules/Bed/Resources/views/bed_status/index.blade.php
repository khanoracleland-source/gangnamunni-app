@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __($module_title) }}</h5>
                @can('add_bed_allocation')
                    {{-- <a href="{{ route('backend.bed-allocation.create') }}" class="btn btn-danger">
                        <i class="ph ph-bed me-1"></i>{{ __('messages.bed_assign') }}
                    </a> --}}
                @endcan
            </div>
        </div>

        <div class="card-body">

            <div class="bed-status-container mb-5 pb-2">

                @php
                    // Debug: Log stats being used in view
                    \Log::info('View Stats Debug:', [
                        'stats_array' => $stats ?? [],
                        'occupied_value' => $stats['occupied'] ?? 'NOT_SET',
                        'stats_keys' => array_keys($stats ?? []),
                        'bed_types_count' => $bedTypes->count() ?? 0,
                    ]);
                    
                    // Calculate occupied count from actual beds - this is the source of truth
                    $calculatedOccupied = $bedTypes->sum(function($bt) {
                        return $bt->beds->filter(function($bed) {
                            // Use getAttribute to get the manually set current_status, not the accessor
                            $status = $bed->getAttribute('current_status');
                            if (!$status) {
                                // Fallback to accessor if attribute not set
                                $status = $bed->current_status;
                            }
                            return $status === 'occupied';
                        })->count();
                    });
                    
                    // Calculate other counts from actual beds as well
                    $calculatedAvailable = $bedTypes->sum(function($bt) {
                        return $bt->beds->filter(function($bed) {
                            $status = $bed->getAttribute('current_status') ?: $bed->current_status;
                            return $status === 'available';
                        })->count();
                    });
                    
                    $calculatedMaintenance = $bedTypes->sum(function($bt) {
                        return $bt->beds->filter(function($bed) {
                            $status = $bed->getAttribute('current_status') ?: $bed->current_status;
                            return $status === 'maintenance';
                        })->count();
                    });
                    
                    $calculatedTotal = $bedTypes->sum(function($bt) {
                        return $bt->beds->count();
                    });
                    
                    // Use calculated values as primary source, fallback to stats if needed
                    $displayOccupied = $calculatedOccupied > 0 ? $calculatedOccupied : ($stats['occupied'] ?? 0);
                    $displayAvailable = $calculatedAvailable > 0 ? $calculatedAvailable : ($stats['available'] ?? 0);
                    $displayMaintenance = $calculatedMaintenance > 0 ? $calculatedMaintenance : ($stats['maintenance'] ?? 0);
                    $displayTotal = $calculatedTotal > 0 ? $calculatedTotal : ($stats['total'] ?? 0);
                    
                    \Log::info('View Calculated Counts from Beds:', [
                        'calculated_occupied' => $calculatedOccupied,
                        'calculated_available' => $calculatedAvailable,
                        'calculated_maintenance' => $calculatedMaintenance,
                        'calculated_total' => $calculatedTotal,
                        'stats_occupied' => $stats['occupied'] ?? 0,
                        'stats_available' => $stats['available'] ?? 0,
                        'stats_maintenance' => $stats['maintenance'] ?? 0,
                        'stats_total' => $stats['total'] ?? 0,
                        'display_occupied' => $displayOccupied,
                    ]);
                @endphp
                <ul class="nav nav-tabs bed-status-content d-flex align-items-center gap-3 flex-wrap mb-4" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="bed-status-total btn bg-transperent text-body border active" id="total-tab" data-bs-toggle="tab" data-bs-target="#total-tab-pane" type="button" role="tab" aria-controls="total-tab-pane" aria-selected="true">{{ __('messages.total_beds') }}: {{ $displayTotal }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="bed-status-total btn btn-success-subtle" id="available-tab" data-bs-toggle="tab" data-bs-target="#available-tab-pane" type="button" role="tab" aria-controls="available-tab-pane" aria-selected="false">{{ __('messages.available') }}: {{ $displayAvailable }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="bed-status-total btn btn-danger-subtle" id="occupied-tab" data-bs-toggle="tab" data-bs-target="#occupied-tab-pane" type="button" role="tab" aria-controls="occupied-tab-pane" aria-selected="false">{{ __('messages.occupied') }}: {{ $displayOccupied }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="bed-status-total btn btn-warning-subtle" id="unavailable-tab" data-bs-toggle="tab" data-bs-target="#unavailable-tab-pane" type="button" role="tab" aria-controls="unavailable-tab-pane" aria-selected="false">{{ __('messages.unavailable') }}: {{ $displayMaintenance }}</button>
                    </li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    @php
                        // Helper function to render beds for a specific status filter
                        function renderBedsByStatus($bedTypes, $statusFilter = 'all') {
                            $html = '';
                            foreach ($bedTypes as $bedType) {
                                // Filter beds based on status
                                $filteredBeds = $bedType->beds->filter(function($bed) use ($statusFilter) {
                                    if ($statusFilter === 'all') {
                                        return true;
                                    }
                                    // Get current_status - use the attribute set by controller
                                    // Controller sets current_status based on active allocations (status = 1)
                                    $currentStatus = $bed->getAttribute('current_status');
                                    if (!$currentStatus) {
                                        // Fallback: determine status if not set
                                        if ($bed->is_under_maintenance || !$bed->status) {
                                            $currentStatus = 'maintenance';
                                        } else {
                                            $currentStatus = 'available';
                                        }
                                    }
                                    return $currentStatus === $statusFilter;
                                });
                                
                                // Only show bed type section if it has beds matching the filter
                                if ($filteredBeds->count() > 0) {
                                    $html .= '<div class="bed-content">';
                                    $bedTypeName = $bedType->type ?? '';
                                    $html .= '<h4 class="mb-4">' . (is_array($bedTypeName) ? e(json_encode($bedTypeName)) : e($bedTypeName)) . '</h4>';
                                    $html .= '<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 gy-4">';
                                    
                                    foreach ($filteredBeds as $bed) {
                                        // Get current_status - use the attribute set by controller
                                        // Controller sets current_status based on active allocations (status = 1)
                                        $currentStatus = $bed->getAttribute('current_status');
                                        if (!$currentStatus) {
                                            // Fallback: determine status if not set
                                            if ($bed->is_under_maintenance || !$bed->status) {
                                                $currentStatus = 'maintenance';
                                            } else {
                                                $currentStatus = 'available';
                                            }
                                        }
                                        
                                        $statusClass = $currentStatus === 'maintenance' ? 'maintenance' : 
                                                      ($currentStatus === 'occupied' ? 'occupied' : 
                                                      ($currentStatus === 'available' ? 'available' : 'unavailable'));
                                        
                                        // Set icon color class based on status to match tab colors
                                        $iconColorClass = '';
                                        if ($currentStatus === 'maintenance') {
                                            $iconColorClass = 'text-warning';
                                        } elseif ($currentStatus === 'occupied') {
                                            $iconColorClass = 'text-danger';
                                        } elseif ($currentStatus === 'available') {
                                            $iconColorClass = 'text-success';
                                        }
                                        
                                        $html .= '<div class="col">';
                                        $html .= '<div class="bed-status-card border rounded ' . $statusClass . '" onclick="showBedDetails(' . $bed->id . ')" style="cursor: pointer;">';
                                        
                                        // Show maintenance icon if bed is under maintenance or inactive
                                        if ($currentStatus === 'maintenance') {
                                            $html .= '<i class="ph ph-gear-six bed-maintenance"></i>';
                                        }
                                        
                                        $html .= '<i class="bed-icon ph ph-bed ' . $iconColorClass . '"></i>';
                                        $bedName = $bed->bed ?? '';
                                        $html .= '<h5 class="bed-identifier mb-0 mt-3">' . (is_array($bedName) ? e(json_encode($bedName)) : e($bedName)) . '</h5>';
                                        $html .= '</div>';
                                        $html .= '</div>';
                                    }
                                    
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }
                            }
                            return $html;
                        }
                    @endphp
                    
                    <!-- Total Beds Tab -->
                    <div class="tab-pane list-of-bed-content fade show active" id="total-tab-pane" role="tabpanel" aria-labelledby="total-tab" tabindex="0">
                        {!! renderBedsByStatus($bedTypes, 'all') !!}
                        @if($bedTypes->isEmpty() || $bedTypes->sum(function($bt) { return $bt->beds->count(); }) == 0)
                            <div class="text-center py-5">
                                <p class="text-muted">{{ __('messages.no_beds_found') }}</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Available Beds Tab -->
                    <div class="tab-pane list-of-bed-content" id="available-tab-pane" role="tabpanel" aria-labelledby="available-tab" tabindex="0">
                        {!! renderBedsByStatus($bedTypes, 'available') !!}
                        @php
                            $availableCount = $bedTypes->sum(function($bt) { 
                                return $bt->beds->where('current_status', 'available')->count(); 
                            });
                        @endphp
                        @if($availableCount == 0)
                            <div class="text-center py-5">
                                <p class="text-muted">{{ __('messages.no_available_beds_found') }}</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Occupied Beds Tab -->
                    <div class="tab-pane list-of-bed-content" id="occupied-tab-pane" role="tabpanel" aria-labelledby="occupied-tab" tabindex="0">
                        {!! renderBedsByStatus($bedTypes, 'occupied') !!}
                        @php
                            $occupiedCount = $bedTypes->sum(function($bt) { 
                                return $bt->beds->where('current_status', 'occupied')->count(); 
                            });
                        @endphp
                        @if($occupiedCount == 0)
                            <div class="text-center py-5">
                                <p class="text-muted">{{ __('messages.no_occupied_beds_found') }}</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Unavailable Beds Tab -->
                    <div class="tab-pane list-of-bed-content" id="unavailable-tab-pane" role="tabpanel" aria-labelledby="unavailable-tab" tabindex="0">
                        {!! renderBedsByStatus($bedTypes, 'maintenance') !!}
                        @php
                            $maintenanceCount = $bedTypes->sum(function($bt) { 
                                return $bt->beds->filter(function($bed) {
                                    // Check if bed is under maintenance or inactive (status = 0)
                                    return $bed->is_under_maintenance || !$bed->status;
                                })->count(); 
                            });
                        @endphp
                        @if($maintenanceCount == 0)
                            <div class="text-center py-5">
                                <p class="text-muted">{{ __('messages.no_unavailable_beds_found') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>


        </div>
    </div>

    <!-- Add Bed Details Modal -->
    <div class="modal fade" id="bedDetailsModal" tabindex="-1" aria-labelledby="bedDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h4 class="modal-title" id="bedDetailsModalLabel">{{ __('messages.bed_details') }}</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="bedDetailsContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after-scripts')
    <script>
        $(document).ready(function() {
            let selectedBedId = null;

            // JS-based filtering for bed status
            $('.status-summary-card').on('click', function() {
                const status = $(this).hasClass('available') ? 'available' : 
                              $(this).hasClass('occupied') ? 'occupied' : 
                              $(this).hasClass('unavailable') ? 'unavailable' : 'all';
                
                // Hide all first, then show only the correct ones
                $('.bed-item').hide();
                
                if (status === 'all') {
                    $('.bed-item').show();
                } else {
                    $(`.bed-item[data-status='${status}']`).show();
                }
                
                // Update active state of cards
                $('.status-summary-card').removeClass('border-primary border-2');
                if (status !== 'all') {
                    $(this).addClass('border-primary border-2');
                }
            });

            // Bed card selection
            $('.bed-card').on('click', function(e) {
                // Remove previous selection
                $('.bed-card').removeClass('selected');
                
                // Add selection to clicked bed
                $(this).addClass('selected');
                selectedBedId = $(this).closest('.bed-item').data('bed-id');
            });

            // Initially show all beds
            $('.bed-item').show();
        });

        function showBedDetails(bedId) {
            // Show loading state in modal
                    $('#bedDetailsContent').html(`
                <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('messages.loading') }}</span>
                    </div>
                </div>
            `);

            // Show modal
            $('#bedDetailsModal').modal('show');

            // Fetch bed details
            $.ajax({
                url: "{{ route('backend.bed-status.bed.details', '') }}/" + bedId,
                type: 'GET',
                success: function(response) {
                    console.log('Bed details response:', response);
                    if (response.status) {
                        const bed = response.data;
                        console.log('Bed data:', bed);
                        console.log('Current allocation:', bed.currentAllocation || bed.current_allocation);
                        let html = `
                            <div class="bed-details-section">
                                <h6 class="mb-3">{{ __('messages.bed_details') }}</h6>
                                <div class="detail-row">
                                    <span class="detail-label text-heading">{{ __('messages.bed_number') }}</span>
                                    <span class="detail-value">${bed.bed}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label text-heading">{{ __('messages.type') }}</span>
                                    <span class="detail-value">${bed.bed_type.type}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label text-heading">{{ __('messages.charges') }}</span>
                                    <span class="detail-value">${bed.formatted_charges || 'N/A'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label text-heading">{{ __('messages.status') }}</span>
                                    <span class="detail-value">`;

                        // Add status badge
                        if (bed.current_status === 'maintenance' || bed.is_under_maintenance) {
                            html += `<span class="badge bg-warning">{{ __('messages.unavailable') }}</span>`;
                        } else if (bed.currentAllocation || bed.current_allocation) {
                            html += `<span class="badge bg-danger">{{ __('messages.occupied') }}</span>`;
                        } else {
                            html += `<span class="badge bg-success">{{ __('messages.available') }}</span>`;
                        }

                        html += `</span></div></div>`;

                        // Add patient information if bed is occupied
                        // Handle both camelCase and snake_case
                        const currentAllocation = bed.currentAllocation || bed.current_allocation;
                        if (currentAllocation && !bed.is_under_maintenance) {
                            const patient = currentAllocation.patient;
                            if (patient) {
                                // Get patient name - handle both name and first_name/last_name
                                const patientName = patient.name || patient.full_name || 
                                    (patient.first_name && patient.last_name ? 
                                        `${patient.first_name} ${patient.last_name}` : 
                                        (patient.first_name || patient.last_name || 'N/A'));
                                
                                html += `
                                    <div class="bed-details-section">
                                        <h6 class="mb-3">{{ __('messages.current_patient') }}</h6>
                                        <div class="patient-timeline">
                                            <div class="timeline-item">
                                                <div class="detail-row">
                                                    <span class="detail-label">{{ __('messages.patient_name') }}</span>
                                                    <span class="detail-value">${patientName}</span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">{{ __('messages.admission_date') }}</span>
                                                    <span class="detail-value">${currentAllocation.assign_date ? (typeof moment !== 'undefined' ? moment(currentAllocation.assign_date).format('DD MMM YYYY') : new Date(currentAllocation.assign_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })) : 'N/A'}</span>
                                                </div>
                                                ${currentAllocation.discharge_date ? `
                                                    <div class="detail-row">
                                                        <span class="detail-label">{{ __('messages.expected_discharge') }}</span>
                                                        <span class="detail-value">${typeof moment !== 'undefined' ? moment(currentAllocation.discharge_date).format('DD MMM YYYY') : new Date(currentAllocation.discharge_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                                                    </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }
                        }

                        // Add unavailable information if bed is under maintenance
                        if (bed.is_under_maintenance) {
                            html += `
                                <div class="bed-details-section">
                                    <h6 class="mb-3">{{ __('messages.unavailable_information') }}</h6>
                                    <div class="alert alert-warning mb-0 d-flex align-items-center gap-2">
                                        <i class="ph ph-x-circle"></i>{{ __('messages.bed_unavailable_message') }}
                                    </div>
                                </div>
                            `;
                        }

                        $('#bedDetailsContent').html(html);
                    } else {
                        $('#bedDetailsContent').html(`
                            <div class="bed-details-section">
                                <div class="alert alert-danger mb-0">
                                    {{ __('messages.error_loading_bed_details') }}
                                </div>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#bedDetailsContent').html(`
                        <div class="bed-details-section">
                            <div class="alert alert-danger mb-0">
                                {{ __('messages.error_loading_bed_details') }}
                            </div>
                        </div>
                    `);
                }
            });
        }

        function toggleBedSettings(bedId) {
            // Handle bed settings (e.g., maintenance toggle, etc.)
            console.log('Toggle settings for bed:', bedId);
            // You can implement a settings modal or dropdown here
        }
    </script>
@endpush
