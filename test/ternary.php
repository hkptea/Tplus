<?php
include '../dist/Tpl-dist.php';

$vals = [
	'bar'	=> 123,
	'foo2'	=> 'hello~',
	'bar3'	=>	'good good'
];


echo Tpl::get('ternary.html', $vals);


/*
if ($parentOpener == '?' and $parentTernaryCount==0) {
	if (preg_match('~^\s*\)|\}|\]~', $userCode)) {
		return true;
	}
}
*/