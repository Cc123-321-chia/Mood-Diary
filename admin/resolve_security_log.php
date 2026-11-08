<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function resolution_reply(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){header('Allow: POST');resolution_reply(['success'=>false,'message'=>'Method not allowed.'],405);}
$csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if($csrf===''||!hash_equals((string)($_SESSION['csrf_token'] ?? ''),$csrf))resolution_reply(['success'=>false,'message'=>'Refresh the dashboard and try again.'],403);
$input=json_decode((string)file_get_contents('php://input'),true);$logId=filter_var($input['log_id'] ?? null,FILTER_VALIDATE_INT);
if(!$logId||$logId<1)resolution_reply(['success'=>false,'message'=>'Invalid security log.'],422);
try{
    $check=$pdo->prepare("SELECT log_id FROM security_logs WHERE log_id=? AND LOWER(CONCAT(COALESCE(event_type,''),' ',COALESCE(description,''))) REGEXP 'fail|blocked|unauthori[sz]ed|denied|invalid|suspicious|attack' LIMIT 1");
    $check->execute([$logId]);if(!$check->fetchColumn())resolution_reply(['success'=>false,'message'=>'This log is not classified as a critical security event.'],404);
    $stmt=$pdo->prepare('INSERT IGNORE INTO security_log_resolutions(log_id) VALUES(?)');$stmt->execute([$logId]);
    resolution_reply(['success'=>true,'message'=>'Security log marked as resolved.']);
}catch(Throwable $e){error_log($e->getMessage());resolution_reply(['success'=>false,'message'=>'Could not save the resolution. Check that resolve_security_logs.sql has been applied.'],500);}
