<?php

declare(strict_types=1);

namespace AmoDocGenerator\Support;

final class RubleFormatter
{
    public static function toWords(int $value): string
    {
        if ($value === 0) {
            return 'ноль рублей';
        }

        $units = [
            ['рубль', 'рубля', 'рублей', 0],
            ['тысяча', 'тысячи', 'тысяч', 1],
            ['миллион', 'миллиона', 'миллионов', 0],
        ];
        $ones = ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $onesFemale = ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $tens = ['', 'десять', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
        $teens = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
        $hundreds = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];

        $parts = [];
        $chunkIndex = 0;
        $number = $value;

        while ($number > 0 && $chunkIndex < count($units)) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $gender = $units[$chunkIndex][3];
                $segment = [];
                $segment[] = $hundreds[intdiv($chunk, 100)];
                $rest = $chunk % 100;
                if ($rest >= 10 && $rest < 20) {
                    $segment[] = $teens[$rest - 10];
                } else {
                    $segment[] = $tens[intdiv($rest, 10)];
                    $segment[] = $gender === 1 ? $onesFemale[$rest % 10] : $ones[$rest % 10];
                }
                $segment[] = self::morph($chunk, $units[$chunkIndex][0], $units[$chunkIndex][1], $units[$chunkIndex][2]);
                $parts[] = trim(implode(' ', array_filter($segment)));
            }

            $number = intdiv($number, 1000);
            $chunkIndex++;
        }

        $text = implode(' ', array_reverse($parts));
        if (!preg_match('/руб(ль|ля|лей)\b/u', $text)) {
            $text .= ' рублей';
        }

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function morph(int $value, string $form1, string $form2, string $form5): string
    {
        $value = abs($value) % 100;
        $last = $value % 10;

        if ($value > 10 && $value < 20) {
            return $form5;
        }
        if ($last > 1 && $last < 5) {
            return $form2;
        }
        if ($last === 1) {
            return $form1;
        }

        return $form5;
    }
}

