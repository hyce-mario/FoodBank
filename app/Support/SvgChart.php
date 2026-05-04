<?php

namespace App\Support;

/**
 * Phase 7.1.b — Server-rendered SVG chart helpers.
 *
 * Why server-rendered SVG:
 *   • Same chart renders on screen, in `window.print()` output, AND in
 *     dompdf-generated PDFs. dompdf cannot run JavaScript, so a
 *     Chart.js / ApexCharts approach would leave board members with
 *     blank rectangles in the printed/emailed PDF copies — which is
 *     exactly what enterprise reporting cannot ship.
 *   • No external CDN dependency; no JS bundle bloat.
 *   • Print-friendly by default — SVG scales to any DPI without
 *     pixelation.
 *
 * Each method returns a complete `<svg>...</svg>` string. The caller
 * embeds it directly into the Blade output. Charts are sized via the
 * `width` + `height` opts (defaults are reasonable for the report shell)
 * and rely on a small set of brand colours (FinanceReportService::PALETTE).
 *
 * NOT a general-purpose charting library — methods support exactly the
 * shapes the finance reports need. Extend deliberately.
 */
class SvgChart
{
    /**
     * Horizontal stacked bar — proportions of a single dataset rendered
     * as a single horizontal bar split into segments. Used in PDF
     * exports where dompdf's path-arc support is unreliable; the bar
     * is built entirely from `<rect>` elements which dompdf renders
     * faithfully.
     *
     * @param array<int, array{label:string, value:float, color:string}> $segments
     * @param array{
     *   width?:int, height?:int,
     *   total_label?:string,
     * } $opts
     */
    public static function horizontalStackedBar(array $segments, array $opts = []): string
    {
        $width  = $opts['width']  ?? 540;
        $height = $opts['height'] ?? 36;

        $total = array_sum(array_column($segments, 'value'));
        if ($total <= 0) {
            return self::emptyChart($width, $height);
        }

        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" role="img">';

        // Background track — light grey rectangle the bar sits inside.
        $svg .= '<rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="#f3f4f6" rx="4"/>';

        $x = 0;
        foreach ($segments as $seg) {
            $value = (float) $seg['value'];
            if ($value <= 0) continue;
            $segWidth = ($value / $total) * $width;
            $svg .= '<rect x="' . round($x, 2) . '" y="0" '
                  . 'width="' . round($segWidth, 2) . '" height="' . $height . '" '
                  . 'fill="' . $seg['color'] . '"/>';
            $x += $segWidth;
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Donut chart — slice proportions of a single dataset.
     *
     * @param array<int, array{label:string, value:float, color:string}> $segments
     * @param array{
     *   width?:int, height?:int,
     *   center_label?:string, center_sub?:string,
     *   show_legend?:bool,
     * } $opts
     */
    public static function donut(array $segments, array $opts = []): string
    {
        $width  = $opts['width']  ?? 280;
        $height = $opts['height'] ?? 280;
        $cx     = $width / 2;
        $cy     = $height / 2;
        $rOuter = min($cx, $cy) - 6;   // 6px breathing room from edge
        $rInner = $rOuter * 0.62;       // donut hole — 62% of outer

        $total = array_sum(array_column($segments, 'value'));

        if ($total <= 0) {
            return self::emptyDonut($width, $height, $cx, $cy, $rOuter, $rInner);
        }

        // Explicit width/height attributes (in addition to viewBox) so
        // dompdf renders at the intended size — without them, dompdf
        // can allocate full-page space per chart.
        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" role="img">';

        $start = -M_PI_2; // start at 12 o'clock
        foreach ($segments as $seg) {
            $value = (float) $seg['value'];
            if ($value <= 0) continue;
            $sweep = ($value / $total) * 2 * M_PI;
            $end   = $start + $sweep;
            $svg  .= self::donutSlice($cx, $cy, $rOuter, $rInner, $start, $end, $seg['color']);
            $start = $end;
        }

        // Center labels (e.g. dollar total)
        if (! empty($opts['center_label'])) {
            $centerY = $cy - (! empty($opts['center_sub']) ? 6 : 0);
            $svg .= '<text x="' . $cx . '" y="' . $centerY . '" text-anchor="middle" '
                  . 'font-family="Helvetica, Arial, sans-serif" font-size="20" font-weight="700" fill="#1b2b4b" dominant-baseline="middle">'
                  . htmlspecialchars($opts['center_label'], ENT_QUOTES) . '</text>';
        }
        if (! empty($opts['center_sub'])) {
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 14) . '" text-anchor="middle" '
                  . 'font-family="Helvetica, Arial, sans-serif" font-size="10" font-weight="500" fill="#6b7280" dominant-baseline="middle">'
                  . htmlspecialchars($opts['center_sub'], ENT_QUOTES) . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Vertical bar chart — one bar per data point. Useful for monthly
     * income vs expense or per-category amounts.
     *
     * @param array<int, array{label:string, value:float, color?:string}> $bars
     * @param array{
     *   width?:int, height?:int,
     *   format_value?:callable, show_values?:bool,
     * } $opts
     */
    public static function bar(array $bars, array $opts = []): string
    {
        $width  = $opts['width']  ?? 540;
        $height = $opts['height'] ?? 220;
        $padX   = 32;
        $padTop = 16;
        $padBot = 36;

        $chartW = $width - 2 * $padX;
        $chartH = $height - $padTop - $padBot;

        if (empty($bars)) {
            return self::emptyChart($width, $height);
        }

        $maxValue = max(array_map(fn ($b) => (float) $b['value'], $bars));
        if ($maxValue <= 0) $maxValue = 1; // avoid /0

        $count   = count($bars);
        $barGap  = 8;
        $barW    = max(4, ($chartW - $barGap * ($count - 1)) / $count);

        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" role="img">';

        // Axis line
        $svg .= '<line x1="' . $padX . '" x2="' . ($width - $padX) . '" '
              . 'y1="' . ($padTop + $chartH) . '" y2="' . ($padTop + $chartH) . '" '
              . 'stroke="#e5e7eb" stroke-width="1"/>';

        $formatter = $opts['format_value'] ?? fn ($v) => number_format((float) $v, 0);
        $showValues = $opts['show_values'] ?? true;

        foreach ($bars as $i => $bar) {
            $value = (float) $bar['value'];
            $h     = ($value / $maxValue) * $chartH;
            $x     = $padX + $i * ($barW + $barGap);
            $y     = $padTop + $chartH - $h;
            $color = $bar['color'] ?? '#1b2b4b';

            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barW . '" height="' . max(1, $h) . '" '
                  . 'fill="' . $color . '" rx="2"/>';

            // Label below bar
            $svg .= '<text x="' . ($x + $barW / 2) . '" y="' . ($padTop + $chartH + 16) . '" '
                  . 'text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="9" fill="#6b7280">'
                  . htmlspecialchars($bar['label'], ENT_QUOTES) . '</text>';

            // Value above bar
            if ($showValues && $value > 0) {
                $svg .= '<text x="' . ($x + $barW / 2) . '" y="' . max($padTop + 9, $y - 4) . '" '
                      . 'text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="9" font-weight="700" fill="#1b2b4b">'
                      . htmlspecialchars($formatter($value), ENT_QUOTES) . '</text>';
            }
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Multi-series line chart — useful for category-trend reports
     * where multiple series share an X-axis.
     *
     * @param array<string, array<int, float>> $series  ['Donations' => [100, 200, ...]]
     * @param array<int, string> $labels                 ['Jan', 'Feb', ...]
     * @param array{
     *   width?:int, height?:int,
     *   colors?:array<string,string>,
     *   format_value?:callable,
     * } $opts
     */
    public static function line(array $series, array $labels, array $opts = []): string
    {
        $width  = $opts['width']  ?? 580;
        $height = $opts['height'] ?? 240;
        $padX   = 36;
        $padTop = 20;
        $padBot = 36;

        $chartW = $width - 2 * $padX;
        $chartH = $height - $padTop - $padBot;

        if (empty($series) || empty($labels)) {
            return self::emptyChart($width, $height);
        }

        $maxVal = 1;
        foreach ($series as $points) {
            foreach ($points as $v) {
                if ((float) $v > $maxVal) $maxVal = (float) $v;
            }
        }

        $colors  = $opts['colors'] ?? [];
        $palette = \App\Services\FinanceReportService::PALETTE;
        $stepX   = count($labels) > 1 ? $chartW / (count($labels) - 1) : 0;

        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" role="img">';

        // Y axis baseline + 4 horizontal gridlines
        for ($i = 0; $i <= 4; $i++) {
            $y = $padTop + ($chartH * $i / 4);
            $svg .= '<line x1="' . $padX . '" x2="' . ($width - $padX) . '" '
                  . 'y1="' . $y . '" y2="' . $y . '" '
                  . 'stroke="' . ($i === 4 ? '#9ca3af' : '#f3f4f6') . '" stroke-width="1"/>';
        }

        // X labels
        foreach ($labels as $i => $label) {
            $x = $padX + $i * $stepX;
            $svg .= '<text x="' . $x . '" y="' . ($padTop + $chartH + 16) . '" '
                  . 'text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="9" fill="#6b7280">'
                  . htmlspecialchars($label, ENT_QUOTES) . '</text>';
        }

        // Plot each series
        $idx = 0;
        foreach ($series as $name => $points) {
            $color = $colors[$name] ?? $palette[$idx % count($palette)];
            $idx++;

            $path = '';
            foreach ($points as $i => $v) {
                $x = $padX + $i * $stepX;
                $y = $padTop + $chartH - (((float) $v / $maxVal) * $chartH);
                $path .= ($i === 0 ? 'M' : 'L') . round($x, 1) . ',' . round($y, 1) . ' ';
            }

            $svg .= '<path d="' . trim($path) . '" fill="none" stroke="' . $color . '" stroke-width="2" '
                  . 'stroke-linejoin="round" stroke-linecap="round"/>';

            // Dots at each point for legibility on small ranges
            foreach ($points as $i => $v) {
                $x = $padX + $i * $stepX;
                $y = $padTop + $chartH - (((float) $v / $maxVal) * $chartH);
                $svg .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="2.5" fill="' . $color . '"/>';
            }
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Single donut "slice" path. Geometry: outer arc → line in to inner
     * radius → inner arc back → close. Uses the standard "large arc
     * flag" trick for slices > 180°.
     */
    private static function donutSlice(float $cx, float $cy, float $rOuter, float $rInner, float $startRad, float $endRad, string $color): string
    {
        $largeArc = ($endRad - $startRad) > M_PI ? 1 : 0;

        $x1 = $cx + $rOuter * cos($startRad);
        $y1 = $cy + $rOuter * sin($startRad);
        $x2 = $cx + $rOuter * cos($endRad);
        $y2 = $cy + $rOuter * sin($endRad);

        $x3 = $cx + $rInner * cos($endRad);
        $y3 = $cy + $rInner * sin($endRad);
        $x4 = $cx + $rInner * cos($startRad);
        $y4 = $cy + $rInner * sin($startRad);

        $path = sprintf(
            'M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z',
            $x1, $y1, $rOuter, $rOuter, $largeArc, $x2, $y2,
            $x3, $y3, $rInner, $rInner, $largeArc, $x4, $y4,
        );

        return '<path d="' . $path . '" fill="' . $color . '" stroke="#fff" stroke-width="1.5"/>';
    }

    /**
     * Empty-state donut — light grey ring with a "No data" middle. Kept
     * separate from the data path so the empty rendering is consistent.
     */
    private static function emptyDonut(int $width, int $height, float $cx, float $cy, float $rOuter, float $rInner): string
    {
        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" role="img">';
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $rOuter . '" fill="#f3f4f6" stroke="#e5e7eb"/>';
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $rInner . '" fill="#fff"/>';
        $svg .= '<text x="' . $cx . '" y="' . $cy . '" text-anchor="middle" '
              . 'font-family="Helvetica, Arial, sans-serif" font-size="11" fill="#9ca3af" dominant-baseline="middle">No data</text>';
        $svg .= '</svg>';
        return $svg;
    }

    private static function emptyChart(int $width, int $height): string
    {
        return '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" role="img">'
             . '<rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="#f9fafb" stroke="#e5e7eb" rx="6"/>'
             . '<text x="' . ($width / 2) . '" y="' . ($height / 2) . '" text-anchor="middle" '
             . 'font-family="Helvetica, Arial, sans-serif" font-size="11" fill="#9ca3af" dominant-baseline="middle">No data</text>'
             . '</svg>';
    }
}
