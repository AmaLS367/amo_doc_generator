<?php

declare(strict_types=1);

use AmoDocGenerator\Support\RubleFormatter;
use PHPUnit\Framework\TestCase;

final class RubleFormatterTest extends TestCase
{
    /**
     * @dataProvider samplesProvider
     */
    public function testToWords(int $value, string $expected): void
    {
        $this->assertSame($expected, RubleFormatter::toWords($value));
    }

    public function samplesProvider(): array
    {
        return [
            [0, 'ноль рублей'],
            [1, 'один рубль'],
            [4, 'четыре рубля'],
            [11, 'одиннадцать рублей'],
            [21, 'двадцать один рубль'],
            [4000, 'четыре тысячи рублей'],
            [1543211, 'один миллион пятьсот сорок три тысячи двести одиннадцать рублей'],
        ];
    }
}

