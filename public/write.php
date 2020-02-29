<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/6/6
 * Time: 17:46
 */
$myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
$txt = "Bill Gates\n";
fwrite($myfile, $txt);
$txt = "Steve Jobs\n";
fwrite($myfile, $txt);
fclose($myfile);