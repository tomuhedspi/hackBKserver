<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInteractiveRequest;
use App\Models\Interactive;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function interactive (StoreInteractiveRequest $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'status' => 'required',
            'device_fcm' => 'required'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $interactive = Interactive::updateOrCreate([
            'comment_id' => $id,
            'device_fcm' => $request->device_fcm
        ],
        [
            'status' => $request->status,
        ]);

        if ($interactive) {
            return response()->json([
                'status' => 201
            ], 201);
        }
        return response()->json([
            'status' => 500
        ], 500);
    }
}
