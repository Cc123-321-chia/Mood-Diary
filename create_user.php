<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function create_reply(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){header('Allow: POST');create_reply(['success'=>false,'message'=>'Method not allowed.'],405);}
$csrf=is_string($_POST['csrf_token'] ?? null)?$_POST['csrf_token']:'';if($csrf===''||!hash_equals((string)($_SESSION['csrf_token'] ?? ''),$csrf))create_reply(['success'=>false,'message'=>'Invalid security token.'],403);
if(!is_string($_POST['name'] ?? null))create_reply(['success'=>false,'message'=>'Invalid name input.'],422);
$name=trim($_POST['name']);
if(!is_string($_POST['email'] ?? null))create_reply(['success'=>false,'message'=>'Invalid email input.'],422);$email=trim($_POST['email']);
if(!is_string($_POST['account_type'] ?? 'user'))create_reply(['success'=>false,'message'=>'Invalid account type.'],422);$type=$_POST['account_type'] ?? 'user';
if(!in_array($type,['user','admin'],true))create_reply(['success'=>false,'message'=>'Choose a valid account type.'],422);
if($name==='' || mb_strlen($name)>50)create_reply(['success'=>false,'message'=>'Name is required and must be 50 characters or fewer.'],422);
if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>120)create_reply(['success'=>false,'message'=>'Enter a valid email address.'],422);
$temporaryPassword=null;
if($type==='admin'){$temporaryPassword=bin2hex(random_bytes(12)).'A!';$passwordHash=password_hash($temporaryPassword,PASSWORD_DEFAULT);}
else{$password=is_string($_POST['password'] ?? null)?$_POST['password']:'';if(!mb_check_encoding($password,'UTF-8')||mb_strlen($password,'UTF-8')<8||!preg_match('/[A-Z]/',$password)||!preg_match('/[\p{P}\p{S}]/u',$password)||strlen($password)>72||strpos($password,"\0")!==false)create_reply(['success'=>false,'message'=>'Use at least 8 characters, 1 special character and 1 uppercase letter (A–Z).'],422);$passwordHash=password_hash($password,PASSWORD_DEFAULT);}
try{
$dupe=$pdo->prepare('SELECT email FROM users WHERE email=? UNION ALL SELECT email FROM admins WHERE email=? LIMIT 1');$dupe->execute([$email,$email]);
if($dupe->fetchColumn())create_reply(['success'=>false,'message'=>'That email address is already used by an account.'],409);
    $pdo->beginTransaction();
    if($type==='admin'){
        $stmt=$pdo->prepare('INSERT INTO admins(name,email,password_hash,must_change_password) VALUES(?,?,?,1)');$stmt->execute([$name,$email,$passwordHash]);$id=(int)$pdo->lastInsertId();
        $event='ADMIN_REGISTRATION';$description='Administrator account created successfully: '.$name.' ('.$email.'). A temporary password was generated.';
    }else{
        $stmt=$pdo->prepare('INSERT INTO users(name,email,password_hash,is_active) VALUES(?,?,?,1)');$stmt->execute([$name,$email,$passwordHash]);$id=(int)$pdo->lastInsertId();
        $event='USER_CREATED';$description='User account created for '.$email;
    }
    $log=$pdo->prepare('INSERT INTO security_logs(admin_id,user_id,event_type,description,ip_address,user_agent) VALUES(?,?, ?,?,?,?)');
    $log->execute([(int)$admin['admin_id'],$type==='user'?$id:null,$event,$description,substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45) ?: null,substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255) ?: null]);
    $pdo->commit();
    $result=['success'=>true,'message'=>$type==='admin'?'Admin account created. Copy the temporary password now; it will not be shown again.':'User created successfully.','account_type'=>$type,'account_id'=>$id];
    if($temporaryPassword!==null)$result['temporary_password']=$temporaryPassword;
    create_reply($result,201);
}catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000')create_reply(['success'=>false,'message'=>'That email address is already in use.'],409);error_log($e->getMessage());create_reply(['success'=>false,'message'=>'Unable to create account.'],500);}
