<?php
/**
 * 注册自动加载回调函数，使用不存在类时自动触发
 */


spl_autoload_register(function ($class) {
    $file = dirname(__FILE__)."/${class}.php";
    if(file_exists($file)){
        include_once  $file;
    }else{
        $file = dirname(__FILE__)."/DqWeb/${class}.php";
        if(file_exists($file)){
            include_once  $file;
        }
    }    
});
