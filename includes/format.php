<?php
require_once __DIR__ . '/settings.php';

function currency_symbol(): string {
    if (function_exists('table_exists') && table_exists('settings')) {
        $sym = setting('currency_symbol', '');
        if ($sym !== '') return $sym;
        $code = setting('currency_code', '');
        if ($code) return $code.' ';
    }
    return '$';
}

function money(float $amount): string {
    $sym = currency_symbol();
    return $sym . number_format($amount, 2);
}

function format_address(array $data, string $prefix = ''): string {
    $address_line_1 = trim($data[$prefix . 'address_line_1'] ?? $data[$prefix . 'address_line'] ?? '');
    $address_line_2 = trim($data[$prefix . 'address_line_2'] ?? '');
    $landmark = trim($data[$prefix . 'landmark'] ?? '');
    $city = trim($data[$prefix . 'city'] ?? '');
    $state = trim($data[$prefix . 'state'] ?? '');
    $zipcode = trim($data[$prefix . 'zipcode'] ?? '');
    $country = trim($data[$prefix . 'country'] ?? '');

    $lines = [];
    if ($address_line_1) $lines[] = $address_line_1;
    if ($address_line_2) $lines[] = $address_line_2;
    if ($landmark) $lines[] = 'Near ' . $landmark;
    
    $city_state_zip = array_filter([$city, $state]);
    if ($city_state_zip) {
        $line = implode(', ', $city_state_zip);
        if ($zipcode) {
            $line .= ' - ' . $zipcode;
        }
        $lines[] = $line;
    }
    
    if ($country) $lines[] = $country;

    return implode("\n", $lines);
}