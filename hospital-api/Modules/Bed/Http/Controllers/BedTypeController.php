<?php

namespace Modules\Bed\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Bed\Models\BedType;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Validator;
use Modules\Bed\Http\Requests\BedRequest;

class BedTypeController extends Controller
{
    protected $module_name = 'bed-type';
    protected $module_title = 'messages.bed_type';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = [
            'status' => $request->get('status'),
        ];

        $module_action = 'List';
        $module_name = $this->module_name;
        $module_title = $this->module_title;
        
        return view('bed::bed_type.index', compact('module_action', 'module_name', 'module_title', 'filter'));
    }

    /**
     * Get data for DataTables
     */
public function index_data(Request $request)
{   
    $query = BedType::query();

    return DataTables::of($query)
        ->addColumn('check', function ($row) {
            return '<input type="checkbox" class="select-table-row" id="datatable-row-' . $row->id . '" name="datatable_ids[]" value="' . $row->id . '" data-type="bed" onclick="dataTableRowCheck(' . $row->id . ')">';
        })
        ->addColumn('action', function ($bed) {
            return view('bed::bed_type.action', compact('bed'))->render();
        })
        ->editColumn('type', function ($row) {
            return ucfirst($row->type ?? 'N/A');
        })
        ->editColumn('description', function ($row) {
            return $row->description ? \Str::limit(strip_tags($row->description), 50) : 'N/A';
        })
        ->editColumn('updated_at', function ($row) {
            return date('Y-m-d H:i:s', strtotime($row->updated_at));
        })
        ->filter(function ($query) use ($request) {
            if ($request->has('search') && !empty($request->search['value'])) {
                $search = $request->search['value'];
                $query->where(function ($q) use ($search) {
                    $q->where('type', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
        })
        ->rawColumns(['check', 'action'])
        ->make(true);
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $module_action = 'Create';
        $module_name = $this->module_name;
        $module_title = $this->module_title;

        return view('bed::bed_type.create', compact('module_action', 'module_name', 'module_title'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BedRequest $request)
    {

        try {
            $data = $request->validated();

            $bed = BedType::create([
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
            ]);

            $message = __('messages.create_form', ['form' => __('messages.bed_type')]);

            return $request->wantsJson()
                ? response()->json(['status' => true, 'message' => $message, 'data' => $bed])
                : redirect()->route('backend.' . $this->module_name . '.index')->with('success', $message);

        } catch (\Exception $e) {
            return $request->wantsJson()
                ? response()->json(['status' => false, 'message' => $e->getMessage()], 500)
                : redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request)
    {
        $beddata = BedType::findOrFail($id);
        $module_action = 'Show';
        $module_name = $this->module_name;
        $module_title = $this->module_title;

        if ($request->is('api/*') || $request->ajax()) {
            $data[] = [
                'id' => $beddata->id,
                'type' => $beddata->type,
                'description' => $beddata->description, // ✅ Add this line
            ];

            $message = __('messages.show', ['name' => __('messages.bed_type')]);
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data,
            ]);
        }

        return view('bed::bed_type.show', compact('module_action', 'module_name', 'module_title', 'beddata'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $beddata = BedType::findOrFail($id);
            $module_action = 'Edit';
            $module_name = $this->module_name;
            $module_title = $this->module_title;

            return view('bed::bed_type.edit', compact('module_action', 'module_name', 'module_title', 'beddata'));
            
        } catch (\Exception $e) {
            return redirect()->route('backend.' . $this->module_name . '.index')
                           ->with('error', 'Record not found or error occurred');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BedRequest $request, $id)
    {
        try {
            $bed = BedType::findOrFail($id);
            $data = $request->validated();

            $bed->update([
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
            ]);

            $message = __('messages.update_form', ['form' => __('messages.bed_type')]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => true,
                    'message' => $message,
                    'data' => $bed,
                ]);
            }

            // Ensure session message is set
            session()->flash('success', $message);
            return redirect()->route('backend.' . $this->module_name . '.index');

        } catch (\Exception $e) {
            return $request->wantsJson()
                ? response()->json([
                    'status' => false,
                    'message' => 'Server error: ' . $e->getMessage(),
                ], 500)
                : redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Get list data for select2 dropdown
     */
    public function index_list(Request $request)
    {
        $term = trim($request->q);

        $query_data = BedType::orderByDesc('id')->where(function ($q) use ($term) {
            if (!empty($term)) {
                $q->where('type', 'LIKE', "%$term%");
            }
        })->get();

        $data = [];
        if(isset($request->typeId) && $request->typeId != '') {
            $query_data = $query_data->where('id', $request->typeId);
        }   

        foreach ($query_data as $row) {
            $data[] = [
                'id' => $row->id,
                'type' => $row->type,
                'description' => $row->description, // ✅ Add this line
            ];
        }
        $message = __('messages.list_form', ['name' => __('messages.bed')]);
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ]);


    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $bed = BedType::findOrFail($id);
            $bed->delete();
            $message = __('messages.delete_form', ['form' => __('messages.bed_type')]);

            if (request()->is('api/*') || request()->ajax()) {
                return response()->json([
                    'status' => true,
                    'message' => $message,
                    'redirect' => route('backend.' . $this->module_name . '.index')
                ]);
            }

            return redirect()->route('backend.' . $this->module_name . '.index')->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Delete Error: ' . $e->getMessage());

            $message = 'Something went wrong. Please try again.';

            if (request()->is('api/*') || request()->ajax()) {
                return response()->json(['status' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }
    }

    /**
     * Handle bulk actions
     */
    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;

        $message = '';
        
        try {
            switch ($actionType) {
                case 'delete':
                    BedType::whereIn('id', $ids)->delete();
                    $message = __('messages.bulk_delete_form', ['form' => __('messages.bed_type')]);
                    break;
                    
                default:
                    return response()->json(['status' => false, 'message' => 'Action not found']);
            }

            return response()->json(['status' => true, 'message' => $message]);
        } catch (\Exception $e) {
            \Log::error('Bulk Action Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Something went wrong']);
        }
    }

    /**
     * Handle restore and force delete actions
     */
    public function action(Request $request, $id)
    {
        // Soft delete actions removed
        return redirect()->back()->with('error', 'Restore and force delete are not supported.');
    }

    /**
     * Update status of the resource
     */
    public function update_status(Request $request, $id)
    {
        try {
            $bed = BedType::findOrFail($id);
            $bed->status = $request->status;
            $bed->save();

            $message = 'Status updated successfully';

            if ($request->ajax()) {
                return response()->json([
                    'status' => true,
                    'message' => $message
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Status Update Error: ' . $e->getMessage());
            
            $message = 'Something went wrong';

            if ($request->ajax()) {
                return response()->json(['status' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }
    }

    /**
     * Get price for a specific bed type
     */
    public function getPrice($id)
    {
        try {
            $bedType = BedType::findOrFail($id);
            
            // Get the first bed master for this bed type to get the price
            $bedMaster = \Modules\Bed\Models\BedMaster::where('bed_type_id', $id)
                ->where('status', true)
                ->first();
            
            $price = $bedMaster ? $bedMaster->charges : 0;
            
            return response()->json([
                'success' => true,
                'price' => $price,
                'bed_type' => $bedType->type
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting bed type price: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error getting bed type price',
                'price' => 0
            ], 500);
        }
    }
}