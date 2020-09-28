<?php
require 'funcDefd.php';

/**
 * @param $returnMsg
 * 回复消息并结束脚本
 */
function over($returnMsg){
    exit("<xml><ToUserName>{$GLOBALS['fromUserName']}</ToUserName><FromUserName>{$GLOBALS['toUserName']}</FromUserName><CreateTime>{$GLOBALS['createTime']}</CreateTime><MsgType>text</MsgType><Content>$returnMsg</Content></xml>");
}

//=======================================================程序开始========================================================

if ($_SERVER['REQUEST_METHOD'] == 'GET'){
    if (checkSignature())
        echo $_GET['echostr'];
}

$connection = mysqli_connect('127.0.0.1', 'root', '123456', 'autoclockin');

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $postContent = file_get_contents('php://input');
    //解析xml
    $parser = xml_parser_create();
    xml_parse_into_struct($parser, $postContent, $values, $index);
    $toUserName = $values[$index['TOUSERNAME'][0]]['value'];
    $fromUserName = $values[$index['FROMUSERNAME'][0]]['value'];
    $createTime = $values[$index['CREATETIME'][0]]['value'];
    $msgType = $values[$index['MSGTYPE'][0]]['value'];
    $content = $values[$index['CONTENT'][0]]['value'];
    $event = $values[$index['EVENT'][0]]['value'];

    $GLOBALS['toUserName'] = $toUserName;
    $GLOBALS['fromUserName'] = $fromUserName;
    $GLOBALS['createTime'] = $createTime;
    $GLOBALS['msgType'] = $msgType;
    $GLOBALS['content'] = $content;

    //关注事件
    if ($event == 'subscribe')
        over("是时候摆脱辅导员的催命了！\n回复“指令列表”开启快意人生\(≧▽≦)/\n注意：符号区分中英文模式！");

    //取消关注事件
    if ($event == 'unsubscribe'){
        $sql = "select username, password from userd where openid = '$fromUserName' and valid = 1;";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(!empty($user)){
            $sql = "update userd set valid = 0 where openid = '$fromUserName'";
            $result = mysqli_query($connection, $sql);
        }
    }

    //===============================================钉钉打卡===============================================================

    //指令列表
    if ($content == '指令列表'){
        over("绑定账号：绑定新版打卡账号，默认启动定时打卡任务，每天7点开始自动打卡\n立即打卡：立即进行一次打卡\n打卡状态：查看当前打卡状态\n取消打卡：取消自动打卡任务\n取消绑定：取消打卡账号绑定\n绑定状态：查看当前绑定账号\n指令列表：显示此列表");
    }

    //绑定账号提示信息
    if ($content == '绑定账号'){
        over("指令错误，该指令须指定用户名和密码，中间用空格分隔。\n(默认密码为身份证号后八位)\n示例:\n绑定账号 A1356323415 12345678");
    }

    //指令 绑定账号
    if (mb_strpos($content, '绑定账号') === 0){
        $info = preg_split('/[\s]+/', mb_substr($content, 4), -1, PREG_SPLIT_NO_EMPTY);
        $username = $info[0];
        $password = $info[1];

        $time = (int)date('H');
        if ($time>0 && $time<6)
            over('打卡服务器已关闭，无法验证账号');

        $logInfo = getLoginPage();
        $loginResult = signin($username, $password, $logInfo['lt'], $logInfo['execution']);
        if (empty($loginResult['tgc'])) over('登录失败，用户名或密码错误');
        //登录成功，写数据库
        $sql = "select username, password from userd where openid = '$fromUserName' and valid = 0;";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(!empty($user)){
            $sql = "delete from userd where openid = '$fromUserName';";
            $result = mysqli_query($connection, $sql);
            if(!$result)
                over('数据库写入失败');
        }
        $sql = "insert into userd values('$fromUserName','$username','$password',1,1);";
        $result = mysqli_query($connection, $sql);
        if(!$result)
            over('数据库写入失败，请查看当前微信是否已绑定账号');
        else
            over('绑定成功');

    }

    //指令 立即打卡
    if ($content == '立即打卡'){
        $sql = "select username, password from userd where openid = '$fromUserName' and valid = 1;";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');

        $time = (int)date('H');
        if ($time<6 || $time>15)
            over('不在规定的打卡时间内，请在每天6:00~15:00之间打卡');

        $logInfo = getLoginPage();
        $loginResult = signin($user['username'], $user['password'], $logInfo['lt'], $logInfo['execution']);
        $authResult = auth($loginResult['jsessionid'], $loginResult['tgc']);
        $formParams = getForm($authResult['sessionID'], $authResult['jsessionid']);
        $finalResult = postForm($authResult['jsessionid'], $authResult['sessionID'], $formParams);
        if($finalResult === true){
            over('打卡成功');
        }
        over('打卡失败');
    }

    //指令 打卡状态
    if ($content == '打卡状态'){
        $time = (int)date('H');
        if ($time>0 && $time<6)
            over('打卡服务器已关闭，无法登录账号');

        $sql = "select username, password from userd where openid = '$fromUserName' and valid = 1;";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');

        $logInfo = getLoginPage();
        $loginResult = signin($user['username'], $user['password'], $logInfo['lt'], $logInfo['execution']);
        $authResult = auth($loginResult['jsessionid'], $loginResult['tgc']);
        $formParams = getForm($authResult['sessionID'], $authResult['jsessionid']);
        if (empty($formParams['tw'])) over('今日未打卡');
        over('今日已打卡');
    }

    //指令 取消打卡
    if ($content == '取消打卡'){
        $sql = "select username, password from userd where openid = '$fromUserName' and valid = 1";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');
        $sql = "update userd set autoclockin = 0 where openid = '$fromUserName'";
        $result = mysqli_query($connection, $sql);
        if (!$result)
            over('数据库写入失败');
        over('已取消自动打卡');
    }

    //指令 取消绑定
    if ($content == '取消绑定'){
        $sql = "select username, password from userd where openid = '$fromUserName' and valid = 1";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');
        $sql = "update userd set valid = 0 where openid = '$fromUserName'";
        $result = mysqli_query($connection, $sql);
        if (!$result)
            over('数据库写入失败');
        over('取消绑定成功');
    }

    //指令 绑定状态
    if ($content == '绑定状态'){
        $sql = "select username, password from userd where openid = '$fromUserName' and valid = 1";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');
        over("当前微信已绑定账号：{$user['username']}");
    }

    //==========================================心系疫情打卡（已弃用）=======================================================
    /*
    //指令列表
    if ($content == '指令列表'){
        over("绑定账号：绑定心系疫情账号，默认启动定时打卡任务，每天7点开始自动打卡\n立即打卡：立即进行一次打卡\n打卡状态：查看当前打卡状态(Beta请以网站显示为准)\n取消打卡：取消自动打卡任务\n取消绑定：取消打卡账号绑定\n绑定状态：查看当前绑定账号\n指令列表：显示此列表");
    }

    //绑定账号提示信息
    if ($content == '绑定账号'){
        over("指令错误，该指令须指定用户名和密码，中间用空格分隔。\n示例:\n绑定账号 A1356323415 Zhdkz12.");
    }

    //指令 绑定账号
    if (mb_strpos($content, '绑定账号') === 0){
        $info = preg_split('/[\s]+/', mb_substr($content, 4), -1, PREG_SPLIT_NO_EMPTY);
        $username = $info[0];
        $password = $info[1];

        $accessToken = login($username, $password);
        //登录成功，写数据库
        $sql = "select username, password from user where openid = '$fromUserName' and valid = 0;";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(!empty($user)){
            $sql = "delete from user where openid = '$fromUserName';";
            $result = mysqli_query($connection, $sql);
            if(!$result)
                over('数据库写入失败');
        }
        $sql = "insert into user values('$fromUserName','$username','$password',1,1);";
        $result = mysqli_query($connection, $sql);
        if(!$result)
            over('数据库写入失败，请查看当前微信是否已绑定账号');
        else
            over('绑定成功');

    }

    //指令 立即打卡
    if ($content == '立即打卡'){
        $sql = "select username, password from user where openid = '$fromUserName' and valid = 1;";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');

        $time = (int)date('H');
        if ($time<6 || $time>15)
            over('不在规定的打卡时间内，请在每天6:00~15:00之间打卡');

        $accessToken = login($user['username'], $user['password']);
        $id = getId($accessToken);
        $sessionid = getSessionid($accessToken, $id);
        if(clockin($accessToken,$sessionid)){
            closeSession($accessToken, $sessionid);
            over('打卡成功');
        }
        closeSession($accessToken, $sessionid);
        over('打卡失败');
    }

    //指令 打卡状态
    if ($content == '打卡状态'){
        $sql = "select username, password from user where openid = '$fromUserName' and valid = 1";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');
        $accessToken = login($user['username'], $user['password']);
        $id = getId($accessToken);
        $sessionid = getSessionid($accessToken, $id);
        $status = getStatus($accessToken, $sessionid);
        closeSession($accessToken, $sessionid);
        over($status);
    }

    //指令 取消打卡
    if ($content == '取消打卡'){
        $sql = "select username, password from user where openid = '$fromUserName' and valid = 1";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');
        $sql = "update user set autoclockin = 0 where openid = '$fromUserName'";
        $result = mysqli_query($connection, $sql);
        if (!$result)
            over('数据库写入失败');
        over('已取消自动打卡');
    }

    //指令 取消绑定
    if ($content == '取消绑定'){
        $sql = "select username, password from user where openid = '$fromUserName' and valid = 1";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');
        $sql = "update user set valid = 0 where openid = '$fromUserName'";
        $result = mysqli_query($connection, $sql);
        if (!$result)
            over('数据库写入失败');
        over('取消绑定成功');
    }

    //指令 绑定状态
    if ($content == '绑定状态'){
        $sql = "select username, password from user where openid = '$fromUserName' and valid = 1";
        $result = mysqli_query($connection, $sql);
        $user = mysqli_fetch_assoc($result);
        if(empty($user))
            over('当前微信未绑定账号');
        over("当前微信已绑定账号：{$user['username']}");
    }

    */


    //聊天机器人
//    autotalk($content);
    over('请输入正确指令');
}
