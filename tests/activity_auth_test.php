<?php
// Real HTTP regression with one disposable account; no original account is changed.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__.'/../config/db.php';
$db=getDbConnection();$email='qa_activity_'.bin2hex(random_bytes(8)).'@uiu.ac.bd';$id=null;
$jar=tempnam(sys_get_temp_dir(),'bb_activity_');$failed=false;
$base=rtrim(getAppConfig()['app']['base_url'],'/').'/api/';
function activityHttp(string $path, ?array $body=null, bool $session=true): int {
    global $base,$jar;
    $c=curl_init($base.$path);$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15];
    if($session){$options[CURLOPT_COOKIEJAR]=$jar;$options[CURLOPT_COOKIEFILE]=$jar;}
    if($body!==null){$options[CURLOPT_POST]=true;$options[CURLOPT_HTTPHEADER]=['Content-Type: application/json'];$options[CURLOPT_POSTFIELDS]=json_encode($body);}
    curl_setopt_array($c,$options);$response=curl_exec($c);
    if($response===false)throw new RuntimeException(curl_error($c));
    $status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return $status;
}
function activityAuthCheck(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
try {
    $db->prepare("INSERT INTO users (full_name,email,password_hash,role) VALUES ('QA Activity Buyer',?,?,'buyer')")->execute([$email,password_hash('QaActivity123!',PASSWORD_BCRYPT)]);$id=(int)$db->lastInsertId();
    $count=function()use($db,$id){$q=$db->prepare('SELECT COUNT(*) FROM user_activity_daily WHERE user_id=?');$q->execute([$id]);return (int)$q->fetchColumn();};
    activityAuthCheck(activityHttp('auth/me.php',null,false)===401 && $count()===0,'anonymous session does not record authenticated activity');
    activityAuthCheck(activityHttp('auth/login.php',['email'=>$email,'password'=>'Wrong!'])===401 && $count()===0,'failed login creates no activity');
    activityAuthCheck(activityHttp('auth/login.php',['email'=>$email,'password'=>'QaActivity123!'])===200 && $count()===1,'successful login records daily activity');
    activityAuthCheck(activityHttp('auth/me.php')===200 && activityHttp('auth/me.php')===200 && $count()===1,'repeated authenticated HTTP requests are deduplicated');
    $db->prepare('DELETE FROM user_activity_daily WHERE user_id=?')->execute([$id]);
    activityAuthCheck(activityHttp('auth/me.php')===200 && $count()===1,'persisting authenticated session records activity without another login');
    activityAuthCheck(activityHttp('admin/sales-report.php')===403,'activity analytics remain admin-only');
} catch(Throwable $e){$failed=true;echo 'FAIL '.$e->getMessage()."\n";}
finally {
    if($id!==null)$db->prepare('DELETE FROM users WHERE id=? AND email=?')->execute([$id,$email]);
    if(file_exists($jar))unlink($jar);
    echo "Disposable account, activity and cookie jar removed.\n";
}
exit($failed?1:0);
