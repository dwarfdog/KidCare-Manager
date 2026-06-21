<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$oldPath = $argv[1] ?? $root . '/bdd/bdd-old.db';
$targetPath = $argv[2] ?? $root . '/bdd/data.db';

if (!is_file($oldPath)) {
    fail("Old database not found: {$oldPath}");
}

if (!is_file($targetPath)) {
    fail("Target database not found: {$targetPath}");
}

$backupPath = sprintf('%s.backup-%s', $targetPath, date('Ymd-His'));
if (!copy($targetPath, $backupPath)) {
    fail("Unable to create target backup: {$backupPath}");
}

$old = pdo($oldPath);
$target = pdo($targetPath);

$report = [
    'backup' => $backupPath,
    'imported_users' => 0,
    'imported_nannies' => 0,
    'imported_assignments' => 0,
    'imported_rates' => 0,
    'imported_cares' => 0,
    'imported_templates' => 0,
    'imported_payments' => 0,
    'paid_payments_preserved' => 0,
    'unpaid_payments_recalculated' => 0,
    'generated_missing_payments' => 0,
];

$importUserIds = $old->query('SELECT DISTINCT user_id FROM nanny_user ORDER BY user_id')->fetchAll(PDO::FETCH_COLUMN);
if ($importUserIds === []) {
    fail('No user attached to a nanny found in old database.');
}

$oldUsers = fetchById($old, 'user', $importUserIds);
$oldNannies = fetchById($old, 'nanny', $old->query('SELECT DISTINCT nanny_id FROM nanny_user ORDER BY nanny_id')->fetchAll(PDO::FETCH_COLUMN));
$assignments = $old->query('SELECT user_id, nanny_id FROM nanny_user ORDER BY user_id, nanny_id')->fetchAll(PDO::FETCH_ASSOC);

$nannyPublicIds = [];
$userPublicIds = [];
$userCreatedAt = [];
$nannyCreatedBy = [];

foreach ($oldUsers as $userId => $user) {
    $userPublicIds[$userId] = ulid();
    $userCreatedAt[$userId] = firstKnownUserDate($old, (int) $userId);
}

foreach ($assignments as $assignment) {
    $nannyId = (int) $assignment['nanny_id'];
    $nannyCreatedBy[$nannyId] ??= (int) $assignment['user_id'];
}

foreach ($oldNannies as $nannyId => $nanny) {
    $nannyPublicIds[$nannyId] = ulid();
}

