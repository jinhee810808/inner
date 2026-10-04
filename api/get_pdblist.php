<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors','0');
function pdReply($status,$body){http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE);exit;}
$db=null;
try{
    ini_set('session.use_strict_mode','1');
    session_set_cookie_params(0,'/','',!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off',true);
    if(!session_start())throw new RuntimeException('세션 시작 실패');
    $uid=isset($_SESSION['inner_user_id'])?(string)$_SESSION['inner_user_id']:'';
    if($uid==='')pdReply(401,array('success'=>false,'message'=>'다시 로그인해주세요.'));
    $c=require __DIR__.'/config.php';
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    $db=new mysqli($c['db_host'],$c['db_user'],$c['db_password'],$c['db_name']);$db->set_charset('utf8mb4');$db->query("SET time_zone='+09:00'");
    $stmt=$db->prepare('SELECT name FROM inner_user WHERE user_id=? LIMIT 1');$stmt->bind_param('s',$uid);$stmt->execute();$stmt->bind_result($manager);$found=$stmt->fetch();$stmt->close();
    if(!$found)pdReply(401,array('success'=>false,'message'=>'사용자 계정이 없습니다.'));
    if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
    $csrf=$_SESSION['csrf_token'];
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $stmt=$db->prepare('SELECT id,name,phone,receipt_date,maker,car_name,manager,manager_id,memo,updated FROM inner_pdblist WHERE manager_id=? ORDER BY id DESC');
        $stmt->bind_param('s',$uid);$stmt->execute();
        $stmt->bind_result($id,$name,$phone,$receipt,$maker,$car,$owner,$ownerId,$memo,$updated);$data=array();
        while($stmt->fetch())$data[]=array('id'=>$id,'name'=>$name,'phone'=>$phone,'receipt_date'=>$receipt,'maker'=>$maker,'car_name'=>$car,'manager'=>$owner,'manager_id'=>$ownerId,'memo'=>$memo,'updated'=>$updated);
        $stmt->close();pdReply(200,array('success'=>true,'data'=>$data,'csrfToken'=>$csrf,'user'=>array('name'=>$manager,'user_id'=>$uid)));
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')pdReply(405,array('success'=>false,'message'=>'GET/POST만 허용됩니다.'));
    $input=json_decode(file_get_contents('php://input'),true);
    if(!is_array($input)||!isset($input['csrfToken'])||!is_string($input['csrfToken'])||!hash_equals($csrf,$input['csrfToken']))pdReply(403,array('success'=>false,'message'=>'인증 정보가 만료되었습니다. 화면을 새로고침해주세요.'));
    $action=isset($input['action'])?$input['action']:'';
    if($action==='delete'){
        if(empty($input['ids'])||!is_array($input['ids'])||count($input['ids'])>1000)pdReply(400,array('success'=>false,'message'=>'삭제할 항목을 선택해주세요. 한 번에 1000건까지 가능합니다.'));
        $ids=array();foreach($input['ids'] as $v){if(filter_var($v,FILTER_VALIDATE_INT)===false||(int)$v<1)pdReply(400,array('success'=>false,'message'=>'삭제 번호가 올바르지 않습니다.'));$ids[]=(int)$v;}
        $db->begin_transaction();$count=0;$stmt=$db->prepare('DELETE FROM inner_pdblist WHERE id=? AND manager_id=?');$stmt->bind_param('is',$id,$uid);
        foreach(array_unique($ids) as $id){$stmt->execute();$count+=$stmt->affected_rows;}$stmt->close();$db->commit();pdReply(200,array('success'=>true,'deletedCount'=>$count));
    }
    if($action!=='save')pdReply(400,array('success'=>false,'message'=>'지원하지 않는 요청입니다.'));
    $limits=array('name'=>50,'phone'=>20,'maker'=>50,'car_name'=>100,'memo'=>10000);$values=array();
    foreach($limits as $key=>$limit){$v=isset($input[$key])?$input[$key]:'';if(!is_string($v))pdReply(400,array('success'=>false,'message'=>'입력 형식이 올바르지 않습니다.'));$v=trim($v);$length=function_exists('mb_strlen')?mb_strlen($v,'UTF-8'):preg_match_all('/./us',$v,$matches);if($length===false||$length>$limit)pdReply(400,array('success'=>false,'message'=>$key.' 입력 길이를 확인해주세요.'));$values[$key]=$v;}
    if($values['name']==='')pdReply(400,array('success'=>false,'message'=>'고객명을 입력해주세요.'));
    $receipt=isset($input['receipt_date'])?$input['receipt_date']:'';
    if(!is_string($receipt))pdReply(400,array('success'=>false,'message'=>'접수일 형식 오류'));
    if($receipt!==''){$d=DateTime::createFromFormat('!Y-m-d',$receipt);if(!$d||$d->format('Y-m-d')!==$receipt||(int)$d->format('Y')<1000)pdReply(400,array('success'=>false,'message'=>'접수일이 올바르지 않습니다.'));}else $receipt=null;
    $name=$values['name'];$phone=$values['phone'];$maker=$values['maker'];$car=$values['car_name'];$memo=$values['memo'];
    // 담당자와 아이디는 요청값을 신뢰하지 않고 로그인 계정으로 지정합니다.
    if(isset($input['id'])){
        if(filter_var($input['id'],FILTER_VALIDATE_INT)===false||(int)$input['id']<1)pdReply(400,array('success'=>false,'message'=>'수정 번호가 올바르지 않습니다.'));
        $id=(int)$input['id'];$db->begin_transaction();
        $stmt=$db->prepare('SELECT id FROM inner_pdblist WHERE id=? AND manager_id=? FOR UPDATE');$stmt->bind_param('is',$id,$uid);$stmt->execute();$stmt->bind_result($foundId);$owned=$stmt->fetch();$stmt->close();
        if(!$owned){$db->rollback();pdReply(404,array('success'=>false,'message'=>'수정 가능한 자료가 없습니다.'));}
        $stmt=$db->prepare('UPDATE inner_pdblist SET name=?,phone=?,receipt_date=?,maker=?,car_name=?,memo=?,manager=? WHERE id=? AND manager_id=?');
        $stmt->bind_param('sssssssis',$name,$phone,$receipt,$maker,$car,$memo,$manager,$id,$uid);$stmt->execute();$stmt->close();$db->commit();
    }else{
        $stmt=$db->prepare('INSERT INTO inner_pdblist (name,phone,receipt_date,maker,car_name,manager,manager_id,memo) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->bind_param('ssssssss',$name,$phone,$receipt,$maker,$car,$manager,$uid,$memo);$stmt->execute();$id=$db->insert_id;$stmt->close();
    }
    pdReply(200,array('success'=>true,'id'=>$id));
}catch(Throwable $e){if($db){try{$db->rollback();}catch(Throwable $ignored){}}error_log('pdblist: '.$e->getMessage());pdReply(500,array('success'=>false,'message'=>'개인 DB 처리 오류입니다. inner_pdblist 테이블과 api/config.php 설정을 확인해주세요.'));}
