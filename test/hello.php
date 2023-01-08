<?php
include '../dist/Tpl-dist.php';

$vals = [
	'content' => 'hello world',
	'a' => 10,
	'b' => 20,
];

echo Tpl::get('hello.html', $vals);