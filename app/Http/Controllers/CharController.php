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
            if($this->isJapanese($search)){
                $chars->where('type', Char::WORD)->where(function ($q) use ($search) {
                    $q->where('word', 'like', "%$search%")->orWhere('reading', 'like', "%$search%");
                });
            }else{
                
                $chars->where('type', Char::WORD)->where(function ($q) use ($search) {
                    $startWithWord = $search . " ";
                    $endWithWord = " " . $search ;
                    $exactWord = $search ;
                    $middleWord = " " .$search . " ";
                    $commaLeftWord = "," . $search ;
                    $commaRightWord = $search . ",";
                    $bracketLeftWord = "(" . $search ;
                    $bracketRightWord = $search . ")";
                    $q->where('meaning', 'like', "$startWithWord%")
                    ->orWhere('meaning', 'like', "%$endWithWord")
                    ->orWhere('meaning', 'like', "$exactWord")
                    ->orWhere('meaning', 'like', "%$middleWord%")
                    
                    ->orwhere('meaning', 'like', "%$commaLeftWord%")
                    ->orWhere('meaning', 'like', "%$commaRightWord%")
                    
                    ->orwhere('meaning', 'like', "%$bracketLeftWord%")
                    ->orWhere('meaning', 'like', "%$bracketRightWord%")
                    ;
                });
            }
        }

        if ($request->search_kanji) {
            $search = $request->search_kanji;
            $chars->where('type', Char::KANJI)->where(function ($q) use ($search) {
                $q->where('word', 'like', "$search%")->orWhere('reading', 'like', "$search%")->orWhere('meaning', 'like', "$search%");
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

    public function show ($id, Request $request)
    {
        $char = Char::with('comments.interactive')->find($id);
        if(empty($char->book)){
            $nextChar = Char::where('id', '>', $id)->where('type', $char->type);
            $prevChar = Char::where('id', '<', $id)->where('type', $char->type)->orderByDesc('id');
        }else{
            $nextChar = Char::where('id', '>', $id)->where('type', $char->type)->where('book', $char->book);
            $prevChar = Char::where('id', '<', $id)->where('type', $char->type)->where('book', $char->book)->orderByDesc('id');
        }
       
        if ($request->book) {
            $nextChar->where('book', $char->book);
            $prevChar->where('book', $char->book);
        }
        if ($char) {
            $response = [
                'status' => 200,
                'data' => new CharResource($char),
                'next' => @$nextChar->first()->id ?: 0,
                'prev' => @$prevChar->first()->id ?: 0,
            ];
        } else {
            $response = [
                'status' => 404,
                'data' => []
            ];
        }
        return response()->json($response, 200);
    }
    private function isJapanese($lang) {
        return preg_match('/[\x{4E00}-\x{9FBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $lang);
    }
}
