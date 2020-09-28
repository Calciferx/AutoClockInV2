<?php

require 'simple_html_dom.php';

/**
 * @return bool 微信平台验证成为开发者
 */
function checkSignature()
{
    $signature = $_GET["signature"];
    $timestamp = $_GET["timestamp"];
    $nonce = $_GET["nonce"];

    $token = 'wxtoken'; //微信 token
    $tmpArr = array($token, $timestamp, $nonce);
    sort($tmpArr, SORT_STRING);
    $tmpStr = implode( $tmpArr );
    $tmpStr = sha1( $tmpStr );

    if( $tmpStr == $signature ){
        return true;
    }else{
        return false;
    }
}

function getLoginPage(){
    $html = file_get_html('https://authserver.zjou.edu.cn/cas/login');
    $lt = $html->find('input[name=lt]', 0)->value;
    $execution = $html->find('input[name=execution]', 0)->value;
    return  ['lt'=>$lt, 'execution'=>$execution];
}

function encrypt($password, $aesKey='key_value_123456', $iv='0987654321123456'){
    $encrypted = openssl_encrypt($password, 'AES-128-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
    return strtoupper(bin2hex($encrypted));
}

function signin($username, $password, $lt, $execution){
    $password = encrypt($password);
    $execution = urlencode($execution);
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://authserver.zjou.edu.cn/cas/login",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "password={$password}&username={$username}&_eventId=submit&lt={$lt}&execution={$execution}",
        CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "Content-Type: application/x-www-form-urlencoded"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $header = substr($response, 0 , $headerSize);
        preg_match('/JSESSIONID=[^;]*;/', $header, $jsessionid);
//        $jsessionid = preg_replace('/(JSESSIONID=[^;]*);/', '$1', $jsessionid[0]);
        preg_match('/TGC=[^;]*;/', $header, $tgc);
//        $tgc = preg_replace('/(TGC=[^;]*);/', '$1', $tgc[0]);

        return ['jsessionid'=>$jsessionid[0], 'tgc'=>$tgc[0]];
    }
}


function auth($jsessionid, $tgc){
    $curl = curl_init();
    $cookie = tempnam('/tmp', 'CURLCOOKIE');

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://authserver.zjou.edu.cn/cas/login?service=https%3A%2F%2Fbdmobile.zjou.edu.cn%2Fwebroot%2Fdecision%2Fview%2Fform%3Fviewlet%3Dxxkj%25252Fmobile%25252Fbpa%25252F%2525E6%25258A%2525A5%2525E5%2525B9%2525B3%2525E5%2525AE%252589.frm%26op%3Dh5",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_HEADER =>true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "Cookie:$jsessionid $tgc"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        preg_match('/fine_auth_token.*;/',$response, $fine_auth_token);
        preg_match('/JSESSIONID=[^;]*;/', $response, $jsessionid);
        preg_match('/sessionID\(\) {return \'(.*)\'}/',$response, $sessionID);
        $sessionID = preg_replace('/sessionID\(\) {return \'(.*)\'}/','$1', $sessionID[0]);
        return ['fine_auth_token'=>$fine_auth_token[0], 'jsessionid'=>$jsessionid[0], 'sessionID'=>$sessionID];
    }
}


function getForm($sessionID, $jsessionid){
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://bdmobile.zjou.edu.cn/webroot/decision/view/form?sessionID={$sessionID}&op=fr_form&cmd=load_content&toVanCharts=true&fine_api_v_json=3&widgetVersion=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "Cookie: $jsessionid"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $response = preg_replace('/,"data":\[\{"id":"1","text":"北京市".*?"hasChildren":false\}\]\}\]\}\]/', '', $response);
        //姓名
        preg_match('/(?:"widgetName":"XM","disabled":true,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $xm);
        //部门
        preg_match('/(?:"widgetName":"BM","disabled":true,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $bm);
        //手机号码
        preg_match('/(?:"widgetName":"SJHM","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $sjhm);
        //籍贯
        preg_match('/(?:"widgetName":"JG","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $jg);

        //上报日期--特殊处理
//        preg_match('/(?:"widgetName":"SBRQ","disabled":true,"invisible":false,"needSubmit":true,"value":\{"date_milliseconds":)(.*?)(?:\},")/', $response, $sbrq);
        $sbrq = date('Y-m-d');

//    //目前状态 正常 为 "1"
//    preg_match('/(?:"widgetName":"MQZT","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $mqzt);
//    //是否发热咳嗽 否 为 "0"
//    preg_match('/(?:"widgetName":"SFFRKS","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $sffrks);
        //体温 37~37.2 为 "2"
        preg_match('/(?:"widgetName":"TW1","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $tw);

        //目前居住地
        preg_match('/(?:"widgetName":"SZD","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $szd);
        //具体地址
        preg_match('/(?:"widgetName":"JTDZ","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $jtdz);

//    //是否经停，否为空
//    preg_match('/(?:"widgetName":"SFJT","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $sfjt);
//    //接触史 以上都没有 为 "9"
//    preg_match('/(?:"widgetName":"JCS","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $jcs);
//    //回国 以上都没有 为 "9"
//    preg_match('/(?:"widgetName":"HG","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $hg);

        //身份证号
        preg_match('/(?:"widgetName":"TEXTEDITOR0","disabled":true,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $sfzh);

//    //杭州健康码 绿码 为 "1"
//    preg_match('/(?:"widgetName":"HZJKM","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $hzjkm);
//    //我承诺 同意签订承诺书 为 "1"
//    preg_match('/(?:"widgetName":"WCN","disabled":false,"invisible":false,"needSubmit":true,"value":")(.*?)(?:")/', $response, $wcn);

        //jsConfId
        preg_match('/(?:jsConfId:")(.*?)(?:")/', $response, $jsConfId);

        return (['xm' => $xm[1], 'bm' => $bm[1], 'sjhm' => $sjhm[1], 'jg' => $jg[1], 'sbrq' => $sbrq, 'szd' => $szd[1], 'jtdz' => $jtdz[1], 'sfzh' => $sfzh[1], 'tw' => $tw[1], 'jsConfId'=>$jsConfId[1]]);
    }
}


function postForm($jsessionid, $sessionID, $formParams){
    $str = urldecode(file_get_contents('/var/www/wx/postParamStr'));
    $parameters = str_replace(['fb90f7ad-ca8e-4968-9e96-6f41ee94ca44', '*脱敏处理*', '*脱敏处理*', '*脱敏处理*', '*脱敏处理*', '2020-08-27', '*脱敏处理*', '*脱敏处理*', '*脱敏处理*'], [$formParams['jsConfId'], $formParams['xm'], $formParams['bm'], $formParams['sjhm'], $formParams['jg'], $formParams['sbrq'], $formParams['szd'], $formParams['jtdz'], $formParams['sfzh']], $str);
    $encodedParams = urlencode($parameters);

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://bdmobile.zjou.edu.cn/webroot/decision/view/form",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "op=dbcommit&__parameters__=$encodedParams",
        CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "Content-Type: application/x-www-form-urlencoded",
            "Cookie: $jsessionid",
            "sessionID: $sessionID"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
//        var_dump($response);
        return true;
    }
}