try {
    $target->exec('PRAGMA foreign_keys = OFF');
    $target->beginTransaction();

    foreach ([
        'activity_log',
        'care_template',
        'monthly_payment',
        'care',
        'nanny_rate',
        'nanny_assignment',
        'nanny',
        'user',
        'messenger_messages',
    ] as $table) {
        $target->exec('DELETE FROM ' . $table);
    }
    $target->exec("DELETE FROM sqlite_sequence WHERE name IN ('activity_log','care_template','monthly_payment','care','nanny_rate','nanny_assignment','nanny','user','messenger_messages')");

    $insertUser = $target->prepare(<<<'SQL'
        INSERT INTO user (
            id, public_id, username, email, email_verified, email_verification_token_hash,
            email_verification_requested_at, roles, password, created_at
        ) VALUES (
            :id, :public_id, :username, NULL, 0, NULL, NULL, :roles, :password, :created_at
        )
    SQL);

    foreach ($oldUsers as $userId => $user) {
        $insertUser->execute([
            'id' => $userId,
            'public_id' => $userPublicIds[$userId],
            'username' => $user['username'],
            'roles' => $user['roles'],
            'password' => $user['password'],
            'created_at' => $userCreatedAt[$userId],
        ]);
        $report['imported_users']++;
    }

    $insertNanny = $target->prepare(<<<'SQL'
        INSERT INTO nanny (
            id, created_by_id, public_id, firstname, lastname, address_line1, address_line2,
            postal_code, city, country, invite_code, created_at
        ) VALUES (
            :id, :created_by_id, :public_id, :firstname, :lastname, NULL, NULL,
            NULL, NULL, NULL, :invite_code, :created_at
        )
    SQL);

    foreach ($oldNannies as $nannyId => $nanny) {
        $insertNanny->execute([
            'id' => $nannyId,
            'created_by_id' => $nannyCreatedBy[$nannyId],
            'public_id' => $nannyPublicIds[$nannyId],
            'firstname' => limit((string) $nanny['firstname'], 120),
            'lastname' => limit((string) $nanny['lastname'], 120),
            'invite_code' => ulid(),
            'created_at' => normalizeDateTime($nanny['created_at']),
        ]);
        $report['imported_nannies']++;
    }

    $insertAssignment = $target->prepare(<<<'SQL'
        INSERT INTO nanny_assignment (user_id, nanny_id, public_id, attached_at, detached_at)
        VALUES (:user_id, :nanny_id, :public_id, :attached_at, NULL)
    SQL);

    foreach ($assignments as $assignment) {
        $userId = (int) $assignment['user_id'];
        $nannyId = (int) $assignment['nanny_id'];
        $insertAssignment->execute([
            'user_id' => $userId,
            'nanny_id' => $nannyId,
            'public_id' => ulid(),
            'attached_at' => minDate([$userCreatedAt[$userId], normalizeDateTime($oldNannies[$nannyId]['created_at'])]),
        ]);
        $report['imported_assignments']++;
    }

    $insertRate = $target->prepare(<<<'SQL'
        INSERT INTO nanny_rate (
            user_id, nanny_id, public_id, nanny_snapshot_key, hourly_rate, meal_rate, effective_from, created_at
        ) VALUES (
            :user_id, :nanny_id, :public_id, :nanny_snapshot_key, :hourly_rate, :meal_rate, :effective_from, :created_at
        )
    SQL);

    foreach ($assignments as $assignment) {
        $userId = (int) $assignment['user_id'];
        $nannyId = (int) $assignment['nanny_id'];
        $nanny = $oldNannies[$nannyId];
        $firstCareDate = firstCareDate($old, $userId, $nannyId);
        $insertRate->execute([
            'user_id' => $userId,
            'nanny_id' => $nannyId,
            'public_id' => ulid(),
            'nanny_snapshot_key' => $nannyPublicIds[$nannyId],
            'hourly_rate' => money($nanny['hourly_rate']),
            'meal_rate' => money($nanny['meal_rate']),
            'effective_from' => $firstCareDate,
            'created_at' => normalizeDateTime($nanny['created_at']),
        ]);
        $report['imported_rates']++;
    }

    $insertCare = $target->prepare(<<<'SQL'
        INSERT INTO care (
            id, user_id, nanny_id, public_id, nanny_display_name, nanny_snapshot_key,
            date, start_time, end_time, meals_count, hours_count, month, segment_group,
            segment_index, segment_total, created_at
        ) VALUES (
            :id, :user_id, :nanny_id, :public_id, :nanny_display_name, :nanny_snapshot_key,
            :date, :start_time, :end_time, :meals_count, :hours_count, :month, :segment_group,
            1, 1, :created_at
        )
    SQL);

    $careRows = $old->query('SELECT * FROM care ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($careRows as $care) {
        $userId = (int) $care['user_id'];
        $nannyId = (int) $care['nanny_id'];
        if (!isset($oldUsers[$userId], $oldNannies[$nannyId])) {
            continue;
        }

        $carePublicId = ulid();
        $insertCare->execute([
            'id' => $care['id'],
            'user_id' => $userId,
            'nanny_id' => $nannyId,
            'public_id' => $carePublicId,
            'nanny_display_name' => nannyName($oldNannies[$nannyId]),
            'nanny_snapshot_key' => $nannyPublicIds[$nannyId],
            'date' => normalizeDate($care['date']),
            'start_time' => normalizeTime($care['start_time']),
            'end_time' => normalizeTime($care['end_time']),
            'meals_count' => max(0, (int) $care['meals_count']),
            'hours_count' => money($care['hours_count']),
            'month' => $care['month'] ?: substr((string) $care['date'], 0, 7),
            'segment_group' => $carePublicId,
            'created_at' => normalizeDateTime($care['created_at']),
        ]);
        $report['imported_cares']++;
    }

    $insertTemplate = $target->prepare(<<<'SQL'
        INSERT INTO care_template (id, user_id, nanny_id, public_id, name, week_schedule, created_at)
        VALUES (:id, :user_id, :nanny_id, :public_id, :name, :week_schedule, :created_at)
    SQL);

    foreach ($old->query('SELECT * FROM care_template ORDER BY id') as $template) {
        $userId = (int) $template['user_id'];
        $nannyId = (int) $template['nanny_id'];
        if (!isset($oldUsers[$userId], $oldNannies[$nannyId])) {
            continue;
        }

        $insertTemplate->execute([
            'id' => $template['id'],
            'user_id' => $userId,
            'nanny_id' => $nannyId,
            'public_id' => ulid(),
            'name' => $template['name'],
            'week_schedule' => normalizeWeekSchedule((string) $template['week_schedule']),
            'created_at' => normalizeDateTime($template['created_at']),
        ]);
        $report['imported_templates']++;
    }

    $oldPaymentIds = [];
    $insertPayment = $target->prepare(<<<'SQL'
        INSERT INTO monthly_payment (
            id, user_id, nanny_id, public_id, nanny_display_name, nanny_snapshot_key,
            totals_hours, total_meals, amount_hours, amount_meals, total_amount, month,
            is_paid, paid_at, created_at, updated_at
        ) VALUES (
            :id, :user_id, :nanny_id, :public_id, :nanny_display_name, :nanny_snapshot_key,
            :totals_hours, :total_meals, :amount_hours, :amount_meals, :total_amount, :month,
            :is_paid, :paid_at, :created_at, :updated_at
        )
    SQL);

    foreach ($old->query('SELECT * FROM monthly_payment ORDER BY id') as $payment) {
        $userId = (int) $payment['user_id'];
        $nannyId = (int) $payment['nanny_id'];
        if (!isset($oldUsers[$userId], $oldNannies[$nannyId])) {
            continue;
        }

        $isPaid = (int) $payment['is_paid'] === 1;
        $values = $isPaid
            ? paymentValuesFromOld($payment)
            : recalculatePayment($target, $oldNannies[$nannyId], $userId, $nannyId, (string) $payment['month']);

        $insertPayment->execute([
            'id' => $payment['id'],
            'user_id' => $userId,
            'nanny_id' => $nannyId,
            'public_id' => ulid(),
            'nanny_display_name' => nannyName($oldNannies[$nannyId]),
            'nanny_snapshot_key' => $nannyPublicIds[$nannyId],
            'totals_hours' => $values['totals_hours'],
            'total_meals' => $values['total_meals'],
            'amount_hours' => $values['amount_hours'],
            'amount_meals' => $values['amount_meals'],
            'total_amount' => $values['total_amount'],
            'month' => $payment['month'],
            'is_paid' => $isPaid ? 1 : 0,
            'paid_at' => $isPaid ? normalizeNullableDateTime($payment['paid_at']) : null,
            'created_at' => normalizeDateTime($payment['created_at']),
            'updated_at' => $isPaid
                ? (normalizeNullableDateTime($payment['paid_at']) ?? normalizeDateTime($payment['created_at']))
                : normalizeDateTime($payment['created_at']),
        ]);
        $oldPaymentIds[] = (int) $payment['id'];
        $report['imported_payments']++;
        $report[$isPaid ? 'paid_payments_preserved' : 'unpaid_payments_recalculated']++;
    }

    $missingPayments = $target->query(<<<'SQL'
        SELECT c.user_id, c.nanny_id, c.month
        FROM care c
        LEFT JOIN monthly_payment mp ON mp.user_id = c.user_id AND mp.nanny_id = c.nanny_id AND mp.month = c.month
        WHERE mp.id IS NULL
        GROUP BY c.user_id, c.nanny_id, c.month
        ORDER BY c.user_id, c.nanny_id, c.month
    SQL)->fetchAll(PDO::FETCH_ASSOC);

    $nextPaymentId = (int) $target->query('SELECT COALESCE(MAX(id), 0) + 1 FROM monthly_payment')->fetchColumn();
    foreach ($missingPayments as $missingPayment) {
        $userId = (int) $missingPayment['user_id'];
        $nannyId = (int) $missingPayment['nanny_id'];
        $month = (string) $missingPayment['month'];
        $values = recalculatePayment($target, $oldNannies[$nannyId], $userId, $nannyId, $month);
        $insertPayment->execute([
            'id' => $nextPaymentId++,
            'user_id' => $userId,
            'nanny_id' => $nannyId,
            'public_id' => ulid(),
            'nanny_display_name' => nannyName($oldNannies[$nannyId]),
            'nanny_snapshot_key' => $nannyPublicIds[$nannyId],
            'totals_hours' => $values['totals_hours'],
            'total_meals' => $values['total_meals'],
            'amount_hours' => $values['amount_hours'],
            'amount_meals' => $values['amount_meals'],
            'total_amount' => $values['total_amount'],
            'month' => $month,
            'is_paid' => 0,
            'paid_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $report['imported_payments']++;
        $report['generated_missing_payments']++;
    }

    $target->prepare(<<<'SQL'
        INSERT INTO activity_log (public_id, user_id, actor_identifier, "action", message, context, created_at)
        VALUES (:public_id, :user_id, :actor_identifier, :action, :message, :context, :created_at)
    SQL)->execute([
        'public_id' => ulid(),
        'user_id' => $importUserIds[0],
        'actor_identifier' => $oldUsers[(int) $importUserIds[0]]['username'] ?? null,
        'action' => 'migration.old_database_import',
        'message' => 'Ancienne base importee.',
        'context' => json_encode($report, JSON_UNESCAPED_SLASHES),
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $foreignKeyErrors = $target->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
    if ($foreignKeyErrors !== []) {
        throw new RuntimeException('Foreign key check failed: ' . json_encode($foreignKeyErrors, JSON_UNESCAPED_SLASHES));
    }

    $integrity = $target->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') {
        throw new RuntimeException('Integrity check failed: ' . $integrity);
    }

    $target->commit();
    $target->exec('PRAGMA foreign_keys = ON');
} catch (Throwable $exception) {
    if ($target->inTransaction()) {
        $target->rollBack();
    }

    $target->exec('PRAGMA foreign_keys = ON');
    restoreBackup($backupPath, $targetPath);
    fail($exception->getMessage() . "\nTarget restored from backup: {$backupPath}");
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function pdo(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function fetchById(PDO $db, string $table, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $db->prepare("SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id");
    $statement->execute(array_values($ids));

    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[(int) $row['id']] = $row;
    }

    return $rows;
}

function firstKnownUserDate(PDO $old, int $userId): string
{
    $statement = $old->prepare(<<<'SQL'
        SELECT MIN(value) FROM (
            SELECT date || ' 00:00:00' AS value FROM care WHERE user_id = :user_id
            UNION ALL SELECT created_at FROM care WHERE user_id = :user_id
            UNION ALL SELECT created_at FROM monthly_payment WHERE user_id = :user_id
            UNION ALL SELECT created_at FROM care_template WHERE user_id = :user_id
            UNION ALL SELECT n.created_at FROM nanny n INNER JOIN nanny_user nu ON nu.nanny_id = n.id WHERE nu.user_id = :user_id
        )
        WHERE value IS NOT NULL AND value != ''
    SQL);
    $statement->execute(['user_id' => $userId]);

    return normalizeDateTime($statement->fetchColumn() ?: date('Y-m-d H:i:s'));
}

function firstCareDate(PDO $old, int $userId, int $nannyId): string
{
    $statement = $old->prepare('SELECT MIN(date) FROM care WHERE user_id = :user_id AND nanny_id = :nanny_id');
    $statement->execute(['user_id' => $userId, 'nanny_id' => $nannyId]);

    return normalizeDate($statement->fetchColumn() ?: date('Y-m-d'));
}

function paymentValuesFromOld(array $payment): array
{
    return [
        'totals_hours' => money($payment['totals_hours']),
        'total_meals' => max(0, (int) $payment['total_meals']),
        'amount_hours' => money($payment['amount_hours']),
        'amount_meals' => money($payment['amount_meals']),
        'total_amount' => money($payment['total_amount']),
    ];
}

function recalculatePayment(PDO $target, array $oldNanny, int $userId, int $nannyId, string $month): array
{
    $statement = $target->prepare(<<<'SQL'
        SELECT COALESCE(SUM(hours_count), 0) AS hours, COALESCE(SUM(meals_count), 0) AS meals
        FROM care
        WHERE user_id = :user_id AND nanny_id = :nanny_id AND month = :month
    SQL);
    $statement->execute(['user_id' => $userId, 'nanny_id' => $nannyId, 'month' => $month]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    $hours = (float) $row['hours'];
    $meals = (int) $row['meals'];
    $amountHours = $hours * (float) $oldNanny['hourly_rate'];
    $amountMeals = $meals * (float) $oldNanny['meal_rate'];

    return [
        'totals_hours' => money($hours),
        'total_meals' => $meals,
        'amount_hours' => money($amountHours),
        'amount_meals' => money($amountMeals),
        'total_amount' => money($amountHours + $amountMeals),
    ];
}

function normalizeWeekSchedule(string $json): string
{
    $source = json_decode($json, true);
    if (!is_array($source)) {
        $source = [];
    }

    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $schedule = [];
    foreach ($days as $day) {
        $config = is_array($source[$day] ?? null) ? $source[$day] : [];
        $active = filter_var($config['isActive'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $slots = [];
        if ($active) {
            foreach ($config['slots'] ?? [] as $slot) {
                if (!is_array($slot)) {
                    continue;
                }

                $slots[] = [
                    'startTime' => normalizeTime($slot['startTime'] ?? '08:00'),
                    'endTime' => normalizeTime($slot['endTime'] ?? '17:00'),
                    'mealsCount' => max(0, (int) ($slot['mealsCount'] ?? 0)),
                ];
            }
        }

        $schedule[$day] = ['isActive' => $active, 'slots' => $slots];
    }

    return json_encode($schedule, JSON_UNESCAPED_SLASHES);
}

function normalizeDateTime(mixed $value): string
{
    return (new DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
}

function normalizeNullableDateTime(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return normalizeDateTime($value);
}

function normalizeDate(mixed $value): string
{
    return (new DateTimeImmutable((string) $value))->format('Y-m-d');
}

function normalizeTime(mixed $value): string
{
    return (new DateTimeImmutable('1970-01-01 ' . (string) $value))->format('H:i:s');
}

function minDate(array $dates): string
{
    sort($dates);

    return normalizeDateTime($dates[0]);
}

function money(mixed $value): string
{
    return number_format((float) $value, 2, '.', '');
}

function nannyName(array $nanny): string
{
    return trim((string) $nanny['firstname'] . ' ' . (string) $nanny['lastname']);
}

function limit(string $value, int $max): string
{
    return mb_substr(trim($value), 0, $max);
}

function ulid(): string
{
    static $encoding = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    $milliseconds = (int) floor(((float) (new DateTimeImmutable())->format('U.u')) * 1000);
    $time = '';
    for ($i = 0; $i < 10; $i++) {
        $time = $encoding[$milliseconds & 31] . $time;
        $milliseconds >>= 5;
    }

    $bytes = random_bytes(10);
    $value = 0;
    $bits = 0;
    $random = '';
    foreach (unpack('C*', $bytes) as $byte) {
        $value = ($value << 8) | $byte;
        $bits += 8;

        while ($bits >= 5) {
            $bits -= 5;
            $random .= $encoding[($value >> $bits) & 31];
        }
    }

    if ($bits > 0) {
        $random .= $encoding[($value << (5 - $bits)) & 31];
    }

    return $time . substr($random, 0, 16);
}

function restoreBackup(string $backupPath, string $targetPath): void
{
    if (is_file($backupPath)) {
        copy($backupPath, $targetPath);
    }
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
