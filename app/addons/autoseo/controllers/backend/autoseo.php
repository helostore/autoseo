<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }



if ($mode == 'test') {
	$urls = file(dirname(__FILE__).'/404.txt', FILE_IGNORE_NEW_LINES);

    foreach ($urls as $source) {
//        aa('Src : '.$source);
        $destination = AutoSEO::resolveUri($source);
        if ($destination == 'http://local.clara-linge.com/cl54.php') {
//            continue;
        }
//        aa('Dest: '.$destination);

//        aa($source.' => '.$destination);
        aa($source.','.$destination);
    }
//	aa($urls,1);
    exit;
}