<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Char;
use League\Csv\Reader;
use App\Utils\StringUtils;

class EnglishHintController extends Controller
{
    private $dictionaryLoader;
    private const SEPARATE_CHARACTER = ' ';
    private const WORD_SEPARATE_CHARACTER = ' - ';
    private $DICT_FINAL_CONSONANT;
    private $DICT_INITIAL_CONSONANT;
    private $DICT_SINGLE_CONSONANT;
    private $DICT_VOWEL;
    private $DICT_STOP_SOUND;
    private $DICT_VIETNAMESE;

    public function __construct()
    {
        $this->dictionaryLoader = new DictionaryLoader();
        $this->loadDictData();
    }

    private function loadDictData()
    {
        // Load the dictionary data from cache
        $dictionaries = $this->dictionaryLoader->loadDictDataForEnglishHint();
        $this->DICT_FINAL_CONSONANT = $dictionaries['DICT_FINAL_CONSONANT'];
        $this->DICT_INITIAL_CONSONANT = $dictionaries['DICT_INITIAL_CONSONANT'];
        $this->DICT_SINGLE_CONSONANT = $dictionaries['DICT_SINGLE_CONSONANT'];
        $this->DICT_VOWEL = $dictionaries['DICT_VOWEL'];
        $this->DICT_STOP_SOUND = $dictionaries['DICT_STOP_SOUND'];
        $this->DICT_VIETNAMESE = $dictionaries['DICT_VIETNAMESE'];
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
        $phonetic = $this->setSpaceAddBetweenSound($phonetic);
        $sentencesArray = $this->getSentences($phonetic);
        $hint = $this->getVietNameseSentences($sentencesArray);

        return response()->json([
            'status' => 200,
            'data' => $hint
        ], 200);
    }

    private function setSpaceAddBetweenSound(string $phonetic): string
    {
        $spaceAdded = $this->replaceUnusedCharacter($phonetic);
        $spaceAdded = $this->addSeparateCharacterToStopSound($spaceAdded);
        $spaceAdded = $this->addSeparateCharacterToDiphthongs($spaceAdded);
        $spaceAdded = $this->addSeparateCharacterToShortVowel($spaceAdded);
        $spaceAdded = $this->addSeparateCharacterBetweenConsonant($spaceAdded);
        $spaceAdded = $this->normalizeSpaces($spaceAdded);
        return $spaceAdded;
    }

    private function replaceUnusedCharacter(string $phonetic): string
    {
        // Remove characters inside square brackets along with the brackets
        $phonetic = preg_replace('/\[.*?\]/', '', $phonetic);
        // Remove other unwanted characters
        return str_replace(['/', ','], '', $phonetic);
    }

    private function addSeparateCharacterToStopSound(string $phonetic): string
    {
        $spaceAdded = $phonetic;
        $stopSoundCharacter = $this->getFirstStopSoundValue();
        foreach ($this->DICT_STOP_SOUND as $key => $value) {
            $spaceAdded = str_replace($key, self::SEPARATE_CHARACTER . $stopSoundCharacter . self::SEPARATE_CHARACTER, $spaceAdded);
        }
        return $spaceAdded;
    }

    private function addSeparateCharacterToDiphthongs(string $phonetic): string
    {
        $spaceAdded = $phonetic;
        foreach ($this->DICT_VOWEL as $key => $value) {
            if (mb_strlen($key, 'UTF-8') >= 2) {
                $spaceAdded = str_replace($key, self::SEPARATE_CHARACTER . $key . self::SEPARATE_CHARACTER, $spaceAdded);
            }
        }
        return $spaceAdded;
    }

    private function addSeparateCharacterToShortVowel(string $phonetic): string
    {
        $spaceAdded = '';
        $splited = explode(self::SEPARATE_CHARACTER, $phonetic);
        foreach ($splited as $part) {
            //nếu phần hiện tại là dipthong thì bỏ qua
            if (mb_strlen($part, 'UTF-8') >= 2 && $this->isVowel($part)) {
                $spaceAdded .= self::SEPARATE_CHARACTER . $part . self::SEPARATE_CHARACTER;
                continue;
            }
            if ($this->isStopSound($part)) {
                $spaceAdded .= self::SEPARATE_CHARACTER . $part . self::SEPARATE_CHARACTER;
                continue;
            }
            //nếu phần hiện tại là nguyên âm đơn (vowl) thì thêm dấu cách vào trước và sau
            for ($i = 0; $i < mb_strlen($part, 'UTF-8'); $i++) {
                $currentChar = mb_substr($part, $i, 1, 'UTF-8');
                $prevChar = mb_substr($part, $i - 1, 1, 'UTF-8') ?? '';
                $nextChar = mb_substr($part, $i + 1, 1, 'UTF-8') ?? '';
                if ($this->isVowel($currentChar) && !$this->isVowel($prevChar) && !$this->isVowel($nextChar)) {
                    $spaceAdded .= self::SEPARATE_CHARACTER . $currentChar . self::SEPARATE_CHARACTER;
                } else {
                    $spaceAdded .= $currentChar;
                }
            }
        }
        return $spaceAdded;
    }

