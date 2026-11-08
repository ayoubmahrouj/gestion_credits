<?php
declare(strict_types=1);

/**
 * Petits générateurs de graphiques SVG (aucune dépendance externe).
 * Les couleurs sont fixes (lisibles en thème sombre et clair),
 * les textes utilisent currentColor pour suivre le thème.
 */

function chart_palette(): array
{
    return [
        'gold' => '#d4af37',
        'green' => '#4ade80',
        'orange' => '#f59e0b',
        'red' => '#ef4444',
        'blue' => '#60a5fa',
        'purple' => '#a78bfa',
        'teal' => '#2dd4bf',
        'pink' => '#f472b6',
    ];
}

function money_short(float $value): string
{
    $value = (float) $value;

    if (abs($value) >= 1000000) {
        return number_format($value / 1000000, 1, ',', ' ') . ' M';
    }

    if (abs($value) >= 1000) {
        return number_format($value / 1000, 0, ',', ' ') . ' k';
    }

    return number_format($value, 0, ',', ' ');
}

/**
 * Histogramme groupé.
 *
 * @param array<int, string> $labels
 * @param array<int, array{label:string, color:string, values:array<int, float|int>}> $series
 */
function chart_grouped_bars(array $labels, array $series, int $height = 280): string
{
    $width = 900;
    $left = 64;
    $right = 14;
    $top = 16;
    $bottom = 38;

    $plotW = $width - $left - $right;
    $plotH = $height - $top - $bottom;

    $count = max(1, count($labels));

    $maxValue = 0.0;
    foreach ($series as $line) {
        foreach ($line['values'] as $value) {
            $maxValue = max($maxValue, (float) $value);
        }
    }

    $max = $maxValue > 0 ? $maxValue * 1.15 : 1.0;

    $svg = '<svg class="chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-hidden="true">';

    // Grille + axe des ordonnées
    for ($i = 0; $i <= 4; $i++) {
        $ratio = $i / 4;
        $y = $top + $plotH - ($ratio * $plotH);

        $svg .= '<line x1="' . $left . '" y1="' . round($y, 1) . '" x2="' . ($width - $right) . '" y2="' . round($y, 1)
            . '" stroke="currentColor" stroke-opacity="' . ($i === 0 ? '0.28' : '0.10') . '" stroke-width="1"/>';

        $svg .= '<text x="' . ($left - 10) . '" y="' . round($y + 4, 1) . '" text-anchor="end" font-size="11" fill="currentColor" fill-opacity="0.65">'
            . e(money_short($max * $ratio)) . '</text>';
    }

    $seriesCount = max(1, count($series));
    $groupW = $plotW / $count;
    $barW = max(2.0, min(28.0, ($groupW * 0.66) / $seriesCount));
    $innerW = $barW * $seriesCount;
    $step = max(1, (int) ceil($count / 10));

    foreach (array_values($labels) as $index => $label) {
        $groupX = $left + ($index * $groupW);
        $baseX = $groupX + (($groupW - $innerW) / 2);

        foreach (array_values($series) as $sIndex => $line) {
            $value = (float) ($line['values'][$index] ?? 0);

            if ($value <= 0) {
                continue;
            }

            $barH = max(1.0, ($value / $max) * $plotH);
            $x = $baseX + ($sIndex * $barW);
            $y = $top + $plotH - $barH;

            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round(max(1.0, $barW - 1), 1)
                . '" height="' . round($barH, 1) . '" rx="2" fill="' . e($line['color']) . '">'
                . '<title>' . e($label . ' · ' . $line['label'] . ' : ' . money($value)) . '</title></rect>';
        }

        if ($index % $step === 0) {
            $svg .= '<text x="' . round($groupX + ($groupW / 2), 1) . '" y="' . ($height - 12) . '" text-anchor="middle" font-size="10.5" fill="currentColor" fill-opacity="0.6">'
                . e($label) . '</text>';
        }
    }

    $svg .= '</svg>';

    return $svg;
}

/**
 * Anneau (donut) de répartition.
 *
 * @param array<int, array{label:string, value:float|int, color:string}> $items
 */
function chart_donut(array $items, int $size = 200): string
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += max(0, (float) $item['value']);
    }

    $radius = 70.0;
    $circumference = 2 * M_PI * $radius;

    $svg = '<svg class="chart chart-donut" viewBox="0 0 200 200" role="img" aria-hidden="true">';

    $svg .= '<circle cx="100" cy="100" r="70" fill="none" stroke="currentColor" stroke-opacity="0.10" stroke-width="24"/>';

    if ($total > 0) {
        $offset = 0.0;

        foreach ($items as $item) {
            $value = max(0, (float) $item['value']);

            if ($value <= 0) {
                continue;
            }

            $length = ($value / $total) * $circumference;

            $svg .= '<circle cx="100" cy="100" r="70" fill="none" stroke="' . e($item['color']) . '" stroke-width="24"'
                . ' stroke-dasharray="' . round($length, 2) . ' ' . round($circumference - $length, 2) . '"'
                . ' stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 100 100)">'
                . '<title>' . e($item['label'] . ' : ' . money($value)) . '</title></circle>';

            $offset += $length;
        }
    }

    $svg .= '<text x="100" y="96" text-anchor="middle" font-size="13" fill="currentColor" fill-opacity="0.55">'
        . e(t('total')) . '</text>';
    $svg .= '<text x="100" y="116" text-anchor="middle" font-size="15" font-weight="700" fill="currentColor">'
        . e(money_short($total)) . '</text>';

    $svg .= '</svg>';

    return $svg;
}
