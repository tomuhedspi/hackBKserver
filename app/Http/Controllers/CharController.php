<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCharRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CharCollection;
use App\Http\Resources\CharResource;
use App\Models\Char;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CharController extends Controller
{
    public function index(Request $request)
    {
        $chars = Char::with('comments.interactive');
        $search = $request->search;
        $type = $request->type;

        // Luôn filter theo type nếu có
        if ($type !== null && $type !== '') {
            $chars->where('type', $type);
        }
        if ($type == Char::KANJI && (empty($search) || trim($search) === '')) {
            $search = $request->search_kanji;
        }

        if ($search && $type == Char::KANJI) {
                if ($this->isJapanese($search)) {
                    // Nếu input là tiếng Nhật, tách từng ký tự và tìm theo word IN (...)
                    $charsArray = preg_split('//u', $search, -1, PREG_SPLIT_NO_EMPTY);
                    $chars->where(function ($q) use ($charsArray) {
                        foreach ($charsArray as $char) {
                            $q->orWhereRaw("BINARY `word` = ?", [$char]);
                        }
                    });
                } else {
                    // Search cho Kanji: tìm trên cả word (ký tự) và meaning (nghĩa tiếng Việt)
                    $searchKhongDau = $this->stripVietnameseAccents($search);

                    $chars->where(function ($q) use ($search, $searchKhongDau) {
                        $q->orWhere('word', $search)
                        ->orWhere('meaning', $search)
                        ->orWhere('word', 'like', "%$search%")
                        ->orWhere('reading', $search)
                        ->orWhere('reading', 'like', "$search%")
                        ->orWhere('meaning', 'like', "$search %")
                        ->orWhere('meaning', 'like', " $search %")
                        ->orWhere('word_khongdau', $searchKhongDau)
                        ->orWhere('meaning_khongdau', $searchKhongDau)
                        ->orWhere('word_khongdau', 'like', "%$searchKhongDau%")
                        ->orWhere('meaning_khongdau', 'like', "% $searchKhongDau %");
                    });

                    $chars->orderByRaw(
                        "CASE
                            WHEN word = ? THEN 0
                            WHEN meaning = ? THEN 1
                            WHEN word LIKE ? THEN 2
                            WHEN word LIKE ? THEN 3
                            WHEN word LIKE ? THEN 4
                            WHEN meaning LIKE ? THEN 5
                            WHEN meaning LIKE ? THEN 6
                            WHEN meaning LIKE ? THEN 7
                            WHEN word_khongdau = ? THEN 8
                            WHEN meaning_khongdau = ? THEN 9
                            WHEN word_khongdau LIKE ? THEN 10
                            WHEN meaning_khongdau LIKE ? THEN 11
                            ELSE 12
                        END",
                        [
                            $search,
                            $search,
                            "$search %",      // đầu từ
                            "% $search",      // cuối từ
                            "% $search %",    // ở giữa
                            "$search %",      // meaning đầu từ
                            "% $search",      // meaning cuối từ
                            "% $search %",    // meaning ở giữa
                            $searchKhongDau,
                            $searchKhongDau,
                            "%$searchKhongDau%",
                            "%$searchKhongDau%"
                        ]
                    );
                }
        }
        elseif ($search && in_array($type, [Char::WORD, Char::ENGLISH])) {
            // Search cho từ vựng tiếng Nhật và tiếng Anh
            $searchKhongDau = $this->stripVietnameseAccents($search);

            $chars->where(function ($q) use ($search, $searchKhongDau) {
                $q->orWhere('word', $search)
                  ->orWhere('meaning', $search)
                  ->orWhere('word', 'like', "%$search%")
                  ->orWhere('meaning', 'like', "$search %")
                  ->orWhere('reading', $search)
                  ->orWhere('reading', 'like', "$search%")
                  ->orWhere('meaning', 'like', " $search %")
                  ->orWhere('word_khongdau', $searchKhongDau)
                  ->orWhere('meaning_khongdau', $searchKhongDau)
                  ->orWhere('word_khongdau', 'like', "%$searchKhongDau%")
                  ->orWhere('meaning_khongdau', 'like', "% $searchKhongDau %");
            });

            $chars->orderByRaw(
                "CASE
                    WHEN word = ? THEN 0
                    WHEN meaning = ? THEN 0
                    WHEN word LIKE BINARY ? THEN 1
                    WHEN meaning LIKE BINARY ? THEN 1
                    ELSE 2
                END ASC, 
                LEAST(
                    IF(LOCATE(?, word) > 0, LENGTH(word), 9999),
                    IF(LOCATE(?, meaning) > 0, LENGTH(meaning), 9999)
                ) ASC,
                id ASC",
                [
                    $search,
                    $search,
                    "%$search%",
                    "%$search%",
                    $search,
                    $search
                ]
            );
        }

        // Các filter khác
        if ($request->book) {
            $chars->where('book', $request->book);
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
            'pos' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $data = $request->all();

        // Xử lý meaning_khongdau: bỏ dấu, thay dấu ngắt câu thành space, thêm space đầu/cuối
        $meaning_khongdau = $this->stripVietnameseAccents($data['meaning'] ?? '');
        $meaning_khongdau = preg_replace('/[.,;:!?()\[\]{}"\'\-_…\/\\\|<>]/u', ' ', $meaning_khongdau);
        $meaning_khongdau = preg_replace('/\s+/u', ' ', $meaning_khongdau);
        $meaning_khongdau = ' ' . trim($meaning_khongdau) . ' ';

        $data['meaning_khongdau'] = $meaning_khongdau;

        $char = Char::create($data);
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
    return preg_match('/['
        // Kanji tiêu chuẩn và mở rộng
        . '\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}\x{2A700}-\x{2B73F}\x{2B740}-\x{2B81F}\x{2B820}-\x{2CEAF}'
        
        // Kanji tương thích và bổ sung
        . '\x{F900}-\x{FAFF}\x{2F800}-\x{2FA1F}'
        
        // Hiragana/Katakana/Katakana Phonetic Extensions
        . '\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31F0}-\x{31FF}'
        
        // Bộ thủ CJK (⺈⺄⺼⻖⺗⺌⻗⺕...)
        . '\x{2E80}-\x{2EFF}\x{2F00}-\x{2FDF}'
        
        // Ký tự số/ký hiệu tiếng Nhật
        . '\x{3000}-\x{303F}\x{3021}\x{30A0}\x{30FB}'
        
        // Các ký tự đặc biệt khác
        . '\x{4DC0}-\x{4DFF}'  // Hexagram symbols
        . '\x{3190}-\x{319F}'  // Kanbun
        
        // Các ký tự riêng lẻ quan trọng
        . '\x{9FB7}'  // 龷
        . '\x{706C}'  // 灬
        . '\x{200A4}' // 𠂤
        . '\x{27607}' // 𧘇
        . '\x{4E28}'  // 丨
        . '\x{5EFF}'  // 廿
        . '\x{5F00}'  // 开
        . '\x{9FBC}'  // 龼
        . '\x{9FCD}'  // 龍
        . '\x{9FD5}'  // 龕
        . '\x{2EBE}'  // ⺾
        . '\x{2ECF}'  // ⻏
        . '\x{2ED8}'  // ⻘
        . '\x{2EE0}'  // ⻠
        
        // Bổ sung thêm các ký tự đặc biệt khác từ danh sách của bạn
        . '\x{20BB7}' // 𠮷
        . '\x{2A6D6}' // 𪛖
        . '\x{2B740}' // 𫝀
        . ']/u', $lang);
}

// Thêm hàm loại bỏ dấu tiếng Việt
private function stripVietnameseAccents($str) {
    $accents_arr = [
        'a'=>'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd'=>'đ',
        'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i'=>'í|ì|ỉ|ĩ|ị',
        'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y'=>'ý|ỳ|ỷ|ỹ|ỵ',
        'A'=>'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ằ|Ẳ|Ẵ|Ặ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
        'D'=>'Đ',
        'E'=>'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
        'I'=>'Í|Ì|Ỉ|Ĩ|Ị',
        'O'=>'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
        'U'=>'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
        'Y'=>'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
    ];
    foreach($accents_arr as $nonAccent=>$accent){
        $str = preg_replace("/($accent)/i", $nonAccent, $str);
    }
    return $str;
}

    // API: Kiểm tra danh sách từ chưa có trong database
    public function checkMissingWords(Request $request)
    {
        $wordsRaw = $request->input('words', '');
        $type = $request->input('type');
        // Tách từ theo các ký tự phân cách: dấu chấm, phẩy, xuống dòng, gạch ngang, ngoặc đơn, ngoặc kép
        $words = preg_split('/[\.,\n\r\-\(\)"\'\,]+/u', $wordsRaw);
        $words = array_map('trim', $words);
        $words = array_filter($words, function($w) { return $w !== ''; });
        $words = array_unique($words);
        if (empty($words) || $type === null) {
            return response()->json([
                'status' => 400,
                'message' => 'Missing words or type'
            ], 400);
        }
        // Lấy các từ đã có trong DB
        $existing = \DB::table('chars')
            ->where('type', $type)
            ->whereIn('word', $words)
            ->pluck('word')
            ->toArray();
        // Trả về các từ chưa có
        $missing = array_values(array_diff($words, $existing));
        return response()->json([
            'status' => 200,
            'missing' => $missing
        ]);
    }

    public function multiExactSearch(Request $request)
    {
        $search = $request->input('search', '');
        $mode = strtolower($request->input('mode', 'all'));
        $perPage = intval($request->input('per_page', 20));
        $page = intval($request->input('page', 1));

        $keywords = [];
        if ($search) {
            $keywords = preg_split('/[,\n\r\t\.\:\;]/u', $search);
            $keywords = array_map('trim', $keywords);
            $keywords = array_filter($keywords, function($w) { return $w !== ''; });
            $keywords = array_unique($keywords);
        }

        $items = [];
        if (!empty($keywords)) {
            if ($mode === 'all') {
                $chars = Char::with('comments.interactive');
                $chars->whereIn('word', $keywords);
                if ($request->type) $chars->where('type', $request->type);
                if ($request->book) $chars->where('book', $request->book);
                $chars->orderBy('word');
                $paginator = $chars->paginate($perPage);
                $result = new CharCollection($paginator);
            } else {
                $query = Char::query()->with('comments.interactive');
                if ($request->type) $query->where('type', $request->type);
                if ($request->book) $query->where('book', $request->book);
                foreach ($keywords as $kw) {
                    if ($mode === 'oldest') {
                        $item = (clone $query)->where('word', $kw)->orderBy('id', 'asc')->first();
                    } else { // newest
                        $item = (clone $query)->where('word', $kw)->orderBy('id', 'desc')->first();
                    }
                    if ($item) $items[] = $item;
                }
                // Phân trang cho mảng $items
                $total = count($items);
                $pagedItems = array_slice($items, ($page - 1) * $perPage, $perPage);
                $paginator = new LengthAwarePaginator(
                    $pagedItems,
                    $total,
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
                $result = new CharCollection($paginator);
            }
        } else {
            // Không có keyword, trả về tất cả (phân trang)
            $chars = Char::with('comments.interactive');
            if ($request->type) $chars->where('type', $request->type);
            if ($request->book) $chars->where('book', $request->book);
            $paginator = $chars->paginate($perPage);
            $result = new CharCollection($paginator);
        }

        return response()->json([
            'status' => 200,
            'data' => $result
        ], 200);
    }
}
