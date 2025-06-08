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
    public function index(Request $request)
    {
        $chars = Char::with('comments.interactive');
        $search = $request->search;
        if (($request->type == Char::WORD || $request->type == Char::KANJI) && $search) {
            if ($this->isJapanese($search)) {
                $chars->where('type', Char::WORD)->where(function ($q) use ($search) {
                    $q->where('word', 'like', "%$search%")->orWhere('reading', 'like', "%$search%");
                });
            } else {
                $chars->where('type', Char::WORD)->where(function ($q) use ($search) {
                    $startWithWord = $search . " ";
                    $endWithWord = " " . $search;
                    $exactWord = $search;
                    $middleWord = " " . $search . " ";
                    $commaLeftWord = "," . $search;
                    $commaRightWord = $search . ",";
                    $bracketLeftWord = "(" . $search;
                    $bracketRightWord = $search . ")";
                    $q->where('meaning', 'like', "$startWithWord%")
                        ->orWhere('meaning', 'like', "%$endWithWord")
                        ->orWhere('meaning', 'like', "$exactWord")
                        ->orWhere('meaning', 'like', "%$middleWord%")
                        ->orWhere('meaning', 'like', "%$commaLeftWord%")
                        ->orWhere('meaning', 'like', "%$commaRightWord%")
                        ->orWhere('meaning', 'like', "%$bracketLeftWord%")
                        ->orWhere('meaning', 'like', "%$bracketRightWord%");
                });
            }
        }
        if ($request->type == Char::ENGLISH && $search) {
            $chars->where('word', 'like', "$search%");
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

        // Add sorting logic here
        if ($search) {
            $chars->orderByRaw("CASE WHEN word = ? THEN 0 ELSE 1 END", [$search]);
        }

        $response = [
            'status' => 200,
            'data' => new CharCollection($chars->paginate(20))
        ];

        return response()->json($response, 200);
    }

    public function exactSearch(Request $request)
    {
        $chars = Char::with('comments.interactive');
        $search = $request->search;

        if ($search) {
            $chars->where('word', $search);
        }

        if ($request->search_kanji) {
            $search = $request->search_kanji;
            $chars->where('type', Char::KANJI)->where(function ($q) use ($search) {
                $q->where('word', $search)->orWhere('reading', $search)->orWhere('meaning', $search);
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

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'word' => 'required|max:255',
            'reading' => 'required|max:255',
            'note' => 'required|max:255',
            'book' => 'nullable|max:255',
            'meaning' => 'required|max:255',
            'created_by' => 'nullable|max:255',
            'child' => 'nullable|max:255',
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

    public function books()
    {
        $books = Char::whereNotNull('book')->pluck('book')->unique()->values();
        return response()->json([
            'status' => 200,
            'data' => $books
        ], 200);
    }

    public function show($id, Request $request)
    {
        $char = Char::with('comments.interactive')->find($id);
        if (empty($char)) {
            return response()->json([
                'status' => 404,
                'data' => []
            ], 404);
        }

        if (empty($char->book)) {
            $nextChar = Char::where('id', '>', $id)->where('type', $char->type);
            $prevChar = Char::where('id', '<', $id)->where('type', $char->type)->orderByDesc('id');
        } else {
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

    public function radicalsTree($id)
    {
        $visited = [];
        $edges = [];
        $nodes = [];
        $maxDepth = 10;

        $char = Char::find($id);
        if (!$char) {
            return response()->json([
                'status' => 404,
                'message' => 'Char not found'
            ], 404);
        }

        $edgeSet = [];
        $this->buildRadicalsTreeById($char, $edges, $nodes, $visited, 0, $maxDepth, $negativeId, $edgeSet);

        return response()->json([
            'nodes' => array_values($nodes),
            'links' => $edges
        ]);
    }

    private function buildRadicalsTreeById($char, &$edges, &$nodes, &$visited, $depth, $maxDepth, &$negativeId = 0, &$edgeSet = [])
    {
        if ($depth > $maxDepth) return;
        if (isset($visited[$char->id])) return;
        $visited[$char->id] = true;

        // Thêm node nếu chưa có
        if (!isset($nodes[$char->id])) {
            $nodes[$char->id] = [
                'id' => (string)$char->id,
                'label' => $char->word,
                'note' => $char->note,
                'reading' => $char->reading,
                'meaning' => $char->meaning
            ];
        }

        if (!$char->child) return;

        $length = mb_strlen($char->child, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $childWord = mb_substr($char->child, $i, 1, 'UTF-8');
            if ($childWord === '') continue;
            $childChar = Char::whereRaw("BINARY `word` = ?", [$childWord])
                ->where('type', Char::KANJI)
                ->first();

            if ($childChar) {
                if (!isset($nodes[$childChar->id])) {
                    $nodes[$childChar->id] = [
                        'id' => (string)$childChar->id,
                        'label' => $childChar->word,
                        'note' => $childChar->note,
                        'reading' => $childChar->reading,
                        'meaning' => $childChar->meaning
                    ];
                }
                $edgeKey = (string)$char->id . '-' . (string)$childChar->id;
                if (!isset($edgeSet[$edgeKey])) {
                    $edges[] = [
                        'source' => (string)$char->id,
                        'target' => (string)$childChar->id
                    ];
                    $edgeSet[$edgeKey] = true;
                }
                $this->buildRadicalsTreeById($childChar, $edges, $nodes, $visited, $depth + 1, $maxDepth, $negativeId, $edgeSet);
            } else {
                $negativeId = intval($negativeId) - 1;
                $virtualId = (string)$negativeId;
                if (!isset($nodes[$virtualId])) {
                    $nodes[$virtualId] = [
                        'id' => $virtualId,
                        'label' => $childWord,
                        'note' => '',
                        'reading' => '',
                        'meaning' => ''
                    ];
                }
                $edgeKey = (string)$char->id . '-' . $virtualId;
                if (!isset($edgeSet[$edgeKey])) {
                    $edges[] = [
                        'source' => (string)$char->id,
                        'target' => $virtualId
                    ];
                    $edgeSet[$edgeKey] = true;
                }
            }
        }
    }

    private function isJapanese($lang)
    {
        return preg_match('/[\x{4E00}-\x{9FBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $lang);
    }
}
