<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store (StoreReportRequest $request)
    {
        $validator = \Validator::make($request->all(), [
            'char' => 'required|max:255',
            'content' => 'required|max: 500'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $report = Report::create([
            'char' => $request->char,
            'content' => $request->content
        ]);

        if ($report) {
            return response()->json([
                'status' => 201
            ], 201);
        }
        return response()->json([
            'status' => 500
        ], 500);
    }
}