    private function addSeparateCharacterBetweenConsonant(string $phonetic): string
    {
        $spaceAdded = '';
        $splited = explode(self::SEPARATE_CHARACTER, $phonetic);
        for ($i = 0; $i < count($splited) - 1 ; $i++) {
            $currentChar = $splited[$i];
            $prevChar = $splited[$i - 1] ?? '';
            $nextChar = $splited[$i + 1] ?? '';
            if (mb_strlen($currentChar, 'UTF-8') == 0) {
                continue;
            }
            if ( $this->isConsonant($currentChar) && $this->isVowel($nextChar)) {
                // mot ky tu consonant   đứng trước nguyên âm thì nó ưu tiên cho nguyên âm đó
                $spaceAdded .= self::WORD_SEPARATE_CHARACTER . $currentChar . self::SEPARATE_CHARACTER;
                continue;
            }
            if ( $this->isVowel($nextChar)) {
                //nếu là nguyên âm thì giữ nguyên
                $spaceAdded .= self::SEPARATE_CHARACTER . $currentChar . self::SEPARATE_CHARACTER;
                continue;
            }
            if (mb_strlen($currentChar, 'UTF-8') == 1) {
                //nếu là consonant đơn hoặc dấu phân cách đơn thì giữ nguyên
                $spaceAdded .= self::SEPARATE_CHARACTER . $currentChar . self::SEPARATE_CHARACTER;
                continue;
                
            }
            if (mb_strlen($currentChar, 'UTF-8') >= 2) {
                // neu có nhiều consonant đứng cạnh nhau ở cuối từ thì nó có thể là single consonant hoặc final consonant-> chia cắt
                if($i == count($splited) - 1) {
                    $spaceAdded .= self::SEPARATE_CHARACTER . separateLastSingleConsonant($currentChar);
                    continue;
                }
                // nếu có nhiều ký tự liền nhau không phải là nguyên âm thì chia cắt
                $spaceAdded .= self::SEPARATE_CHARACTER . $this->separateTwoConsonant($currentChar);
            }
        }
        return $spaceAdded;
    }

    private function normalizeSpaces(string $input): string
    {
        return preg_replace('/\s+/', self::SEPARATE_CHARACTER, trim($input));
    }

    private function isVowel(string $myCharacters): bool
    {
        return isset($this->DICT_VOWEL[$myCharacters]);
    }

    private function isStopSound(string $myCharacters): bool
    {
        return isset($this->DICT_STOP_SOUND[$myCharacters]);
    }

    private function isConsonant(string $myCharacters): bool
    {
        return isset($this->DICT_INITIAL_CONSONANT[$myCharacters]) ||
               isset($this->DICT_FINAL_CONSONANT[$myCharacters]) ||
               isset($this->DICT_SINGLE_CONSONANT[$myCharacters]);
    }

    private function isSingleConsonant(string $myCharacters): bool
    {
        return isset($this->DICT_SINGLE_CONSONANT[$myCharacters]);
    }

    private function separateTwoConsonant(string $consonantString): string
    {
        foreach ($this->DICT_INITIAL_CONSONANT as $key => $value) {
            if (mb_substr($consonantString, -mb_strlen($key, 'UTF-8'), null, 'UTF-8') === $key) {
                $leftPart = mb_substr($consonantString, 0, -mb_strlen($key, 'UTF-8'), 'UTF-8');
                $rightPart = $key;
                return $leftPart . self::WORD_SEPARATE_CHARACTER . $rightPart;
            }
        }
        return $consonantString;
    }

