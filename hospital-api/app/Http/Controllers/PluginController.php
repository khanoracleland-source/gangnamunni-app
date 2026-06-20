<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PluginController extends Controller
{
    public function status(Request $request)
    {
        $status = checkPlugin('Pharma'); // uses your helper
        return response()->json(['status' => $status]);
    }
} 