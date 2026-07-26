<?php
/**
 * api_helper.php
 * Wrapper for the Grapes Disease ML prediction API.
 * Shared by adddata.php (on sensor insert) and the admin panel.
 */

/**
 * Calls the ML prediction API and returns the decoded JSON as an array.
 * Returns an empty array on any network or parse failure.
 *
 * @param float $temp      Temperature (°C)
 * @param float $rh        Relative Humidity (%)
 * @param float $sunlight  Sunlight hours
 * @param float $rainfall  Rainfall (mm)
 * @param float $leafw     Leaf Wetness index
 * @return array           ['dsi'=>float, 'risk_level'=>string, ...] or []
 */
function callMLApi(float $temp, float $rh, float $sunlight, float $rainfall, float $leafw, int $timeout = 20): array
{
    $payload = json_encode([
        'Leafwetness' => $leafw,
        'Rain-level'  => $rainfall,
        'RH'          => $rh,
        'Temp'        => $temp,
        'Sunlight'    => $sunlight,
    ]);

    if (!function_exists('curl_init')) {
        return [];
    }

    $ch = curl_init('https://grapes-render-ml.onrender.com/api/predict');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return [];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}
