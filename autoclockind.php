<?php
require 'funcDefd.php';


if (php_sapi_name() == 'apache2handler')
    exit('此脚本禁止外部访问');
if (php_sapi_name() == 'cli'){
    $connection = mysqli_connect('127.0.0.1', 'root', '123456', 'autoclockin');
    $sql = "select username, password from userd where valid = 1 and autoclockin = 1;";
    $result = mysqli_query($connection, $sql);
    while($user = mysqli_fetch_assoc($result)){
        $logInfo = getLoginPage();
        $loginResult = signin($user['username'], $user['password'], $logInfo['lt'], $logInfo['execution']);
        $authResult = auth($loginResult['jsessionid'], $loginResult['tgc']);
        $formParams = getForm($authResult['sessionID'], $authResult['jsessionid']);
        $finalResult = postForm($authResult['jsessionid'], $authResult['sessionID'], $formParams);
        if($finalResult === true){
            echo date('Y-m-d H:i:s    ').$user['username'].'    打卡成功'."\n";
        }else
            echo date('Y-m-d H:i:s    ').$user['username'].'    打卡失败'."\n";
        sleep(60);
    }

}