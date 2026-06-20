<?php

namespace Modules\Plugins\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
// use App\Trait\ModuleTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Plugins\Http\Requests\PluginRequest;
use Modules\Plugins\Models\Plugins;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

class PluginsController extends Controller
{

    protected string $exportClass = '\App\Exports\CouponExport';

    // use ModuleTrait {
    //     initializeModuleTrait as private traitInitializeModuleTrait;
    // }

    public function __construct()
    {
        // $this->traitInitializeModuleTrait(
        //     'coupon.title', // module title
        //     'coupons', // module name
        //     'fa-solid fa-clipboard-list' // module icon
        // );
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(auth()->user()->user_type == 'user')
        {
            return redirect()->route('backend.home');
        }
        $module_title = __('messages.plugins_management');
        $result = Plugins::query()->whereNull('deleted_at')->whereNull('old_plugin_id');

        if (isset($request->queryString) && !empty($request->queryString) && !isset($request->cursor) && empty($request->cursor)) {
            $result = $result->where('plugin_name', 'like', '%' . $request->queryString . '%')
                ->orderBy('created_at','DESC')
                ->cursorPaginate(6);

            $fileterData = '';

            foreach ($result as $key => $value) {
                if ($value['status'] == 1) {
                    $statusVal = 0;
                    $status = "Active";
                    $class = "success";
                    $statusName = "Deactivated";
                    $statusNameClass = "danger";
                } else {
                    $statusVal = 1;
                    $status = "Deactive";
                    $class = "danger";
                    $statusName = "Activated";
                    $statusNameClass = "success";
                }

                $oldPlugins = Plugins::whereNull('deleted_at')
                    ->where('old_plugin_id', $value->id)
                    ->orderBy('id','DESC')
                    ->first(['id','version']);

                    $btnd = '';
                    // if (!empty($oldPlugins))
                    // {
                    //     $btnd ='<div class="col-lg-6">
                    //                 <p style="margin-bottom:0px">Latest Version : <span class="text-info fw-bold"> '.$oldPlugins->version.' </span> </p>
                    //                 <p style="margin-bottom:0px;cursor:pointer" class="text-success fw-bold" id="actionBtn" data-id="'.$value['id'] .'" data-type="updated" title="if you want to update this plugin click here.">
                    //                     <i class="fa fa-refresh me-2"></i>
                    //                     Update Available
                    //                 </p>
                    //             </div>';
                    // }


                if (!empty($oldPlugins))
                {
                    $btnd = '<div class="col-lg-7">
                        <p style="margin-bottom:0px">Latest Version : <span class="text-info fw-bold"> '.$oldPlugins->version.' </span> </p>
                        <p style="margin-bottom:0px;cursor:pointer" class="text-success fw-bold" id="actionBtn" data-id="'.$value['id'] .'" data-type="updated" title="if you want to update this plugin click here.">
                            <i class="fa fa-refresh me-2"></i>
                            Update Available
                        </p>
                    </div>

                    <div class="col-lg-5">
                        <p style="margin-bottom:0px;cursor:pointer" class="text-danger fw-bold" id="actionBtn" data-id="'.$value['id'] .'" data-type="change_log" title="if you want to show change log then click here.">
                            <i class="fa fa-clock me-2" aria-hidden="true"></i>
                            Change Log
                        </p>
                    </div>';
                }


                $fileterData .= '
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="py-2">
                                <div class="row">
                                    <div class="col-lg-2">
                                        <i class="px-2 fa-solid fa-gear" style="font-size:35px"></i>
                                    </div>
                                    <div class="col-lg-8">
                                        <h5 class="line-one"> ' . ucwords($value["plugin_name"]) . '</h5>
                                    </div>
                                    <div class="col-lg-2" style="padding-left:0px">
                                        <span class="badge bg-' . $class . '">' . $status . '</span>
                                    </div>
                                </div>';
                                ?>
                                <?php
                                if (empty($oldPlugins))
                                {
                                    $fileterData .= '<p style="overflow: hidden;display: -webkit-box;-webkit-line-clamp: 5;-webkit-box-orient: vertical;height:100px">'.$value['description'].'</p>';
                                }else{
                                    $fileterData .= '<p style="overflow: hidden;display: -webkit-box;-webkit-line-clamp: 3;line-clamp: 3;-webkit-box-orient: vertical;height:54px">'. $value['description'].'</p>';
                                }
                                if ($value["status"] == 0) {
                                    $fileterData .= '<button type="button" id="actionBtn" data-id="' . $value["id"] . '" data-type="activated" title="if you want to active this plugin click here." class="me-3 btn btn-success">
                                        Activate
                                    </button>
                                    <button type="button" id="actionBtn" data-plugin="'.$value['plugin_name'].'" data-id="' . $value['id'] . '" data-type="deleted" title="if you want to uninstall this plugin click here." class="me-3 btn btn-danger">
                                        Delete
                                    </button>';
                                } else {
                                    $fileterData .= '
                                    <button type="button" id="actionBtn" data-id="' . $value['id'] . '" data-type="deactivated" title="if you want to deactive this plugin click here." class="me-3 btn btn-danger">
                                        Deactivate
                                    </button>
                                    <div class="col-lg-12 mt-3">
                                        <div class="row">
                                        '.$btnd.'
                                        </div>
                                    </div>';
                                }
                                ?>
                                <?php
                                $fileterData .= '</div>
                        </div>
                    </div>
                </div>';
            }

            $fileterData .= '<div class="pagination">
                ' . $result->appends(['queryString' => $request->queryString])->render() . '
            </div>';
            return response()->json(['status' => true, 'data' => $fileterData], 200);
        } else if (isset($request->queryString) && !empty($request->queryString) && isset($request->cursor) && !empty($request->cursor)) {
            $result = $result->where('plugin_name', 'like', '%' . $request->queryString . '%')
            ->orderBy('created_at','DESC')
            ->cursorPaginate(6);
            return view('plugins::backend.plugins.index', [
                "module_title" => $module_title,
                "result" => $result,
                "queryString" => $request->queryString
            ]);
        } else {
            $result = $result->orderBy('created_at','DESC')->cursorPaginate(6);
            return view('plugins::backend.plugins.index', [
                "module_title" => $module_title,
                "result" => $result
            ]);
        }
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if(auth()->user()->user_type == 'user')
        {
            return redirect()->route('backend.home');
        }
        $module_title = __('messages.plugins_management');
        $plugins = plugins::whereNull('deleted_at')->get(['id','version','plugin_name']);

        return view('plugins::backend.plugins.form', [
            "module_title" => $module_title,
            "plugins" => $plugins
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PluginRequest $request)
    {
        try {
            if ($request->hasFile('file_name')) {
                $file = $request->file('file_name');
                $fileName = $file->getClientOriginalName();
    
                isset($request->old_plugin_id) && !empty($request->old_plugin_id) &&
                    $oldVersion = Plugins::where('id', $request->old_plugin_id)->pluck('version')->first();
    
                $plName = pathinfo($fileName, PATHINFO_FILENAME);
    
                $plug = Plugins::where('filename', $plName)
                    ->where('id', '!=', $request->old_plugin_id)
                    ->whereNull('deleted_at')
                    ->exists();
    
                if ($plug) {
                    return back()->withInput()->with('error', 'Plugin name Already Exists.');
                }
    
                $data = $request->all();
    
                $file->storeAs('plugins', $fileName, 'public');
                $moduleName = pathinfo($fileName, PATHINFO_FILENAME);
    
                unset($data['_token'], $data['file_name']);
                $data['filename'] = $moduleName;
    
                if (!empty($oldVersion)) {
                    $data['version'] = versionPlus($oldVersion);
                }
    
                $plugin = Plugins::create($data);
    
                return redirect()->route('backend.plugins.index')
                    ->with('success',  __('messages.plugin_added_success'));
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ]);
        }
    }

    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir")
                        self::rrmdir($dir . "/" . $object);
                    else unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    public function activate(Request $request, $id)
    {
        try {
            $plugin = Plugins::findOrFail($id);
            Artisan::call('optimize:clear');

            $fileName = $plugin->filename;
            $filePath = storage_path('app/public/plugins/' . $fileName . '.zip');
            $moduleName = pathinfo($fileName, PATHINFO_FILENAME);
            $moduleFolder = base_path('Modules/' . $moduleName);

            // Only extract if module folder doesn't exist
            if (!File::exists($moduleFolder)) {
                if (!file_exists($filePath)) {
                    return response()->json(['success' => false, 'message' => 'ZIP file not found.']);
                }

                $zip = new ZipArchive;
                if ($zip->open($filePath) !== TRUE) {
                    return response()->json(['success' => false, 'message' => 'Failed to open the ZIP file.']);
                }

                if (!$zip->extractTo(base_path('Modules/'))) {
                    $zip->close();
                    return response()->json(['success' => false, 'message' => 'Failed to extract the ZIP file.']);
                }

                $zip->close();
            }

            // Update plugin status (activate)
            $plugin->update(['status' => true]);

            // Update modules_statuses.json
            $statusFilePath = base_path('modules_statuses.json');
            if (!File::exists($statusFilePath)) {
                File::put($statusFilePath, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
            }

            $statusData = json_decode(File::get($statusFilePath), true);
            $statusData[$moduleName] = true;
            File::put($statusFilePath, json_encode($statusData, JSON_PRETTY_PRINT));

            // Run migrations if present
            $moduleMigrationPath = 'Modules/' . $moduleName . '/database/migrations';
            if (File::exists(base_path($moduleMigrationPath))) {
                $migrateResult = Artisan::call('migrate', ['--path' => $moduleMigrationPath]);
                if ($migrateResult !== 0) {
                    return response()->json(['success' => false, 'message' => 'Migration failed.']);
                }
            }

            // Save activated plugin names to settings
            $activatedPlugins = Plugins::where('status', true)->pluck('filename')->toArray();
            Setting::add('plugin_activation', json_encode($activatedPlugins), 'array');

            return response()->json(['success' => true, 'message' => 'Plugin has been activated successfully.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }


    // public function activate(Request $request, $id)
    // {
    //     try {
    //         $plugin = Plugins::findOrFail($id);
    //         Artisan::call('optimize:clear');
            
    //         $fileName = $plugin->filename;
    //         $filePath = storage_path('app/public/plugins/' . $fileName . '.zip');
    //         // dd($plugin);

    //         // Check if the file exists
    //         if (!file_exists($filePath)) {
    //             return response()->json(['success' => false, 'message' => 'File not found.']);
    //         }

    //         // Extract the ZIP file
    //         $zip = new ZipArchive;

    //         if ($zip->open($filePath) !== TRUE) {
    //             return response()->json(['success' => false, 'message' => 'Failed to open the ZIP file.']);
    //         }
    //         // $path = env('PLUGIN_PATH').'/Modules/';
    //         $path = base_path('Modules/');

    //         if (!$zip->extractTo($path)) {
    //             $zip->close();
    //             return response()->json(['success' => false, 'message' => 'Failed to extract the ZIP file.']);
    //         }
    //         $zip->close();
    //         $plugin->update(['status' => !$plugin->status]);

    //         // Update modules_statuses.json
    //         $statusFilePath = base_path().'/modules_statuses.json';
    //         if (!File::exists($statusFilePath)) {
    //             File::put($statusFilePath, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
    //         }
    //         $statusData = json_decode(File::get($statusFilePath), true);
    //         $moduleName = pathinfo($fileName, PATHINFO_FILENAME);

    //         $statusData[$moduleName] = $plugin->status;

    //         File::put($statusFilePath, json_encode($statusData, JSON_PRETTY_PRINT));

    //         // Run migrations
    //         $modulePath = 'Modules/' . pathinfo($fileName, PATHINFO_FILENAME) . '/database/migrations';
    //         $migrateResult = \Artisan::call('migrate', ['--path' => $modulePath]);
    //         if ($migrateResult !== 0)
    //         {
    //             return response()->json(['success' => false, 'message' => 'Migration failed.']);
    //         }

    //         // Save the activated plugins to settings
    //         $activatedPlugins = Plugins::where('status', true)->pluck('filename')->toArray();

    //         Setting::add('plugin_activation', json_encode($activatedPlugins), 'array');

    //         return response()->json(['success' => true, 'message' => 'Plugin has been activated successfully.']);
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    //     }
    // }

    public function deactivate(Request $request, $id)
    {
        try {
            $plugin = Plugins::findOrFail($id);
            Artisan::call('optimize:clear');

            $fileName = $plugin->filename;
            $plugin->update(['status' => 0]);

            // Update modules_statuses.json
            $statusFilePath = base_path().'/modules_statuses.json';
            if (!File::exists($statusFilePath)) {
                File::put($statusFilePath, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
            }
            $statusData = json_decode(File::get($statusFilePath), true);
            $moduleName = pathinfo($fileName, PATHINFO_FILENAME);

            $statusData[$moduleName] = false;

            File::put($statusFilePath, json_encode($statusData, JSON_PRETTY_PRINT));

            // Save the activated plugins to settings
            $activatedPlugins = Plugins::where('status', true)->pluck('filename')->toArray();

            Setting::add('plugin_activation', json_encode($activatedPlugins), 'array');

            return response()->json(['success' => true, 'message' => 'Plugin has been deactivated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }


    public function delete(Request $request, $id)
    {
        try {
            $plugin = Plugins::findOrFail($id);
            Artisan::call('optimize:clear');

            $fileName = $plugin->filename;

            $plugin->update([
                'status' => 0,
                'deleted_by' => auth()->user()->id,
                'deleted_at' => Carbon::now()
            ]);

            // Update modules_statuses.json
            $statusFilePath = base_path().'/modules_statuses.json';
            if (!File::exists($statusFilePath)) {
                File::put($statusFilePath, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
            }
            $statusData = json_decode(File::get($statusFilePath), true);
            $moduleName = pathinfo($fileName, PATHINFO_FILENAME);

            $statusData[$moduleName] = false;

            File::put($statusFilePath, json_encode($statusData, JSON_PRETTY_PRINT));

            ### removed plugin folder
            $dirname = env('PLUGIN_PATH').'/Modules/' . $fileName;
            $r = self::rrmdir($dirname);

            ### removed uploaded zip file
            $dir2 = storage_path('app/public/plugins/' . $fileName . '.zip');
            // $dir2 = env('PLUGIN_PATH').'/storage/app/public/plugins/' . $fileName.'.zip';
            (!empty($dir2)) && $r = unlink($dir2);

            // Save the activated plugins to settings
            $activatedPlugins = Plugins::where('status', true)->pluck('filename')->toArray();

            Setting::add('plugin_activation', json_encode($activatedPlugins), 'array');

            return response()->json(['success' => true, 'message' => 'Plugin has been deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $plugin = Plugins::where('old_plugin_id',$id)->first();
            Artisan::call('optimize:clear');

            Plugins::find($id)->update([
                'status' => 0,
                'deleted_by' => auth()->user()->id,
                'deleted_at' => Carbon::now()
            ]);

            $fileName = $plugin->filename;
            $filePath = storage_path('app/public/plugins/' . $fileName . '.zip');

            // Check if the file exists
            if (!file_exists($filePath)) {
                return response()->json(['success' => false, 'message' => 'File not found.']);
            }

            // Extract the ZIP file
            $zip = new ZipArchive;

            if ($zip->open($filePath) !== TRUE) {
                return response()->json(['success' => false, 'message' => 'Failed to open the ZIP file.']);
            }
            // $path = env('PLUGIN_PATH').'/Modules/';
            $path = base_path('Modules/');

            if (!$zip->extractTo($path)) {
                $zip->close();
                return response()->json(['success' => false, 'message' => 'Failed to extract the ZIP file.']);
            }
            $zip->close();
            $plugin->update([
                'status' => !$plugin->status,
                'prev_plugin_id' => $plugin->old_plugin_id,
                'old_plugin_id' => NULL
            ]);

            // Update modules_statuses.json
            $statusFilePath = base_path().'/modules_statuses.json';
            if (!File::exists($statusFilePath)) {
                File::put($statusFilePath, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
            }
            $statusData = json_decode(File::get($statusFilePath), true);
            $moduleName = pathinfo($fileName, PATHINFO_FILENAME);

            $statusData[$moduleName] = $plugin->status;

            File::put($statusFilePath, json_encode($statusData, JSON_PRETTY_PRINT));

            // Run migrations
            $modulePath = 'Modules/' . pathinfo($fileName, PATHINFO_FILENAME) . '/database/migrations';
            $migrateResult = \Artisan::call('migrate', ['--path' => $modulePath]);
            if ($migrateResult !== 0)
            {
                return response()->json(['success' => false, 'message' => 'Migration failed.']);
            }

            // Save the activated plugins to settings
            $activatedPlugins = Plugins::where('status', true)->pluck('filename')->toArray();

            Setting::add('plugin_activation', json_encode($activatedPlugins), 'array');

            return response()->json(['success' => true, 'message' => 'plugin updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    public function changeLogs(Request $request,$id)
    {
        try {
            // $currentPlugin = Plugins::findOrFail($id);
            $latestPlugin = Plugins::where('old_plugin_id',$id)->pluck('description')->first();
            return response()->json([
                    'success' => true,
                    'data' => $latestPlugin
                ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

}
