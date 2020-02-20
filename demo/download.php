<?php
require '_inc.php';
use Ares333\Curl\Toolkit;
$toolkit = new Toolkit();
$toolkit->setCurl();
$curl = $toolkit->getCurl();
$curl->onInfo = null;
$url = 'https://www.baidu.com/img/bd_logo1.png';
$file = __DIR__ . '/output/download.png';
// $fp is closed automatically on download finished.
$fp = fopen($file, 'w');
$curl->add(
    array(
        'opt' => array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_HEADER => false
        ),
        'args' => array(
            'file' => $file
        )
    ),
    function ($r, $args) {
        if($r['info']['http_code']==200) {
            echo "download finished successfully, file=$args[file]\n";
        }else{
            echo "download failed\n";
        }
    })->start();