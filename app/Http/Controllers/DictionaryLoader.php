<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;

class DictionaryLoader
{
    private const CACHE_TTL = 60 * 60 * 24; // Cache for 24 hours

    public function loadDictVietnameseData(): array
    {
        $dictVietnamese = [];
        $csvFilePath = resource_path('csv/VIETNAMESE_DICTIONARY.csv');
        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $key = $data[0];
                $values = array_filter(array_slice($data, 1), function($value) {
                    return $value !== '';
                });

                if (isset($dictVietnamese[$key])) {
                    $dictVietnamese[$key] = array_merge($dictVietnamese[$key], $values);
                } else {
                    $dictVietnamese[$key] = $values;
                }
            }
            fclose($handle);
        }
        return $dictVietnamese;
    }

    public function loadInitialConsonantData(): array
    {
        $dictInitialConsonant = [];
        $csvFilePath = resource_path('csv/INITIAL_CONSONANT.csv');
        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $key = $data[0];
                $values = array_filter(array_slice($data, 1), function($value) {
                    return $value !== '';
                });

                if (isset($dictInitialConsonant[$key])) {
                    $dictInitialConsonant[$key] = array_merge($dictInitialConsonant[$key], $values);
                } else {
                    $dictInitialConsonant[$key] = $values;
                }
            }
            fclose($handle);
        }
        return $dictInitialConsonant;
    }

    public function loadSingleConsonantData(): array
    {
        $dictSingleConsonant = [];
        $csvFilePath = resource_path('csv/SINGLE_CONSONANT.csv');
        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $key = $data[0];
                $values = array_filter(array_slice($data, 1), function($value) {
                    return $value !== '';
                });

                if (isset($dictSingleConsonant[$key])) {
                    $dictSingleConsonant[$key] = array_merge($dictSingleConsonant[$key], $values);
                } else {
                    $dictSingleConsonant[$key] = $values;
                }
            }
            fclose($handle);
        }
        return $dictSingleConsonant;
    }

    public function loadVowelData(): array
    {
        $dictVowel = [];
        $csvFilePath = resource_path('csv/VOWEL.csv');
        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $key = $data[0];
                $values = array_filter(array_slice($data, 1), function($value) {
                    return $value !== '';
                });

                if (isset($dictVowel[$key])) {
                    $dictVowel[$key] = array_merge($dictVowel[$key], $values);
                } else {
                    $dictVowel[$key] = $values;
                }
            }
            fclose($handle);
        }
        return $dictVowel;
    }

    public function loadStopSoundData(): array
    {
        $dictStopSound = [];
        $csvFilePath = resource_path('csv/STOP_SOUND.csv');
        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $key = $data[0];
                $values = array_filter(array_slice($data, 1), function($value) {
                    return $value !== '';
                });

                if (isset($dictStopSound[$key])) {
                    $dictStopSound[$key] = array_merge($dictStopSound[$key], $values);
                } else {
                    $dictStopSound[$key] = $values;
                }
            }
            fclose($handle);
        }
        return $dictStopSound;
    }

    public function loadFinalConsonantData(): array
    {
        $dictFinalConsonant = [];
        $csvFilePath = resource_path('csv/FINAL_CONSONANT.csv');
        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $key = $data[0];
                $values = array_filter(array_slice($data, 1), function($value) {
                    return $value !== '';
                });

                if (isset($dictFinalConsonant[$key])) {
                    $dictFinalConsonant[$key] = array_merge($dictFinalConsonant[$key], $values);
                } else {
                    $dictFinalConsonant[$key] = $values;
                }
            }
            fclose($handle);
        }
        return $dictFinalConsonant;
    }

    public function sortByKeyLength(&$array) {
        uksort($array, function($key1, $key2) {
            return mb_strlen($key2, 'UTF-8') - mb_strlen($key1, 'UTF-8');
        });
    }

    public function loadDictData(): array
    {
        return Cache::remember('dictionary_data', self::CACHE_TTL, function () {
            $dictVietnamese = $this->loadDictVietnameseData();
            $dictInitialConsonant = $this->loadInitialConsonantData();
            $dictSingleConsonant = $this->loadSingleConsonantData();
            $dictVowel = $this->loadVowelData();
            $dictStopSound = $this->loadStopSoundData();
            $dictFinalConsonant = $this->loadFinalConsonantData();

            // Sort the dictionaries by key length
            $this->sortByKeyLength($dictVietnamese);
            $this->sortByKeyLength($dictInitialConsonant);
            $this->sortByKeyLength($dictSingleConsonant);
            $this->sortByKeyLength($dictVowel);
            $this->sortByKeyLength($dictStopSound);
            $this->sortByKeyLength($dictFinalConsonant);

            return [
                'DICT_VIETNAMESE' => $dictVietnamese,
                'DICT_INITIAL_CONSONANT' => $dictInitialConsonant,
                'DICT_SINGLE_CONSONANT' => $dictSingleConsonant,
                'DICT_VOWEL' => $dictVowel,
                'DICT_STOP_SOUND' => $dictStopSound,
                'DICT_FINAL_CONSONANT' => $dictFinalConsonant,
            ];
        });
    }
}