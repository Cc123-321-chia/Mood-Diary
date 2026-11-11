<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function account_reply(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){header('Allow: POST');account_reply(['success'=>false,'message'=>'Method not allowed.'],405);}
$csrf=is_string($_POST['csrf_token'] ?? null)?$_POST['csrf_token']:'';if($csrf===''||!hash_equals((string)($_SESSION['csrf_token'] ?? ''),$csrf))account_reply(['success'=>false,'message'=>'Invalid security token.'],403);
$rawType=$_POST['account_type'] ?? 'user';if(!is_string($rawType))account_reply(['success'=>false,'message'=>'Invalid account type.'],422);$type=$rawType;
$id=filter_var($_POST['account_id'] ?? $_POST['user_id'] ?? null,FILTER_VALIDATE_INT);
$rawAction=$_POST['action'] ?? '';if(!is_string($rawAction))account_reply(['success'=>false,'message'=>'Invalid action.'],422);$action=$rawAction;
if(!in_array($type,['user','admin'],true)||!$id||$id<1||!in_array($action,['edit','delete'],true))account_reply(['success'=>false,'message'=>'Invalid account request.'],422);
try{
    if($action==='edit'){
        if(!is_string($_POST['name'] ?? null))account_reply(['success'=>false,'message'=>'Invalid name input.'],422);$name=trim($_POST['name']);
if(!    is_string($_POST['email'] ?? null))account_reply(['success'=>false,'message'=>'Invalid email input.'],422);$email=trim($_POST['email']);
        if($name===''||mb_strlen($name)>50||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>120)account_reply(['success'=>false,'message'=>'Enter a valid name and email.'],422);
        $key=$type==='user'?'user_id':'admin_id';$table=$type==='user'?'users':'admins';$other=$type==='user'?'admins':'users';$otherKey=$type==='user'?'admin_id':'user_id';
        $check=$pdo->prepare("SELECT email FROM $table WHERE email=? AND $key<>? UNION ALL SELECT email FROM $other WHERE email=? LIMIT 1");$check->execute([$email,$id,$email]);if($check->fetchColumn())account_reply(['success'=>false,'message'=>'That email is already in use.'],409);
        $stmt=$pdo->prepare("UPDATE $table SET name=?,email=? WHERE $key=?");$stmt->execute([$name,$email,$id]);
        if($stmt->rowCount()===0){$exists=$pdo->prepare("SELECT $key FROM $table WHERE $key=?");$exists->execute([$id]);if(!$exists->fetchColumn())account_reply(['success'=>false,'message'=>'Account not found.'],404);}
        $log=$pdo->prepare('INSERT INTO security_logs(admin_id,user_id,event_type,description,ip_address,user_agent) VALUES(?,?,?,?,?,?)');
        $log->execute([(int)$admin['admin_id'],$type==='user'?$id:null,$type==='user'?'USER_UPDATED':'ADMIN_UPDATED',ucfirst($type).' account updated: '.$name.' ('.$email.')',substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45) ?: null,substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255) ?: null]);
        account_reply(['success'=>true,'message'=>ucfirst($type).' account updated.']);
    }
    if($type==='admin'){
        if((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn()<=1)account_reply(['success'=>false,'message'=>'The last admin account cannot be deleted.'],409);
        $get=$pdo->prepare('SELECT name,email FROM admins WHERE admin_id=?');$get->execute([$id]);$target=$get->fetch(PDO::FETCH_ASSOC);if(!$target)account_reply(['success'=>false,'message'=>'Admin account not found.'],404);
        $pdo->beginTransaction();$pdo->prepare("INSERT INTO security_logs(admin_id,event_type,description,ip_address,user_agent) VALUES(?,'ADMIN_DELETED',?,?,?)")->execute([(int)$admin['admin_id'],'Admin account deleted: '.$target['name'].' ('.$target['email'].')',substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45) ?: null,substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255) ?: null]);$pdo->prepare('DELETE FROM admins WHERE admin_id=?')->execute([$id]);$pdo->commit();account_reply(['success'=>true,'message'=>'Admin account deleted.']);
    }
    $get=$pdo->prepare('SELECT name,email FROM users WHERE user_id=?');$get->execute([$id]);$target=$get->fetch(PDO::FETCH_ASSOC);if(!$target)account_reply(['success'=>false,'message'=>'User account not found.'],404);
    $pdo->beginTransaction();$pdo->prepare("INSERT INTO security_logs(admin_id,event_type,description,ip_address,user_agent) VALUES(?,'USER_DELETED',?,?,?)")->execute([(int)$admin['admin_id'],'User account deleted: '.$target['name'].' ('.$target['email'].')',substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45) ?: null,substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255) ?: null]);$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);$pdo->commit();account_reply(['success'=>true,'message'=>'User account deleted.']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());account_reply(['success'=>false,'message'=>'Unable to update the account.'],500);}
