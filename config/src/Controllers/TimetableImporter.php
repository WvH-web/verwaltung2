<?php
namespace Controllers;

use PDO;

/**
 * WvH TimetableImporter
 * Importiert timetable.csv (FET-Format) in die Datenbank
 *
 * CSV-Spalten (Semikolon; UTF-8-BOM):
 * Activity Id | Day | Hour | Students Sets | Subject | Teachers | Activity Tags | Room | Comments
 *
 * Besonderheiten dieser Datei:
 * - Co-Teaching: "Björn Bredow+Nele Schmidt" → 2 separate Einträge
 * - 84 Einträge ohne Lehrer (AGs, eigenverantwortlich) → werden übersprungen
 * - Doppelstunden: gleicher Lehrer + Fach + Tag, End- = Startzeit des Folge-Slots
 * - Async-Erkennung aus Subject-Klammern: "(... asynchrone Stunde)"
 */
class TimetableImporter
{
    private PDO $db;

    private const WEEKDAYS = [
        'Montag'=>1,'Dienstag'=>2,'Mittwoch'=>3,'Donnerstag'=>4,'Freitag'=>5,
    ];

    private const SLOT_ORDER = [
        '14:00','14:45','15:45','16:30','17:30','18:15','19:10',
    ];

    public function __construct()
    {
        $this->db = getDB();
    }

    // ============================================================
    // A) CSV PARSEN
    // ============================================================

