<?php
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');

// ── DB credential update (submitted before connection attempt) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_host'])) {
    $host = trim($_POST['db_host'] ?? '');
    $port = trim($_POST['db_port'] ?? '3306');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';

    $tpl = <<<'PHP'
<?php
define('DB_HOST', __HOST__);
define('DB_PORT', __PORT__);
define('DB_NAME', __NAME__);
define('DB_USER', __USER__);
define('DB_PASS', __PASS__);

function getDb(): PDO {
    static $pdo = null;
    static $migrationChecked = false;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    if (!$migrationChecked) {
        $migrationChecked = true;
        $skipScripts = ['migrate.php', 'run_schema.php'];
        if (!in_array(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), $skipScripts, true)) {
            try {
                $pdo->query("SELECT `Status` FROM `tblUser` LIMIT 1");
            } catch (PDOException $e) {
                $base = defined('BASE_URL') ? BASE_URL : '';
                header('Location: ' . $base . '/migrate.php');
                exit;
            }
        }
    }

    return $pdo;
}
PHP;
    $tpl = str_replace(
        ['__HOST__', '__PORT__', '__NAME__', '__USER__', '__PASS__'],
        [var_export($host,true), var_export($port,true), var_export($name,true),
         var_export($user,true), var_export($pass,true)],
        $tpl
    );
    file_put_contents(__DIR__ . '/config/db.php', $tpl);
    header('Location: migrate.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

// ── Migration definitions ─────────────────────────────────────────────────────
// Each migration has:
//   check : a query that succeeds (no exception) only if already applied
//   stmts : SQL to run when not yet applied
// Statements that may already be applied (ADD COLUMN, CREATE TABLE) are wrapped
// in a try/catch inside the runner — "duplicate column" and "already exists"
// errors are silently ignored so re-running is always safe.
// ─────────────────────────────────────────────────────────────────────────────
$migrations = [
    [
        'id'   => 'M001',
        'desc' => 'Initial tables (tblUser, tblDevices, tblApiKeys, tblDeviceLog, tblAdmsCredentials)',
        'check' => "SELECT 1 FROM `tblUser` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblUser` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `Name`      VARCHAR(100) NOT NULL,
                `Email`     VARCHAR(150) NOT NULL UNIQUE,
                `Password`  VARCHAR(255) NOT NULL,
                `Role`      ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user',
                `Status`    ENUM('pending','active','rejected') NOT NULL DEFAULT 'active',
                `CompanyLimit` INT NOT NULL DEFAULT 1 COMMENT '-1 = unlimited',
                `ParentAdminId` INT UNSIGNED NULL DEFAULT NULL,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            "INSERT IGNORE INTO `tblUser` (`Name`,`Email`,`Password`,`Role`,`Status`) VALUES
                ('Super Admin','batrasagar@gmail.com','\$2y\$10\$mBFepdju5KPEBAUfZ.3yt.TyDgcQD4hUgLKy0UZeSH9SteIr.mbua','superadmin','active')",

            "CREATE TABLE IF NOT EXISTS `tblCompany` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `AdminId`   INT UNSIGNED NOT NULL,
                `Name`      VARCHAR(150) NOT NULL,
                `Address`   TEXT NULL,
                `Phone`     VARCHAR(20) NULL DEFAULT NULL,
                `Email`     VARCHAR(150) NULL DEFAULT NULL,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_admin` (`AdminId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblDevices` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `Company`      VARCHAR(150) NOT NULL DEFAULT '',
                `SerialNumber` VARCHAR(100) NOT NULL UNIQUE,
                `LastPing`     DATETIME NULL DEFAULT NULL,
                `Stamp`        VARCHAR(20) NOT NULL DEFAULT '1541180497',
                `CreatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblApiKeys` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `Label`     VARCHAR(100) NOT NULL,
                `Company`   VARCHAR(150) NOT NULL,
                `RawKey`    CHAR(64) NOT NULL UNIQUE,
                `KeyHash`   CHAR(64) NOT NULL UNIQUE,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblDeviceLog` (
                `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `SerialNumber`  VARCHAR(100) NOT NULL,
                `EnrollId`      VARCHAR(50) NOT NULL,
                `PunchDateTime` DATETIME NOT NULL,
                `Mode`          VARCHAR(10) NOT NULL DEFAULT '',
                `Stamp`         VARCHAR(20) NOT NULL DEFAULT '',
                `blSms`         TINYINT(1) NOT NULL DEFAULT 0,
                `blEmail`       TINYINT(1) NOT NULL DEFAULT 0,
                `CreatedAt`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_punch` (`SerialNumber`,`EnrollId`,`PunchDateTime`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblAdmsCredentials` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `Label`     VARCHAR(100) NOT NULL,
                `Endpoint`  VARCHAR(500) NOT NULL,
                `ApiKey`    VARCHAR(100) NOT NULL,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        ],
    ],

    [
        'id'   => 'M002',
        'desc' => 'Add multi-tenancy columns to tblUser (Status, CompanyLimit, ParentAdminId, superadmin role)',
        'check' => "SELECT `Status` FROM `tblUser` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblUser` MODIFY COLUMN `Role`
                ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user'",
            "ALTER TABLE `tblUser` ADD COLUMN `Status`
                ENUM('pending','active','rejected') NOT NULL DEFAULT 'active'
                AFTER `Role`",
            "ALTER TABLE `tblUser` ADD COLUMN `CompanyLimit`
                INT NOT NULL DEFAULT 1 COMMENT '-1 = unlimited'
                AFTER `Status`",
            "ALTER TABLE `tblUser` ADD COLUMN `ParentAdminId`
                INT UNSIGNED NULL DEFAULT NULL
                AFTER `CompanyLimit`",
            // Make existing admin the superadmin
            "UPDATE `tblUser` SET `Role`='superadmin', `Status`='active'
                WHERE `Email`='batrasagar@gmail.com'",
            // Mark all other existing users as active
            "UPDATE `tblUser` SET `Status`='active' WHERE `Status` IS NULL OR `Status`=''",
            "CREATE TABLE IF NOT EXISTS `tblCompany` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `AdminId`   INT UNSIGNED NOT NULL,
                `Name`      VARCHAR(150) NOT NULL,
                `Address`   TEXT NULL,
                `Phone`     VARCHAR(20) NULL DEFAULT NULL,
                `Email`     VARCHAR(150) NULL DEFAULT NULL,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_admin` (`AdminId`)
            ) ENGINE=InnoDB",
        ],
    ],

    [
        'id'   => 'M003',
        'desc' => 'Employee master table (tblEmployee)',
        'check' => "SELECT 1 FROM `tblEmployee` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblEmployee` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`    INT UNSIGNED NOT NULL,
                `EmployeeCode` VARCHAR(50)  NOT NULL DEFAULT '',
                `EnrollId`     VARCHAR(50)  NOT NULL DEFAULT '' COMMENT 'Maps to biometric device EnrollId',
                `Name`         VARCHAR(100) NOT NULL,
                `Email`        VARCHAR(150) NULL DEFAULT NULL,
                `Phone`        VARCHAR(20)  NULL DEFAULT NULL,
                `Department`   VARCHAR(100) NULL DEFAULT NULL,
                `Designation`  VARCHAR(100) NULL DEFAULT NULL,
                `JoinDate`     DATE         NULL DEFAULT NULL,
                `Status`       ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
                `CreatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`),
                INDEX `idx_enroll`  (`EnrollId`)
            ) ENGINE=InnoDB",
        ],
    ],

    [
        'id'   => 'M004',
        'desc' => 'Shifts, Holidays, Leaves, Overtime, Contractor/Photo columns on tblEmployee',
        'check' => "SELECT 1 FROM `tblShift` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblEmployee` ADD COLUMN `Contractor` VARCHAR(100) NULL DEFAULT NULL AFTER `Department`",
            "ALTER TABLE `tblEmployee` ADD COLUMN `Photo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'filename in uploads/employees/' AFTER `Contractor`",

            "CREATE TABLE IF NOT EXISTS `tblShift` (
                `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`        INT UNSIGNED NOT NULL,
                `ShiftName`        VARCHAR(100) NOT NULL,
                `ArrivalTime`      TIME NOT NULL COMMENT 'Expected arrival',
                `DepartureTime`    TIME NOT NULL COMMENT 'Expected departure',
                `MinArrivalTime`   TIME NULL DEFAULT NULL COMMENT 'Earliest gate open',
                `MaxArrivalTime`   TIME NULL DEFAULT NULL COMMENT 'Latest arrival before marked late',
                `MaxDepartureTime` TIME NULL DEFAULT NULL COMMENT 'Latest departure counted',
                `HrsP`             DECIMAL(4,2) NOT NULL DEFAULT 8.00 COMMENT 'Hours for full-day present',
                `HrsHlf`           DECIMAL(4,2) NOT NULL DEFAULT 4.00 COMMENT 'Hours for half-day present',
                `IsActive`         TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblHoliday` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`   INT UNSIGNED NOT NULL,
                `HolidayDate` DATE NOT NULL,
                `Name`        VARCHAR(150) NOT NULL,
                `Type`        ENUM('national','optional','restricted') NOT NULL DEFAULT 'national',
                `CreatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_company_date` (`CompanyId`, `HolidayDate`),
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblLeave` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`  INT UNSIGNED NOT NULL,
                `EmployeeId` INT UNSIGNED NOT NULL,
                `LeaveDate`  DATE NOT NULL,
                `LeaveType`  ENUM('full_day','half_am','half_pm') NOT NULL DEFAULT 'full_day',
                `Reason`     VARCHAR(255) NULL DEFAULT NULL,
                `CreatedBy`  INT UNSIGNED NOT NULL,
                `CreatedAt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_emp_date` (`EmployeeId`, `LeaveDate`),
                INDEX `idx_company`  (`CompanyId`),
                INDEX `idx_employee` (`EmployeeId`),
                INDEX `idx_date`     (`LeaveDate`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblOvertime` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`  INT UNSIGNED NOT NULL,
                `EmployeeId` INT UNSIGNED NOT NULL,
                `OTDate`     DATE NOT NULL,
                `OTHours`    DECIMAL(4,2) NOT NULL DEFAULT 0.00,
                `Reason`     VARCHAR(255) NULL DEFAULT NULL,
                `CreatedBy`  INT UNSIGNED NOT NULL,
                `CreatedAt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_emp_date` (`EmployeeId`, `OTDate`),
                INDEX `idx_company`  (`CompanyId`),
                INDEX `idx_employee` (`EmployeeId`),
                INDEX `idx_date`     (`OTDate`)
            ) ENGINE=InnoDB",
        ],
    ],

    [
        'id'   => 'M005',
        'desc' => 'Extended employee master — Home/Profile/Salary/Nominee/Family fields',
        'check' => "SELECT `FatherName` FROM `tblEmployee` LIMIT 1",
        'stmts' => [
            // Home tab — new fields
            "ALTER TABLE `tblEmployee`
                ADD COLUMN `Sr`               INT           NULL DEFAULT NULL AFTER `EnrollId`,
                ADD COLUMN `FatherName`       VARCHAR(100)  NULL DEFAULT NULL AFTER `Name`,
                ADD COLUMN `DOB`              DATE          NULL DEFAULT NULL,
                ADD COLUMN `Age`              VARCHAR(20)   NULL DEFAULT NULL,
                ADD COLUMN `Gender`           VARCHAR(20)   NULL DEFAULT NULL,
                ADD COLUMN `PresentAdd`       TEXT          NULL DEFAULT NULL,
                ADD COLUMN `PermanentAdd`     TEXT          NULL DEFAULT NULL,
                ADD COLUMN `WeekdayNo`        INT           NULL DEFAULT NULL,
                ADD COLUMN `ShiftNo`          INT           NULL DEFAULT NULL,
                ADD COLUMN `ShiftRotation`    VARCHAR(50)   NULL DEFAULT NULL,
                ADD COLUMN `ShiftRotationDate` DATE         NULL DEFAULT NULL,
                ADD COLUMN `BasicSalary`      DECIMAL(12,2) NULL DEFAULT NULL,
                ADD COLUMN `OT`               TINYINT(1)    NOT NULL DEFAULT 0,
                ADD COLUMN `DOL`              DATE          NULL DEFAULT NULL COMMENT 'Date of leaving',
                ADD COLUMN `InterviewDate`    DATE          NULL DEFAULT NULL,
                ADD COLUMN `AppointmentDate`  DATE          NULL DEFAULT NULL,
                ADD COLUMN `AppDate`          DATE          NULL DEFAULT NULL,
                ADD COLUMN `PlaceOfBirth`     VARCHAR(100)  NULL DEFAULT NULL,
                ADD COLUMN `Place`            VARCHAR(100)  NULL DEFAULT NULL,
                ADD COLUMN `AgeProof`         VARCHAR(100)  NULL DEFAULT NULL",

            // Profile tab — new fields
            "ALTER TABLE `tblEmployee`
                ADD COLUMN `FatherDOB`           DATE         NULL DEFAULT NULL,
                ADD COLUMN `FatherAge`           VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `FatherAadharNo`      VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `RelFatherHusband`    VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `MotherName`          VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `MotherDOB`           DATE         NULL DEFAULT NULL,
                ADD COLUMN `MotherAge`           VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `MotherAadharNo`      VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `CharacterCertificate` VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `MaritalStatus`       VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Qualification`       VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `EmployeeType`        VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `PassportNo`          VARCHAR(30)  NULL DEFAULT NULL,
                ADD COLUMN `DriveLicenseNo`      VARCHAR(30)  NULL DEFAULT NULL,
                ADD COLUMN `VoterID`             VARCHAR(30)  NULL DEFAULT NULL,
                ADD COLUMN `AdhaarID`            VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Religion`            VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `Nationality`         VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `EmploymentCategory`  VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `EmploymentType`      VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `Grade`               VARCHAR(30)  NULL DEFAULT NULL,
                ADD COLUMN `PhoneNo`             VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `BloodGroup`          VARCHAR(5)   NULL DEFAULT NULL,
                ADD COLUMN `MachineNo`           VARCHAR(30)  NULL DEFAULT NULL,
                ADD COLUMN `Thana`               VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `RegionCode`          VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `District`            VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `Dispensery`          VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `HairColor`           VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `IdentityMark`        VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `Height`              VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `WitnessName1`        VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `WitnessAdd1`         TEXT         NULL DEFAULT NULL,
                ADD COLUMN `WitnessName2`        VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `WitnessAdd2`         TEXT         NULL DEFAULT NULL",

            // Salary tab — new fields
            "ALTER TABLE `tblEmployee`
                ADD COLUMN `PastExp`               VARCHAR(20)   NULL DEFAULT NULL,
                ADD COLUMN `PrevEmployerName`      VARCHAR(100)  NULL DEFAULT NULL,
                ADD COLUMN `PrevEmployerCompany`   VARCHAR(100)  NULL DEFAULT NULL,
                ADD COLUMN `PrevEmployerContactNo` VARCHAR(20)   NULL DEFAULT NULL,
                ADD COLUMN `PrevDOJ`               DATE          NULL DEFAULT NULL,
                ADD COLUMN `PrevDOL`               DATE          NULL DEFAULT NULL,
                ADD COLUMN `OldPfNo`               VARCHAR(30)   NULL DEFAULT NULL,
                ADD COLUMN `OldEsicNo`             VARCHAR(30)   NULL DEFAULT NULL,
                ADD COLUMN `EmergencyNo`           VARCHAR(20)   NULL DEFAULT NULL,
                ADD COLUMN `UAN`                   VARCHAR(20)   NULL DEFAULT NULL,
                ADD COLUMN `PfNo`                  VARCHAR(30)   NULL DEFAULT NULL,
                ADD COLUMN `EsiNo`                 VARCHAR(30)   NULL DEFAULT NULL,
                ADD COLUMN `PanNo`                 VARCHAR(15)   NULL DEFAULT NULL,
                ADD COLUMN `PfAreaCode`            VARCHAR(20)   NULL DEFAULT NULL,
                ADD COLUMN `DA`                    DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN `Hra`                   DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN `Medical`               DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN `Conveyence`            DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN `OtherAllowance`        DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN `CC_Allowance`          DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN `GradeAmt`              DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN `GrossSalary`           DECIMAL(12,2) NULL DEFAULT NULL,
                ADD COLUMN `BankName`              VARCHAR(100)  NULL DEFAULT NULL,
                ADD COLUMN `BranchName`            VARCHAR(100)  NULL DEFAULT NULL,
                ADD COLUMN `BankAcNo`              VARCHAR(30)   NULL DEFAULT NULL,
                ADD COLUMN `IFSCCode`              VARCHAR(15)   NULL DEFAULT NULL,
                ADD COLUMN `LicPolicyNo`           VARCHAR(30)   NULL DEFAULT NULL",

            // Nominee tab — new fields
            "ALTER TABLE `tblEmployee`
                ADD COLUMN `FH`                       VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `SpouseName`               VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `SpouseAadharNo`           VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Nominee1`                 VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `NomineeRelation1`         VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `NomineeDOB1`              DATE         NULL DEFAULT NULL,
                ADD COLUMN `NomineeAge1`              VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `NomineeAdd1`              TEXT         NULL DEFAULT NULL,
                ADD COLUMN `Nominee1FatherHusband`    VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `Nominee1RelFatherHusband` VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `Nominee2`                 VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `NomineeRelation2`         VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `NomineeDOB2`              DATE         NULL DEFAULT NULL,
                ADD COLUMN `NomineeAge2`              VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `NomineeAdd2`              TEXT         NULL DEFAULT NULL,
                ADD COLUMN `Nominee2FatherHusband`    VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `Nominee2RelFatherHusband` VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `Rel1`                     VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `FamilyMember1`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `MemberAdhaar1`            VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Member1DOB`               DATE         NULL DEFAULT NULL,
                ADD COLUMN `MemberAge1`               VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Member1ResidingWith`      VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `Rel2`                     VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `FamilyMember2`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `MemberAdhaar2`            VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Member2DOB`               DATE         NULL DEFAULT NULL,
                ADD COLUMN `MemberAge2`               VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Member2ResidingWith`      VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `Rel3`                     VARCHAR(50)  NULL DEFAULT NULL,
                ADD COLUMN `FamilyMember3`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `MemberAdhaar3`            VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Member3DOB`               DATE         NULL DEFAULT NULL,
                ADD COLUMN `MemberAge3`               VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Member3ResidingWith`      VARCHAR(50)  NULL DEFAULT NULL",

            // Family tab — children fields
            "ALTER TABLE `tblEmployee`
                ADD COLUMN `SD1`               VARCHAR(10)  NULL DEFAULT NULL,
                ADD COLUMN `Child1`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `ChildAdhaar1`      VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child1DOB`         DATE         NULL DEFAULT NULL,
                ADD COLUMN `ChildAge1`         VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child1ResidingWith` VARCHAR(50) NULL DEFAULT NULL,
                ADD COLUMN `SD2`               VARCHAR(10)  NULL DEFAULT NULL,
                ADD COLUMN `Child2`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `ChildAdhaar2`      VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child2DOB`         DATE         NULL DEFAULT NULL,
                ADD COLUMN `ChildAge2`         VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child2ResidingWith` VARCHAR(50) NULL DEFAULT NULL,
                ADD COLUMN `SD3`               VARCHAR(10)  NULL DEFAULT NULL,
                ADD COLUMN `Child3`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `ChildAdhaar3`      VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child3DOB`         DATE         NULL DEFAULT NULL,
                ADD COLUMN `ChildAge3`         VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child3ResidingWith` VARCHAR(50) NULL DEFAULT NULL,
                ADD COLUMN `SD4`               VARCHAR(10)  NULL DEFAULT NULL,
                ADD COLUMN `Child4`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `ChildAdhaar4`      VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child4DOB`         DATE         NULL DEFAULT NULL,
                ADD COLUMN `ChildAge4`         VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child4ResidingWith` VARCHAR(50) NULL DEFAULT NULL,
                ADD COLUMN `SD5`               VARCHAR(10)  NULL DEFAULT NULL,
                ADD COLUMN `Child5`            VARCHAR(100) NULL DEFAULT NULL,
                ADD COLUMN `ChildAdhaar5`      VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child5DOB`         DATE         NULL DEFAULT NULL,
                ADD COLUMN `ChildAge5`         VARCHAR(20)  NULL DEFAULT NULL,
                ADD COLUMN `Child5ResidingWith` VARCHAR(50) NULL DEFAULT NULL",
        ],
    ],
    [
        'id'    => 'M006',
        'desc'  => 'Attendance pipeline — tblPunchLog (local) + tblPunchLogCorrection',
        'check' => "SELECT `IsProcessed` FROM `tblPunchLog` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblPunchLog` (
                `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `CompanyId`   INT UNSIGNED    NOT NULL,
                `EmpCode`     VARCHAR(50)     NOT NULL DEFAULT '',
                `EnrollId`    VARCHAR(50)     NOT NULL DEFAULT '',
                `PunchTime`   DATETIME        NOT NULL,
                `PunchType`   TINYINT         NOT NULL DEFAULT 0 COMMENT '0=Unknown 1=In 2=Out',
                `DeviceSerial` VARCHAR(100)   NOT NULL DEFAULT '',
                `RawStamp`    VARCHAR(20)     NOT NULL DEFAULT '',
                `IsProcessed` TINYINT         NOT NULL DEFAULT 0 COMMENT '0=Pending 1=Done 2=Skip',
                `SyncedAt`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_punch` (`CompanyId`, `EnrollId`, `PunchTime`),
                KEY `idx_pending`  (`IsProcessed`, `CompanyId`, `PunchTime`),
                KEY `idx_emp_date` (`CompanyId`, `EmpCode`, `PunchTime`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblPunchLogCorrection` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `CompanyId`   INT UNSIGNED NOT NULL,
                `EmpCode`     VARCHAR(50)  NOT NULL,
                `tDate`       DATE         NOT NULL,
                `InTime`      TIME         DEFAULT NULL,
                `OutTime`     TIME         DEFAULT NULL,
                `AttStatus`   CHAR(4)      DEFAULT NULL COMMENT 'Force status: P/A/HD/WO/L etc',
                `Reason`      VARCHAR(200) NOT NULL DEFAULT '',
                `CorrectedBy` INT UNSIGNED NOT NULL DEFAULT 0,
                `CorrectedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_corr` (`CompanyId`, `EmpCode`, `tDate`),
                KEY `idx_date` (`CompanyId`, `tDate`)
            ) ENGINE=InnoDB",
        ],
    ],
    [
        'id'   => 'M007',
        'desc' => 'Device enrollment map (SerialNumber+EnrollId → CompanyId+EmpCode); fix tblPunchLog unique key',
        'check' => "SELECT 1 FROM `tblDeviceEnrollment` LIMIT 1",
        'stmts' => [
            // Authoritative lookup: (DeviceSerial, EnrollId) → one company-employee pair.
            // Same EnrollId on different machines = different employees on each machine.
            "CREATE TABLE IF NOT EXISTS `tblDeviceEnrollment` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `DeviceSerial` VARCHAR(100) NOT NULL,
                `EnrollId`     VARCHAR(50)  NOT NULL,
                `CompanyId`    INT UNSIGNED NOT NULL,
                `EmpCode`      VARCHAR(50)  NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_device_enroll` (`DeviceSerial`, `EnrollId`),
                KEY `idx_company_emp` (`CompanyId`, `EmpCode`)
            ) ENGINE=InnoDB",

            // Fix tblPunchLog unique key: source uniqueness is (DeviceSerial, EnrollId, PunchTime),
            // not (CompanyId, EnrollId, PunchTime).
            "ALTER TABLE `tblPunchLog` DROP KEY `uq_punch`",
            "ALTER TABLE `tblPunchLog` ADD UNIQUE KEY `uq_punch` (`DeviceSerial`, `EnrollId`, `PunchTime`)",
        ],
    ],
    [
        'id'   => 'M009',
        'desc' => 'Leave master — tblLeaveType, tblLeavePolicy, tblLeavePolicyDetail, tblEmployeeLeavePolicy, tblLeaveBalance; LeaveTypeId on tblLeave',
        'check' => "SELECT 1 FROM `tblLeaveType` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblLeaveType` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`       INT UNSIGNED NOT NULL,
                `Code`            VARCHAR(10)  NOT NULL,
                `Name`            VARCHAR(100) NOT NULL,
                `IsPaid`          TINYINT(1)   NOT NULL DEFAULT 1,
                `IsHalfDayAllowed` TINYINT(1)  NOT NULL DEFAULT 1,
                `IsActive`        TINYINT(1)   NOT NULL DEFAULT 1,
                `CreatedAt`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_company_code` (`CompanyId`, `Code`),
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblLeavePolicy` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`  INT UNSIGNED NOT NULL,
                `PolicyName` VARCHAR(100) NOT NULL,
                `IsActive`   TINYINT(1)  NOT NULL DEFAULT 1,
                `CreatedAt`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblLeavePolicyDetail` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `PolicyId`    INT UNSIGNED    NOT NULL,
                `LeaveTypeId` INT UNSIGNED    NOT NULL,
                `DaysPerYear` DECIMAL(5,1)   NOT NULL DEFAULT 0,
                UNIQUE KEY `uq_policy_type` (`PolicyId`, `LeaveTypeId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblEmployeeLeavePolicy` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `EmployeeId` INT UNSIGNED NOT NULL,
                `CompanyId`  INT UNSIGNED NOT NULL,
                `PolicyId`   INT UNSIGNED NOT NULL,
                `Year`       YEAR        NOT NULL,
                `AssignedAt` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_emp_year` (`EmployeeId`, `Year`),
                INDEX `idx_company_year` (`CompanyId`, `Year`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblLeaveBalance` (
                `id`          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
                `EmployeeId`  INT UNSIGNED  NOT NULL,
                `CompanyId`   INT UNSIGNED  NOT NULL,
                `LeaveTypeId` INT UNSIGNED  NOT NULL,
                `Year`        YEAR          NOT NULL,
                `Allocated`   DECIMAL(5,1)  NOT NULL DEFAULT 0,
                `Used`        DECIMAL(5,1)  NOT NULL DEFAULT 0,
                `Adjusted`    DECIMAL(5,1)  NOT NULL DEFAULT 0,
                UNIQUE KEY `uq_emp_type_year` (`EmployeeId`, `LeaveTypeId`, `Year`),
                INDEX `idx_company_year` (`CompanyId`, `Year`)
            ) ENGINE=InnoDB",

            "ALTER TABLE `tblLeave` ADD COLUMN `LeaveTypeId` INT UNSIGNED NULL DEFAULT NULL AFTER `LeaveType`",
            "ALTER TABLE `tblLeave` ADD COLUMN `LeaveCode` VARCHAR(10) NULL DEFAULT NULL AFTER `LeaveTypeId`",
        ],
    ],
    [
        'id'    => 'M008',
        'desc'  => 'API keys: replace Company with UserId (user-wise keys)',
        'check' => "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblApiKeys' AND COLUMN_NAME='UserId' LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblApiKeys` ADD COLUMN `UserId` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `Label`",
            "ALTER TABLE `tblApiKeys` DROP COLUMN `Company`",
        ],
    ],
    [
        'id'    => 'M010',
        'desc'  => 'PunchLog sharding — rename monolithic tblPunchLog to tblPunchLog_legacy; ShardManager creates YYMM shards on demand',
        'check' => "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblPunchLog_legacy' LIMIT 1",
        'stmts' => [
            "RENAME TABLE `tblPunchLog` TO `tblPunchLog_legacy`",
        ],
    ],
    [
        'id'    => 'M011',
        'desc'  => 'tblDevices: add LastSyncedAt to track when ADMS punch sync last ran per device',
        'check' => "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblDevices' AND COLUMN_NAME='LastSyncedAt' LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblDevices` ADD COLUMN `LastSyncedAt` DATETIME NULL DEFAULT NULL AFTER `Company`",
        ],
    ],
    [
        'id'    => 'M012',
        'desc'  => 'tblUser: add CompanyId to link user-role accounts to a specific company',
        'check' => "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblUser' AND COLUMN_NAME='CompanyId' LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblUser` ADD COLUMN `CompanyId` INT UNSIGNED NULL DEFAULT NULL AFTER `ParentAdminId`",
        ],
    ],
    [
        'id'    => 'M013',
        'desc'  => 'Payroll — tblPayrollSettings, tblEmployeePayroll, tblPayrollComponent, tblEmployeePayComponent, tblPayrollRun, tblPayrollDetail',
        'check' => "SELECT 1 FROM `tblPayrollSettings` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblPayrollSettings` (
                `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`           INT UNSIGNED NOT NULL,
                `WorkingDaysPerMonth` INT NOT NULL DEFAULT 26,
                `PFEmployeeRate`      DECIMAL(5,2) NOT NULL DEFAULT 12.00,
                `PFEmployerRate`      DECIMAL(5,2) NOT NULL DEFAULT 12.00,
                `PFWageCeiling`       INT NOT NULL DEFAULT 15000,
                `ESIEmployeeRate`     DECIMAL(5,2) NOT NULL DEFAULT 0.75,
                `ESIEmployerRate`     DECIMAL(5,2) NOT NULL DEFAULT 3.25,
                `ESIWageCeiling`      INT NOT NULL DEFAULT 21000,
                `OTMultiplier`        DECIMAL(4,2) NOT NULL DEFAULT 1.50,
                `UpdatedAt`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblEmployeePayroll` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `EmployeeId`    INT UNSIGNED NOT NULL,
                `CompanyId`     INT UNSIGNED NOT NULL,
                `WageType`      ENUM('monthly','daily','hourly','piece_rate') NOT NULL DEFAULT 'monthly',
                `WageRate`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `HoursPerDay`   DECIMAL(4,2) NOT NULL DEFAULT 8.00,
                `OTAllowed`     TINYINT(1) NOT NULL DEFAULT 1,
                `OTMultiplier`  DECIMAL(4,2) NULL DEFAULT NULL COMMENT 'NULL = use company default',
                `PFApplicable`  TINYINT(1) NOT NULL DEFAULT 1,
                `ESIApplicable` TINYINT(1) NOT NULL DEFAULT 1,
                `TDSApplicable` TINYINT(1) NOT NULL DEFAULT 0,
                `UpdatedAt`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_employee` (`EmployeeId`),
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblPayrollComponent` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`    INT UNSIGNED NOT NULL,
                `Name`         VARCHAR(100) NOT NULL,
                `Type`         ENUM('earning','deduction') NOT NULL DEFAULT 'earning',
                `CalcType`     ENUM('fixed','percent_basic','percent_gross') NOT NULL DEFAULT 'fixed',
                `DefaultValue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `IsActive`     TINYINT(1) NOT NULL DEFAULT 1,
                `SortOrder`    INT NOT NULL DEFAULT 0,
                `CreatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblEmployeePayComponent` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `EmployeeId`  INT UNSIGNED NOT NULL,
                `CompanyId`   INT UNSIGNED NOT NULL,
                `ComponentId` INT UNSIGNED NOT NULL,
                `Value`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                UNIQUE KEY `uq_emp_comp` (`EmployeeId`, `ComponentId`),
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblPayrollRun` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`   INT UNSIGNED NOT NULL,
                `RunMonth`    CHAR(7) NOT NULL COMMENT 'YYYY-MM',
                `Status`      ENUM('draft','finalized') NOT NULL DEFAULT 'draft',
                `CreatedBy`   INT UNSIGNED NOT NULL DEFAULT 0,
                `CreatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `FinalizedAt` DATETIME NULL DEFAULT NULL,
                UNIQUE KEY `uq_company_month` (`CompanyId`, `RunMonth`),
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblPayrollDetail` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `RunId`           INT UNSIGNED NOT NULL,
                `EmployeeId`      INT UNSIGNED NOT NULL,
                `CompanyId`       INT UNSIGNED NOT NULL,
                `PresentDays`     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `HalfDays`        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `AbsentDays`      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `OTHours`         DECIMAL(6,2) NOT NULL DEFAULT 0.00,
                `Pieces`          INT NOT NULL DEFAULT 0,
                `WageType`        VARCHAR(20) NOT NULL DEFAULT 'monthly',
                `WageRate`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `EarnedBasic`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `EarningsJson`    TEXT NULL DEFAULT NULL,
                `OTAmount`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `TotalEarnings`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `PFEmployee`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `PFEmployer`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `ESIEmployee`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `ESIEmployer`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `TDSAmount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `DeductionsJson`  TEXT NULL DEFAULT NULL,
                `TotalDeductions` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `NetSalary`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `Remarks`         VARCHAR(255) NOT NULL DEFAULT '',
                UNIQUE KEY `uq_run_emp` (`RunId`, `EmployeeId`),
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",
        ],
    ],
    [
        'id'    => 'M014',
        'desc'  => 'Shift Management — tblShiftCycle, tblShiftCycleDay, tblEmployeeShiftCycle, tblCompOff',
        'check' => "SELECT 1 FROM `tblShiftCycle` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblShiftCycle` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`   INT UNSIGNED NOT NULL,
                `Name`        VARCHAR(100) NOT NULL,
                `CycleDays`   INT NOT NULL DEFAULT 14 COMMENT 'Length of the rotation in days',
                `Description` VARCHAR(255) NULL DEFAULT NULL,
                `IsActive`    TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblShiftCycleDay` (
                `id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CycleId`  INT UNSIGNED NOT NULL,
                `DayNo`    INT NOT NULL COMMENT '1-based day number within the cycle',
                `ShiftId`  INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = rest/week-off day',
                UNIQUE KEY `uq_cycle_day` (`CycleId`, `DayNo`),
                INDEX `idx_cycle` (`CycleId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblEmployeeShiftCycle` (
                `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `EmployeeId`     INT UNSIGNED NOT NULL,
                `CompanyId`      INT UNSIGNED NOT NULL,
                `CycleId`        INT UNSIGNED NOT NULL,
                `CycleStartDate` DATE NOT NULL COMMENT 'Calendar date that maps to DayNo=1',
                `IsActive`       TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_employee` (`EmployeeId`),
                INDEX `idx_company`  (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblCompOff` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`   INT UNSIGNED NOT NULL,
                `EmployeeId`  INT UNSIGNED NOT NULL,
                `WorkedOn`    DATE NOT NULL COMMENT 'Date employee worked on their off/holiday',
                `CompOffDate` DATE NULL DEFAULT NULL COMMENT 'Date the comp off was redeemed',
                `Reason`      VARCHAR(255) NULL DEFAULT NULL,
                `Status`      ENUM('pending','approved','rejected','redeemed') NOT NULL DEFAULT 'pending',
                `ApprovedBy`  INT UNSIGNED NULL DEFAULT NULL,
                `CreatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company`  (`CompanyId`),
                INDEX `idx_employee` (`EmployeeId`)
            ) ENGINE=InnoDB",
        ],
    ],
    [
        'id'    => 'M015',
        'desc'  => 'HR Documents — tblDocTemplate, tblEmployeeDocument, tblIssuedMaterial, tblFnFTemplate, tblFnFSettlement, tblFnFItem',
        'check' => "SELECT 1 FROM `tblDocTemplate` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblDocTemplate` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId` INT UNSIGNED NOT NULL,
                `Name`      VARCHAR(150) NOT NULL,
                `DocType`   ENUM('offer_letter','joining_letter','appointment_letter','experience_letter','warning_letter','termination_letter','custom') NOT NULL DEFAULT 'custom',
                `Content`   MEDIUMTEXT NOT NULL,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblEmployeeDocument` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`  INT UNSIGNED NOT NULL,
                `EmployeeId` INT UNSIGNED NOT NULL,
                `TemplateId` INT UNSIGNED NULL DEFAULT NULL,
                `Title`      VARCHAR(200) NOT NULL,
                `DocType`    VARCHAR(50) NOT NULL DEFAULT 'custom',
                `Content`    MEDIUMTEXT NULL DEFAULT NULL COMMENT 'Rendered HTML for generated docs',
                `FilePath`   VARCHAR(255) NULL DEFAULT NULL COMMENT 'Relative path for uploaded files',
                `IssuedOn`   DATE NOT NULL,
                `CreatedBy`  INT UNSIGNED NOT NULL DEFAULT 0,
                `CreatedAt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_company`  (`CompanyId`),
                INDEX `idx_employee` (`EmployeeId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblIssuedMaterial` (
                `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`         INT UNSIGNED NOT NULL,
                `EmployeeId`        INT UNSIGNED NOT NULL,
                `ItemName`          VARCHAR(150) NOT NULL,
                `ItemCode`          VARCHAR(50)  NULL DEFAULT NULL,
                `SerialNo`          VARCHAR(100) NULL DEFAULT NULL,
                `IssuedOn`          DATE NOT NULL,
                `ReturnDue`         DATE NULL DEFAULT NULL,
                `ReturnedOn`        DATE NULL DEFAULT NULL,
                `ConditionOnIssue`  VARCHAR(30) NOT NULL DEFAULT 'good',
                `ConditionOnReturn` VARCHAR(30) NULL DEFAULT NULL,
                `Remarks`           VARCHAR(255) NULL DEFAULT NULL,
                `CreatedBy`         INT UNSIGNED NOT NULL DEFAULT 0,
                `CreatedAt`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company`  (`CompanyId`),
                INDEX `idx_employee` (`EmployeeId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblFnFTemplate` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId` INT UNSIGNED NOT NULL,
                `ItemName`  VARCHAR(150) NOT NULL,
                `Category`  VARCHAR(50) NOT NULL DEFAULT 'general',
                `SortOrder` INT NOT NULL DEFAULT 0,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblFnFSettlement` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`   INT UNSIGNED NOT NULL,
                `EmployeeId`  INT UNSIGNED NOT NULL,
                `InitiatedOn` DATE NOT NULL,
                `CompletedOn` DATE NULL DEFAULT NULL,
                `Status`      ENUM('open','completed') NOT NULL DEFAULT 'open',
                `Remarks`     TEXT NULL DEFAULT NULL,
                `CreatedBy`   INT UNSIGNED NOT NULL DEFAULT 0,
                `CreatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company`  (`CompanyId`),
                INDEX `idx_employee` (`EmployeeId`)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS `tblFnFItem` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `SettlementId` INT UNSIGNED NOT NULL,
                `ItemName`     VARCHAR(150) NOT NULL,
                `Category`     VARCHAR(50) NOT NULL DEFAULT 'general',
                `SortOrder`    INT NOT NULL DEFAULT 0,
                `Status`       ENUM('pending','done','na') NOT NULL DEFAULT 'pending',
                `Remarks`      VARCHAR(255) NULL DEFAULT NULL,
                `DoneAt`       DATETIME NULL DEFAULT NULL,
                `DoneBy`       INT UNSIGNED NULL DEFAULT NULL,
                INDEX `idx_settlement` (`SettlementId`)
            ) ENGINE=InnoDB",
        ],
    ],

    [
        'id'    => 'M016',
        'desc'  => 'Add MachinesLimit and EmpLimit to tblUser',
        'check' => "SELECT `MachinesLimit` FROM `tblUser` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblUser`
                ADD COLUMN `MachinesLimit` INT NOT NULL DEFAULT 5  COMMENT '-1 = unlimited' AFTER `CompanyLimit`,
                ADD COLUMN `EmpLimit`      INT NOT NULL DEFAULT 100 COMMENT '-1 = unlimited' AFTER `MachinesLimit`",
        ],
    ],
    [
        'id'    => 'M017',
        'desc'  => 'Shift lunch break — HasLunch, LunchOutTime, LunchInTime on tblShift',
        'check' => "SELECT `HasLunch` FROM `tblShift` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblShift`
                ADD COLUMN `HasLunch`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Shift includes a lunch break' AFTER `HrsHlf`,
                ADD COLUMN `LunchOutTime` TIME NULL DEFAULT NULL COMMENT 'Lunch start — employee punches out' AFTER `HasLunch`,
                ADD COLUMN `LunchInTime`  TIME NULL DEFAULT NULL COMMENT 'Lunch end — employee punches back in' AFTER `LunchOutTime`",
        ],
    ],
    [
        'id'    => 'M018',
        'desc'  => "Add 'operator' role to tblUser.Role (co-admin, everything except user management)",
        // Pure ENUM change has no column/table to probe, so a marker table gates the check:
        // it is absent (query throws → pending) until this migration creates it.
        'check' => "SELECT 1 FROM `tblMigOperatorRole` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblUser` MODIFY COLUMN `Role`
                ENUM('superadmin','admin','user','operator') NOT NULL DEFAULT 'user'",
            "CREATE TABLE IF NOT EXISTS `tblMigOperatorRole` (
                `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1
            ) ENGINE=InnoDB",
        ],
    ],
    [
        'id'    => 'M019',
        'desc'  => 'Per-date shift override — add ShiftNo to tblPunchLogCorrection (attendance grid "mark for day" shift)',
        'check' => "SELECT `ShiftNo` FROM `tblPunchLogCorrection` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblPunchLogCorrection`
                ADD COLUMN `ShiftNo` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Per-date shift override → tblShift.id' AFTER `AttStatus`",
        ],
    ],
    [
        'id'    => 'M029',
        'desc'  => 'Login log — tblLoginLog (successful & failed sign-ins, for admin/superadmin audit)',
        'check' => "SELECT 1 FROM `tblLoginLog` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblLoginLog` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `UserId`    INT UNSIGNED NULL DEFAULT NULL,
                `Email`     VARCHAR(150) NOT NULL DEFAULT '',
                `IpAddress` VARCHAR(45)  NOT NULL DEFAULT '',
                `UserAgent` VARCHAR(255) NOT NULL DEFAULT '',
                `Method`    VARCHAR(30)  NOT NULL DEFAULT 'password' COMMENT 'password/email_otp/whatsapp_otp/password_2fa',
                `Status`    ENUM('success','failed') NOT NULL DEFAULT 'success',
                `LoggedAt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user` (`UserId`),
                INDEX `idx_time` (`LoggedAt`)
            ) ENGINE=InnoDB",
        ],
    ],
    [
        'id'    => 'M028',
        'desc'  => 'Department master — tblDepartment (per-company) + seed from existing tblEmployee departments',
        'check' => "SELECT 1 FROM `tblDepartment` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblDepartment` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId` INT UNSIGNED NOT NULL,
                `Name`      VARCHAR(100) NOT NULL,
                `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`),
                UNIQUE KEY `uq_company_name` (`CompanyId`, `Name`)
            ) ENGINE=InnoDB",
            // Pre-populate the master from departments already used on employees.
            "INSERT IGNORE INTO `tblDepartment` (`CompanyId`, `Name`)
                SELECT DISTINCT `CompanyId`, TRIM(`Department`) FROM `tblEmployee`
                WHERE `Department` IS NOT NULL AND TRIM(`Department`) <> ''",
        ],
    ],
    [
        'id'    => 'M027',
        'desc'  => '2FA delivery channels — tblUser.TwoFactorChannels (email/whatsapp/sms) + Mobile for OTP delivery',
        'check' => "SELECT `TwoFactorChannels` FROM `tblUser` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblUser`
                ADD COLUMN `TwoFactorChannels` VARCHAR(50) NULL DEFAULT NULL COMMENT 'csv: email,whatsapp,sms' AFTER `TwoFactorEnabled`,
                ADD COLUMN `Mobile`            VARCHAR(20) NULL DEFAULT NULL COMMENT 'for SMS/WhatsApp OTP delivery'",
        ],
    ],
    [
        'id'    => 'M026',
        'desc'  => 'WhatsApp Business API settings — tblWhatsappSettings (per-company + global default row) + seed default',
        'check' => "SELECT 1 FROM `tblWhatsappSettings` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblWhatsappSettings` (
                `id`                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`              INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = global default channel',
                `Provider`              ENUM('meta','aisensy','gupshup') NOT NULL DEFAULT 'meta',
                `MetaPhoneNumberId`      VARCHAR(100)  NULL DEFAULT NULL,
                `MetaAccessToken`        VARCHAR(1000) NULL DEFAULT NULL,
                `MetaBusinessId`         VARCHAR(100)  NULL DEFAULT NULL,
                `MetaAppId`              VARCHAR(100)  NULL DEFAULT NULL,
                `MetaAppSecret`          VARCHAR(1000) NULL DEFAULT NULL,
                `MetaApiVersion`         VARCHAR(10)   NOT NULL DEFAULT 'v25.0',
                `MetaWebhookVerifyToken` VARCHAR(200)  NULL DEFAULT NULL,
                `AisensyApiKey`          VARCHAR(500)  NULL DEFAULT NULL,
                `AisensySourceName`      VARCHAR(100)  NULL DEFAULT NULL,
                `GupshupApiKey`          VARCHAR(500)  NULL DEFAULT NULL,
                `GupshupSource`          VARCHAR(20)   NULL DEFAULT NULL,
                `GupshupAppName`         VARCHAR(100)  NULL DEFAULT NULL,
                `Enabled`                TINYINT(1) NOT NULL DEFAULT 0,
                `UpdatedAt`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_company` (`CompanyId`)
            ) ENGINE=InnoDB",
            // Seed the global default channel row (disabled until real Meta creds are entered).
            "INSERT IGNORE INTO `tblWhatsappSettings` (`CompanyId`,`Provider`,`MetaApiVersion`,`Enabled`) VALUES (0,'meta','v25.0',0)",
        ],
    ],
    [
        'id'    => 'M025',
        'desc'  => 'Development issue log — tblDevIssue (bug tracker: url, detail, snapshot, expected, AI prompt, status)',
        'check' => "SELECT 1 FROM `tblDevIssue` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblDevIssue` (
                `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId` INT UNSIGNED NOT NULL,
                `Url`       VARCHAR(1000) NULL DEFAULT NULL,
                `Detail`    TEXT NOT NULL,
                `Snapshot`  VARCHAR(255) NULL DEFAULT NULL COMMENT 'filename in uploads/dev-issues/',
                `Expected`  TEXT NULL DEFAULT NULL,
                `AiPrompt`  MEDIUMTEXT NULL DEFAULT NULL,
                `Status`    VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                `ClosedAt`  DATETIME NULL DEFAULT NULL,
                `CreatedBy` INT UNSIGNED NOT NULL DEFAULT 0,
                `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`),
                INDEX `idx_status`  (`Status`)
            ) ENGINE=InnoDB",
        ],
    ],
    [
        'id'    => 'M024',
        'desc'  => 'Two-factor auth — tblUser.TwoFactorEnabled (opt-in email OTP second factor at login)',
        'check' => "SELECT `TwoFactorEnabled` FROM `tblUser` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblUser`
                ADD COLUMN `TwoFactorEnabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Require email OTP as a 2nd factor after password'",
        ],
    ],
    [
        'id'    => 'M023',
        'desc'  => 'Full & Final payment lines — tblFnFPayItem (earnings/deductions for settlement statement)',
        'check' => "SELECT 1 FROM `tblFnFPayItem` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblFnFPayItem` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `SettlementId` INT UNSIGNED NOT NULL,
                `Label`        VARCHAR(150) NOT NULL,
                `Type`         ENUM('earning','deduction') NOT NULL DEFAULT 'earning',
                `Amount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `SortOrder`    INT NOT NULL DEFAULT 0,
                INDEX `idx_settlement` (`SettlementId`)
            ) ENGINE=InnoDB",
        ],
    ],
    [
        'id'    => 'M022',
        'desc'  => 'Overtime approval + monthly-cap→incentive settings + HR SMS (MSG91)',
        'check' => "SELECT `Status` FROM `tblOvertime` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblOvertime`
                ADD COLUMN `Status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER `Reason`,
                ADD COLUMN `ApprovedBy` INT UNSIGNED NULL DEFAULT NULL,
                ADD COLUMN `ApprovedAt` DATETIME NULL DEFAULT NULL",
            "ALTER TABLE `tblPayrollSettings`
                ADD COLUMN `OTApprovalRequired` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'OT needs approval before it counts',
                ADD COLUMN `OTMonthlyCap`       INT NOT NULL DEFAULT 48 COMMENT 'OT hours/month above which OT is paid as incentive',
                ADD COLUMN `OTIncentiveAsBonus` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Pay OT above the cap as a separate incentive/bonus line',
                ADD COLUMN `HRManagerMobile`    VARCHAR(20) NULL DEFAULT NULL COMMENT 'Mobile for OT SMS notifications'",
        ],
    ],
    [
        'id'    => 'M021',
        'desc'  => 'Signatures — tblEmployee.Signature (employee sign) + tblCompany authorized-signatory (SignImage/SignName/SignDesignation)',
        'check' => "SELECT `Signature` FROM `tblEmployee` LIMIT 1",
        'stmts' => [
            "ALTER TABLE `tblEmployee`
                ADD COLUMN `Signature` VARCHAR(255) NULL DEFAULT NULL COMMENT 'filename in uploads/employees/' AFTER `Photo`",
            "ALTER TABLE `tblCompany`
                ADD COLUMN `SignImage`       VARCHAR(255) NULL DEFAULT NULL COMMENT 'Authorized signatory sign, filename in uploads/company/',
                ADD COLUMN `SignName`        VARCHAR(150) NULL DEFAULT NULL COMMENT 'Authorized signatory name',
                ADD COLUMN `SignDesignation` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Authorized signatory designation'",
        ],
    ],
    [
        'id'    => 'M020',
        'desc'  => 'Quick wage-worker register — tblWageWorker (daily/hourly/monthly workers, minimal entry)',
        'check' => "SELECT 1 FROM `tblWageWorker` LIMIT 1",
        'stmts' => [
            "CREATE TABLE IF NOT EXISTS `tblWageWorker` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `CompanyId`    INT UNSIGNED NOT NULL,
                `Department`   VARCHAR(100) NULL DEFAULT NULL,
                `EmployeeCode` VARCHAR(50)  NOT NULL DEFAULT '',
                `Name`         VARCHAR(100) NOT NULL,
                `Activity`     VARCHAR(150) NULL DEFAULT NULL COMMENT 'Work/task performed',
                `EmpType`      VARCHAR(50)  NULL DEFAULT NULL COMMENT 'e.g. daily-wage, contract, casual',
                `Mobile`       VARCHAR(20)  NULL DEFAULT NULL,
                `Address`      TEXT         NULL DEFAULT NULL,
                `AadharNo`     VARCHAR(20)  NULL DEFAULT NULL,
                `WageType`     ENUM('hourly','daily','monthly','piece_rate') NOT NULL DEFAULT 'daily',
                `WageRate`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `ShiftId`      INT UNSIGNED NULL DEFAULT NULL COMMENT '→ tblShift.id (working timings)',
                `Status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `CreatedBy`    INT UNSIGNED NOT NULL DEFAULT 0,
                `CreatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `UpdatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_company` (`CompanyId`)
            ) ENGINE=InnoDB",
        ],
    ],
];

// ── DB connection ─────────────────────────────────────────────────────────────
$dbError = '';
$db      = null;
try {
    $db = getDb();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// ── Evaluate which migrations are pending ─────────────────────────────────────
$migrationStatus = []; // 'done' | 'pending'
if ($db) {
    foreach ($migrations as $m) {
        try {
            $db->query($m['check']);
            $migrationStatus[$m['id']] = 'done';
        } catch (PDOException $e) {
            $migrationStatus[$m['id']] = 'pending';
        }
    }
}

$allDone = $db && !in_array('pending', $migrationStatus, true);

if ($allDone && empty($_GET['show'])) {
    header('Location: index.php');
    exit;
}

// ── Run migrations on POST ────────────────────────────────────────────────────
$runResult = null;
$runError  = '';
$ranCount  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db && !$allDone) {
    try {
        foreach ($migrations as $m) {
            if ($migrationStatus[$m['id']] !== 'pending') continue;
            foreach ($m['stmts'] as $sql) {
                try {
                    $db->exec($sql);
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    $safe = strpos($msg, 'Duplicate column') !== false
                         || strpos($msg, 'already exists') !== false
                         || strpos($msg, "Can't DROP") !== false
                         || strpos($msg, 'Duplicate key name') !== false;
                    if (!$safe) throw $e;
                }
            }
            $ranCount++;
        }
        $runResult = 'success';
    } catch (PDOException $e) {
        $runResult = 'error';
        $runError  = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HRMS — Migration</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>body{background:#f4f6fb;}</style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card border-0 shadow" style="max-width:560px;width:100%">
  <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
    <i class="bi bi-building fs-5"></i>
    <span class="fw-semibold">HRMS — Database Migration</span>
  </div>
  <div class="card-body p-4">

    <?php if ($dbError): ?>
      <div class="alert alert-danger">
        <strong>Cannot connect to database.</strong><br>
        <code class="small"><?= htmlspecialchars($dbError) ?></code>
      </div>
      <hr>
      <p class="fw-semibold mb-3">Configure Database Connection</p>
      <form method="POST">
        <div class="row g-2 mb-2">
          <div class="col-8">
            <label class="form-label small">Host</label>
            <input type="text" name="db_host" class="form-control form-control-sm"
                   value="<?= htmlspecialchars(DB_HOST) ?>" required placeholder="localhost">
          </div>
          <div class="col-4">
            <label class="form-label small">Port</label>
            <input type="number" name="db_port" class="form-control form-control-sm"
                   value="<?= htmlspecialchars(DB_PORT) ?>" required placeholder="3306">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small">Database Name</label>
          <input type="text" name="db_name" class="form-control form-control-sm"
                 value="<?= htmlspecialchars(DB_NAME) ?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label small">Username</label>
          <input type="text" name="db_user" class="form-control form-control-sm"
                 value="<?= htmlspecialchars(DB_USER) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label small">Password</label>
          <div class="input-group input-group-sm">
            <input type="password" name="db_pass" id="dbpass" class="form-control"
                   value="<?= htmlspecialchars(DB_PASS) ?>">
            <button type="button" class="btn btn-outline-secondary"
                    onclick="var i=document.getElementById('dbpass');i.type=i.type==='password'?'text':'password'">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-save me-1"></i>Save &amp; Retry Connection
        </button>
      </form>

    <?php elseif ($runResult === 'success'): ?>
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= $ranCount ?> migration(s) applied successfully.</span>
      </div>
      <p class="mb-1 small"><strong>Default super-admin credentials:</strong></p>
      <ul class="small mb-3">
        <li>Email: <code>batrasagar@gmail.com</code></li>
        <li>Password: <code>1234@@</code></li>
      </ul>
      <a href="login.php" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Go to Login</a>

    <?php elseif ($runResult === 'error'): ?>
      <div class="alert alert-danger">
        <strong>Migration failed.</strong><br>
        <code class="small"><?= htmlspecialchars($runError) ?></code>
      </div>
      <form method="POST">
        <button class="btn btn-warning w-100">Retry</button>
      </form>

    <?php else: ?>
      <p class="text-muted mb-3">
        Database: <strong><?= htmlspecialchars(DB_NAME) ?></strong>
      </p>
      <table class="table table-sm mb-4">
        <thead class="table-light"><tr><th>Migration</th><th>Description</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($migrations as $m): ?>
          <?php $s = $migrationStatus[$m['id']]; ?>
          <tr>
            <td><code><?= $m['id'] ?></code></td>
            <td class="small text-muted"><?= htmlspecialchars($m['desc']) ?></td>
            <td>
              <?php if ($s === 'done'): ?>
                <span class="badge bg-success"><i class="bi bi-check-lg"></i> Done</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Pending</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (!$allDone): ?>
      <form method="POST">
        <button class="btn btn-primary w-100"><i class="bi bi-play-fill me-1"></i>Run Pending Migrations</button>
      </form>
      <?php else: ?>
      <div class="alert alert-success mb-3">All migrations are up to date.</div>
      <a href="index.php" class="btn btn-primary w-100">Go to Dashboard</a>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
