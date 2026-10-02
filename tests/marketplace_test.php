<?php
// Reject HTTP execution before loading configuration or touching the database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/db.php';
if (getAppConfig()['db']['dbname'] !== 'bookbridge_review_20261002' || getAppConfig()['app']['base_url'] !== 'http://localhost/UIU-Used-Textbook-Marketplace-main-review') { fwrite(STDERR, "Marketplace tests require the isolated review database and checkout.\n"); exit(1); }
$db = getDbConnection();
$base = rtrim(getAppConfig()['app']['base_url'], '/') . '/api/marketplace/';
$tag = 'market_test_' . bin2hex(random_bytes(6));
$seller = null;
$category = null;
$ids = [];
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
    echo "PASS $label\n";
}
function request(string $path, array $query = [], string $method = 'GET'): array {
    global $base;
    $curl = curl_init($base . $path . ($query ? '?' . http_build_query($query) : ''));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CUSTOMREQUEST => $method]);
    $body = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    curl_close($curl);
    return [$code, json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
function snapshot(PDO $db): array {
    $result = [];
    foreach (['users','categories','listings','purchase_requests','wishlists','messages','reviews'] as $table) {
        $result[$table] = hash('sha256', json_encode($db->query("SELECT * FROM $table ORDER BY id")->fetchAll()));
    }
    return $result;
}
$before = snapshot($db);
$cleanup = function () use ($db, &$ids, &$seller, &$category): void {
    foreach ($ids as $id) $db->prepare('DELETE FROM listings WHERE id=?')->execute([$id]);
    $ids = [];
    if ($seller !== null) { $db->prepare('DELETE FROM users WHERE id=?')->execute([$seller]); $seller = null; }
    if ($category !== null) { $db->prepare('DELETE FROM categories WHERE id=?')->execute([$category]); $category = null; }
};
register_shutdown_function($cleanup);
$failed = false;
try {
    $db->prepare("INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,'seller')")->execute([$tag, "$tag@uiu.ac.bd", password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT)]);
    $seller = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO categories (name,type) VALUES (?,'Subject')")->execute([$tag]);
    $category = (int)$db->lastInsertId();
    $insert = $db->prepare('INSERT INTO listings (seller_id,category_id,title,author,course_code,department,subject,item_type,condition_type,price,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach (['available','available','available','sold','pending_approval','rejected','changes_requested'] as $i => $status) {
        $insert->execute([$seller,$category,"$tag Book $i","$tag Author","$tag","CSE",$tag,'Textbook','Good',100+$i*10,'Fixture description',$status]);
        $ids[] = (int)$db->lastInsertId();
    }
    [$code,$body] = request('listings.php', ['search'=>$tag]);
    check($code===200 && $body['data']['total']===3 && count($body['data']['listings'])===3, 'guest browse exposes only available fixtures');
    [$code,$body] = request('listing-details.php', ['id'=>$ids[0]]);
    check($code===200 && $body['data']['listing']['seller_id']===$seller, 'guest details');
    $allowed = ['id','category_id','title','author','edition','course_code','department','subject','item_type','condition_type','price','image_url','created_at','seller_name','seller_rating','description','seller_id','seller_avatar_url'];
    check(array_diff(array_keys($body['data']['listing']), $allowed)===[], 'details response contains only public fields');
    foreach (array_slice($ids,3) as $id) {
        [$code,$body] = request('listing-details.php', ['id'=>$id]);
        check($code===404 && $body['message']==='Listing not found.', "hidden listing $id returns 404");
    }
    [$code,$body] = request('listing-details.php', ['id'=>2147483647]);
    check($code===404, 'missing listing returns same status');
    $filters = ['search'=>$tag,'department'=>'CSE','subject'=>$tag,'type'=>'Textbook','condition'=>'Good','category_id'=>$category,'min_price'=>'105','max_price'=>'120','sort'=>'price','direction'=>'asc','per_page'=>1,'page'=>2];
    [$code,$body] = request('listings.php', $filters);
    check($code===200 && $body['data']['total']===2 && $body['data']['listings'][0]['id']===$ids[2] && $body['data']['pagination']['total_pages']===2, 'combined filters and paginated price order');
    foreach (['title','created_at','price'] as $sort) foreach (['asc','desc'] as $direction) {
        [$code,$body] = request('listings.php', ['search'=>$tag,'sort'=>$sort,'direction'=>$direction]);
        $expected = $direction==='asc' ? array_slice($ids,0,3) : array_reverse(array_slice($ids,0,3));
        check($code===200 && array_column($body['data']['listings'],'id')===$expected, "$sort $direction stable ordering");
    }
    foreach (['title'=>'Book 1','author'=>'Author','course_code'=>$tag,'subject'=>$tag] as $field=>$term) {
        [$code,$body] = request('listings.php', ['search'=>"$tag" . ($field==='title' || $field==='author' ? " $term" : '')]);
        check($code===200 && $body['data']['total']>0, "search $field");
    }
    foreach ([['search'=>$tag,'department'=>'EEE'],['search'=>$tag,'type'=>'Notes'],['search'=>$tag,'condition'=>'Poor'],['search'=>"$tag%"],['search'=>$tag,'page'=>99]] as $query) {
        [$code,$body] = request('listings.php', $query);
        check($code===200 && $body['data']['listings']===[], 'empty results are successful arrays');
    }
    foreach ([['page'=>'0'],['per_page'=>'101'],['page'=>['1']],['search'=>['x']],['sort'=>'price;DROP'],['direction'=>'sideways'],['condition'=>'Broken'],['type'=>'Book'],['min_price'=>'-1'],['min_price'=>'20','max_price'=>'10'],['category_id'=>'0'],['subject'=>str_repeat('a',101)]] as $query) {
        [$code] = request('listings.php', $query);
        check($code===422, 'invalid filters rejected: '.json_encode($query));
    }
    foreach ([[],['id'=>0],['id'=>['1']]] as $query) {
        [$code] = request('listing-details.php',$query);
        check($code===422, 'invalid details ID rejected');
    }
    [$code,$body] = request('categories.php',['department'=>'CSE']);
    $subject = array_values(array_filter($body['data']['subjects'], fn($row)=>$row['id']===$category));
    check($code===200 && count($subject)===1 && $subject[0]['departments']===['CSE'], 'subject relationship from available listings');
    [$code,$body] = request('categories.php',['department'=>'EEE']);
    check(!in_array($category,array_column($body['data']['subjects'],'id'),true), 'no unrelated department cross join');
    foreach (['listings.php','listing-details.php','categories.php'] as $path) {
        [$code] = request($path, [], 'POST');
        check($code===405, "$path rejects POST");
    }
} catch (Throwable $error) {
    $failed = true;
    echo 'FAIL ' . $error->getMessage() . "\n";
} finally {
    $cleanup();
    try { check(snapshot($db)===$before, 'all original table rows preserved and fixtures cleaned'); }
    catch (Throwable $error) { $failed=true; echo 'FAIL '.$error->getMessage()."\n"; }
}
echo "$checks checks passed\n";
exit($failed ? 1 : 0);
