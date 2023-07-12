<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Resources\UnitCollection;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function units ()
    {
        $units = Unit::all();
        return response()->json([
            'status' => 200,
            'data' => $units
        ], 200);
    }
}
