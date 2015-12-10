<?php

define('ROOT', dirname(__DIR__));

require_once ROOT.'/vendor/autoload.php';

$in_dir = ROOT.'/vendor/riot/riot/test/tag';
$out_dir = ROOT.'/test/output/compile';

if (!file_exists($in_dir)) {
    exit('Make sure to `composer install` with dev-require on');
}

@mkdir($out_dir, 0755, true);

$Compiler = new \RiotPhpTags\Compiler;
$Compiler->compile(glob($in_dir.'/*.tag'), $out_dir);

echo 'Done';
