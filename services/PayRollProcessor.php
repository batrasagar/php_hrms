<?php
require_once __DIR__ . '/ShardManager.php';

/**
 * Stage 4: tblMonthlyAttendance_YYMM → tblPayRoll_YYMM
 *
 * Reads monthly attendance summary + employee salary master,
 * computes pro-rated earnings, PF, ESI, OT amount, net salary,
 * and writes to the payroll shard (status = 'draft').
 *
 * PF  cap : employee 12% of Basic, capped at ₹1800 (₹15,000 ceiling)
 * ESI cap : employee 0.75% of Gross if Gross ≤ ₹21,000
 * OT rate : Basic / 26 / 8 × 2 × OT_hours  (double rate)
 */
class PayRollProcessor
{
    public function __construct(
        private PDO          $db,
        private ShardManager $shard
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Compute payroll for a company+month. Returns rows written.
     * $yr can be 4-digit (2026) or 2-digit (26).
     */
    public function processMonth(int $companyId, int $yr, int $mnth): int
    {
        $yr2      = $yr % 100;
        $ym       = sprintf('%02d%02d', $yr2, $mnth);
        $monthTbl = $this->shard->tbl('MonthlyAttendance', $ym);
        $prTbl    = $this->shard->tbl('PayRoll',           $ym);

        $fullYear     = $yr2 + 2000;
        $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $mnth, $fullYear);

        // Join monthly attendance with employee salary master in one query
        $stmt = $this->db->prepare("
            SELECT
                m.EmpCode, m.WorkDays, m.TotOT, m.TotA, m.TotP, m.TotHD,
                COALESCE(e.BasicSalary, 0)     AS BasicSalary,
                COALESCE(e.DA, 0)              AS DA,
                COALESCE(e.Hra, 0)             AS HRA,
                COALESCE(e.Medical, 0)         AS Medical,
                COALESCE(e.Conveyence, 0)      AS Conveyence,
                COALESCE(e.OtherAllowance, 0)  AS OtherAllow,
                COALESCE(e.CC_Allowance, 0)    AS CCAllow,
                COALESCE(e.GradeAmt, 0)        AS GradeAmt,
                COALESCE(e.OT, 0)              AS HasOT
            FROM `{$monthTbl}` m
            JOIN tblEmployee e
                ON e.CompanyId = ? AND e.EmployeeCode = m.EmpCode
            WHERE m.CompanyId = ?
        ");
        $stmt->execute([$companyId, $companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) return 0;

        $written = 0;
        foreach ($rows as $r) {
            $payableDays = (float)$r['WorkDays'];
            $totAbsent   = $daysInMonth - $payableDays - (int)$r['TotOT']; // rough
            $totAbsent   = max(0.0, (float)$r['TotA'] + (float)$r['TotHD'] * 0.5);

            // Pro-rate all earnings by payable days
            $ratio    = $daysInMonth > 0 ? $payableDays / $daysInMonth : 0;
            $basic    = $this->round2($r['BasicSalary']  * $ratio);
            $da       = $this->round2($r['DA']           * $ratio);
            $hra      = $this->round2($r['HRA']          * $ratio);
            $medical  = $this->round2($r['Medical']      * $ratio);
            $convey   = $this->round2($r['Conveyence']   * $ratio);
            $other    = $this->round2(($r['OtherAllow'] + $r['CCAllow']) * $ratio);
            $grade    = $this->round2($r['GradeAmt']     * $ratio);

            // OT amount: Basic/26/8 × 2 × OT_hours
            $otAmt = 0.0;
            if ($r['HasOT'] && $r['TotOT'] > 0) {
                $ratePerMin = ($r['BasicSalary'] > 0)
                    ? (float)$r['BasicSalary'] / 26 / 8 / 60
                    : 0;
                $otAmt = $this->round2($ratePerMin * 2 * (int)$r['TotOT']);
            }

            $gross = $basic + $da + $hra + $medical + $convey + $other + $grade + $otAmt;

            // Deductions
            $pf  = ($r['BasicSalary'] <= 15000)
                ? $this->round2($basic * 0.12)
                : 1800.00;
            $esi = ($gross <= 21000)
                ? $this->round2($gross * 0.0075)
                : 0.0;

            $totDeduct = $pf + $esi;
            $net       = $this->round2($gross - $totDeduct);

            $this->db->prepare("
                REPLACE INTO `{$prTbl}`
                (`CompanyId`, `EmpCode`, `Yr`, `Mnth`,
                 `TotPresent`, `TotAbsent`, `TotOTMins`, `PayableDays`,
                 `Basic`, `DA`, `HRA`, `Medical`, `Conveyence`, `OtherAllow`, `GradeAmt`,
                 `OTAmount`, `GrossSalary`,
                 `PF`, `ESI`, `TDS`, `AdvanceDeduct`, `OtherDeduct`, `TotDeduct`,
                 `NetSalary`, `Status`, `ProcessedAt`)
                VALUES (?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, 0, 0, 0, ?,
                        ?, 'draft', NOW())
            ")->execute([
                $companyId, $r['EmpCode'], $yr2, $mnth,
                $payableDays, $totAbsent, (int)$r['TotOT'], $payableDays,
                $basic, $da, $hra, $medical, $convey, $other, $grade,
                $otAmt, $gross,
                $pf, $esi, $totDeduct,
                $net,
            ]);
            $written++;
        }
        return $written;
    }

    /**
     * Recompute a single employee's payroll (e.g. after manual correction).
     */
    public function processEmployee(int $companyId, string $empCode, int $yr, int $mnth): void
    {
        // Delegate — processMonth will REPLACE existing row
        // For a single employee, load their monthly record and recompute
        $yr2      = $yr % 100;
        $ym       = sprintf('%02d%02d', $yr2, $mnth);
        $monthTbl = $this->shard->tbl('MonthlyAttendance', $ym);

        $stmt = $this->db->prepare("SELECT EmpCode FROM `{$monthTbl}` WHERE CompanyId=? AND EmpCode=?");
        $stmt->execute([$companyId, $empCode]);
        if ($stmt->fetch()) {
            $this->processMonth($companyId, $yr, $mnth);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function round2(float $v): float
    {
        return round($v, 2);
    }
}
