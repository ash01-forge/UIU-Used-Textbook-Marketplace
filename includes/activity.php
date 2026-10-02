<?php
/** Daily authenticated account activity; no IP addresses, tokens or guest tracking. */
function recordUserActivity(PDO $db, int $userId): void {
    if ($userId <= 0) return;
    $query = $db->prepare('INSERT INTO user_activity_daily (user_id, activity_date)
        VALUES (?, CURRENT_DATE()) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)');
    $query->execute([$userId]);
}

function activitySummary(PDO $db, string $from = '', string $to = ''): array {
    $started = $db->query('SELECT started_at FROM activity_tracking_meta WHERE id=1')->fetchColumn();
    if (!$started) throw new RuntimeException('Activity tracking migration is missing.');
    $conditions = []; $parameters = [];
    if ($from !== '') { $conditions[] = 'activity_date >= ?'; $parameters[] = $from; }
    if ($to !== '') { $conditions[] = 'activity_date <= ?'; $parameters[] = $to; }
    $query = $db->prepare('SELECT COUNT(DISTINCT user_id) FROM user_activity_daily'.
        ($conditions ? ' WHERE '.implode(' AND ', $conditions) : ''));
    $query->execute($parameters);
    $beforeTracking = $to !== '' && $to < substr($started, 0, 10);
    $complete = $from !== '' && $from > substr($started, 0, 10);
    return [
        'active_users' => $beforeTracking ? null : (int)$query->fetchColumn(),
        'activity_tracking_started_at' => $started,
        'activity_coverage_complete' => $complete,
        'activity_note' => 'Active Users counts unique accounts with a successful login or authenticated API request in the selected period (all roles, excluding guests). Tracking began '.$started.' in database server time.'.
            ($complete ? '' : ' Activity before tracking began was not recorded; the starting day is partial.'),
    ];
}

function trackAuthenticatedUser(int $userId): void {
    try { recordUserActivity(getDbConnection(), $userId); }
    catch (Throwable $e) {
        // Analytics must not prevent login or interrupt an otherwise valid action.
        error_log('User activity tracking failed: '.$e->getMessage());
    }
}
