<?php

namespace App\Http\Middleware;

use App\Trait\Menu;

class GenerateMenus
{
    use Menu;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle()
    {
        return \Menu::make('menu', function ($menu) {

            if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('demo_admin')) {
                $this->staticMenu($menu, [
                    'title' =>  __('menu.main'),
                    'order' => 0
                ]);
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-squares-four',
                    'title' => __('sidebar.dashboard'),
                    'route' => 'backend.home',
                    'active' => ['app', 'app/dashboard'],
                    'order' => 0,
                ]);
            } else if (auth()->user()->hasRole('doctor')) {
                $this->staticMenu($menu, ['title' => __('menu.main'), 'order' => 0]);

                $this->mainRoute($menu, [
                    'icon' => 'ph ph-squares-four',
                    'title' => __('sidebar.dashboard'),
                    'route' => 'backend.doctor-dashboard',
                    'active' => ['app', 'app/doctor-dashboard'],
                    'order' => 0,
                ]);
            } else if (auth()->user()->hasRole('receptionist')) {
                $this->staticMenu($menu, ['title' => __('menu.main'), 'order' => 0]);

                // main
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-squares-four',
                    'title' => __('sidebar.dashboard'),
                    'route' => 'backend.receptionist-dashboard',
                    'active' => ['app', 'app/receptionist-dashboard'],
                    'order' => 0,
                ]);
            } else if (auth()->user()->hasRole('vendor')) {

                $this->mainRoute($menu, [
                    'icon' => 'ph ph-squares-four',
                    'title' => __('sidebar.dashboard'),
                    'route' => 'backend.vendor-dashboard',
                    'active' => ['app', 'app/receptionist-dashboard'],
                    'order' => 0,
                ]);
            }
            else if (auth()->user()->hasRole('pharma')) {
                $this->staticMenu($menu, ['title' => __('menu.main'), 'order' => 0]);

                // main
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-squares-four',
                    'title' => __('sidebar.dashboard'),
                    'route' => 'backend.pharma-dashboard',
                    'active' => ['app', 'app/pharma-dashboard'],
                    'order' => 0,
                ]);
            }


            $this->mainRoute($menu, [
                'icon' => 'ph ph-sliders-horizontal',
                'title' => __('sidebar.appointment'),
                'route' => 'backend.appointments.index',
                'permission' => ['view_clinic_appointment_list'],
                'active' => ['app/appointments'],
                'order' => 0,
            ]);



        $BedMannagement = $this->parentMenu($menu, [
            'icon' => 'ph ph-bed',
            'title' =>  __('sidebar.bed_mannagement'),
            'nickname' => 'bed_mannagement',
            'order' => 0,
        ]);

    //bed allocation
            $this->childMain($BedMannagement, [
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="icon" style="width: 1.125rem; height: 1.125rem; flex-shrink: 0;" fill="currentColor" viewBox="0 0 256 256"><path d="M160,112h48a16,16,0,0,0,16-16V48a16,16,0,0,0-16-16H160a16,16,0,0,0-16,16V64H128a24,24,0,0,0-24,24v32H72v-8A16,16,0,0,0,56,96H24A16,16,0,0,0,8,112v32a16,16,0,0,0,16,16H56a16,16,0,0,0,16-16v-8h32v32a24,24,0,0,0,24,24h16v16a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V160a16,16,0,0,0-16-16H160a16,16,0,0,0-16,16v16H128a8,8,0,0,1-8-8V88a8,8,0,0,1,8-8h16V96A16,16,0,0,0,160,112ZM56,144H24V112H56v32Zm104,16h48v48H160Zm0-112h48V96H160Z"></path></svg>',
                'title' => __('sidebar.bed_allocation'),
                'route' => 'backend.bed-allocation.index',
                'active' => ['app/bed-allocation'],
                'permission' => ['view_allocations'],
                'order' => 0,
            ]);
            // All Bed - Visible to all users (doctors can view only, no create/edit)
            $this->childMain($BedMannagement, [
                'icon' => 'ph ph-bed',
                'title' => __('sidebar.all_bed'),
                'route' => 'backend.bed-master.index',
                'active' => ['app/bed-master'],
                'permission' => ['view_bed_master'],
                'order' => 0,
            ]);

            // Bed Type
            $this->childMain($BedMannagement, [
                'icon' => 'ph ph-squares-four',
                'title' => __('sidebar.bed_type'),
                'route' => 'backend.bed-type.index',
                'active' => ['app/bed-type'],
                'permission' => ['view_bed_type'],
                'order' => 1,
            ]);
