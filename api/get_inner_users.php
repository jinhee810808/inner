<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors','0');
require __DIR__ . '/user_activity.php';
function usersReply($status,$body) { http_response_code($status); echo json_encode($body,JSON_UNESCAPED_UNICODE); exit; }
function queryRows($db,$sql) { $r=$db->query($sql); $rows=array(); while($row=$r->fetch_assoc())$rows[]=$row; $r->free(); return $rows; }
try {
    if ($_SERVER['REQUEST_METHOD']!=='GET') usersReply(405,array('success'=>false,'message'=>'GET 요청만 허용됩니다.'));
    activitySession();
    $id=isset($_SESSION['inner_user_id'])?(string)$_SESSION['inner_user_id']:'';
    if ($id==='') usersReply(401,array('success'=>false,'message'=>'로그인 세션이 없습니다. 다시 로그인해주세요.'));
    $db=activityDb();
    $stmt=$db->prepare('SELECT grade FROM inner_user WHERE user_id=? LIMIT 1');
    $stmt->bind_param('s',$id); $stmt->execute(); $stmt->bind_result($grade); $found=$stmt->fetch(); $stmt->close();
    if (!$found||!in_array($grade,array('마스터','마스터1'),true)) usersReply(403,array('success'=>false,'message'=>'사용자 목록 조회 권한이 없습니다.'));
    session_write_close();
    $users=queryRows($db,"SELECT u.id,u.user_id,u.name,u.phone,u.position,u.department,u.grade,u.created_at,u.updated_at,
        l.last_login,s.last_seen,IF(s.last_seen >= NOW()-INTERVAL 5 MINUTE,1,0) AS is_online
        FROM inner_user u
        LEFT JOIN (SELECT user_id,MAX(logged_at) last_login FROM inner_login_log WHERE result='success' GROUP BY user_id) l ON l.user_id=u.user_id
        LEFT JOIN (SELECT user_id,MAX(last_seen) last_seen FROM inner_user_session WHERE ended_at IS NULL GROUP BY user_id) s ON s.user_id=u.user_id
        ORDER BY u.id ASC");
    // 이력은 최신 500건, 상단 통계는 전체 오늘 기록에서 계산합니다.
    $logs=queryRows($db,'SELECT user_id,name,logged_at,result,ip,device FROM inner_login_log ORDER BY logged_at DESC,id DESC LIMIT 500');
    $counts=queryRows($db,"SELECT COUNT(DISTINCT CASE WHEN result='success' THEN user_id END) AS today_users,
        COALESCE(SUM(result='failed'),0) AS failed FROM inner_login_log WHERE logged_at>=CURDATE() AND logged_at<CURDATE()+INTERVAL 1 DAY");
    $online=0; foreach($users as $u)if((int)$u['is_online']===1)$online++;
    $stats=array('total'=>count($users),'online'=>$online,'today'=>(int)$counts[0]['today_users'],'failed'=>(int)$counts[0]['failed']);
    $db->close(); usersReply(200,array('success'=>true,'users'=>$users,'logs'=>$logs,'stats'=>$stats));
} catch(Throwable $e) {
    error_log('get_inner_users: '.$e->getMessage());
    // usersReply(500,array('success'=>false,'message' => '현황 조회 서버 오류입니다. 로그인 이력 테이블 생성 여부와 PHP 오류 로그를 확인해주세요.'));
    usersReply(500,array('success'=>false,'message' => '오류 확인: ' . $e->getMessage()));
}
