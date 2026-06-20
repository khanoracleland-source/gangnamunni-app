<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Constant\Models\Constant;
use Modules\Service\Models\Service;
use Modules\Service\Models\SystemServiceCategory;
use Modules\World\Models\Country;
use Modules\World\Models\State;
use Modules\World\Models\City;
use Modules\Clinic\Models\Clinics;
use Modules\Clinic\Models\ClinicsCategory;
use Modules\Clinic\Models\SystemService;
use Modules\Clinic\Models\ClinicsService;
use Modules\Pharma\Models\Supplier;
use Modules\Pharma\Models\SupplierType;
use DB;
use Illuminate\Support\Facades\Cache;
use Modules\Appointment\Models\EncounterPrescription;
use Modules\Pharma\Models\Manufacturer;
use Modules\Pharma\Models\Medicine;
use Modules\Pharma\Models\MedicineCategory;
use Modules\Pharma\Models\MedicineForm;
use App\Models\Setting;
use Modules\Commission\Models\Commission;
use Carbon\Carbon;

class SearchController extends Controller
{
    public function get_search_data(Request $request)
    {
        $is_multiple = isset($request->multiple) ? explode(',', $request->multiple) : null;
        if (isset($is_multiple) && count($is_multiple)) {
            $multiplItems = [];
            foreach ($is_multiple as $key => $value) {
                $multiplItems[$key] = $this->getData(collect($request[$value]));
            }

            return response()->json(['status' => 'true', 'results' => $multiplItems]);
        } else {
            return response()->json(['status' => 'true', 'results' => $this->getData($request->all())]);
        }
    }

