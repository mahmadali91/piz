<?php
/**
 * Столбчатый график выручки и прибыли.
 * @var array<string, array{revenue:int, profit:int, count:int}> $series
 * @var int|string|null $height
 * @var bool $showProfit
 */
$height = (int) ($height ?? 250);
$showProfit = $showProfit ?? true;
$width = 760;
$padL = 46;
$padR = 10;
$padT = 14;
$padB = 30;
$plotW = $width - $padL - $padR;
$plotH = $height - $padT - $padB;
$count = max(1, count($series));
$max = 0;
foreach ($series as $point) {
    $max = max($max, (int) $point['revenue'], $showProfit ? (int) $point['profit'] : 0);
}
$max = max(1, $max);
$step = $plotW / $count;
$barW = max(2.5, min(15, $step * ($showProfit ? 0.32 : 0.5)));
$labelStep = max(1, (int) ceil($count / 9));
$allZero = $max <= 1;
?>
<div class="chart">
    <svg class="chart__svg" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="График выручки">
        <?php for ($i = 0; $i <= 4; $i++):
            $y = $padT + $plotH - ($plotH / 4) * $i;
            $value = (int) round($max / 4 * $i);
            ?>
            <line class="chart__grid" x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $width - $padR ?>" y2="<?= $y ?>"></line>
            <text class="chart__axis" x="<?= $padL - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= e(money_short($value)) ?></text>
        <?php endfor; ?>

        <?php $i = 0; foreach ($series as $date => $point):
            $revenue = (int) $point['revenue'];
            $profit = (int) $point['profit'];
            $center = $padL + $step * ($i + 0.5);
            $revH = $revenue > 0 ? max(2, $revenue / $max * $plotH) : 0;
            $profH = $profit > 0 ? max(2, $profit / $max * $plotH) : 0;
            $showLabel = ($i % $labelStep === 0);
            if ($i === $count - 1 && !$showLabel) {
                $distance = ($count - 1) - (int) floor(($count - 1) / $labelStep) * $labelStep;
                $showLabel = $distance >= max(1, (int) floor($labelStep / 2));
            }
            ?>
            <?php if ($showProfit): ?>
                <rect class="chart__bar chart__bar--rev" x="<?= round($center - $barW - 1, 2) ?>" y="<?= round($padT + $plotH - $revH, 2) ?>" width="<?= round($barW, 2) ?>" height="<?= round($revH, 2) ?>" rx="3">
                    <title><?= e(date('d.m.Y', strtotime($date))) ?> · выручка <?= e(money($revenue)) ?></title>
                </rect>
                <rect class="chart__bar chart__bar--profit" x="<?= round($center + 1, 2) ?>" y="<?= round($padT + $plotH - $profH, 2) ?>" width="<?= round($barW, 2) ?>" height="<?= round($profH, 2) ?>" rx="3">
                    <title><?= e(date('d.m.Y', strtotime($date))) ?> · прибыль <?= e(money($profit)) ?></title>
                </rect>
            <?php else: ?>
                <rect class="chart__bar chart__bar--rev" x="<?= round($center - $barW / 2, 2) ?>" y="<?= round($padT + $plotH - $revH, 2) ?>" width="<?= round($barW, 2) ?>" height="<?= round($revH, 2) ?>" rx="3">
                    <title><?= e(date('d.m.Y', strtotime($date))) ?> · выручка <?= e(money($revenue)) ?></title>
                </rect>
            <?php endif; ?>
            <?php if ($showLabel): ?>
                <text class="chart__label" x="<?= round($center, 2) ?>" y="<?= $height - 8 ?>" text-anchor="middle"><?= e(date('d.m', strtotime($date))) ?></text>
            <?php endif; ?>
            <?php $i++; endforeach; ?>
    </svg>
    <?php if ($allZero): ?>
        <div class="chart__empty">
            <?= icon('chart') ?>
            <span>Продаж за этот период пока нет</span>
        </div>
    <?php endif; ?>
</div>
