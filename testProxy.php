<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2018/5/4
 * Time: 上午11:36
 */

function test() {
    
    $requestUrl = 'http://www.baidu.com';
    $ch         = curl_init();
    $timeout    = 5;
    curl_setopt($ch, CURLOPT_URL, $requestUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1"); //代理服务器地址
    curl_setopt($ch, CURLOPT_PROXYPORT, 9509); //代理服务器端口
    $p = 'test:test33';
    $p = base64_encode($p);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["proxy-check:$p"]); //使用http代理模式
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); //使用http代理模式
    $file_contents = curl_exec($ch);
    curl_close($ch);
    echo $file_contents;
}

test();