    public function parseCsv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return $this->emptyResult(['Datei nicht gefunden.']);
        }

        // Datei laden, BOM entfernen
        $raw = file_get_contents($filePath);
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
        $lines = explode("\n", str_replace("\r\n", "\n", $raw));

        // Header
        $headerLine = array_shift($lines);
        $header = str_getcsv($headerLine, ';', '"');
        $header = array_map(fn($h) => trim($h, " \"\r"), $header);

        $rows       = [];
        $errors     = [];
        $teacherSet = [];
        $subjectSet = [];
        $classSet   = [];
        $lineNum    = 1;
        $skippedNoTeacher = 0;

        foreach ($lines as $line) {
            $lineNum++;
            $line = trim($line);
            if ($line === '') continue;

            $cols = str_getcsv($line, ';', '"');
            $cols = array_map(fn($v) => trim($v, " \"\r"), $cols);

            $get = function(string $col) use ($cols, $header): string {
                $idx = array_search($col, $header);
                return ($idx !== false && isset($cols[$idx])) ? trim($cols[$idx]) : '';
            };

            $teachersRaw = $get('Teachers');

            // Einträge ohne Lehrer überspringen (AGs, eigenverantwortliche Stunden)
            if ($teachersRaw === '') {
                $skippedNoTeacher++;
                continue;
            }

            $dayStr  = $get('Day');
            $hourStr = $get('Hour');
            $weekday = self::WEEKDAYS[$dayStr] ?? null;
            if (!$weekday) {
                $errors[] = "Zeile {$lineNum}: Unbekannter Tag '{$dayStr}'";
                continue;
            }

            $times = $this->parseHour($hourStr);
            if (!$times) {
                $errors[] = "Zeile {$lineNum}: Ungültige Zeit '{$hourStr}'";
                continue;
            }

            $subject      = $get('Subject');
            $students     = $get('Students Sets');
            $subjectClean = $this->cleanSubject($subject);
            $asyncHours   = $this->detectAsync($subject);

            // Lehrer aufsplitten (Co-Teaching: "+")
            $teacherList = array_values(array_filter(
                array_map('trim', explode('+', $teachersRaw)), fn($t) => $t !== ''
            ));

            // Klassen aufsplitten
            $classList = array_values(array_filter(
                array_map('trim', explode('+', $students)), fn($c) => $c !== ''
            ));

            foreach ($teacherList as $t) $teacherSet[$t] = $teacherSet[$t] ?? null;
            $subjectSet[$subjectClean] = true;
            foreach ($classList as $c) if ($c) $classSet[$c] = true;

            $rows[] = [
                'activity_id'      => $get('Activity Id'),
                'weekday'          => $weekday,
                'weekday_name'     => $dayStr,
                'time_start'       => $times[0],
                'time_end'         => $times[1],
                'period_start'     => $this->slotNumber($times[0]),
                'subject_raw'      => $subject,
                'subject_clean'    => $subjectClean,
                'async_hours'      => $asyncHours,
                'teachers'         => $teacherList,
                'classes'          => $classList,
                'is_double_first'  => false,
                'is_double_second' => false,
                'double_key'       => null,
            ];
        }

        $rows = $this->detectDoubles($rows);
        $doubles = count(array_filter($rows, fn($r) => $r['is_double_first']));

        return [
            'rows'     => $rows,
            'teachers' => array_keys($teacherSet),
            'subjects' => array_keys($subjectSet),
            'classes'  => array_keys($classSet),
            'errors'   => $errors,
            'stats'    => [
                'total'               => count($rows),
                'doubles'             => $doubles,
                'skipped_no_teacher'  => $skippedNoTeacher,
                'teachers'            => count($teacherSet),
                'subjects'            => count($subjectSet),
                'classes'             => count($classSet),
            ],
        ];
    }

    // ============================================================
    // B) AUTO-MAPPING: CSV-Namen → DB-Lehrer
    // ============================================================

    public function getTeacherMapping(array $csvNames): array
    {
        $stmt = $this->db->query(
            "SELECT t.id AS teacher_id, CONCAT(u.first_name,' ',u.last_name) AS display_name
             FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.is_active=1"
        );
        $dbList = $stmt->fetchAll();

        $mapping = [];
        foreach ($csvNames as $csvName) {
            $mapping[$csvName] = null;
            // Exakter Match
            foreach ($dbList as $dbt) {
                if (strcasecmp($dbt['display_name'], $csvName) === 0) {
                    $mapping[$csvName] = (int)$dbt['teacher_id'];
                    break;
                }
            }
            // Fuzzy: mind. 2 übereinstimmende Wörter
            if ($mapping[$csvName] === null) {
                $csvParts = array_map('strtolower', explode(' ', $csvName));
                foreach ($dbList as $dbt) {
                    $dbParts = array_map('strtolower', explode(' ', $dbt['display_name']));
                    if (count(array_intersect($csvParts, $dbParts)) >= 2) {
                        $mapping[$csvName] = (int)$dbt['teacher_id'];
                        break;
                    }
                }
            }
        }
        return $mapping;
    }

    // ============================================================
    // C) IMPORT IN DB
    // ============================================================

    public function importToDb(
        array  $parseResult,
        array  $teacherMap,   // csvName → teacher_id|null
        array  $planData,     // [name, valid_from, valid_until]
        int    $uploadedBy,
        string $csvPath
    ): array {
        try {
            $this->db->beginTransaction();

            // Vorherigen überlappenden Plan kürzen
            $this->shortenPreviousPlan($planData['valid_from']);

            // Plan anlegen
            $this->db->prepare(
                "INSERT INTO timetable_plans (name, valid_from, valid_until, csv_file, uploaded_by)
                 VALUES (?,?,?,?,?)"
            )->execute([
                $planData['name'], $planData['valid_from'],
                $planData['valid_until'], $csvPath, $uploadedBy,
            ]);
            $planId = (int)$this->db->lastInsertId();

            $subjectMap     = $this->upsertSubjects($parseResult['subjects']);
            $classMap       = $this->upsertClasses($parseResult['classes']);
            $imported       = 0;
            $skipped        = 0;
            $dgSeq          = 1;
            $dgIds          = [];

            foreach ($parseResult['rows'] as $row) {
                $subjectId = $subjectMap[$row['subject_clean']] ?? null;
                if (!$subjectId) { $skipped++; continue; }

                // double_group_id
                $dgId = null;
                if ($row['double_key']) {
                    if (!isset($dgIds[$row['double_key']])) {
                        $dgIds[$row['double_key']] = $dgSeq++;
                    }
                    $dgId = $dgIds[$row['double_key']];
                }

                // Einen DB-Eintrag pro Lehrer (Co-Teaching)
                foreach ($row['teachers'] as $csvName) {
                    $teacherId = $teacherMap[$csvName] ?? null;
                    if (!$teacherId) { $skipped++; continue; }

                    $this->db->prepare(
                        "INSERT INTO timetable_entries
                           (plan_id,teacher_id,subject_id,weekday,period_start,
                            time_start,time_end,is_double_first,is_double_second,
                            double_group_id,csv_activity_id)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                    )->execute([
                        $planId, $teacherId, $subjectId,
                        $row['weekday'], $row['period_start'],
                        $row['time_start'], $row['time_end'],
                        $row['is_double_first']  ? 1 : 0,
                        $row['is_double_second'] ? 1 : 0,
                        $dgId, $row['activity_id'],
                    ]);
                    $entryId = (int)$this->db->lastInsertId();
                    $imported++;

                    // Klassen zuordnen
                    foreach ($row['classes'] as $cn) {
                        $cid = $classMap[$cn] ?? null;
                        if ($cid) {
                            $this->db->prepare(
                                "INSERT IGNORE INTO timetable_entry_classes (entry_id,class_id) VALUES (?,?)"
                            )->execute([$entryId, $cid]);
                        }
                    }
                }
            }

            $this->db->commit();
            logAudit($uploadedBy, 'timetable.imported', 'timetable_plans', $planId, [],
                ['name' => $planData['name'], 'imported' => $imported, 'skipped' => $skipped]);

            return ['success'=>true,'plan_id'=>$planId,'imported'=>$imported,'skipped'=>$skipped];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[WvH-Import] '.$e->getMessage());
            return ['success'=>false,'error'=>$e->getMessage()];
        }
    }

    // ============================================================
    // D) GÜLTIGKEITSZEITRAUM ÄNDERN
    // ============================================================

    public function updateValidity(int $planId, string $from, string $until, int $userId): array
    {
        if ($from >= $until) return ['success'=>false,'error'=>'Enddatum muss nach Startdatum liegen.'];

        $s = $this->db->prepare("SELECT * FROM timetable_plans WHERE id=?");
        $s->execute([$planId]);
        $old = $s->fetch();
        if (!$old) return ['success'=>false,'error'=>'Plan nicht gefunden.'];

        $this->db->prepare("UPDATE timetable_plans SET valid_from=?,valid_until=? WHERE id=?")
                 ->execute([$from, $until, $planId]);

        logAudit($userId, 'timetable.validity_changed', 'timetable_plans', $planId,
            ['valid_from'=>$old['valid_from'],'valid_until'=>$old['valid_until']],
            ['valid_from'=>$from,'valid_until'=>$until]
        );
        return ['success'=>true];
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function parseHour(string $h): ?array
    {
        if (preg_match('/(\d{2}:\d{2})\s*[-–]\s*(\d{2}:\d{2})/', $h, $m)) {
            return [$m[1].':00', $m[2].':00'];
        }
        return null;
    }

    private function slotNumber(string $timeStart): int
    {
        $hm  = substr($timeStart, 0, 5);
        $pos = array_search($hm, self::SLOT_ORDER);
        return $pos !== false ? (int)$pos + 1 : 99;
    }

    private function cleanSubject(string $raw): string
    {
        return trim(preg_replace('/\s*\([^)]*\)\s*/', '', $raw));
    }

    private function detectAsync(string $subject): int
    {
        if (preg_match('/(\d+)\s+asynchrone?\s+Stunde/i', $subject, $m)) return (int)$m[1];
        if (preg_match('/(\d+)\s+Stunde[n]?\s+eigenverantwortlich/i', $subject, $m)) return (int)$m[1];
        return 0;
    }

    private function detectDoubles(array $rows): array
    {
        $groups = [];
        foreach ($rows as $idx => $row) {
            $key = $row['weekday'].'|'.implode('+',$row['teachers']).'|'.$row['subject_clean'];
            $groups[$key][] = $idx;
        }
        foreach ($groups as $key => $indices) {
            if (count($indices) < 2) continue;
            usort($indices, fn($a,$b) => strcmp($rows[$a]['time_start'],$rows[$b]['time_start']));
            for ($i = 0; $i < count($indices)-1; $i++) {
                $a = $indices[$i]; $b = $indices[$i+1];
                if ($rows[$a]['time_end'] === $rows[$b]['time_start']) {
                    $dgKey = $key.'@'.$rows[$a]['time_start'];
                    $rows[$a]['is_double_first']  = true;
                    $rows[$a]['double_key']       = $dgKey;
                    $rows[$b]['is_double_second'] = true;
                    $rows[$b]['double_key']       = $dgKey;
                    $i++;
                }
            }
        }
        return $rows;
    }

    private function upsertSubjects(array $names): array
    {
        $map = [];
        foreach ($names as $n) {
            if (!$n) continue;
            $this->db->prepare("INSERT IGNORE INTO subjects (name) VALUES (?)")->execute([$n]);
            $s = $this->db->prepare("SELECT id FROM subjects WHERE name=?");
            $s->execute([$n]);
            $map[$n] = (int)$s->fetchColumn();
        }
        return $map;
    }

    private function upsertClasses(array $names): array
    {
        $map = [];
        foreach ($names as $n) {
            if (!$n) continue;
            preg_match('/^(\d+)/', $n, $m);
            $grade   = isset($m[1]) ? (int)$m[1] : null;
            $isUpper = ($grade !== null && $grade >= 10) ? 1 : 0;
            $this->db->prepare(
                "INSERT IGNORE INTO classes (name,grade_level,is_upper) VALUES (?,?,?)"
            )->execute([$n, $grade, $isUpper]);
            $s = $this->db->prepare("SELECT id FROM classes WHERE name=?");
            $s->execute([$n]);
            $map[$n] = (int)$s->fetchColumn();
        }
        return $map;
    }

    private function shortenPreviousPlan(string $newFrom): void
    {
        $s = $this->db->prepare(
            "SELECT id FROM timetable_plans WHERE valid_from < ? AND valid_until >= ?
             ORDER BY valid_from DESC LIMIT 1"
        );
        $s->execute([$newFrom, $newFrom]);
        $oldId = $s->fetchColumn();
        if ($oldId) {
            $this->db->prepare("UPDATE timetable_plans SET valid_until=? WHERE id=?")
                     ->execute([date('Y-m-d', strtotime($newFrom.' -1 day')), $oldId]);
        }
    }

    private function emptyResult(array $errors): array
    {
        return ['rows'=>[],'teachers'=>[],'subjects'=>[],'classes'=>[],
                'errors'=>$errors,'stats'=>[]];
    }
}
