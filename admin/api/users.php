<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
$admin = requireAdmin(true);
require_once __DIR__ . '/../../config/db.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {header('Allow: GET');http_response_code(405);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>'Method not allowed.']);exit;}
$search=trim((string)($_GET['search'] ?? ''));
$page=max(1,filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT) ?: 1);
$perPage=25;
$accounts="SELECT 'user' AS account_type,user_id AS account_id,name,email,is_active,created_at FROM users UNION ALL SELECT 'admin' AS account_type,admin_id AS account_id,name,email,1 AS is_active,created_at FROM admins";
$where='';$params=[];
if($search!==''){$where=' WHERE name LIKE :name OR email LIKE :email';$like='%'.$search.'%';$params=['name'=>$like,'email'=>$like];}
$count=$pdo->prepare("SELECT COUNT(*) FROM ($accounts) AS accounts $where");$count->execute($params);$total=(int)$count->fetchColumn();
$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
$stmt=$pdo->prepare("SELECT account_type,account_id,name,email,is_active,created_at FROM ($accounts) AS accounts $where ORDER BY created_at ASC,account_type ASC,account_id ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
echo json_encode(['success'=>true,'accounts'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'pages'=>$pages,'total'=>$total,'csrf_token'=>$_SESSION['csrf_token']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
