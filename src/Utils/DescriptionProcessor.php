<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Utils;

final class DescriptionProcessor
{
    public static function processDescription(string $description, int $maxLength = 100): iterable
    {
        $parts = preg_split('/(\n+)/u', $description, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $result = [];
        foreach ($parts as $part) {
            array_push($result, ...self::splitLongString(trim($part)));
        }

        return array_filter($result);
    }

    protected static function splitLongString(string $input, int $maxLength = 100): array
    {
        $parts = [];
        $currentPart = '';
        $words = preg_split('/(\s+)/u', $input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            if (mb_strlen($currentPart . $word) > $maxLength) {
                $parts[] = trim($currentPart);
                $currentPart = $word;
            } else {
                $currentPart .= $word;
            }
        }

        if (!empty($currentPart)) {
            $parts[] = trim($currentPart);
        }

        return $parts;
    }
}
