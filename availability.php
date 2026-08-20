<?php
/**
 * availability.php — obsazenost slotů pro kalendář
 *
 * Vrací pro zvolený měsíc seznam obsazených začátků slotů po dnech.
 * Používá ho kalendář na veřejném webu i v administraci.
 *
 * GET ?month=YYYY-MM
 *
 * Odpověď:
 * {
 *   "success": true,
 *   "month":   "2026-08",
 *   "slots":   ["09:00", "10:00", ...],   // všechny možné začátky
 *   "taken":   { "2026-08-21": ["09:00", "14:00"] },
 *   "today":   "2026-08-20",
 *   "now":     "14:35"
 * }
 *
 * Záměrně nevrací žádné osobní údaje — jen kdy je obsazeno.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$month = (string) ($_GET['month'] ?? '');

// Přísný formát YYYY-MM; jinak bereme aktuální měsíc.
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

$first = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
if (!$first) {
    json_response(['success' => false, 'message' => 'Neplatný měsíc.'], 422);
}

// Nedovolíme dotazovat se stovky let dopředu — omezíme na rok od dneška.
$limit = new DateTimeImmutable('first day of this month');
if ($first < $limit->modify('-1 month') || $first > $limit->modify('+13 months')) {
    json_response(['success' => false, 'message' => 'Měsíc je mimo dostupný rozsah.'], 422);
}

$last = $first->modify('last day of this month');

try {
    $taken = taken_slots(db(), $first->format('Y-m-d'), $last->format('Y-m-d'));
} catch (PDOException $e) {
    error_log('[availability] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Dostupnost se nepodařilo načíst.'], 500);
}

json_response([
    'success' => true,
    'month'   => $first->format('Y-m'),
    'slots'   => booking_slots(),
    'taken'   => $taken,
    'today'   => date('Y-m-d'),
    'now'     => date('H:i'),
]);
