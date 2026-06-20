<div class="iq-blog blog-standard position-relative">
    <div class="blog-image position-relative">
        <!-- Bed Master icon with status indicator -->
        <span
            class="d-inline-flex align-items-center gap-1 px-2 {{ $data->status ? 'bg-success' : 'bg-secondary' }} border border-2 border-white rounded-5 text-white font-size-14 fw-500 position-absolute top-0 end-0 mt-3 me-3">
            <i class="fa-solid fa-bed"></i>
            {{ $data->status ? 'Active' : 'Inactive' }}
        </span>

        <!-- Capacity badge -->
        <span
            class="d-inline-flex align-items-center gap-1 px-2 bg-primary border border-2 border-white rounded-5 text-white font-size-12 fw-500 position-absolute top-0 start-0 mt-3 ms-3">
            <i class="fa-solid fa-users"></i>
            {{ $data->capacity }}
        </span>
    </div>

    <div class="iq-post-details position-relative">
        <div class="blog-meta-data bg-light rounded">
            <div class="d-flex align-items-center justify-content-between gap-3 px-3 py-2">
                @php
                    $sitesetup = App\Models\Setting::where('type', 'site-setup')->where('key', 'site-setup')->first();
                    $datetime = $sitesetup ? json_decode($sitesetup->value) : null;
                @endphp
                <div class="blog-publish-date">
                    <small class="text-muted">
                        <i class="fa-regular fa-calendar me-1"></i>
                        {{ $datetime ? date($datetime->date_format, strtotime($data->created_at)) : $data->created_at->format('Y-m-d') }}
                    </small>
                </div>

                <!-- Bed Type Badge -->
                @if ($data->bedType)
                    <div class="bed-type-badge">
                        <span class="badge bg-info text-white">{{ $data->bedType->type }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="blog-title text-start mb-2 mt-3">
            <h5 class="text-capitalize line-count-2 mb-1">{{ $data->bed }}</h5>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-success fs-6">{{ $data->formatted_charges }}</span>
                <small class="text-muted">per day</small>
            </div>
        </div>

        <div class="mb-3">
            @if ($data->description)
                <p class="text-muted mb-0">{{ Str::limit(strip_tags($data->description), 80) }}</p>
            @else
                <p class="text-muted mb-0 fst-italic">No description available</p>
            @endif
        </div>

        <!-- Additional Info -->
        <div class="bed-info-grid mb-3">
            <div class="row g-2">
                <div class="col-6">
                    <div class="info-item text-center p-2 bg-light rounded">
                        <div class="fw-bold text-primary">{{ $data->capacity }}</div>
                        <small class="text-muted">Capacity</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="info-item text-center p-2 bg-light rounded">
                        <div class="fw-bold {{ $data->status ? 'text-success' : 'text-danger' }}">
                            {{ $data->status_text }}
                        </div>
                        <small class="text-muted">Status</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="iq-blog-meta-bottom d-flex align-items-center justify-content-between mt-4">
            <div class="bed-actions d-flex gap-2">
                <!-- Quick Status Toggle -->
                @if (auth()->user()->can('bed master edit') && !auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('receptionist'))
                    <button type="button"
                        class="btn btn-sm {{ $data->status ? 'btn-outline-success' : 'btn-outline-secondary' }} status-toggle-btn"
                        data-id="{{ $data->id }}" data-status="{{ $data->status }}" title="Toggle Status">
                        <i class="ph {{ $data->status ? 'ph-toggle-right' : 'ph-toggle-left' }}"></i>
                    </button>
                @endif

                <!-- View Button -->
                @if (auth()->user()->can('bed master view'))
                    <a href="{{ route('backend.bed-master.show', $data->id) }}" class="btn btn-sm btn-outline-info"
                        title="View Details">
                        <i class="ph ph-eye"></i>
                    </a>
                @endif
            </div>

            <div class="iq-btn-container">
                @if(!auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('receptionist'))
                <a class="btn btn-link p-0 text-capitalize"
                    href="{{ route('backend.bed-master.create', ['id' => $data->id]) }}">
                    {{ __('landingpage.read_more') }}
                    <span class="btn-link-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"
                            fill="none">
                            <path d="M2.5 9.5L9.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M5.5 2.5H9.5V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>
                </a>
                @endif
            </div>
        </div>
    </div>
