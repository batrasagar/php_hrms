<?php
/**
 * ShardManager — creates and caches YrMnth-sharded tables on demand.
 * Shard key format: YYMM  (e.g. 2606 = June 2026)
 *
 * Usage:
 *   $sm = new ShardManager($db);
 *   $tbl = $sm->tbl('Attendance', '2606');        // tblAttendance_2606
 *   $tbl = $sm->tbl('MonthlyAttendance', '2606'); // tblMonthlyAttendance_2606
 *   $tbl = $sm->tbl('PayRoll', '2606');           // tblPayRoll_2606
 *
 *   // Convenience: current month
 *   $ym  = ShardManager::ym();          // '2606'
 *   $ym  = ShardManager::ym(2026, 5);   // '2605'
 */
class ShardManager
{
    private PDO   $db;
    private array $ensured = [];   // tableName => true, to avoid repeated DDL checks

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /** Returns YYMM string for the given (or current) year+month */
    public static function ym(int $year = 0, int $month = 0): string
    {
        if (!$year)  $year  = (int)date('Y');
        if (!$month) $month = (int)date('n');
        return sprintf('%02d%02d', $year % 100, $month);
    }

    /** Returns the physical table name, creating it if absent. */
    public function tbl(string $base, string $ym): string
    {
        $name = "tbl{$base}_{$ym}";
        if (!isset($this->ensured[$name])) {
            $this->ensure($base, $name);
            $this->ensured[$name] = true;
        }
        return $name;
    }

