<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/activity.php';
function activityCheck(bool $ok,string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}
$c=getAppConfig()['db'];
$db=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4',$c['username'],$c['password'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$name='bookbridge_activity_test_'.bin2hex(random_bytes(8));$created=false;$failed=false;
try {
    $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");$created=true;$db->exec("USE `$name`");
    // Also verify the legacy unsigned-ID case that differs from the current fresh schema.
    $db->exec('CREATE TABLE users (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $db->exec('INSERT INTO users VALUES (1),(2),(3)');
    $migration=file_get_contents(__DIR__.'/../database/migrations/005_user_activity_tracking.sql');
    $db->exec($migration);$db->exec("UPDATE activity_tracking_meta SET started_at='2026-01-10 12:00:00' WHERE id=1");
    recordUserActivity($db,1);recordUserActivity($db,1);recordUserActivity($db,2);
    activityCheck((int)$db->query('SELECT COUNT(*) FROM user_activity_daily')->fetchColumn()===2,'repeat activity is deduplicated per account/day');
    $before=$db->query('SELECT * FROM user_activity_daily ORDER BY user_id,activity_date')->fetchAll();
    $db->exec($migration);
    activityCheck($before===$db->query('SELECT * FROM user_activity_daily ORDER BY user_id,activity_date')->fetchAll()
        && $db->query('SELECT started_at FROM activity_tracking_meta WHERE id=1')->fetchColumn()==='2026-01-10 12:00:00','repeat migration preserves activity and initial tracking date');
    $db->exec('DELETE FROM user_activity_daily');
    $db->exec("INSERT INTO user_activity_daily VALUES (1,'2026-02-01'),(1,'2026-02-02'),(2,'2026-02-02'),(3,'2026-03-01')");
    $summary=activitySummary($db,'2026-02-01','2026-02-28');
    activityCheck($summary['active_users']===2,'selected month counts distinct accounts, not daily rows');
    activityCheck(activitySummary($db,'2026-02-02','2026-02-02')['active_users']===2,'both date boundaries are inclusive');
    activityCheck(activitySummary($db,'2026-02-03','2026-02-28')['active_users']===0,'tracked empty period is zero');
    activityCheck(activitySummary($db,'2026-01-01','2026-01-09')['active_users']===null,'period before tracking is unknown rather than fabricated zero');
    activityCheck($summary['activity_coverage_complete'] && !activitySummary($db,'2026-01-01','2026-02-28')['activity_coverage_complete'],'partial historical coverage is disclosed');
    recordUserActivity($db,0);
    activityCheck((int)$db->query('SELECT COUNT(*) FROM user_activity_daily')->fetchColumn()===4,'guest/invalid identity does not create activity');
    $db->exec('DELETE FROM users WHERE id=2');
    activityCheck(activitySummary($db,'2026-02-01','2026-02-28')['active_users']===1,'removed accounts do not leave orphan activity');
    activityCheck(activitySummary($db)['active_users']===2,'unfiltered count deduplicates across all days');
} catch (Throwable $e) { $failed=true;echo 'FAIL '.$e->getMessage()."\n"; }
finally {if($created && preg_match('/\Abookbridge_activity_test_[a-f0-9]{16}\z/',$name))$db->exec("DROP DATABASE `$name`");}
exit($failed?1:0);
