<div class="offcanvas" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
    <div class="offcanvas-header border-bottom">
        @if (isset($title))
            {{ $title }}
        @endif
        <button type="button" data-bs-dismiss="offcanvas" aria-label="Close" class="btn-close-offcanvas"><i
                class="ph ph-x-circle"></i></button>
    </div>
    <div class="offcanvas-body">
        {{ $slot }}
    </div>
    <div class="offcanvas-body">
        @if (isset($footer))
            {{ $footer }}
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var offcanvasElem = document.getElementById('offcanvasExample');
        if (document.documentElement.dir === 'rtl') {
            offcanvasElem.classList.add('offcanvas-start');
        } else {
            offcanvasElem.classList.add('offcanvas-end');
        }

        var resetButton = document.getElementById('reset-filter');
        if (resetButton) {
            resetButton.addEventListener('click', function(event) {
                var offcanvas = bootstrap.Offcanvas.getInstance(offcanvasElem);
                offcanvas.hide();
            });
        }
        offcanvasElem.addEventListener('shown.bs.offcanvas', function() {
            // Only re-initialize select2 inside the offcanvas
            $('#offcanvasExample .datatable-filter .select2').select2({
                dropdownParent: $('#offcanvasExample')
            });
        });
        offcanvasElem.addEventListener('hidden.bs.offcanvas', function() {
            // Re-initialize the main status filter Select2 with default parent
            $('#column_status').select2({
                dropdownParent: $('body')
            });
        });
    });
    const offcanvasElem = document.querySelector('#offcanvasExample')
    if (offcanvasElem) {
        offcanvasElem.addEventListener('shown.bs.offcanvas', function() {
            // Destroy any existing Select2 instances first
            $('#offcanvasExample .select2').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });

            // Re-initialize Select2 for dropdowns INSIDE the offcanvas
            setTimeout(function() {
                $('#offcanvasExample .select2').each(function() {
                    var $select = $(this);
                    var ajaxUrl = $select.data('ajax--url');

                    // Get the field label for placeholder
                    var fieldLabel = $select.closest('.form-group').find('label').text().trim();
                    if (!fieldLabel) {
                        fieldLabel = $select.attr('name') || 'Select an option';
                    }

                    var select2Options = {
                        dropdownParent: $('#offcanvasExample'),
                        minimumResultsForSearch: 0,
                        width: '100%',
                        allowClear: false,
                        placeholder: fieldLabel
                    };

                    // If it has AJAX URL, add AJAX configuration
                    if (ajaxUrl) {
                        select2Options.ajax = {
                            url: ajaxUrl,
                            dataType: 'json',
                            delay: 250,
                            cache: $select.data('ajax--cache') === true,
                            data: function(params) {
                                return {
                                    q: params.term || '', // search term
                                    page: params.page || 1
                                };
                            },
                            processResults: function(data, params) {
                                console.log('AJAX Response:', data); // Debug log
                                return {
                                    results: data.results || data || [],
                                    pagination: {
                                        more: data.pagination ? data.pagination.more :
                                            false
                                    }
                                };
                            },
                            error: function(xhr, status, error) {
                                console.error('Select2 AJAX Error:', error);
                            }
                        };
                    }

                    $select.select2(select2Options);
                });
            }, 100); // Small delay to ensure DOM is ready
        });
    }
</script>
