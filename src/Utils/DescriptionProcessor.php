<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Utils;

final class DescriptionProcessor
{
    /** @return list<string> */
    public static function processDescription(string $description, int $maxLength = 100): array
    {
        $parts = preg_split('/(\n+)/u', $description, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        $result = [];
        foreach ($parts as $part) {
            array_push($result, ...self::splitLongString(trim($part), $maxLength));
        }

        return array_values(array_filter($result));
    }

    /** @return list<string> */
    protected static function splitLongString(string $input, int $maxLength = 100): array
    {
        $parts = [];
        $currentPart = '';
        $words = preg_split('/(\s+)/u', $input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        foreach ($words as $word) {
            if (mb_strlen($currentPart . $word) > $maxLength) {
                $parts[] = trim($currentPart);
                $currentPart = $word;
            } else {
                $currentPart .= $word;
            }
        }

        if ($currentPart !== '') {
            $parts[] = trim($currentPart);
        }

        return $parts;
    }
}
