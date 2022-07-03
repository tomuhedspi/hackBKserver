<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCharRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CharCollection;
use App\Http\Resources\CharResource;
use App\Models\Char;
use App\Models\Comment;
use Illuminate\Http\Request;

class CharController extends Controller
{
    public function index (Request $request)
    {
        $chars = Char::with('comments.interactive');
        if ($request->search) {
            $search = $request->search;
            $chars->where('type', Char::WORD)->where(function ($q) use ($search) {
                $q->where('word', 'like', "%$search%")->orWhere('reading', 'like', "%$search%");
            });
        }

        if ($request->search_kanji) {
            $search = $request->search_kanji;
            $chars->where('type', Char::KANJI)->where(function ($q) use ($search) {
                $q->where('word', 'like', "%$search%")->orWhere('reading', 'like', "%$search%");
            });
        }

        if ($request->book) {
            $chars->where('book', $request->book);
        }

        if ($request->type) {
            $chars->where('type', $request->type);
        }

        $response = [
            'status' => 200,
            'data' => new CharCollection($chars->paginate(20))
        ];

        return response()->json($response, 200);
    }

    public function storeComment($id, Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'author_name' => 'required|max: 30',
            'content' => 'required|max: 225'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $comment = Comment::create([
            'author_name' => $request->author_name,
            'content' => $request->content,
            'char_id' => $id
        ]);

        if ($comment) {
            return response()->json([
                'status' => 201
            ], 201);
        }
        return response()->json([
            'status' => 500
        ], 500);
    }

    public function store (Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'word' => 'required|max:255',
            'reading' => 'required|max:255',
            'note' => 'required|max:255',
            'book' => 'nullable|max:255',
            'meaning' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $char = Char::create($request->all());
        if ($char) {
            return response()->json([
                'status' => 201
            ], 201);
        }
        return response()->json([
            'status' => 500
        ], 500);
    }

    public function books ()
    {
        $books = Char::whereNotNull('book')->pluck('book')->unique()->values();
        return response()->json([
            'status' => 200,
            'data' => $books
        ], 200);
    }

    public function show ($id)
    {
        $char = Char::with('comments.interactive')->find($id);
        if ($char) {
            $response = [
                'status' => 200,
                'data' => new CharResource($char)
            ];
        } else {
            $response = [
                'status' => 404,
                'data' => []
            ];
        }
        return response()->json($response, 200);
    }
}
