<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\StringUtils;

class JapaneseHintController extends Controller
{
    private $dictionaryLoader;
    private const SEPARATE_CHARACTER = ' ';
    private const FIRST_WORD_SEPARATE_CHARACTER = ' -';
    private const SECOND_WORD_SEPARATE_CHARACTER = '- ';
    private $DICT_JAPANESE_VIETNAMESE;
    private $DICT_JAPANESE_SIMILAR_PRONUNCIATION;

    public function __construct()
    {
        $this->dictionaryLoader = new DictionaryLoader();
        $this->loadDictData();
    }

    private function loadDictData()
    {
        // Load the dictionary data from cache
        $dictionaries = $this->dictionaryLoader->loadDictDataForJapaneseHint();
        $this->DICT_JAPANESE_VIETNAMESE = $dictionaries['DICT_JAPANESE_VIETNAMESE'];
        $this->DICT_JAPANESE_SIMILAR_PRONUNCIATION = $dictionaries['DICT_JAPANESE_SIMILAR_PRONUNCIATION'];
    }

    public function getPhoneticHints(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'reading' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([]);
        }

        $phonetic = $request->reading;
        $phonetic = $this->simplifySound($phonetic);
        $hint = $this->getSentences($phonetic);

        return response()->json([
            'status' => 200,
            'data' => $hint
        ], 200);
    }

    private function getSentences(string $phonetic): array
    {
        $smallPartsArray = $this->getVietNamesePart($phonetic);
        $wordsArray = StringUtils::combineStrings($smallPartsArray,self::SEPARATE_CHARACTER);
        return $wordsArray;
    }

    private function getVietNamesePart(string $phonetic): array
    {
        $hintCharacters = [];
        $splited = explode(self::SEPARATE_CHARACTER, $phonetic);
        for ($i = 0; $i < count($splited); $i++) {
            $currentChar = $splited[$i];
            if (mb_strlen($currentChar, 'UTF-8') == 0) {
                continue;
            }
            $hintCharacters[] = $this->DICT_JAPANESE_VIETNAMESE[$currentChar] ?? [];
        }
        return $hintCharacters;
    }

    private function simplifySound(string $phonetic): string
    {
        $result = $this->convertKatakanaToHiragana($phonetic);
        $result = $this->getSimilarPronunciation($result);
        $result = $this->separateIntoSingleWord($result);
        return $result;
    }
    private function convertKatakanaToHiragana($string) {
        // Bảng map Katakana -> Hiragana
        $katakana = explode(' ', 'ァ ア ィ イ ゥ ウ ェ エ ォ オ カ ガ キ ギ ク グ ケ ゲ コ ゴ サ ザ シ ジ ス ズ セ ゼ ソ ゾ タ ダ チ ヂ ッ ツ ヅ テ デ ト ド ナ ニ ヌ ネ ノ ハ バ パ ヒ ビ ピ フ ブ プ ヘ ベ ペ ホ ボ ポ マ ミ ム メ モ ャ ヤ ュ ユ ョ ヨ ラ リ ル レ ロ ヮ ワ ヰ ヱ ヲ ン ヴ ヵ ヶ');
        $hiragana = explode(' ', 'ぁ あ ぃ い ぅ う ぇ え ぉ お か が き ぎ く ぐ け げ こ ご さ ざ し じ す ず せ ぜ そ ぞ た だ ち ぢ っ つ づ て で と ど な に ぬ ね の は ば ぱ ひ び ぴ ふ ぶ ぷ へ べ ぺ ほ ぼ ぽ ま み む め も ゃ や ゅ ゆ ょ よ ら り る れ ろ ゎ わ ゐ ゑ を ん ゔ ゕ ゖ');
        
        return str_replace($katakana, $hiragana, $string);
    }

    private function getSimilarPronunciation(string $phonetic): string
    {
        $result = $phonetic;
        foreach ($this->DICT_JAPANESE_SIMILAR_PRONUNCIATION as $key => $value) {
            $individualSound = self::FIRST_WORD_SEPARATE_CHARACTER . (string)$value[0] . self::SECOND_WORD_SEPARATE_CHARACTER;
            $result = str_replace($key, $individualSound, $result);
        }
        return $result;
    }

    private function separateIntoSingleWord(string $phonetic): string
    {
        $multiSounds = $phonetic;
        $individualSound = [];

        foreach ($this->DICT_JAPANESE_VIETNAMESE as $key => $value) {
            $position = mb_strpos($multiSounds, $key, 0, 'UTF-8');

            while ($position !== false) {
                $individualSound[$position] = $key;
                $multiSounds = $this->replaceStringAtPosition($multiSounds, $key, $position);
                $position = mb_strpos($multiSounds, $key, 0, 'UTF-8');
            }
        }

        // combine the individual sounds into a single string
        $result = $this->combineStringsFromIndividualSound($individualSound);

        return $result;
    }

    private function replaceStringAtPosition(string $string, string $oldSubstring, int $position): string
    {
        $newSubstring = $this->createSpaceString($oldSubstring);
        $result = "";
        $left = mb_substr($string, 0, $position, 'UTF-8');
        $right = mb_substr($string, $position + mb_strlen($oldSubstring, 'UTF-8'), null, 'UTF-8');
        $result = $left . $newSubstring . $right;

        return $result;
    }
    private function createSpaceString($a) {
        // Lấy độ dài của chuỗi a
        $length =  mb_strlen($a, 'UTF-8');
        // Tạo chuỗi b gồm $length ký tự space
        return str_repeat(' ', $length);
    }

    private function combineStringsFromIndividualSound(array $array): string
    {
        $result = "";
        // Sắp xếp mảng theo key
        ksort($array);
        foreach ($array as $key => $value) {
            $result .= self::SEPARATE_CHARACTER . $value ;
        }
        return $result;
    }

}