//bed status
                $this->childMain($BedMannagement, [
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="icon" style="width: 1.125rem; height: 1.125rem; flex-shrink: 0;" fill="currentColor" viewBox="0 0 256 256"><path d="M168,128a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,128Zm-8,24H96a8,8,0,0,0,0,16h64a8,8,0,0,0,0-16ZM216,40V200a32,32,0,0,1-32,32H72a32,32,0,0,1-32-32V40a8,8,0,0,1,8-8H72V24a8,8,0,0,1,16,0v8h32V24a8,8,0,0,1,16,0v8h32V24a8,8,0,0,1,16,0v8h24A8,8,0,0,1,216,40Zm-16,8H184v8a8,8,0,0,1-16,0V48H136v8a8,8,0,0,1-16,0V48H88v8a8,8,0,0,1-16,0V48H56V200a16,16,0,0,0,16,16H184a16,16,0,0,0,16-16Z"></path></svg>',
                'title' => __('sidebar.bed_satus'),
                'route' => 'backend.bed-status.index',
                'active' => ['app/bed-status'],
                'order' => 1,
            ]);
            if(auth()->user()->hasRole('receptionist')){
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-list-bullets',
                    'title' => __('sidebar.encounter'),
                    'route' => 'backend.encounter.index',
                    'active' => 'app/encounter',
                    'permission' => ['view_encounter'],
                    'order' => 0,
                ]);
            }

            // Pharma menu for receptionist
            if(auth()->user()->hasRole('receptionist') && checkPlugin('pharma') == 'active'){
                $this->staticMenu($menu, ['title' => __('sidebar.pharma'), 'order' => 0]);
                
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-asclepius',
                    'title' => __('sidebar.pharma'),
                    'route' => \Illuminate\Support\Facades\Route::has('backend.pharma.index') ? 'backend.pharma.index' : null,
                    'active' => ['app/pharma'],
                    'order' => 0,
                ]);
            }

            if (auth()->user()->hasRole(['admin', 'demo_admin',])) {
                $encounter = $this->parentMenu($menu, [
                    'icon' => 'ph ph-clock-counter-clockwise',
                    'title' =>  __('sidebar.encounter'),
                    'route' => 'backend.encounter.index',
                    'permission' => ['view_encounter'],
                    'nickname' => 'encounter',
                    'order' => 0,
                ]);
                $this->childMain($encounter, [
                    'icon' => 'ph ph-list-bullets',
                    'title' => __('sidebar.encounter'),
                    'route' => 'backend.encounter.index',
                    'active' => 'app/encounter',
                    'permission' => ['view_encounter'],
                    'order' => 0,
                ]);

                $this->childMain($encounter, [
                    'icon' => 'ph ph-layout',
                    'title' => __('sidebar.encounter_template'),
                    'route' => 'backend.encounter-template.index',
                    'active' => 'app/encounter-template',
                    'permission' => ['view_encounter_template'],
                    'order' => 0,
                ]);

                $this->childMain($encounter, [
                    'icon' => 'ph ph-warning-diamond',
                    'title' => __('sidebar.problems'),
                    'route' => 'backend.problems.index',
                    'active' => 'app/problems',
                    'permission' => ['view_encounter'],
                    'order' => 0,
                ]);
                $this->childMain($encounter, [
                    'icon' => 'ph ph-eye',
                    'title' => __('appointment.observation'),
                    'route' => 'backend.observation.index',
                    'active' => 'app/observation',
                    'permission' => ['view_encounter'],
                    'order' => 0,
                ]);
            }

            if (!auth()->user()->hasRole(['doctor'])) {
                $doctor = $this->parentMenu($menu, [
                    'icon' => 'ph ph-stethoscope',
                    'title' => __('sidebar.doctor'),
                    'route' => 'backend.doctor.index',
                    'permission' => ['view_doctors_session'],
                    'nickname' => 'doctor',
                    'order' => 0,
                ]);
                $this->childMain($doctor, [
                    'icon' => 'ph ph-stethoscope',
                    'title' => __('sidebar.doctor'),
                    'route' => 'backend.doctor.index',
                    'active' => 'app/doctor',
                    'permission' => ['view_doctors'],
                    'order' => 0,
                ]);
                $this->childMain($doctor, [
                    'icon' => 'ph ph-clock',
                    'title' => __('clinic.doctor_session'),
                    'route' => 'backend.doctor-session.index',
                    'active' => 'app/doctor-session',
                    'permission' => ['view_doctors_session'],
                    'order' => 0,
                ]);
            }

            $this->mainRoute($menu, [
                'icon' => 'ph ph-calendar-heart',
                'title' => __('clinic.specialization'),
                'route' => 'backend.specializations.index',
                'active' => ['app/specializations'],
                'permission' => ['view_specialization'],
                'order' => 0,
            ]);


            $permissionsToCheck = ['view_clinics_center', 'view_clinics_category', 'view_clinics_service', 'view_doctors', 'view_doctors_session', 'view_clinic_patient_list', 'view_patient_soap', 'view_clinic_appointment_list', 'view_encounter_template', 'view_encounter'];

            if (collect($permissionsToCheck)->contains(fn($permission) => auth()->user()->can($permission))) {

                if (multiVendor() == "1" || auth()->user()->hasRole(['admin', 'demo_admin', 'vendor', 'doctor', 'receptionist'])) {

                    $this->staticMenu($menu, ['title' => __('sidebar.clinic_center'), 'order' => 0]);
                }
            }
            if (!auth()->user()->hasRole('receptionist')) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-hospital',
                    'title' => __('sidebar.clinic'),
                    'route' => 'backend.clinics.index',
                    'permission' => ['view_clinics_center'],
                    'active' => ['app/clinics'],
                    'order' => 0,
                ]);
            }
            $this->mainRoute($menu, [
                'icon' => 'ph ph-list-bullets',
                'title' => __('sidebar.categories'),
                'route' => 'backend.category.index',
                'permission' => ['view_clinics_category'],
                'active' => ['app/category'],
                'order' => 0,
            ]);



            $this->mainRoute($menu, [
                'icon' => 'ph ph-first-aid-kit',
                'title' => __('sidebar.services'),
                'route' => 'backend.services.index',
                'active' => ['app/services'],
                'permission' => ['view_clinics_service'],
                'order' => 0,
            ]);



            if (auth()->user()->hasRole(['doctor'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-clock',
                    'title' => __('clinic.doctor_session'),
                    'route' => 'backend.doctor-session.index',
                    'active' => 'app/doctor-session',
                    'permission' => ['view_doctors_session'],
                    'order' => 0,
                ]);
            }


            if (auth()->user()->hasRole(['doctor'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-list-bullets',
                    'title' => __('sidebar.encounter'),
                    'route' => 'backend.encounter.index',
                    'active' => 'app/encounter',
                    'permission' => ['view_encounter'],
                    'order' => 0,
                ]);
            }
            $this->mainRoute($menu, [
                'icon' => ' ph ph-star',
                'title' => __('sidebar.reviews'),
                'route' => ['backend.doctors.review'],
                'active' => ['app/doctors-review'],
                'permission' => ['view_reviews'],
                'order' => 0,
            ]);


            //shop

            // $permissionsToCheck = ['view_product', 'view_brand', 'view_product_category', 'view_product_subcategory','view_unit','view_tag','view_product_variation',
            // 'view_order','view_supply','view_logistics','view_shipping_zones'];


            // if (collect($permissionsToCheck)->contains(fn ($permission) => auth()->user()->can($permission))) {
            //     $this->staticMenu($menu, ['title' => __('sidebar.shop'), 'order' => 0]);
            // }
            // $product = $this->parentMenu($menu, [
            //     'icon' => 'fa-solid fa-store',
            //     'title' => __('sidebar.product'),
            //     'route' => 'backend.products.index',
            //     'permission' => ['view_product'],
            //     'nickname' => 'PR',
            //     'order' => 0,
            // ]);
            // $this->childMain($product, [
            //     'title' => __('sidebar.all_product'),
            //     'route' => 'backend.products.index',
            //     'active' => 'app/products',
            //     'shortTitle' => 'AP',
            //     'permission' => ['view_product'],
            //     'order' => 0,
            // ]);
            // $this->childMain($product, [
            //     'title' => __('sidebar.brand'),
            //     'route' => 'backend.brands.index',
            //     'shortTitle' => 'BR',
            //     'permission' => ['view_brand'],
            //     'active' => ['app/brands'],
            //     'order' => 0,
            // ]);
            // $this->childMain($product, [
            //     'title' => __('sidebar.categories'),
            //     'route' => 'backend.products-categories.index',
            //     'shortTitle' => 'C',
            //     'permission' => ['view_product_category'],
            //     'active' => ['app/products-categories'],
            //     'order' => 0,
            // ]);
            // $this->childMain($product, [
            //     'title' => __('sidebar.sub_categories'),
            //     'route' => 'backend.products-categories.index_nested',
            //     'shortTitle' => 'SC',
            //     'permission' => ['view_product_subcategory'],
            //     'active' => ['app/products-sub-categories'],
            //     'order' => 0,
            // ]);

            // $this->childMain($product, [
            //     'title' => __('sidebar.units'),
            //     'route' => 'backend.units.index',
            //     'shortTitle' => 'U',
            //     'permission' => ['view_unit'],
            //     'active' => ['app/units'],
            //     'order' => 0,
            // ]);

            // $this->childMain($product, [
            //     'title' => __('sidebar.tag'),
            //     'route' => 'backend.tags.index',
            //     'shortTitle' => 'T',
            //     'permission' => ['view_tag'],
            //     'active' => ['app/tags'],
            //     'order' => 0,
            // ]);

            // $this->mainRoute($menu, [
            //     'icon' => 'fa-solid fa-swatchbook',
            //     'title' => __('sidebar.variations'),
            //     'route' => ['backend.variations.index'],
            //     'active' => ['app/variations'],
            //     'permission' => ['view_product_variation'],
            //     'order' => 0,
            // ]);

            // $this->mainRoute($menu, [
            //     'icon' => 'fa-solid fa-bag-shopping',
            //     'title' => __('sidebar.orders'),
            //     'permission' => 'view_tag',
            //     'route' => ['backend.orders.index'],
            //     'permission' => ['view_order'],
            //     'active' => ['app/orders'],
            //     'order' => 0,
            // ]);

            // $supply = $this->parentMenu($menu, [
            //     'icon' => 'fa-solid fa-truck-field',
            //     'title' => __('sidebar.supply'),
            //     'nickname' => 'supply',
            //     'permission' => ['view_supply'],
            //     'order' => 0,
            // ]);

            // $this->childMain($supply, [
            //     'title' => __('sidebar.logistics'),
            //     'route' => 'backend.logistics.index',
            //     'shortTitle' => 'AP',
            //     'active' => ['app/logistics'],
            //     'permission' => ['view_logistics'],
            //     'order' => 0,
            // ]);

            // $this->childMain($supply, [
            //     'title' => __('sidebar.logistic_zone'),
            //     'route' => 'backend.logistic-zones.index',
            //     'permission' => ['view_shipping_zones'],
            //     'shortTitle' => 'AP',
            //     'active' => ['app/logistic-zones'],
            //     'order' => 0,
            // ]);

            // FINANCE Static

            $permissionsToCheck = ['view_customer', 'view_clinic_receptionist_list', 'view_vendor_list'];

            if (collect($permissionsToCheck)->contains(fn($permission) => auth()->user()->can($permission))) {
                $this->staticMenu($menu, ['title' => __('sidebar.user'), 'order' => 0]);
            }


            $this->mainRoute($menu, [
                'icon' => 'ph ph-users',
                'title' =>  __('sidebar.patient'),
                'route' => 'backend.customers.index',
                'active' => ['app/customers'],
                'permission' => 'view_customer',
                'order' => 0,
            ]);

            $this->mainRoute($menu, [
                'icon' => 'ph ph-user-circle-gear',
                'title' => __('sidebar.receptionist'),
                'route' => 'backend.receptionist.index',
                'active' => ['app/receptionist'],
                'permission' => ['view_clinic_receptionist_list'],
                'order' => 0,
            ]);
            if (auth()->user()->hasRole(['admin', 'demo_admin'])) {
                // $this->mainRoute($menu, [
                //     'icon' => 'ph ph-user-square',
                //     'title' => __('sidebar.suppliers'),
                //     'route' => 'backend.suppliers.index',
                //     'active' => ['app/suppliers'],
                //     'order' => 0,
                // ]);
                // $this->mainRoute($menu, [
                //     'icon' => 'ph ph-user-square',
                //     'title' => __('sidebar.suppliers_type'),
                //     'route' => 'backend.supplier-type.index',
                //     'active' => ['app/supplier-type'],
                //     'order' => 0,
                // ]);
            }
            if (multiVendor() == "1" && auth()->user()->hasRole(['admin', 'demo_admin'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-user-square',
                    'title' => __('sidebar.vendors'),
                    'route' => 'backend.multivendors.index',
                    'active' => ['app/multivendors'],
                    'permission' => ['view_vendor_list'],
                    'order' => 0,
                ]);
            }
            if(checkPlugin('pharma') == 'active' && auth()->user()->hasRole(['pharma'])){

                $permissionsToCheck = ['view_prescription'];

                if (collect($permissionsToCheck)->contains(fn ($permission) => auth()->user()->can($permission))) {
                    $this->staticMenu($menu, ['title' => __('sidebar.prescription'), 'order' => 0]);
                }

                // dd(checkPlugin('pharma'));
                if(checkPlugin('pharma') == 'active'){
                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-calendar-minus',
                        'title' => __('sidebar.prescription'),
                        'route' => 'backend.prescription.index',
                        'active' => ['app/prescription'],
                        'permission' => 'view_prescription',
                        'order' => 0,
                    ]);
                }

                $permissionsToCheck = ['view_medicine', 'add_medicine', 'edit_medicine', 'delete_medicine'];

                if (checkPlugin('pharma') == 'active' && !auth()->user()->hasRole(['admin', 'demo_admin'])){
                    if (collect($permissionsToCheck)->contains(fn ($permission) => auth()->user()->can($permission))) {
                        $this->staticMenu($menu, ['title' => __('sidebar.medicine'), 'order' => 0]);
                    }

                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-pill',
                        'title' => __('sidebar.all_medicine'),
                        'route' => 'backend.medicine.index',
                        'active' => ['app/medicine'],
                        'permission' => 'view_medicine',
                        'order' => 0,
                    ]);

                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-clock-countdown',
                        'title' => __('sidebar.expired_medicine'),
                        'route' => 'backend.expired-medicine.index',
                        'active' => ['app/expired-medicine'],
                        'permission' => 'view_expired_medicine',
                        'order' => 0,
                    ]);

                }



                $permissionsToCheck = ['view_suppliers'];

                if (checkPlugin('pharma') == 'active' && !auth()->user()->hasRole(['admin', 'demo_admin'])){
                    if (collect($permissionsToCheck)->contains(fn ($permission) => auth()->user()->can($permission))) {
                        $this->staticMenu($menu, ['title' => __('sidebar.all_suppliers'), 'order' => 0]);
                    }

                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-handshake',
                        'title' => __('sidebar.suppliers'),
                        'route' => 'backend.suppliers.index',
                        'active' => ['app/suppliers'],
                        'permission' => 'view_suppliers',
                        'order' => 0,
                    ]);
                }


                $permissionsToCheck = ['view_purchased_order'];

                if (collect($permissionsToCheck)->contains(fn ($permission) => auth()->user()->can($permission))) {
                    $this->staticMenu($menu, ['title' => __('sidebar.purchased_order'), 'order' => 0]);
                }

                if(checkPlugin('pharma') == 'active'){
                    $this->mainRoute($menu, [
                    'icon' => 'ph ph-shopping-cart',
                    'title' => __('sidebar.purchased_order'),
                    'route' => 'backend.order-medicine.index',
                        'active' => ['app/order-medicine'],
                        'permission' => auth()->user()->hasRole(['admin','demo_admin']) ? '' :  'view_purchased_order',
                        'order' => 0,
                    ]);
                }

            }

            // if(checkPlugin('pharma') == 'active'){
            if (auth()->user()->hasRole(['admin', 'demo_admin']) || (multiVendor() == "1" && auth()->user()->hasRole(['vendor']))) {


                $pluginStatus = checkPlugin('pharma');
                // dd($pluginStatus);
                if($pluginStatus == 'active' || $pluginStatus == 'not_found'){

                    $isPharmaEnabled = $pluginStatus == 'active';
                    // dd($isPharmaEnabled);
                    $this->staticMenu($menu, [
                        'title' => __('sidebar.pharma'),
                        'order' => 0
                    ]);

                    // Add all pharma menu items normally (no upgrade_message here)
                    $pharmaRoute = ($isPharmaEnabled && \Illuminate\Support\Facades\Route::has('backend.pharma.index')) 
                        ? 'backend.pharma.index' 
                        : null;
                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-asclepius',
                        'title' => __('sidebar.pharma'),
                        'route' => $pharmaRoute,
                        'active' => ['app/pharma'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);

                    if (checkPlugin('pharma') == 'active') {
                        $this->mainRoute($menu, [
                            'icon' => 'ph ph-notepad',
                            'title' => __('sidebar.prescription'),
                            'route' => 'backend.prescription.index',
                            'active' => ['app/prescription'],
                            'li_class' => 'nav-item',
                            'link_attributes' => [
                                'class' => 'nav-link',
                                'title' => '',
                            ],
                        ]);
                    }


                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-handshake',
                        'title' => __('sidebar.suppliers'),
                        'route' => $isPharmaEnabled ? 'backend.suppliers.index' : null,
                        'active' => ['app/suppliers'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);

                    if (auth()->user()->hasRole(['admin', 'demo_admin'])){
                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-handshake',
                        'title' => __('sidebar.suppliers_type'),
                        'route' => $isPharmaEnabled ? 'backend.supplier-type.index' : null,
                        'active' => ['app/supplier-type'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);
                    }


                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-pill',
                        'title' => __('sidebar.all_medicine'),
                        'route' => $isPharmaEnabled ? 'backend.medicine.index' : null,
                        'active' => ['app/medicine'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);
                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-shopping-cart',
                        'title' => __('sidebar.purchased_order'),
                        'route' => $isPharmaEnabled ? 'backend.order-medicine.index' : null,
                        'active' => ['app/order-medicine'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);

                    if (auth()->user()->hasRole(['admin', 'demo_admin'])){
                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-list-bullets',
                        'title' => __('sidebar.category'),
                        'route' => $isPharmaEnabled ? 'backend.medicine-category.index' : null,
                        'active' => ['app/medicine-category'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);

                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-syringe',
                        'title' => __('sidebar.medicine_form'),
                        'route' => $isPharmaEnabled ? 'backend.medicine-form.index' : null,
                        'active' => ['app/medicine-form'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);
                    }

                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-clock-countdown',
                        'title' => __('sidebar.expired_medicine'),
                        'route' => $isPharmaEnabled ? 'backend.expired-medicine.index' : null,
                        'active' => ['app/expired-medicine'],
                        'order' => 0,
                        'li_class' => $isPharmaEnabled ? 'nav-item' : 'nav-item blurred-menu',
                        'link_attributes' => [
                            'class' => 'nav-link',
                            'title' => $isPharmaEnabled ? '' : 'Addon required',
                        ],
                    ]);

                    // **Add ONE centered Upgrade to Pro message ONLY if pharma is disabled**
                    // if (!$isPharmaEnabled) {
                    //     $menu->add('Upgrade to Pro', [
                    //         'url' => '#',
                    //         'class' => 'nav-item upgrade-message-item',
                    //     ])->link->attr([
                    //         'class' => 'nav-link disabled',
                    //         'style' => 'text-align:center; color:#d9534f; font-weight:600; cursor:default;',
                    //     ]);
                    // }
                }



            $permissionsToCheck = ['view_tax', 'view_earning', 'view_billing_record'];

            if (collect($permissionsToCheck)->contains(fn ($permission) => auth()->user()->can($permission))) {
                //dd("Dsdsd");
                $this->staticMenu($menu, ['title' => __('sidebar.finance'), 'order' => 0]);
            }

            if(auth()->user()->user_type != 'pharma'){
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-percent',
                    'title' => __('sidebar.tax'),
                    'route' => 'backend.tax.index',
                    'active' => ['app/tax'],
                    // 'permission' => 'view_tax',
                    'order' => 0,
                ]);
            }
            if(auth()->user()->hasRole(['admin','demo_admin'])){
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-invoice',
                    'title' => __('sidebar.billing_record'),
                    'route' => 'backend.billing-record.index',
                    'active' => ['app/billing-record'],
                    'permission' => 'view_billing_record',
                    'order' => 0,
                ]);

            }
            if(auth()->user()->hasRole('pharma')){
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-invoice',
                    'title' => __('sidebar.billing_record'),
                    'route' => 'backend.pharma.billing-records.index',
                    'active' => ['app/pharma/billing-records'],
                    'permission' => 'view_billing_record',
                    'order' => 0,
                ]);
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-currency-dollar',
                    'title' => __('sidebar.pharma_payout'),
                    'route' => 'backend.payout.pharma-payout-report',
                    'active' => ['app/pharma-payout'],
                    'permission' => 'view_pharma_payout',
                    'order' => 0,
                ]);

                if(auth()->user()->user_type != 'pharma'){
                    $this->mainRoute($menu, [
                        'icon' => 'ph ph-percent',
                        'title' => __('sidebar.commission'),
                        // 'route' => 'backend.billing-record.index',
                        'active' => ['app/pharma-payout'],
                        'permission' => 'view_commission',
                        'order' => 0,
                    ]);
                }

                // Bed Management for pharma users
                $BedMannagementPharma = $this->parentMenu($menu, [
                    'icon' => 'ph ph-bed',
                    'title' =>  __('sidebar.bed_mannagement'),
                    'nickname' => 'bed_mannagement',
                    'order' => 0,
                ]);

                //bed allocation
                $this->childMain($BedMannagementPharma, [
                    'icon' => 'ph ph-sliders-horizontal',
                    'title' => __('sidebar.bed_allocation'),
                    'route' => 'backend.bed-allocation.index',
                    'active' => ['app/bed-allocation'],
                    'permission' => ['view_allocations'],
                    'order' => 0,
                ]);
                // All Bed
                $this->childMain($BedMannagementPharma, [
                    'icon' => 'ph ph-bed',
                    'title' => __('sidebar.all_bed'),
                    'route' => 'backend.bed-master.index',
                    'active' => ['app/bed-master'],
                    'permission' => ['view_bed_master'],
                    'order' => 0,
                ]);
                // Bed Type
                $this->childMain($BedMannagementPharma, [
                    'icon' => 'ph ph-squares-four',
                    'title' => __('sidebar.bed_type'),
                    'route' => 'backend.bed-type.index',
                    'active' => ['app/bed-type'],
                    'permission' => ['view_bed_type'],
                    'order' => 1,
                ]);
                //bed status
                $this->childMain($BedMannagementPharma, [
                    'icon' => 'ph ph-sliders-horizontal',
                    'title' => __('sidebar.bed_satus'),
                    'route' => 'backend.bed-status.index',
                    'active' => ['app/bed-status'],
                    'order' => 1,
                ]);

            }

            $this->mainRoute($menu, [
                'icon' => 'ph ph-currency-dollar',
                'title' => __('sidebar.doctor_earning'),
                'route' => 'backend.earnings.index',
                'active' => ['app/earnings'],
                'permission' => ['view_doctor_earning'],
                'order' => 0,
            ]);
            if (checkPlugin('pharma') == 'active' && auth()->user()->hasRole(['admin', 'demo_admin'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-credit-card',
                    'title' => __('pharma::sidebar.pharma_earnings'),
                    'route' => 'backend.earning.index',
                    'active' => ['app/earning'],
                    // 'permission' => ['view_pharma_earning'],
                    'order' => 0,
                ]);
            }



            if (multiVendor() == "1" && auth()->user()->hasRole(['admin', 'demo_admin'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-user-circle-check',
                    'title' => __('sidebar.vendor_earning'),
                    'route' => 'backend.vendor-earnings.index',
                    'active' => ['app/vendor-earnings'],
                    'permission' => ['view_vendor_earning'],
                    'order' => 0,
                ]);
            }



            //Report

            $permissionsToCheck = ['view_daily_bookings', 'view_overall_bookings', 'view_staff_payouts', 'view_staff_service', 'view_order_reports', 'view_commission_reports', 'view_appointment_overview', 'view_clinic_overview'];

            if (collect($permissionsToCheck)->contains(fn($permission) => auth()->user()->can($permission))) {
                $this->staticMenu($menu, ['title' => __('sidebar.reports'), 'order' => 0]);
            }



            if (auth()->user()->hasRole('vendor')) {

                $this->mainRoute($menu, [
                    'icon' => 'ph ph-percent',
                    'title' =>  __('appointment.revenue_breakdown'),
                    'route' => 'backend.reports.commission-revenue',
                    'active' => ['app/commission-revenue'],
                    'order' => 0,
                ]);
            }


            if (auth()->user()->hasRole('vendor') || auth()->user()->hasRole('demo_admin') ||  auth()->user()->hasRole('admin')) {

                $this->mainRoute($menu, [
                    'icon' => 'ph ph-file-magnifying-glass',
                    'title' =>  __('dashboard.lbl_title_appointment_overview'),
                    'route' => 'backend.reports.appointment-overview',
                    'active' => ['app/appointment-overview'],
                    'order' => 0,
                ]);
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-map-pin-plus',
                    'title' =>  __('sidebar.clinic_overview'),
                    'route' => 'backend.reports.clinic-overview',
                    'active' => ['app/clinic-overview'],
                    'order' => 0,
                ]);
            }


            if (multiVendor() == "1") {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-share',
                    'title' =>  __('sidebar.request_service'),
                    'route' => 'backend.requestservices.index',
                    'active' => ['app/requestservices'],
                    'permission' => ['view_request_service'],
                    'order' => 0,
                ]);
            }


            $this->mainRoute($menu, [
                'icon' => 'ph ph-hand-coins',
                'title' => __('sidebar.doctor_payout'),
                'route' => 'backend.reports.doctor-payout-report',
                'active' => ['app/doctor-payout-report'],
                'permission' => ['view_doctor_payouts'],
                'order' => 0,
            ]);
            if (checkPlugin('pharma') == 'active' && auth()->user()->hasRole(['admin', 'demo_admin'])) {
                $this->mainRoute($menu, [
                'icon' => 'ph ph-currency-circle-dollar',
                'title' => __('pharma::sidebar.pharma_payout'),
                'route' => 'backend.payout.pharma-payout-report',
                'active' => ['app/pharma-payout-report'],
                // 'permission' => ['view_pharma_payouts'],
                'order' => 0,
            ]);
            }

            if (multiVendor() == "1" && auth()->user()->hasRole(['admin', 'demo_admin'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-currency-dollar-simple',
                    'title' => __('sidebar.vendor_payout'),
                    'route' => 'backend.reports.vendor-payout-report',
                    'active' => ['app/vendor-payout-report'],
                    'permission' => ['view_vendor_payouts'],
                    'order' => 0,
                ]);
            }

            // System Static
            $permissionsToCheck = [
                'view_setting',
                'add_setting',
                'edit_setting',
                'delete_setting',
                'view_location',
                'view_city',
                'view_state',
                'view_country',
                'view_pages',
                'view_notification',
                'view_notification_template',
                'view_app_banner',
                'view_constant',
                'view_permission',
                'view_promotions',
                'view_vital',
                'view_subscription',
                'view_my_account',
                'view_subscription_list',
                'view_plan_list',
                'view_plan_limitation',
                'view_backup'
            ];

            if (collect($permissionsToCheck)->contains(fn($permission) => auth()->user()->can($permission))) {
                $this->staticMenu($menu, ['title' => __('sidebar.system'), 'order' => 0]);
            }
            if(auth()->user()->hasRole(['admin', 'demo_admin'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-file',
                    'title' => __('sidebar.plugins'),
                    'route' => 'backend.plugins.index',
                    'active' => 'app/plugins',
                    'order' => 0,
                ]);
            }
            if (multiVendor() == "1" && auth()->user()->hasRole(['admin', 'demo_admin'])) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-airplay',
                    'title' => __('sidebar.system_service'),
                    'route' => 'backend.system-service.index',
                    'active' => ['app/system-service'],
                    'permission' => ['view_system_service'],
                    'order' => 0,
                ]);
            }
            $this->mainRoute($menu, [
                'icon' => 'ph ph-map-pin-plus',
                'title' => __('messages.incidence'),
                'route' => 'backend.incidence.index',
                'active' => 'app/incidence',
                'permission' => ['view_incidence_report'],
                'order' => 0,
            ]);

            if(auth()->user()->user_type != 'pharma'){
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-gear-six',
                    'title' => __('menu.settings'),
                    'route' => 'backend.settings',
                    'active' => 'app/settings',
                    'permission' => ['view_setting'],
                    'order' => 0,
                ]);
            }

            if(!auth()->user()->hasRole('pharma')){
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-pencil-simple',
                    'title' => __('sidebar.blog'),
                    'route' => 'backend.blog.index',
                    'active' => ['app/blog'],
                    'order' => 0,
                ]);
            }

            $location = $this->parentMenu($menu, [
                'icon' => 'ph ph-map-pin-line',
                'title' => __('sidebar.location'),
                'nickname' => 'location',
                'permission' => ['view_location'],
                'order' => 0,
            ]);

            $this->childMain($location, [
                'title' => __('sidebar.city'),
                'route' => 'backend.city.index',
                'active' => 'app/city',
                'shortTitle' => 'CT',
                'permission' => ['view_city'],
                'order' => 0,
                'icon' => 'ph ph-city',
            ]);
            $this->childMain($location, [
                'title' => __('sidebar.state'),
                'route' => 'backend.state.index',
                'shortTitle' => 'CT',
                'permission' => ['view_state'],
                'active' => ['app/state'],
                'order' => 0,
                'icon' => 'ph ph-map-trifold',
            ]);
            $this->childMain($location, [
                'title' => __('sidebar.country'),
                'route' => 'backend.country.index',
                'shortTitle' => 'CT',
                'active' => ['app/country'],
                'permission' => ['view_country'],
                'order' => 0,
                'icon' => 'ph ph-globe-hemisphere-east',
            ]);

            $this->mainRoute($menu, [
                'icon' => ' ph ph-note',
                'title' => __('page.title'),
                'route' => ['backend.pages.index'],
                'active' => ['app/pages'],
                'permission' => ['view_pages'],
                'order' => 0,
            ]);

            // --- APP BANNER ---
            $this->mainRoute($menu, [
                'icon' => 'ph ph-exam',
                'title' => __('sidebar.app_banner'),
                'route' => 'backend.app-banners.index',
                'active' => 'app/app-banners',
                'permission' => ['view_app_banner'],
                'order' => 0,
            ]);

            // --- CUSTOM FORMS ---
            if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('demo_admin')) {
                $custom_form = $this->parentMenu($menu, [
                    'icon' => 'ph ph-table',
                    'title' => __('messages.customforms'),
                    'nickname' => 'custom_form',
                    'permission' => ['view_notification'],
                    'order' => 0,
                ]);
                $this->childMain($custom_form, [
                    'icon' => 'ph ph-list-bullets',
                    'title' => __('messages.customforms_list'),
                    'route' => 'backend.custom-form.index',
                    'shortTitle' => 'Li',
                    'active' => 'app/settings#/customform',
                    'permission' => ['view_notification'],
                    'order' => 0,
                ]);
            }

            // --- NOTIFICATION ---
            $notification = $this->parentMenu($menu, [
                'icon' => 'ph ph-bell',
                'title' => __('notification.title'),
                'nickname' => 'notifications',
                'permission' => ['view_notification'],
                'order' => 0,
            ]);

            $this->childMain($notification, [
                'icon' => 'ph ph-list-bullets',
                'title' => __('notification.list'),
                'route' => 'backend.notifications.index',
                'shortTitle' => 'Li',
                'active' => 'app/notifications',
                'permission' => ['view_notification'],
                'order' => 0,
            ]);
            if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('demo_admin')) {
                $this->childMain($notification, [
                    'icon' => 'ph ph-layout',
                    'title' => __('notification.template'),
                    'route' => 'backend.notification-templates.index',
                    'shortTitle' => 'TE',
                    'active' => 'app/notification-templates*',
                    'permission' => ['view_notification_template'],
                    'order' => 0,
                ]);
            }

            // --- FRONTEND SETTING ---
            if (!auth()->user()->hasRole('doctor') && !auth()->user()->hasRole('vendor') && !auth()->user()->hasRole('receptionist')) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-layout',
                    'title' =>  __('sidebar.frontend_setting'),
                    'route' => 'frontend_setting.index',
                    'active' => ['app/frontend_setting'],
                    'permission' => 'view_customer',
                    'order' => 0,
                ]);
            }

            // --- LOG/BACKUPS ---
            $location11 = $this->parentMenu($menu, [
                'icon' => 'ph ph-note',
                'title' => __('sidebar.log'),
                'nickname' => 'log',
                'permission' => ['view_backup'],
                'order' => 0,
            ]);
        }
        if(!auth()->user()->hasRole('pharma')){
            $this->mainRoute($menu, [
                'icon' => 'ph ph-question',
                'title' => __('messages.faq_title'),
                'route' => 'backend.faqs.index',
                'active' => ['app/faqs'],
                'order' => 0,
            ]);
        }

        if (isset($location11)) {
            $this->childMain($location11, [
                'title' => __('sidebar.backups'),
                'route' => 'backend.backups.index',
                'active' => 'app/backups',
                'shortTitle' => '',
                'permission' => ['view_backup'],
                'order' => 0,
                'icon' => 'ph ph-note',
            ]);
            $this->childMain($location11, [
                'title' => __('sidebar.activity_logs'),
                'route' => 'backend.backups.logs',
                'active' => 'app/backups/logs',
                'shortTitle' => '',
                'permission' => ['view_backup'],
                'order' => 0,
                'icon' => 'ph ph-note',
            ]);
        }

            // --- ACCESS CONTROL ---
            if (auth()->user()->hasRole('admin')) {
                $this->mainRoute($menu, [
                    'icon' => 'ph ph-devices',
                    'title' => __('sidebar.access_control'),
                    'route' => 'backend.permission-role.list',
                    'active' => ['app/permission-role'],
                    'order' => 10,
                ]);
            }

            // Access Permission Check
            $menu->filter(function ($item) {
                if ($item->data('permission')) {
                    if (auth()->check()) {
                        if (\Auth::getDefaultDriver() == 'admin') {
                            return true;
                        }
                        if (auth()->user()->hasAnyPermission($item->data('permission'), \Auth::getDefaultDriver())) {
                            return true;
                        }
                    }

                    return false;
                } else {
                    return true;
                }
            });
            // Set Active Menu
            $menu->filter(function ($item) {
                if ($item->activematches) {
                    $activematches = (is_string($item->activematches)) ? [$item->activematches] : $item->activematches;
                    foreach ($activematches as $pattern) {
                        if (request()->is($pattern)) {
                            $item->active();
                            $item->link->active();
                            if ($item->hasParent()) {
                                $item->parent()->active();
                            }
                        }
                    }
                }

                return true;
            });
        })->sortBy('order');
    }
}
