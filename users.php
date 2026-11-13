<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../../config/db.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {header('Allow: GET');http_response_code(405);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>'Method not allowed.']);exit;}
$rawSearch=$_GET['search'] ?? '';
if(!is_string($rawSearch)){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>'Invalid search input.']);exit;}
$search=trim($rawSearch);if(mb_strlen($search,'UTF-8')>120){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>'Search must not exceed 120 characters.']);exit;}
$rawPage=$_GET['page'] ?? '1';
if(!is_string($rawPage)&&!is_int($rawPage)||filter_var($rawPage,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>'Page must be a positive integer.']);exit;}
$page=(int)$rawPage;
$perPage=25;
$accounts="SELECT 'user' AS account_type,user_id AS account_id,name,email,is_active,created_at FROM users UNION ALL SELECT 'admin' AS account_type,admin_id AS account_id,name,email,1 AS is_active,created_at FROM admins";
$where='';$params=[];
if($search!==''){$where=' WHERE name LIKE :name OR email LIKE :email';$like='%'.$search.'%';$params=['name'=>$like,'email'=>$like];}
try{
$count=$pdo->prepare("SELECT COUNT(*) FROM ($accounts) AS accounts $where");$count->execute($params);$total=(int)$count->fetchColumn();
$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
$stmt=$pdo->prepare("SELECT account_type,account_id,name,email,is_active,created_at FROM ($accounts) AS accounts $where ORDER BY created_at ASC,account_type ASC,account_id ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
echo json_encode(['success'=>true,'accounts'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'pages'=>$pages,'total'=>$total,'csrf_token'=>$_SESSION['csrf_token']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){error_log('Admin users API failed: '.$e->getMessage());http_response_code(500);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>'Unable to load users. Please try again later.']);}