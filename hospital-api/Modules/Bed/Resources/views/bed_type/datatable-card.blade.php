<div class="iq-blog blog-standard position-relative">
    <div class="blog-image position-relative">
        <!-- Optional: Add a bed icon or placeholder image -->
        <span
            class="d-inline-flex align-items-center gap-1 px-2 bg-primary border border-2 border-white rounded-5 text-white font-size-14 fw-500 position-absolute top-0 end-0 mt-3 me-3">
            <i class="fa-solid fa-bed"></i>
        </span>
    </div>
    <div class="iq-post-details position-relative">
        <div class="blog-meta-data bg-light rounded">
            <div class="d-flex align-items-center justify-content-center gap-3">
                @php
                    $sitesetup = App\Models\Setting::where('type', 'site-setup')->where('key', 'site-setup')->first();
                    $datetime = $sitesetup ? json_decode($sitesetup->value) : null;
                @endphp
                <div class="blog-publish-date">
                    <span>
                        {{ $datetime ? date($datetime->date_format, strtotime($data->created_at)) : $data->created_at->format('Y-m-d') }}
                    </span>
                </div>
            </div>
        </div>
        <div class="blog-title text-start mb-2">
            <h5 class="text-capitalize line-count-2">{{ $data->type }}</h5>
        </div>
        <div class="mb-2">
            <span class="text-muted">{{ Str::limit(strip_tags($data->description), 100) }}</span>
        </div>
        <div class="iq-blog-meta-bottom d-flex align-items-center justify-content-between mt-5">
            <div></div>
            <div class="iq-btn-container">
                <a class="btn btn-link p-0 text-capitalize"
                    href="{{ route('backend.bed-type.create', ['id' => $data->id]) }}">
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
            </div>
        </div>
    </div>
</div>
