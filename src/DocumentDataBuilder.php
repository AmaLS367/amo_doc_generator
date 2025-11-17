<?php

declare(strict_types=1);

namespace AmoDocGenerator;

/**
 * Produces normalized rows and totals for document templates.
 */
final class DocumentDataBuilder
{
    /**
     * Build per-row values for template cloning.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, int|string>>
     */
    public static function buildRows(array $products): array
    {
        $rows = [];

        foreach ($products as $index => $item) {
            $qty = (int)($item['qty'] ?? ($item['quantity'] ?? 1));
            $qty = max(1, $qty);

            $unitCandidate = (int)($item['unit_price'] ?? 0);
            if ($unitCandidate <= 0) {
                $price = (int)($item['price'] ?? 0);
                $unitCandidate = $qty > 0 ? (int)round($price / $qty) : $price;
            }

            $gross = $unitCandidate * $qty;
            if ($gross <= 0) {
                $gross = (int)($item['price'] ?? 0);
                if ($qty > 0) {
                    $unitCandidate = $gross > 0 ? (int)round($gross / $qty) : $unitCandidate;
                }
            }

            $discountPercent = (float)($item['discount_percent'] ?? 0);
            $discountValue = (int)($item['discount'] ?? 0);
            $net = self::applyDiscounts($gross, $discountPercent, $discountValue);

            $rows[] = [
                'index' => $index + 1,
                'name' => $item['name'] ?? '',
                'qty' => $qty,
                'unit_price' => $unitCandidate,
                'discount_label' => self::formatDiscountLabel($discountPercent, $discountValue),
                'net_sum' => $net,
            ];
        }

        return $rows;
    }

    /**
     * Summarize gross/net totals.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array{sum_gross:int,sum_after:int,discount:int,total:int,count:int}
     */
    public static function summarize(array $products, int $discount): array
    {
        $sumGross = 0;
        $sumAfter = 0;

        foreach ($products as $item) {
            $qty = (int)($item['qty'] ?? ($item['quantity'] ?? 1));
            $qty = max(1, $qty);

            $unit = (int)($item['unit_price'] ?? 0);
            $gross = $unit ? $unit * $qty : (int)($item['price'] ?? 0);

            $discountPercent = (float)($item['discount_percent'] ?? 0);
            $discountValue = (int)($item['discount'] ?? 0);
            $net = self::applyDiscounts($gross, $discountPercent, $discountValue);

            $sumGross += $gross;
            $sumAfter += $net;
        }

        $globalDiscount = max(0, $discount);
        $total = max(0, $sumAfter - $globalDiscount);

        return [
            'sum_gross' => $sumGross,
            'sum_after' => $sumAfter,
            'discount' => $globalDiscount,
            'total' => $total,
            'count' => count($products),
        ];
    }

    private static function applyDiscounts(int $gross, float $percent, int $value): int
    {
        $net = $gross;
        if ($percent > 0) {
            $net = (int)round($net * (1 - $percent / 100));
        }
        if ($value > 0) {
            $net = max(0, $net - $value);
        }

        return $net;
    }

    private static function formatDiscountLabel(float $percent, int $value): string
    {
        if ($percent > 0) {
            return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
        }

        return $value > 0 ? (string)$value : '-';
    }
}

