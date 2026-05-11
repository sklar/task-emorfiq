<?php

function format_price(int $amount): string
{
    return number_format($amount, 0, ',', ' ') . "\u{00A0}Kč";
}
