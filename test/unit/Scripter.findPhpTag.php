<?php
namespace TplusTest;

include_once '../../dist/TplusCompiler.php';


function report($a, $b, $c) {
	if ($a===$b) {
		echo 'ok!<br/>';
	} else {
		ob_start();
		print_r(func_get_args());
		$x=ob_get_clean();
		echo (htmlspecialchars($x));
		exit;

	}
}

// $path = '';
// $htmlPath = './html/'.$path;
// $compileRoot = './html.php/';
// $compilePath = $compileRoot.$path;


$test = [
	'func'=>'findPhpTag',
	'args'=>[str_replace("\r", '', file_get_contents('compile.findPhpTag.find.html'))]
];

// compile($htmlPath, $compilePath, $compileRoot, 9, $test);
[$currentLine, $foundPhpTag] = \Tplus\Compiler::compile(null, null, null, null, $test);
report($currentLine, 6, 'compile.findPhpTag.Line');
report($foundPhpTag, "<?php\n", 'compile.findPhpTag.phpTag');

$test = [
	'func'=>'findPhpTag',
	'args'=>[str_replace("\r", '', file_get_contents('compile.findPhpTag.find.2.html'))]
];


// compile($htmlPath, $compilePath, $compileRoot, 9, $test);
[$currentLine, $foundPhpTag] = \Tplus\Compiler::compile(null, null, null, null, $test);
report($currentLine, 3, 'compile.findPhpTag.Line.2');
report($foundPhpTag, "<?=x", 'compile.findPhpTag.phpTag.2');