    // case 'users':
    // select('id as $key','name as $value')
    // select(\DB::raw("value $key,label $value"))
    // if($keyword != ''){
    //   $items->where('category_name', 'LIKE', $keyword.'%');
    // }
    //   break;
    protected function getData($request)
    {
        $items = [];

        $type = $request['type'];
        $sub_type = $request['sub_type'] ?? null;

        $keyword = $request['q'] ?? null;

        switch ($type) {

            case 'system_category':
                $items = SystemServiceCategory::select('id', 'name as text');
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'clinic_admin':
                $items = User::where(function ($query) {
            $query->role('vendor')
                  ->orWhereHas('roles', function ($q) {
                      $q->where('name', 'admin');
                  });
            })->select('id', \DB::raw("CONCAT(first_name,' ',last_name) AS text"));

                if ($keyword != '') {
                    $items->where(\DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'country':

                $items = Country::where('status', 1)->select('id', 'name as text');
                
                
                if ($keyword != '') {

                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->get();
                break;

            case 'state':

                $items = State::where('status', 1)->select('id', 'name as text');
                
                if ($sub_type != null) {

                    $items = State::where('country_id', $sub_type)->where('status', 1)->select('id', 'name as text');
                }

                if ($keyword != '') {

                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->get();
                break;

            case 'city':

                $items = City::where('status', 1)->select('id', 'name as text');
                
                if ($sub_type != null) {
                    
                    $items = City::where('state_id', $sub_type)->where('status', 1)->select('id', 'name as text');
                }

                if ($keyword != '') {

                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->get();
                break;

            case 'encounter_problem':

                $items =  $query = Constant::where('type', 'encounter_problem');


                if ($keyword != '') {

                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->get();

                break;

            case 'encounter_observations':

                $items =  $query = Constant::where('type', 'encounter_observations');

                if ($keyword != '') {

                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->get();

                break;

            case 'encounter_prescription':

                $items =  $query = EncounterPrescription::select('id', 'name');

                if ($keyword != '') {

                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->get();

                break;


            case 'employees':
                // Need To Add Role Base
                $items = User::role('employee')->select('id', \DB::raw("CONCAT(first_name,' ',last_name) AS text"));
                if ($keyword != '') {
                    $items->where(\DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }
                $items = $items->limit(50)->get();
                break;
            case 'customers':
                $items = User::role('user')->select('id', \DB::raw("CONCAT(first_name,' ',last_name) AS text"));
                if ($keyword != '') {
                    $items->where(\DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }
                $items = $items->limit(50)->get();
                break;
            case 'doctors':

                $items = User::role('doctor')->SetRole(auth()->user())->with('doctor', 'doctorclinic')->select('id', \DB::raw("CONCAT(first_name,' ',last_name) AS text"));
                if ($keyword != '') {
                    $items->where(\DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }
                $items = $items->limit(50)->get();
                break;
            case 'vendors':
                $items = User::role('vendor')->select('id', \DB::raw("CONCAT(first_name,' ',last_name) AS text"));
                if ($keyword != '') {
                    $items->where(\DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }
                $items = $items->limit(50)->get();
                break;
            case 'services':
                $items = Service::select('id', 'name as text');

                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;
            case 'earning_payment_method':
                $query = Constant::getAllConstant()
                    ->where('type', 'EARNING_PAYMENT_TYPE');
                foreach ($query as $key => $data) {
                    if ($keyword != '') {
                        if (strpos($data->name, str_replace(' ', '_', strtolower($keyword))) !== false) {
                            $items[] = [
                                'id' => $data->name,
                                'text' => ucfirst($data->value),
                            ];
                        }
                    } else {
                        $items[] = [
                            'id' => $data->name,
                            'text' => ucfirst($data->value),
                        ];
                    }
                }
                break;

            case 'booking_status':
                $query = Constant::getAllConstant()
                    ->where('type', 'BOOKING_STATUS');
                foreach ($query as $key => $data) {
                    if ($keyword != '') {
                        if (strpos($data->name, str_replace(' ', '_', strtolower($keyword))) !== false) {
                            $items[] = [
                                'id' => $data->name,
                                'text' => $data->value,
                            ];
                        }
                    } else {
                        $items[] = [
                            'id' => $data->name,
                            'text' => $data->value,
                        ];
                    }
                }
                break;

            case 'time_zone':
                $items = timeZoneList();

                // foreach ($items as $k => $v) {

                //    if($value !=''){
                //         if (strpos($v, $value) !== false) {

                //         }else{
                //              unset($items[$k]);
                //         }
                //    }
                // }

                $data = [];
                $i = 0;
                foreach ($items as $key => $row) {
                    $data[$i] = [
                        'id' => $key,
                        'text' => $row,
                    ];

                    $i++;
                }

                $items = $data;

                break;

            case 'additional_permissions':
                $query = Constant::getAllConstant()
                    ->where('type', 'additional_permissions');
                foreach ($query as $key => $data) {
                    if ($keyword != '') {
                        if (strpos($data->name, str_replace(' ', '_', strtolower($keyword))) !== false) {
                            $items[] = [
                                'id' => $data->name,
                                'text' => $data->value,
                            ];
                        }
                    } else {
                        $items[] = [
                            'id' => $data->name,
                            'text' => $data->value,
                        ];
                    }
                }

                break;

            case 'constant':
                $query = Constant::getAllConstant()->where('type', $sub_type);
                foreach ($query as $key => $data) {
                    if ($keyword != '') {
                        if (strpos($data->name, str_replace(' ', '_', strtolower($keyword))) !== false) {
                            $items[] = [
                                'id' => $data->name,
                                'text' => $data->value,
                            ];
                        }
                    } else {
                        $items[] = [
                            'id' => $data->name,
                            'text' => $data->value,
                        ];
                    }
                }

                break;

            case 'role':
                $query = Role::all();
                foreach ($query as $key => $data) {
                    if ($keyword != '') {
                        if (strpos($data->name, str_replace(' ', '_', strtolower($keyword))) !== false) {
                            $items[] = [
                                'id' => $data->id,
                                'text' => $data->name,
                            ];
                        }
                    } else {
                        $items[] = [
                            'id' => $data->id,
                            'text' => $data->name,
                        ];
                    }
                }

                break;

            case 'clinic_name':

                $userId = auth()->id();

                $items = Clinics::SetRole(auth()->user())->with('clinicdoctor', 'specialty', 'clinicdoctor', 'receptionist')->select('id', 'name as text');

                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'clinic_category':
                $items = ClinicsCategory::select('id', 'name as text');
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'system_service':
                $items = SystemService::select('id', 'name as text');
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;


            case 'date_formate':
                $items = dateFormatList();
                $data = [];
                $i = 0;
                foreach ($items as $formatCode => $format) {
                    $data[$i] = [
                        'id' => $formatCode,
                        'text' => $format,
                    ];

                    $i++;
                }

                $items = $data;
                break;

            case 'time_formate':
                $items = timeFormatList();
                $data = [];
                $i = 0;
                foreach ($items as $timeFormat) {
                    $data[$i] = [
                        'id' =>  $timeFormat['format'],
                        'text' => $timeFormat['time'],
                    ];

                    $i++;
                }
                $items = $data;
                break;
            case 'price_range':
                $service = ClinicsService::SetRole(auth()->user())->with('sub_category', 'doctor_service', 'ClinicServiceMapping', 'systemservice')->where('status', 1)->get();
                $prices = $service->pluck('charges');
                $minPrice = 0;
                $maxPrice = $prices->max();

                $interval = 50;
                $priceRanges = [];
                if ($maxPrice <= $interval) {
                    $priceRanges[] = [$minPrice, $maxPrice];
                } else {
                    for ($i = $minPrice; $i <= $maxPrice; $i += $interval) {
                        $priceRanges[] = [$i, min($i + $interval, $maxPrice)];
                    }
                }

                $data = [];
                $i = 0;
                foreach ($priceRanges as $range) {
                    $val = $range[0] . ' - ' . $range[1];
                    $data[$i] = [
                        'id' => $val,
                        'text' => $val,
                    ];

                    $i++;
                }

                $items = $data;
                break;

            case 'supplier_name':

                $userId = auth()->id();

                $items = Supplier::select('id', DB::raw("CONCAT(first_name, ' ', last_name) as text"))->where('status', 1);
                if (!auth()->user()->hasRole(['admin', 'demo_admin'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }
                if ($keyword != '') {
                    $items->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'supplier_type':

                $userId = auth()->id();

                $items = SupplierType::select('id', DB::raw("name as text"))->where('status', 1);

                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'supplier_contact_number':

                $userId = auth()->id();

                // $items = Supplier::select('id', 'contact_number', DB::raw("contact_number as text"));
                $items = Supplier::select('contact_number')->where('status', 1);
                if (!auth()->user()->hasRole(['admin', 'demo_admin'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }
                if ($keyword != '') {
                    $items->where('contact_number', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                $items = $items->map(function($item){
                        return [
                            'id' => $item->contact_number,
                            'text' => $item->contact_number
                        ];
                    });

                return $items;
                break;

            case 'pharma_id':

                $userId = auth()->id();

                $items = User::pharmaRole(auth()->user())->where('user_type', 'pharma')->where('status', 1)->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as text"));

                if ($keyword != '') {
                    $items->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;


            case 'supplier_by_pharma':
                if (auth()->user()->hasRole('pharma')) {
                    $pharmaId = auth()->id();
                } else {
                    $pharmaId = $request['pharma_id'] ?? null;
                }

                $keyword = $request['term'] ?? '';

                $items = \Modules\Pharma\Models\Supplier::query()
                    ->where('status', 1)
                    ->where('pharma_id', $pharmaId)
                    ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as text"));

                if (!empty($keyword)) {
                    $items->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'manufacturer_by_pharma':
                if (auth()->user()->hasRole('pharma')) {
                    $pharmaId = auth()->id();
                } else {
                    $pharmaId = $request['pharma_id'] ?? null;
                }
                $keyword = $request['term'] ?? '';

                $items = \Modules\Pharma\Models\Manufacturer::query()
                    ->select('id', 'name as text')
                    ->where('pharma_id', $pharmaId);

                if (!empty($keyword)) {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;





            case 'pharma':
                $userId = auth()->id();
                $keyword = request()->get('q');
                $fullUrl = request()->headers->get('referer');
                $segments = explode('/', parse_url($fullUrl, PHP_URL_PATH));
                $encounterId = end($segments);
                $clinicId = DB::table('patient_encounters')
                    ->where('id', $encounterId)
                    ->value('clinic_id');

                $items = DB::table('users')
                    ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as text"))
                    ->where('user_type', 'pharma')
                    ->where('status', 1);

                if ($clinicId) {
                    $items->where('clinic_id', $clinicId);
                }

                if (!empty($keyword)) {
                    $items->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;


            case 'medicine':
                $items = Medicine::select('id', 'name as text')->where('expiry_date', '>=', Carbon::today());
                if (!auth()->user()->hasRole(['admin', 'demo_admin'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'dosage':
                $items = Medicine::select('id', 'dosage as text')->where('expiry_date', '>=', Carbon::today());
                if (!auth()->user()->hasRole(['admin', 'demo_admin'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }
                if ($keyword != '') {
                    $items->where('dosage', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'expired_medicine':
                $items = Medicine::select('id', 'name as text')
                    ->where('expiry_date', '<', Carbon::today());

                if (!auth()->user()->hasRole(['admin', 'demo_admin'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }

                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;


            case 'form':
                $items = MedicineForm::select('id', 'name as text')->where('status', 1);
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'category':
                $items = MedicineCategory::select('id', 'name as text')->where('status', 1);
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'supplier':
                $items = Supplier::select('id', DB::raw("CONCAT(first_name, ' ', last_name) as text"))->where('status', 1);

                if (!auth()->user()->hasRole(['admin', 'demo_admin'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }
                if ($keyword != '') {
                    $items->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%' . $keyword . '%')
                        ->where('status', 1);
                }
                $items = $items->limit(50)->get();
                break;

            case 'manufacturer':
                $items = Manufacturer::select('id', 'name as text');
                if (auth()->user()->hasRole(['pharma'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;



            case 'batch_no':
                $items = Medicine::select('id', 'batch_no as text')->where('expiry_date', '>=', Carbon::today());
                if (!auth()->user()->hasRole(['admin', 'demo_admin'])) {
                    $items->where('pharma_id', auth()->user()->id);
                }
                if ($keyword != '') {
                    $items->where('batch_no', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'clinics_services':
                $items = ClinicsService::select('id', 'name as text');
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'pharma_contact_number':
                $items = User::where('user_type', 'pharma')
                    ->select('id', 'mobile as text'); // Use user ID as the value

                if ($keyword != '') {
                    $items->where('mobile', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'payment_method':
                // dd(":dssdsd");
                $items = Setting::select(DB::raw("name as id"), DB::raw("REPLACE(REPLACE(name, '_payment_method', ''), '_', ' ') as text"))
                    ->where('name', 'LIKE', '%_payment_method')
                    ->where('val', 1);

                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;

            case 'pharma_commission':
                $items = Commission::select('id', 'title as text')->where('type', 'pharma_commission');
                if ($keyword != '') {
                    $items->where('name', 'LIKE', '%' . $keyword . '%');
                }

                $items = $items->limit(50)->get();
                break;
        }

        return $items;
    }

    public function advanceFilter(Request $request)
    {
        $type = $request->query('type');

        switch ($type) {
            case 'observationFilter':
                $data = Constant::where('type', 'encounter_observations')->get();
                break;

            case 'problemFilter':
                $data = Cache::remember('encounter_problems', 10, function () {
                    return Constant::where('type', 'encounter_problem')->get();
                });
                break;

            case 'unknown_type':
                $data = Constant::where('type', 'unknown_type')->get();
                break;

            default:
                $data = [];
                break;
        }

        return response()->json(['data' => $data]);
    }
}
