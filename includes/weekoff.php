<?php
/**
 * Week-off pay rules — shared by the attendance report, its print view and the
 * status-grid view so all three agree on which week offs are paid.
 *
 * Driven by three per-company settings (see modules/settings/index.php):
 *   wo_deduct_adj_absent   — absent on the nearest attendance day either side
 *   wo_deduct_low_present  — fewer than the minimum present days that week
 *   wo_min_present_days    — the minimum for the rule above (default 3)
 */

/** A paid weekly off (Sunday or an explicitly marked WO) — holidays are excluded on purpose. */
function woIsOff(string $t): bool { return $t === 'SUN' || $t === 'WO'; }

/** Days that carry no attendance verdict, so adjacency scans look straight through them. */
function woIsNeutral(string $t): bool { return in_array($t, ['SUN', 'WO', 'HOL', 'FUT', ''], true); }

/** Read the week-off settings out of an already-loaded settings map. */
function woConfig(array $settings): array {
    return [
        'adj'    => !empty($settings['wo_deduct_adj_absent']),
        'low'    => !empty($settings['wo_deduct_low_present']),
        'minDays'=> array_key_exists('wo_min_present_days', $settings) ? (float)$settings['wo_min_present_days'] : 3.0,
    ];
}

/**
 * Apply the week-off pay rules to one employee's day map (mutates $days, flagging
 * unpaid offs with woCut/woWhy) and return how many offs were deducted.
 *
 * $days is keyed by Y-m-d with at least a 'type' per entry; $dates is the ordered
 * list of dates in the report range.
 *
 * Rule 1 (adjacency): absent on the nearest attendance day before OR after the off.
 * Rule 2 (min present): fewer than $minPresent present days in that ISO (Mon–Sun) week.
 * Approved leave is not absence and does not count as present under either rule.
 */
function woDeductWeekOffs(array &$days, array $dates, bool $adj, bool $low, float $minPresent): int {
    $n = count($dates);

    // Present days per ISO week. Only dates inside the report range are visible here,
    // so a range starting mid-week can undercount — the setting's help text says so.
    $weekPresent = [];
    if ($low) {
        foreach ($dates as $dt) {
            $t = $days[$dt]['type'] ?? '';
            $w = date('o-W', strtotime($dt));
            if (!isset($weekPresent[$w])) $weekPresent[$w] = 0.0;
            if      ($t === 'P')                  $weekPresent[$w] += 1.0;
            elseif  ($t === 'HP' || $t === 'HL')  $weekPresent[$w] += 0.5;
        }
    }

    $cut = 0;
    foreach ($dates as $i => $dt) {
        if (!woIsOff($days[$dt]['type'] ?? '')) continue;
        $why = '';

        if ($adj) {
            for ($j = $i - 1; $j >= 0; $j--) {          // nearest real day before
                $pt = $days[$dates[$j]]['type'] ?? '';
                if (woIsNeutral($pt)) continue;
                if ($pt === 'A') $why = 'Absent on ' . $dates[$j];
                break;
            }
            if (!$why) for ($j = $i + 1; $j < $n; $j++) {   // nearest real day after
                $nt = $days[$dates[$j]]['type'] ?? '';
                if (woIsNeutral($nt)) continue;
                if ($nt === 'A') $why = 'Absent on ' . $dates[$j];
                break;
            }
        }

        if (!$why && $low && $minPresent > 0) {
            $have = $weekPresent[date('o-W', strtotime($dt))] ?? 0.0;
            if ($have < $minPresent) {
                $why = 'Only ' . rtrim(rtrim(number_format($have, 1), '0'), '.') . ' present day(s) this week';
            }
        }

        if ($why) { $days[$dt]['woCut'] = true; $days[$dt]['woWhy'] = $why; $cut++; }
    }
    return $cut;
}
