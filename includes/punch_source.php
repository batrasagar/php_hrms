<?php
/**
 * Local punch source — reads the tblPunchLog_YYMM shards.
 *
 * The attendance reports historically fetched punches only from the remote ADMS
 * API. That works for tenants whose devices sync live, but not for tenants whose
 * punches were bulk-imported from a legacy system (UBSL): their rows exist only
 * in the local shards, so the reports resolved nothing and marked everyone absent.
 *
 * Reading the shards fixes that for every tenant at once, because AdmsSyncService
 * also writes synced punches into these same tables. The lookup keys on EmpCode,
 * so it needs no tblDeviceEnrollment mapping and no EnrollId at query time.
 *
 * The ADMS fetch is still worth keeping alongside this: it picks up punches made
 * since the last sync cron, which have not reached a shard yet.
 */

/** Shard tables covering a date range, oldest first (a report range spans 1–2 months). */
function punchShardsForRange(string $from, string $to): array {
    $shards = [];
    $cur = strtotime(date('Y-m-01', strtotime($from)));
    $end = strtotime(date('Y-m-01', strtotime($to)));
    while ($cur !== false && $cur <= $end) {
        $shards[] = 'tblPunchLog_' . date('ym', $cur);
        $cur = strtotime('+1 month', $cur);
    }
    return $shards;
}

/**
 * Merge locally-stored punches into $punchMap, in the shape the reports expect:
 *   $punchMap[EmpCode][Y-m-d] = ['in'=>'HH:MM','out'=>'HH:MM','count'=>int,'punches'=>['HH:MM',…]]
 *
 * Times already present for that employee/date are skipped, so calling this after
 * the ADMS fetch tops up what is missing rather than double-counting.
 *
 * Returns the number of punches actually added.
 */
function punchMapAddLocal(PDO $db, int $companyId, string $from, string $to, array &$punchMap): int {
    $added = 0;
    foreach (punchShardsForRange($from, $to) as $tbl) {
        try {
            $st = $db->prepare(
                "SELECT EmpCode, DATE(PunchTime) AS d, TIME_FORMAT(PunchTime, '%H:%i') AS t
                   FROM `$tbl`
                  WHERE CompanyId = ? AND PunchTime BETWEEN ? AND ?
                    AND EmpCode IS NOT NULL AND EmpCode <> ''
                  ORDER BY EmpCode, PunchTime"
            );
            $st->execute([$companyId, $from . ' 00:00:00', $to . ' 23:59:59']);
        } catch (PDOException $e) {
            continue;                    // no shard for that month — nothing imported yet
        }

        foreach ($st->fetchAll() as $row) {
            $code = (string)$row['EmpCode'];
            $date = $row['d'];
            $time = $row['t'];

            if (!isset($punchMap[$code][$date])) {
                $punchMap[$code][$date] = ['in' => $time, 'out' => $time, 'count' => 0, 'punches' => []];
            }
            $entry = &$punchMap[$code][$date];
            if (in_array($time, $entry['punches'], true)) { unset($entry); continue; }  // already seen via ADMS
            $entry['count']++;
            $entry['punches'][] = $time;
            if ($time < $entry['in'])  $entry['in']  = $time;
            if ($time > $entry['out']) $entry['out'] = $time;
            unset($entry);
            $added++;
        }
    }
    return $added;
}
