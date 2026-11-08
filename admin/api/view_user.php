<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function view_reply(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='GET'){header('Allow: GET');view_reply(['success'=>false,'message'=>'Method not allowed.'],405);}
$id=filter_var($_GET['id'] ?? $_GET['account_id'] ?? null,FILTER_VALIDATE_INT);$type=(string)($_GET['type'] ?? $_GET['account_type'] ?? 'user');
if(!$id||$id<1||!in_array($type,['user','admin'],true))view_reply(['success'=>false,'message'=>'Invalid account.'],400);
if($type==='admin')$stmt=$pdo->prepare('SELECT admin_id AS user_id,name,email,NULL AS avatar_url,1 AS is_active,created_at,created_at AS updated_at FROM admins WHERE admin_id=? LIMIT 1');
else $stmt=$pdo->prepare('SELECT user_id,name,email,avatar_url,is_active,created_at,updated_at FROM users WHERE user_id=? LIMIT 1');
try{$stmt->execute([$id]);$record=$stmt->fetch(PDO::FETCH_ASSOC);if(!$record)view_reply(['success'=>false,'message'=>'Account not found.'],404);view_reply(['success'=>true,'account_type'=>$type,'user'=>$record]);}catch(PDOException $e){error_log($e->getMessage());view_reply(['success'=>false,'message'=>'The account details could not be read from the database.'],500);}
