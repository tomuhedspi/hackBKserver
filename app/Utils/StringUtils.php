<?php

namespace App\Utils;

class StringUtils
{
    public static function combineStrings(array $arrays, string $separateCharacter = ''): array 
    {
        if (empty($arrays)) {
            return [];
        }
    
        $result = [[]];
    
        foreach ($arrays as $currentArray) {
            if (empty($currentArray)) {
                return [];
            }
    
            $temp = [];
            foreach ($result as $existingCombination) {
                foreach ($currentArray as $element) {
                    $temp[] = array_merge($existingCombination, [$element]);
                }
            }
            $result = $temp;
        }
    
        // Convert combined arrays to strings
        $output = [];
        foreach ($result as $combination) {
            $output[] = implode($separateCharacter, $combination);
        }
    
        return $output;
    }
}