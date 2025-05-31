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
    private const MAX_LENGTH_HINT = 1800;
    private $DICT_FINAL_CONSONANT;
    private $DICT_INITIAL_CONSONANT;
    private $DICT_SINGLE_CONSONANT;
    private $DICT_VOWEL;
    private $DICT_STOP_SOUND;
    private $DICT_VIETNAMESE;
    private $DICT_ENGLISH_SIMILAR_PRONUNCIATION;

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
        $this->DICT_ENGLISH_SIMILAR_PRONUNCIATION = $dictionaries['DICT_ENGLISH_SIMILAR_PRONUNCIATION'];
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
        $sentencesArray = $this->removeSimilarSound($sentencesArray);
        $hint = $this->getVietNameseSentences($sentencesArray);

        return response()->json([
            'status' => 200,
            'data' => $hint
        ], 200);
    }

    private function removeSimilarSound(array $sentencesArray): array
    {
        $result = [];
        foreach ($sentencesArray as $sentence) {
            $result[] = $this->removeSimilarSoundInSentence($sentence);
        }
        return $result;
    }

    private function removeSimilarSoundInSentence(string $sentence): string
    {
        $splited = explode(self::SEPARATE_CHARACTER, $sentence);
        $result = [];
        foreach ($splited as $currentWord) {
            if (mb_strlen($currentWord, 'UTF-8') == 0) {
                continue;
            }
            $result[] = $this->removeSimilarSoundInWord($currentWord);
        }
        return implode(self::SEPARATE_CHARACTER, $result);
    }

    private function removeSimilarSoundInWord(string $word): string
    {
        $result = $word;

        foreach ($this->DICT_ENGLISH_SIMILAR_PRONUNCIATION as $key => $value) {
            $result = str_replace((string)$key, (string)$value[0], $result);
        }
        return  $result;
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
        return str_replace(['/', ',', 'ː',':'], '', $phonetic);
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
                $currentChar = $this->getCurrentChar($part, $i);
                $prevChar = $this->getPrevChar($part, $i);
                $nextChar = $this->getNextChar($part, $i);
                if ($this->isVowel($currentChar) && (!$this->isVowel($prevChar)) && (!$this->isVowel($nextChar))) {
                    $spaceAdded .= self::SEPARATE_CHARACTER . $currentChar . self::SEPARATE_CHARACTER;
                } else {
                    $spaceAdded .= $currentChar;
                }
            }
        }
        return $spaceAdded;
    }
    private function getCurrentChar($part, $i)
    {
        if($i < 0) {
            return '';
        }
        if($i>=mb_strlen($part, 'UTF-8')) {
            return '';
        }
        return mb_substr($part, $i, 1, 'UTF-8') ?? '';
    }
    private function getPrevChar($part, $i)
    {
        if($i <= 0) {
            return '';
        }
        if($i>=mb_strlen($part, 'UTF-8')) {
            return '';
        }
        return mb_substr($part, $i-1, 1, 'UTF-8') ?? '';
    }

    private function getNextChar($part, $i)
    {
        if($i < 0) {
            return '';
        }
        if($i>=mb_strlen($part, 'UTF-8')-1) {
            return '';
        }
        return mb_substr($part, $i+1, 1, 'UTF-8') ?? '';
    }


    private function addSeparateCharacterBetweenConsonant(string $phonetic): string
    {
        $spaceAdded = '';
        $splited = explode(self::SEPARATE_CHARACTER, $phonetic);
        for ($i = 0; $i < count($splited) ; $i++) {
            $currentChar = $splited[$i];
            $prevChar = $splited[$i - 1] ?? '';
            $nextChar = $splited[$i + 1] ?? '';
            if (mb_strlen($currentChar, 'UTF-8') == 0) {
                continue;
            }
            if ( $this->isInitialConsonant($currentChar) && $this->isVowel($nextChar)) {
                // mot ky tu consonant   đứng trước nguyên âm thì nó ưu tiên cho nguyên âm đó
                $spaceAdded .= self::WORD_SEPARATE_CHARACTER . $currentChar . self::SEPARATE_CHARACTER;
                continue;
            }
            if ( $this->isVowel($currentChar)) {
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
                    $spaceAdded .= self::SEPARATE_CHARACTER . $this->separateLastSingleConsonant($currentChar);
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
        if($this->isSingleConsonant($consonantString)) {
            return self::WORD_SEPARATE_CHARACTER . $consonantString . self::WORD_SEPARATE_CHARACTER;
        }

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
                if(mb_strlen($leftPart, 'UTF-8')>=1) {
                    return $leftPart . self::WORD_SEPARATE_CHARACTER . $rightPart;
                } else {
                    return  $consonantString;
                }
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
            if ($this->isStopSound($currentChar)) {
                $hintCharacters[] = $this->DICT_STOP_SOUND[$currentChar];
                continue;
            }
            if ($this->isVowel($currentChar)) {
                $hintCharacters[] = $this->DICT_VOWEL[$currentChar];
                continue;
            } 
            if ($this->isInitialConsonant($currentChar) && $this->isVowel($nextChar)) {
                $hintCharacters[] = $this->DICT_INITIAL_CONSONANT[$currentChar];
                continue;
            }
            if ($this->isFinalConsonant($currentChar) && $this->isVowel($prevChar) && !$this->isVowel($nextChar)) {
                $hintCharacters[] = $this->DICT_FINAL_CONSONANT[$currentChar];
                continue;
            }
            if ($this->isSingleConsonant($currentChar) && $i == count($splited) - 1) {
                $hintCharacters[] = $this->DICT_SINGLE_CONSONANT[$currentChar];
                continue;
            }
            if ($this->isSingleConsonant($currentChar) && $this->isStopSound($prevChar) && $this->isStopSound($nextChar)) {
                $hintCharacters[] = $this->DICT_SINGLE_CONSONANT[$currentChar];
                continue;
            }
        }
        return $hintCharacters;
    }

    private function getVietNameseSentences(array $sentencesArray): array
    {
        $result = [];

        foreach ($sentencesArray as $sentence) {
            $possibleAddingNewSentencesCount = $this->getPossibleCountOfAddingNewSentences($result);
            $hintSentences = $this->convertEnglishSentenceToVietnamese($sentence);
            if (count($hintSentences) > $possibleAddingNewSentencesCount) {
                $hintSentences = array_slice($hintSentences, 0, $possibleAddingNewSentencesCount);
            }
            $result[] = $hintSentences;
        }
        return $result;
    }

    private function getPossibleCountOfAddingNewSentences(array $array): int
    {
        $result = 0;
        $countArrayElements = $this->countArrayElements($array);
        if( (self::MAX_LENGTH_HINT - $countArrayElements) > 0) {
            $result = self::MAX_LENGTH_HINT - $countArrayElements;
        }
        return $result;
    }

    private function countArrayElements(array $array): int
    {
        $result = 0;
        foreach ($array as $subArray) {
            if (is_array($subArray)) {
                $result += count($subArray);
            } else {
                $result++;
            }
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
            }else{
                return [];
            }
        }
        $vietnameseHint = $this->cutArrayToLimitLength($vietnameseHint);
        $result = StringUtils::combineStrings($vietnameseHint, self::SEPARATE_CHARACTER);
        return $result;
    }

    private function cutArrayToLimitLength(array $dataArray): array
    {
        $result = [];
        $newSize = [];

        $newSize = $this->getNewSize($dataArray);
        $result = $this->cutArrayBySize($dataArray, $newSize);

        return $result;
    }

    private function cutArrayBySize(array $dataArray, array $sizeArray): array 
    {
        $result = [];

        if (count($dataArray) !== count($sizeArray)) {
            return $dataArray;
        }
        
        foreach ($dataArray as $index => $subArray) {
            $newSize = $sizeArray[$index] ?? 1; 
            $result[] = array_slice($subArray, 0, max(0, $newSize));
        }
        
        return $result;
    }
    private function getNewSize(array $array): array
    {
        $result = $this->setDefaultSizeForArray($array);

        for($i = 0; $i < count($result); $i++) {
            $productOfOtherElements= $this->getProductOfArrayElementsExceptPosition($result,$i);
            $newLength = intdiv( self::MAX_LENGTH_HINT,$productOfOtherElements);
            $possibleLength =  count($array[$i]);

            $result[$i] = min($newLength,$possibleLength);
        }
        return $result;
    }

    private function getProductOfArrayElementsExceptPosition(array $array, int $position): int
    {
        $result = 1;
        for($i = 0; $i < count($array); $i++) {
            if($i!=$position){
                $result=$result*$array[$i];
            }
        }
        return $result;
    }


    private function setDefaultSizeForArray(array $array): array
    {
        $result = [];
        $dadLength = count($array);
        $lengthNewChild = intval(pow(self::MAX_LENGTH_HINT, 1 / max(1, $dadLength)));
        for ($i = 0; $i < $dadLength; $i++) {
            $result[$i] = $lengthNewChild;
        }
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