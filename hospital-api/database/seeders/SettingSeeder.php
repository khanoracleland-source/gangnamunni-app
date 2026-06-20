<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;
use Illuminate\Support\Facades\File;
class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $directory = storage_path('app/data');
        $jsonFiles = File::glob($directory . '/*.json');

        foreach ($jsonFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
        $modules = [
            [
                'name' => 'default_time_zone',
                'val' => 'Asia/Kolkata',
                'type' =>'misc',
                'datatype' => 'misc',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'is_application_link',
                'val' => 1,
                'type' =>'integaration',
            ],
            [
                'name' => 'patient_app_play_store',
                'val' => 'https://play.google.com/store/apps/details?id=com.wellness.customer',
                'type' =>'is_application_link',
            ],
            [
                'name' => 'patient_app_app_store',
                'val' => 'https://apps.apple.com/in/app/kivicare-clinic-admin-app/id6502933651',
                'type' =>'is_application_link',
            ],
            [
                'name' => 'clinicadmin_app_play_store',
                'val' => 'https://play.google.com/store/apps/details?id=com.wellness.vendor',
                'type' =>'is_application_link',
            ],
            [
                'name' => 'clinicadmin_app_app_store',
                'val' => 'https://apps.apple.com/in/app/kivicare-employee-app/id6502933651',
                'type' =>'is_application_link',
            ],
            [
                'name' => 'is_zoom',
                'val' => 1,
                'type' =>'integaration',
            ],
            [
                'name' => 'account_id',
                'val' => 'WJHpsUd9TKKt99vWOKqeig',
                'type' =>'is_zoom',
            ],
            [
                'name' => 'client_id',
                'val' => 'AcILlYbFS2ajeVjFPQMdwg',
                'type' =>'is_zoom',
            ],
            [
                'name' => 'client_secret',
                'val' => '150kB12FZyJ5W4AHoDi1EpwG9mCrxJX9',
                'type' =>'is_zoom',
            ],

            [
                'name' => 'razor_payment_method',
                'val' => 1,
                'type' =>'razorpayPayment',
            ],
            [
                'name' => 'razorpay_secretkey',
                'val' => 'rzp_test_CLw7tH3O3P5eQM',
                'type' =>'razor_payment_method',
            ],
            [
                'name' => 'razorpay_publickey',
                'val' => 'rzp_test_CLw7tH3O3P5eQM',
                'type' =>'razor_payment_method',
            ],
            [
                'name' => 'str_payment_method',
                'val' => 1,
                'type' =>'stripePayment',
            ],
            [
                'name' => 'stripe_secretkey',
                'val' => 'sk_test_CG2JhAIXvVWDeFUFqtUizO4N00zmvm7o8J',
                'type' =>'str_payment_method',
            ],
            [
                'name' => 'stripe_publickey',
                'val' => 'pk_test_HtQwwWoE9b43mfy5km6ThSPN00xunQv8J9',
                'type' =>'str_payment_method',
            ],
            [
                'name' => 'paystack_payment_method',
                'val' => 1,
                'type' =>'paystackPayment',
            ],
            [
                'name' => 'paystack_secretkey',
                'val' => 'sk_test_9b5bf65070d9773c7a2b3aa7dd8d41310c5fc03c',
                'type' =>'paystack_payment_method',
            ],
            [
                'name' => 'paystack_publickey',
                'val' => 'pk_test_8c41a6f40d2753586db092fbe22320ac8eda874d',
                'type' =>'paystack_payment_method',
            ],
            [
                'name' => 'paypal_payment_method',
                'val' => 1,
                'type' =>'paypalPayment',
            ],
            [
                'name' => 'paypal_secretkey',
                'val' => 'EGvqxtKeQIK5LIPbYLuWTMLoCtqzuoNaFUEvaltLlW2Ka58OwTg5fiv_QuD_fhjguk4RsCExBGpvxu7u',
                'type' =>'paypal_payment_method',
            ],
            [
                'name' => 'paypal_clientid',
                'val' => 'AepfSIAvfjV4DCulR7pzq2baaxjpkt0vcl0CBJt-YFKaQ6i7fwSY6LubCPtftIGXBX4elIvUL-aPyB2e',
                'type' =>'paypal_payment_method',
            ],
            [
                'name' => 'flutterwave_payment_method',
                'val' => 1,
                'type' =>'flutterwavePayment',
            ],
            [
                'name' => 'flutterwave_secretkey',
                'val' => 'FLWSECK_TEST-76e58fc4d85dd2c3fc01ea7ef5b9e2bb-X',
                'type' =>'flutterwave_payment_method',
            ],
            [
                'name' => 'flutterwave_publickey',
                'val' => 'FLWPUBK_TEST-0e16d1deea10a74762ea18fd0bf5be1c-X',
                'type' =>'flutterwave_payment_method',
            ],
            [
                'name' => 'is_event',
                'val' => 1,
                'type' =>'other_settings',
            ],
            [
                'name' => 'is_blog',
                'val' => 1,
                'type' =>'other_settings',
            ],
            [
                'name' => 'is_user_push_notification',
                'val' => 1,
                'type' =>'other_settings',
            ],
            [
                'name' => 'is_provider_push_notification',
                'val' => 1,
                'type' =>'other_settings',
            ],

            [
                'name' => 'firebase_notification',
                'val' => '1',
                'type' =>'other_settings',
            ],
            [
                'name' => 'firebase_project_id',
                'val' => 'health-and-wellness-flutter',
                'type' =>'firebase_notification',
            ],

            [
                'name' => 'view_patient_soap',
                'val' => '1',
                'type' =>'module_settings',
            ],
            [
                'name' => 'is_body_chart',
                'val' => '1',
                'type' =>'module_settings',
            ],
            [
                'name' => 'is_telemed_setting',
                'val' => '1',
                'type' =>'module_settings',
            ],
            [
                'name' => 'is_multi_vendor',
                'val' => '0',
                'type' =>'module_settings',
            ],
            [
                'name' => 'is_encounter_problem',
                'val' => '1',
                'type' =>'module_settings',
            ],
            [
                'name' => 'is_encounter_observation',
                'val' => '1',
                'type' =>'module_settings',
            ],
            [
                'name' => 'is_encounter_note',
                'val' => '1',
                'type' =>'module_settings',
            ],
            [
                'name' => 'is_encounter_prescription',
                'val' => '1',
                'type' =>'module_settings',
            ],
            [
                'name' => 'date_formate',
                'val' => 'Y-m-d',
                'type' =>'misc',
                'datatype' => 'misc',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'time_formate',
                'val' => 'g:i A',
                'type' =>'misc',
                'datatype' => 'misc',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'theme_mode',
                'val' => 'whiteTheme',
                'type' =>'string',
            ],
            [
                'name' => 'Menubar_position',
                'val' => 'top',
                'type' =>'string',
            ],
            [
                'name' => 'image_handling',
                'val' => 'new_image',
                'type' =>'string',
            ],
            [
                'name' => 'menu_items',
                'val' => 'crop,rotate,flip,draw,shape,icon,text,mask',
                'type' =>'array',
            ],
            [
                'name' => 'clinic',
                'val' => 1,
                'type' =>'misc',
                'datatype' => 'misc',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'helpline_number',
                'val' => '146527891234',
                'type' =>'bussiness', 
                'datatype' => 'bussiness',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'inquriy_email',
                'val' => 'kivicare@demo.com',
                'type' =>'bussiness', 
                'datatype' => 'bussiness',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'short_description',
                'val' => 'KiviCare: Simplifying online appointments. Find doctors, book slots, manage health conveniently.',
                'type' =>'bussiness', 
                'datatype' => 'bussiness',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'is_dummy_credentials',
                'val' => 1,
                'type' =>'integaration', 
                'datatype' => '',
                'created_by' => 1,
                'updated_by' => 1,
            ],

        ];
        foreach ($modules as $key => $value) {

            $service = [
                'name' => $value['name'],
                'val' => $value['val'],
                'type' => $value['type'],
                'datatype' => $value['datatype'] ?? null,
                'created_by' => 1,
                'updated_by' => 1,
            ];
            $service = Setting::create($service);
        }

    }
}
