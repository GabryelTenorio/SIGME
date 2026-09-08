<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class BrazilianCurrency
{
    public static function format(string|int $amount): string
    {
        $decimal = (string) BigDecimal::of((string) $amount)->toScale(2, RoundingMode::Unnecessary);
        $negative = str_starts_with($decimal, '-');
        [$integer, $fraction] = explode('.', ltrim($decimal, '-'));
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer);

        return ($negative ? '-' : '').'R$ '.$grouped.','.$fraction;
    }
}
