<?php

declare(strict_types=1);

use AmoDocGenerator\DocumentDataBuilder;
use PHPUnit\Framework\TestCase;

final class DocumentDataBuilderTest extends TestCase
{
    private array $products = [
        [
            'name' => 'Диагностика',
            'unit_price' => 1500,
            'qty' => 1,
        ],
        [
            'name' => 'Ремонт',
            'price' => 6000,
            'quantity' => 2,
            'discount_percent' => 10,
        ],
        [
            'name' => 'Химчистка',
            'unit_price' => 3000,
            'qty' => 1,
            'discount' => 500,
        ],
    ];

    public function testBuildRowsReturnsNormalizedValues(): void
    {
        $rows = DocumentDataBuilder::buildRows($this->products);

        $this->assertCount(3, $rows);
        $this->assertSame(1, $rows[0]['index']);
        $this->assertSame('Диагностика', $rows[0]['name']);
        $this->assertSame('-', $rows[0]['discount_label']);
        $this->assertSame('10', $rows[1]['discount_label']);
        $this->assertSame(2, $rows[1]['qty']);
        $this->assertSame(3000, $rows[2]['unit_price']);
        $this->assertSame(2500, $rows[2]['net_sum']);
    }

    public function testSummarizeCalculatesTotals(): void
    {
        $summary = DocumentDataBuilder::summarize($this->products, 700);

        $this->assertSame(10500, $summary['sum_gross']);
        $this->assertSame(9400, $summary['sum_after']);
        $this->assertSame(700, $summary['discount']);
        $this->assertSame(8700, $summary['total']);
        $this->assertSame(3, $summary['count']);
    }
}

