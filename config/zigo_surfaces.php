<?php
$surface = static function (string $urlKey, string $hostKey, string $default): array {
    $url = rtrim((string) env($urlKey, $default), '/');
    return ['url' => $url, 'host' => strtolower((string) (parse_url($url, PHP_URL_HOST) ?: env($hostKey)))];
};
$corporateDefault = in_array((string) env('APP_ENV', 'production'), ['local', 'testing'], true)
    ? 'http://zigo.local:8000'
    : (string) env('APP_URL', 'https://zigo-envios.com');
return [
    'corporate' => $surface('ZIGO_PLATFORM_URL', 'ZIGO_PLATFORM_HOST', $corporateDefault),
    'network' => $surface('ZIGO_NETWORK_URL', 'ZIGO_NETWORK_HOST', 'http://network.zigo.local:8000'),
    'payments' => $surface('ZIGO_PAYMENTS_URL', 'ZIGO_PAYMENTS_HOST', 'http://payments.zigo.local:8000'),
];
