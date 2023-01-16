<?php
include '../dist/Tpl-dist.php';

$vals = [
	'foo'	=> 'hello~',
];


echo Tpl::get('nest.html', $vals);