    /** List existing shard months for a base table, newest first. */
    public function listShards(string $base): array
    {
        $prefix = "tbl{$base}\_";
        $stmt   = $this->db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$prefix . '%']);
        $names  = array_column($stmt->fetchAll(PDO::FETCH_NUM), 0);
        rsort($names);
        return $names;
    }

    // ── Private: DDL ─────────────────────────────────────────────────────────

    private function ensure(string $base, string $name): void
    {
        // Fast existence check — avoids CREATE TABLE overhead on hot path
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn()) return;

        $ddl = match ($base) {
            'Attendance'        => $this->ddlAttendance($name),
            'MonthlyAttendance' => $this->ddlMonthlyAttendance($name),
            'PayRoll'           => $this->ddlPayRoll($name),
            'PunchLog'          => $this->ddlPunchLog($name),
            default             => throw new \InvalidArgumentException("Unknown shard base: $base"),
        };
        $this->db->exec($ddl);
    }

    private function ddlAttendance(string $t): string
    {
        return "CREATE TABLE IF NOT EXISTS `{$t}` (
            `CompanyId`   INT UNSIGNED NOT NULL,
            `EmpCode`     VARCHAR(50)  NOT NULL,
            `tDate`       DATE         NOT NULL,
            `WeekDay`     TINYINT      NOT NULL DEFAULT 0 COMMENT '1=Mon 7=Sun',
            `AttStatus`   CHAR(4)      NOT NULL DEFAULT 'A'
                          COMMENT 'P/A/HD/WO/WOP/PH/L/SL/CO/OD',
            `TimeIn`      TIME         DEFAULT NULL,
            `TimeOut`     TIME         DEFAULT NULL,
            `TotalMins`   SMALLINT     NOT NULL DEFAULT 0,
            `ShiftNo`     INT          NOT NULL DEFAULT 0,
            `WO`          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Is scheduled week-off',
            `WOGiven`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Compensatory WO granted',
            `OT`          SMALLINT     NOT NULL DEFAULT 0 COMMENT 'OT minutes',
            `ShortTime`   SMALLINT     NOT NULL DEFAULT 0 COMMENT 'Short-time minutes',
            `IsManual`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=from correction table',
            `ProcessedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`CompanyId`, `EmpCode`, `tDate`),
            KEY `idx_date`    (`tDate`),
            KEY `idx_co_date` (`CompanyId`, `tDate`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function ddlMonthlyAttendance(string $t): string
    {
        $dayCols = '';
        for ($i = 1; $i <= 31; $i++) {
            $dayCols .= sprintf("`D%02d` CHAR(4) NOT NULL DEFAULT '',\n            ", $i);
        }
        return "CREATE TABLE IF NOT EXISTS `{$t}` (
            `CompanyId`   INT UNSIGNED  NOT NULL,
            `EmpCode`     VARCHAR(50)   NOT NULL,
            `Yr`          SMALLINT      NOT NULL COMMENT 'e.g. 26 for 2026',
            `Mnth`        TINYINT       NOT NULL COMMENT '1-12',
            {$dayCols}`TotP`       TINYINT       NOT NULL DEFAULT 0 COMMENT 'Full present days',
            `TotA`        TINYINT       NOT NULL DEFAULT 0,
            `TotHD`       TINYINT       NOT NULL DEFAULT 0 COMMENT 'Half days',
            `TotWO`       TINYINT       NOT NULL DEFAULT 0,
            `TotPH`       TINYINT       NOT NULL DEFAULT 0 COMMENT 'Public holidays',
            `TotL`        TINYINT       NOT NULL DEFAULT 0 COMMENT 'Leaves (all types)',
            `TotOT`       SMALLINT      NOT NULL DEFAULT 0 COMMENT 'Total OT minutes',
            `TotShort`    SMALLINT      NOT NULL DEFAULT 0 COMMENT 'Total short-time minutes',
            `WorkDays`    DECIMAL(5,1)  NOT NULL DEFAULT 0 COMMENT 'Payable days (HD=0.5)',
            `DayData`     JSON          DEFAULT NULL
                          COMMENT 'Per-day detail: {\"d\":{in,out,tot,ot,sts,sh}}',
            `ProcessedAt` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`CompanyId`, `EmpCode`),
            KEY `idx_co` (`CompanyId`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function ddlPayRoll(string $t): string
    {
        return "CREATE TABLE IF NOT EXISTS `{$t}` (
            `CompanyId`     INT UNSIGNED NOT NULL,
            `EmpCode`       VARCHAR(50)  NOT NULL,
            `Yr`            SMALLINT     NOT NULL,
            `Mnth`          TINYINT      NOT NULL,
            `TotPresent`    DECIMAL(5,1) NOT NULL DEFAULT 0 COMMENT 'HD=0.5',
            `TotAbsent`     DECIMAL(5,1) NOT NULL DEFAULT 0,
            `TotOTMins`     SMALLINT     NOT NULL DEFAULT 0,
            `PayableDays`   DECIMAL(5,1) NOT NULL DEFAULT 0,
            `Basic`         DECIMAL(10,2) NOT NULL DEFAULT 0,
            `DA`            DECIMAL(10,2) NOT NULL DEFAULT 0,
            `HRA`           DECIMAL(10,2) NOT NULL DEFAULT 0,
            `Medical`       DECIMAL(10,2) NOT NULL DEFAULT 0,
            `Conveyence`    DECIMAL(10,2) NOT NULL DEFAULT 0,
            `OtherAllow`    DECIMAL(10,2) NOT NULL DEFAULT 0,
            `GradeAmt`      DECIMAL(10,2) NOT NULL DEFAULT 0,
            `OTAmount`      DECIMAL(10,2) NOT NULL DEFAULT 0,
            `GrossSalary`   DECIMAL(10,2) NOT NULL DEFAULT 0,
            `PF`            DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Emp 12pct',
            `ESI`           DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Emp 0.75pct',
            `TDS`           DECIMAL(10,2) NOT NULL DEFAULT 0,
            `AdvanceDeduct` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `OtherDeduct`   DECIMAL(10,2) NOT NULL DEFAULT 0,
            `TotDeduct`     DECIMAL(10,2) NOT NULL DEFAULT 0,
            `NetSalary`     DECIMAL(10,2) NOT NULL DEFAULT 0,
            `Status`        ENUM('draft','approved','paid','hold') NOT NULL DEFAULT 'draft',
            `Remarks`       VARCHAR(200)  NOT NULL DEFAULT '',
            `ProcessedBy`   INT UNSIGNED  NOT NULL DEFAULT 0,
            `ProcessedAt`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
            `ApprovedBy`    INT UNSIGNED  NOT NULL DEFAULT 0,
            `ApprovedAt`    DATETIME      DEFAULT NULL,
            PRIMARY KEY (`CompanyId`, `EmpCode`),
            KEY `idx_status` (`CompanyId`, `Status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function ddlPunchLog(string $t): string
    {
        return "CREATE TABLE IF NOT EXISTS `{$t}` (
            `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `CompanyId`    INT UNSIGNED    NOT NULL,
            `EmpCode`      VARCHAR(50)     NOT NULL DEFAULT '',
            `EnrollId`     VARCHAR(50)     NOT NULL DEFAULT '',
            `PunchTime`    DATETIME        NOT NULL,
            `PunchType`    TINYINT         NOT NULL DEFAULT 0,
            `DeviceSerial` VARCHAR(100)    NOT NULL DEFAULT '',
            `RawStamp`     VARCHAR(20)     NOT NULL DEFAULT '',
            `IsProcessed`  TINYINT         NOT NULL DEFAULT 0,
            `SyncedAt`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_punch` (`DeviceSerial`, `EnrollId`, `PunchTime`),
            KEY `idx_pending`  (`IsProcessed`, `CompanyId`, `PunchTime`),
            KEY `idx_emp_date` (`CompanyId`, `EmpCode`, `PunchTime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}
