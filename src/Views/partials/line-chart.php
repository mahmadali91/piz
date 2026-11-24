<?php
/**
 * Линейный график: выручка, прибыль, расходы.
 * @var array<string, array{revenue:int, profit:int, expense?:int, count:int}> $series
 * @var int|string|null $height
 */
$height = (int) ($height ?? 160);
$width = 760;
$padL = 46;
$padR = 12;
$padT = 12;
$padB = 24;
$plotW = $width - $padL - $padR;
$plotH = $height - $padT - $padB;
$count = max(1, count($series));
$max = 0;
foreach ($series as $point) {
    $max = max($max, (int) $point['revenue'], (int) $point['profit'], (int) ($point['expense'] ?? 0));
}
$max = max(1, $max);
$step = $count > 1 ? $plotW / ($count - 1) : 0;
$labelStep = max(1, (int) ceil($count / 9));
$allZero = $max <= 1;
$points = static function (string $field) use ($series, $padL, $padT, $plotH, $max, $step): string {
    $out = [];
    $i = 0;
    foreach ($series as $point) {
        $value = max(0, (int) ($point[$field] ?? 0));
        $x = $padL + ($i * $step);
        $y = $padT + $plotH - ($value / $max * $plotH);
        $out[] = round($x, 2) . ',' . round($y, 2);
        $i++;
    }
    return implode(' ', $out);
};
?>
<div class="line-chart">
    <svg class="chart__svg" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="Линейный график выручки, прибыли и расходов">
        <?php for ($i = 0; $i <= 4; $i++):
            $y = $padT + $plotH - ($plotH / 4) * $i;
            $value = (int) round($max / 4 * $i);
            ?>
            <line class="chart__grid" x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $width - $padR ?>" y2="<?= $y ?>"></line>
            <text class="chart__axis" x="<?= $padL - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= e(money_short($value)) ?></text>
        <?php endfor; ?>

        <polyline class="line-chart__line line-chart__line--expense" points="<?= e($points('expense')) ?>"></polyline>
        <polyline class="line-chart__line line-chart__line--profit" points="<?= e($points('profit')) ?>"></polyline>
        <polyline class="line-chart__line line-chart__line--revenue" points="<?= e($points('revenue')) ?>"></polyline>

        <?php $dotIndex = 0; foreach ($series as $dotDate => $dotPoint): ?>
            <?php
            $dotX = $padL + ($dotIndex * $step);
            foreach (['expense', 'profit', 'revenue'] as $dotField) {
                $dotValue = max(0, (int) ($dotPoint[$dotField] ?? 0));
                $dotY = $padT + $plotH - ($dotValue / $max * $plotH);
                echo '<circle class="line-chart__dot line-chart__dot--' . $dotField . '" cx="' . round($dotX, 2) . '" cy="' . round($dotY, 2) . '" r="2.2"><title>' . e(date('d.m.Y', strtotime((string) $dotDate))) . ': ' . e(money($dotValue)) . '</title></circle>';
            }
            $dotIndex++;
            ?>
        <?php endforeach; ?>

        <?php $i = 0; foreach ($series as $date => $point): ?>
            <?php
            $x = $padL + ($i * $step);
            $showLabel = ($i % $labelStep === 0);
            if ($i === $count - 1 && !$showLabel) {
                $distance = ($count - 1) - (int) floor(($count - 1) / $labelStep) * $labelStep;
                $showLabel = $distance >= max(1, (int) floor($labelStep / 2));
            }
            ?>
            <?php if ($showLabel): ?>
                <text class="chart__label" x="<?= round($x, 2) ?>" y="<?= $height - 6 ?>" text-anchor="middle"><?= e(date('d.m', strtotime((string) $date))) ?></text>
            <?php endif; ?>
            <?php $i++; endforeach; ?>
    </svg>
    <div class="line-chart__legend">
        <span><i class="legend__dot legend__dot--orange"></i>Выручка</span>
        <span><i class="legend__dot legend__dot--green"></i>Прибыль</span>
        <span><i class="legend__dot legend__dot--red"></i>Расходы</span>
    </div>
    <?php if ($allZero): ?>
        <div class="chart__empty"><?= icon('chart') ?><span>Продаж за этот период пока нет</span></div>
    <?php endif; ?>
</div>
