<?php
// Run with PHP CLI. Uses a uniquely named disposable database, never the app database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/db.php';

function releaseConnection(string $database = ''): PDO {
    $config = getAppConfig()['db'];
    $dsn = 'mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4';
    if ($database !== '') $dsn .= ';dbname='.$database;
    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
}
function releaseAssert(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function sendErrorResponse($message, $status, $errors = []): void {
    if (($GLOBALS['argv'][1] ?? '') === 'review-worker') {
        echo json_encode(['status'=>$status,'errors'=>$errors]);
        exit;
    }
    throw new RuntimeException(json_encode(['status'=>$status,'errors'=>$errors]));
}
function sendSuccessResponse($message, $data = [], $status = 200): void {
    echo json_encode(['status'=>$status,'data'=>$data]);
    exit;
}

if (($argv[1] ?? '') === 'review-worker') {
    $database = $argv[2] ?? '';
    if (!preg_match('/\Abookbridge_release_test_[a-f0-9]{16}\z/', $database)) exit(2);
    $testDb = releaseConnection($database);
    function requireRole($role) { return ['id'=>3,'role'=>'buyer']; }
    function requireCsrfToken() {}
    function getJsonRequestBody() { return ['purchase_request_id'=>1,'rating'=>4,'comment'=>'Concurrent test']; }
    $source = file_get_contents(__DIR__.'/../api/reviews/create.php');
    $source = preg_replace('/^require_once .*;\s*$/m', '', $source);
    $source = str_replace('$db = getDbConnection();', '$db = $testDb;', $source);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    try { eval(substr($source, 5)); }
    catch (RuntimeException $error) { echo $error->getMessage(); }
    exit;
}

$database = 'bookbridge_release_test_'.bin2hex(random_bytes(8));
$server = releaseConnection();
$created = false;
$workers = [];
$failed = false;
try {
    $source = file_get_contents(__DIR__.'/../api/auth/register.php');
    $start = strpos($source, '$errors = [];');
    $end = strpos($source, '// Database operation');
    if ($start === false || $end === false) throw new RuntimeException('Registration validation not found');
    $validation = substr($source, $start, $end-$start);
    foreach (['student@uiu.ac.bd'=>true,'student@bscse.uiu.ac.bd'=>true,
        'student@example.com'=>false,'student@uiu.ac.bd.example.com'=>false,
        'student@fakeuiu.ac.bd'=>false] as $email=>$allowed) {
        $body = ['full_name'=>'Release Student','email'=>$email,'password'=>'password123','role'=>'buyer'];
        $accepted = true;
        try { eval($validation); } catch (RuntimeException $error) { $accepted = false; }
        releaseAssert($accepted === $allowed, 'email validation: '.$email);
    }

    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $testDb = releaseConnection($database);
    $schema = str_replace('`bookbridge_db`', '`'.$database.'`', file_get_contents(__DIR__.'/../database/bookbridge.sql'));
    $testDb->exec($schema);
    foreach ($testDb->query('SELECT email,password_hash FROM users')->fetchAll() as $user) {
        releaseAssert(password_verify('password123', $user['password_hash']), 'fresh seed login password: '.$user['email']);
    }
    $testDb->prepare('INSERT INTO users (full_name,email,password_hash,role,student_id,phone,department,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute(['Fresh Student','fresh@uiu.ac.bd',password_hash('password123',PASSWORD_BCRYPT),'buyer',null,null,'CSE']);
    releaseAssert((int)$testDb->lastInsertId()>5, 'fresh schema supports registration without migration 002');
    $testDb->query('SELECT department,subject FROM categories')->fetchAll();
    releaseAssert(true, 'fresh schema supports category relationship queries');
    $testDb->exec("UPDATE purchase_requests SET status='completed',completed_at=NOW() WHERE id=1");
    $testDb->exec("UPDATE listings SET status='sold' WHERE id=1");

    for ($i=0;$i<2;$i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY,__FILE__,'review-worker',$database],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to start concurrent review worker');
        fclose($pipes[0]);
        $workers[] = [$process,$pipes];
    }
    $statuses = [];
    foreach ($workers as [$process,$pipes]) {
        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || $stderr !== '') throw new RuntimeException('Review worker failed: '.$stderr);
        $result = json_decode($output,true,512,JSON_THROW_ON_ERROR);
        $statuses[] = $result['status'];
    }
    $workers = [];
    sort($statuses);
    releaseAssert($statuses === [201,422], 'parallel review requests: one succeeds, one rejects duplicate');
    releaseAssert((int)$testDb->query('SELECT COUNT(*) FROM reviews WHERE purchase_request_id=1')->fetchColumn()===1,
        'exactly one review stored for the completed purchase');
} catch (Throwable $error) {
    $failed = true;
    echo 'FAIL '.$error->getMessage()."\n";
} finally {
    foreach ($workers as [$process,$pipes]) {
        if (is_resource($process)) {proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
    }
    if ($created && preg_match('/\Abookbridge_release_test_[a-f0-9]{16}\z/', $database)
        && $database !== getAppConfig()['db']['dbname']) {
        $testDb = null;
        $server->exec("DROP DATABASE `$database`");
        echo "PASS disposable database removed; app database untouched\n";
    }
}
exit($failed ? 1 : 0);
