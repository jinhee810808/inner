<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
require __DIR__ . '/user_activity.php';
function reply($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
$db = null;
try {
    activitySession();
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        activityLogout(); reply(200, array('success'=>true));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (empty($_SESSION['inner_user_id'])) {
            reply(401, array('success'=>false, 'message'=>'로그인이 필요합니다.'));
        }
        $db = activityDb();
        $id = (string)$_SESSION['inner_user_id'];
        $stmt = $db->prepare('SELECT name, grade FROM inner_user WHERE user_id=? LIMIT 1');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->bind_result($name, $grade);
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found || trim((string)$grade) === '') {
            reply(403, array('success'=>false, 'message'=>'사용자 등급을 확인할 수 없습니다.'));
        }
        $_SESSION['inner_user_name'] = $name;
        $_SESSION['grade'] = trim((string)$grade);
        reply(200, array(
            'success'=>true,
            'user_id'=>$id,
            'name'=>$name,
            'grade'=>$_SESSION['grade'],
            'csrfToken'=>$_SESSION['csrf_token'] ?? ''
        ));
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405,array('success'=>false,'message'=>'허용되지 않은 요청입니다.'));
    $input = json_decode(file_get_contents('php://input'),true);
    $id = is_array($input) && isset($input['userId']) ? trim((string)$input['userId']) : '';
    $password = is_array($input) && isset($input['password']) ? (string)$input['password'] : '';
    if ($id === '' || $password === '') reply(400,array('success'=>false,'message'=>'아이디와 비밀번호를 입력해주세요.'));
    if (strlen($id)>50) reply(400,array('success'=>false,'message'=>'아이디는 50바이트 이내로 입력해주세요.'));
    $db = activityDb();
    $stmt = $db->prepare('SELECT user_id,password_hash,name,grade FROM inner_user WHERE user_id=? LIMIT 1');
    $stmt->bind_param('s',$id); $stmt->execute(); $stmt->bind_result($foundId,$hash,$name,$grade);
    $found=$stmt->fetch(); $stmt->close();
    if (!$found || !password_verify($password,$hash)) {
        activityLog($db,$id,'','failed');
        reply(401,array('success'=>false,'message'=>'아이디 또는 비밀번호가 올바르지 않습니다.'));
    }
    if (trim((string)$grade) === '') reply(403,array('success'=>false,'message'=>'사용자 등급을 확인할 수 없습니다.'));
    $oldHash = activityHash();
    $csrf = bin2hex(random_bytes(32));
    if (!session_regenerate_id(true)) throw new RuntimeException('세션 갱신 실패');
    $sessionHash = activityHash();
    $db->begin_transaction();
    $stmt=$db->prepare('UPDATE inner_user_session SET ended_at=NOW() WHERE session_hash=? AND ended_at IS NULL');
    $stmt->bind_param('s',$oldHash); $stmt->execute(); $stmt->close();
    activityLog($db,$foundId,$name,'success');
    $stmt=$db->prepare('INSERT INTO inner_user_session (session_hash,user_id,logged_at,last_seen) VALUES (?,?,NOW(),NOW())');
    $stmt->bind_param('ss',$sessionHash,$foundId); $stmt->execute(); $stmt->close();
    $db->commit();
    $_SESSION['inner_user_id']=$foundId;
    $_SESSION['inner_user_name']=$name;
    $_SESSION['csrf_token']=$csrf;
    $_SESSION['grade']=trim((string)$grade);
    reply(200,array('success'=>true,'user_id'=>(string)$foundId,'name'=>$name,'grade'=>$_SESSION['grade'],'csrfToken'=>$csrf));
} catch (Throwable $e) {
    if ($db) { try { $db->rollback(); } catch (Throwable $ignored) {} }
    error_log('inner login: '.$e->getMessage());
    reply(500,array('success'=>false,'message'=>'로그인 서버 오류입니다. DB 설정과 로그인 이력 테이블 생성 여부를 확인해주세요.'));
}