    private function separateLastSingleConsonant(string $consonantString): string
    {
        foreach ($this->DICT_SINGLE_CONSONANT as $key => $value) {
            if (mb_substr($consonantString, -mb_strlen($key, 'UTF-8'), null, 'UTF-8') === $key) {
                $leftPart = mb_substr($consonantString, 0, -mb_strlen($key, 'UTF-8'), 'UTF-8');
                $rightPart = $key;
                return $leftPart . self::WORD_SEPARATE_CHARACTER . $rightPart;
            }
        }
        return $consonantString;
    }

    private function getSentences(string $phonetic): array
    {
        $smallPartsArray = $this->getVietNamesePart($phonetic);
        $wordsArray = StringUtils::combineStrings($smallPartsArray);
        return $wordsArray;
    }

    private function getVietNamesePart(string $phonetic): array
    {
        $hintCharacters = [];
        $splited = explode(self::SEPARATE_CHARACTER, $phonetic);
        for ($i = 0; $i < count($splited); $i++) {
            $currentChar = $splited[$i];
            $nextChar = $splited[$i + 1] ?? '';
            $prevChar = $splited[$i - 1] ?? '';
            if (mb_strlen($currentChar, 'UTF-8') == 0) {
                continue;
            }
            if ($this->isVowel($currentChar)) {
                $hintCharacters[] = $this->DICT_VOWEL[$currentChar];
            } 
            if ($this->isInitialConsonant($currentChar) && $this->isVowel($nextChar)) {
                $hintCharacters[] = $this->DICT_INITIAL_CONSONANT[$currentChar];
            }
            if ($this->isFinalConsonant($currentChar) && $this->isVowel($prevChar) && !$this->isVowel($nextChar)) {
                $hintCharacters[] = $this->DICT_FINAL_CONSONANT[$currentChar];
            }
            if ($this->isSingleConsonant($currentChar) && $i == count($splited) - 1) {
                $hintCharacters[] = $this->DICT_SINGLE_CONSONANT[$currentChar];
            }
            if ($this->isConsonant($currentChar) && $this->isStopSound($prevChar) && $this->isStopSound($nextChar)) {
                $hintCharacters[] = $this->DICT_SINGLE_CONSONANT[$currentChar];
            }
            if ($this->isStopSound($currentChar)) {
                $hintCharacters[] = $this->DICT_STOP_SOUND[$currentChar];
            }
        }
        return $hintCharacters;
    }

    private function getVietNameseSentences(array $sentencesArray): array
    {
        $result = [];
        foreach ($sentencesArray as $sentence) {
            $hintSentences = $this->convertEnglishSentenceToVietnamese($sentence);
            $result[] = $hintSentences;
        }
        return $result;
    }

    private function convertEnglishSentenceToVietnamese(string $englishSentence): array
    {
        $vietnameseHint = [];
        $result = [];

        $temp = $this->convertStopSoundToSplitCharacter($englishSentence);
        $splited = explode(self::SEPARATE_CHARACTER, $temp);

        foreach ($splited as $currentWord) {
            if (mb_strlen($currentWord, 'UTF-8') == 0) {
                continue;
            }

            $hintForWord = $this->DICT_VIETNAMESE[$currentWord] ?? null;
            if ($hintForWord) {
                $vietnameseHint[] = $hintForWord;
            }
        }
        $result = StringUtils::combineStrings($vietnameseHint, self::SEPARATE_CHARACTER);
        return $result;
    }

    private function convertStopSoundToSplitCharacter(string $phonetic): string
    {
        $result = $phonetic;
        $stopSoundCharacter = $this->getFirstStopSoundValue();
        if ($stopSoundCharacter) { 
            $result = str_replace($stopSoundCharacter, self::SEPARATE_CHARACTER, $result);
        }

        return $result;
    }

    private function getFirstStopSoundValue(): ?string
    {
        if (empty($this->DICT_STOP_SOUND)) {
            return null;
        }
        $firstKey = array_key_first($this->DICT_STOP_SOUND);
        $firstValueArray = $this->DICT_STOP_SOUND[$firstKey];

        if (is_array($firstValueArray) && !empty($firstValueArray)) {
            return (string) $firstValueArray[0];
        }

        return null;
    }

    private function isInitialConsonant(string $myCharacters): bool
    {
        return isset($this->DICT_INITIAL_CONSONANT[$myCharacters]);
    }

    private function isFinalConsonant(string $myCharacters): bool
    {
        return isset($this->DICT_FINAL_CONSONANT[$myCharacters]);
    }
}