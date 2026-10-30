<?php

/*
 * Bangladesh general CPI, point-to-point inflation (% vs the same month a year earlier), from BBS
 * releases as reported by BSS / Bangladesh Bank / major newspapers. Used to show amounts in
 * today's taka ("real BDT"). Months marked 'estimated' were not found in a source and are linear
 * interpolations between the nearest verified months: replace them with official figures when
 * you have them, then run `php artisan config:cache`. Months after the last entry reuse it.
 *
 * The monthly index is built as I(m) = I(m-1) * (1 + rate(m)/100)^(1/12), a smooth approximation
 * used because the official index levels are only published in PDF tables.
 */
return [
    'rates' => [
        '2023-01' => 8.57, '2023-02' => 8.78, '2023-03' => 9.01, '2023-04' => 9.24, '2023-05' => 9.94, '2023-06' => 9.74,
        '2023-07' => 9.69, '2023-08' => 9.92, '2023-09' => 9.63, '2023-10' => 9.93, '2023-11' => 9.49, '2023-12' => 9.52,
        '2024-01' => 9.56, '2024-02' => 9.59, '2024-03' => 9.62, '2024-04' => 9.66, '2024-05' => 9.69, '2024-06' => 9.72,
        '2024-07' => 11.66, '2024-08' => 10.49, '2024-09' => 9.92, '2024-10' => 10.87, '2024-11' => 10.48, '2024-12' => 10.10,
        '2025-01' => 9.71, '2025-02' => 9.32, '2025-03' => 9.35, '2025-04' => 9.20, '2025-05' => 9.05, '2025-06' => 8.48,
        '2025-07' => 8.55, '2025-08' => 8.29, '2025-09' => 8.36, '2025-10' => 8.17, '2025-11' => 8.29, '2025-12' => 8.49,
        '2026-01' => 8.58, '2026-02' => 9.13, '2026-03' => 8.71, '2026-04' => 9.04, '2026-05' => 9.42, '2026-06' => 9.16,
        '2026-07' => 8.71, '2026-08' => 8.26, '2026-09' => 8.34,
    ],
    'estimated' => ['2023-03', '2023-12', '2024-01', '2024-02', '2024-03', '2024-04', '2024-05', '2024-11', '2024-12', '2025-01', '2025-04', '2026-07'],
];
