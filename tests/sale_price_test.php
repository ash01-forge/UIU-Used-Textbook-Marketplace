<?php
// CLI-only, disposable database. No writes to the application's database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/sales.php';
function priceConnection(string $name = ''): PDO {
    $c = getAppConfig()['db'];
    return new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4'.($name ? ';dbname='.$name : ''),
        $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function priceCheck(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}
if (($argv[1] ?? '') === 'worker') {
    if (!preg_match('/\Abookbridge_price_test_[a-f0-9]{16}\z/', $argv[2] ?? '')) exit(2);
    $testDb = priceConnection($argv[2]);
    $input = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
    function requireRole($role) { global $input; return ['id'=>$input['user_id'] ?? 2,'role'=>$role]; }
    function requireCsrfToken() {}
    function adminRequireMethod($methods) {}
    function adminQueryInt($key,$default,$min,$max) { return (int)($_GET[$key] ?? $default); }
    function adminQueryText($key,$length) { return (string)($_GET[$key] ?? ''); }
    function getJsonRequestBody() { global $input; return $input; }
    function sendSuccessResponse($message,$data=[],$status=200) { echo json_encode(['status'=>$status,'data'=>$data]); exit; }
    function sendErrorResponse($message,$status,$errors=[]) { echo json_encode(['status'=>$status,'message'=>$message]); exit; }
    $_GET = $input['query'] ?? [];
    $_SERVER['REQUEST_METHOD'] = isset($input['endpoint']) ? 'GET' : 'POST';
    $endpoint = $input['endpoint'] ?? 'api/buyer/seller-request-action.php';
    $source = file_get_contents(__DIR__.'/../'.$endpoint);
    $source = preg_replace('/^require_once .*;\s*$/m','',$source);
    $source = str_replace('$db = getDbConnection();','$db = $testDb;',$source);
    eval(substr($source,5));
    exit;
}
function priceWorker(string $db, array $input): array {
    $pipes=[];
    $process=proc_open([PHP_BINARY,__FILE__,'worker',$db,json_encode($input)],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Worker failed to start');
    fclose($pipes[0]);$result=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    if ($exit || $errors) throw new RuntimeException($errors ?: $result);
    return json_decode($result,true,512,JSON_THROW_ON_ERROR);
}
$name='bookbridge_price_test_'.bin2hex(random_bytes(8));
$server=priceConnection();$created=false;$failed=false;
try {
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;
    $db=priceConnection($name);
    $db->exec(str_replace('`bookbridge_db`','`'.$name.'`',file_get_contents(__DIR__.'/../database/bookbridge.sql')));
    $db->exec("UPDATE purchase_requests SET status='completed',completed_at=NOW() WHERE id=1");
    $legacy=$db->query('SELECT * FROM purchase_requests ORDER BY id')->fetchAll();
    // Reproduce an older installation without snapshots, then apply the migration twice.
    $db->exec('ALTER TABLE purchase_requests DROP COLUMN sale_price');
    $migration=file_get_contents(__DIR__.'/../database/migrations/004_sale_price_snapshot.sql');
    $db->exec($migration);$db->exec($migration);
    priceCheck($db->query('SELECT * FROM purchase_requests ORDER BY id')->fetchAll()===$legacy,
        'repeatable migration preserves existing rows and leaves historical prices unknown');
    $db->exec("INSERT INTO listings (seller_id,title,course_code,department,price,description,status) VALUES (2,'Snapshot regression','CSE-101','CSE',123.45,'Disposable test','available')");
    $listing=(int)$db->lastInsertId();
    $insert=$db->prepare("INSERT INTO purchase_requests (listing_id,buyer_id,seller_id,meeting_location,preferred_date,status) VALUES (?,3,2,'Library',CURDATE(),'accepted')");
    $insert->execute([$listing]);$request=(int)$db->lastInsertId();
    $denied=priceWorker($name,['request_id'=>$request,'action'=>'complete','user_id'=>4]);
    priceCheck($denied['status']===403 && $db->query("SELECT sale_price FROM purchase_requests WHERE id=$request")->fetchColumn()===null,'another seller cannot record a sale');
    $done=priceWorker($name,['request_id'=>$request,'action'=>'complete']);
    priceCheck($done['status']===200 && $done['data']['sale_price']===123.45,'completion records the locked listing price');
    $db->exec("UPDATE listings SET price=999,status='available' WHERE id=$listing");
    priceCheck($db->query("SELECT sale_price FROM purchase_requests WHERE id=$request")->fetchColumn()==='123.45','editing/relisting preserves the old sale amount');
    priceCheck(priceWorker($name,['request_id'=>$request,'action'=>'complete'])['status']===422,'repeat completion cannot overwrite a snapshot');
    $db->exec("UPDATE listings SET price=0 WHERE id=$listing");$insert->execute([$listing]);$zero=(int)$db->lastInsertId();
    priceCheck(priceWorker($name,['request_id'=>$zero,'action'=>'complete'])['status']===200,'zero-price sale completes');
    $summary=saleSummary($db);
    priceCheck($summary['revenue']===123.45 && $summary['priced_sales_count']===2 && $summary['unpriced_sales_count']===1,'totals distinguish zero-price sales from missing historical amounts');
    priceCheck($summary['average_order_value']===61.725,'average excludes unknown historical prices and includes zero');
    $report=priceWorker($name,['endpoint'=>'api/admin/sales-report.php','query'=>['per_page'=>1]]);
    priceCheck(count($report['data']['transactions'])===1 && $report['data']['summary']['revenue']===123.45,'report totals are independent of row pagination');
    $empty=priceWorker($name,['endpoint'=>'api/admin/sales-report.php','query'=>['from'=>'2000-01-01','to'=>'2000-01-02']]);
    priceCheck($empty['data']['revenue']===0 && $empty['data']['summary']['completed_sales_count']===0,'date filter excludes other sales and returns zero revenue');
    $dashboard=priceWorker($name,['endpoint'=>'api/admin/dashboard.php']);
    priceCheck($dashboard['data']['revenue']===123.45 && $dashboard['data']['unpriced_sales_count']===1,'dashboard reports recorded revenue with legacy coverage');
    priceCheck(saleSummary($db,"pr.status='completed' AND pr.seller_id=?",[4])['revenue']===0.0,'seller revenue excludes other sellers');
} catch (Throwable $e) { $failed=true; echo 'FAIL '.$e->getMessage()."\n"; }
finally {
    if($created && $name!==getAppConfig()['db']['dbname'] && preg_match('/\Abookbridge_price_test_[a-f0-9]{16}\z/',$name)) {
        $db=null;$server->exec("DROP DATABASE `$name`");echo "PASS disposable database removed\n";
    }
}
exit($failed ? 1 : 0);
