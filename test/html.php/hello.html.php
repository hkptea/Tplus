<?php /* Tplus 2023-01-08 14:46:08 D:\Work\tpl\test\html\hello.html 000000434 */ ?>
<html>
<head>
    <title>welcome!</title>
</head>
<body>

	<div><?= \TplValWrapper::_o($V["content"])->substr(0,2) ?></div>


    <div><?php $L1=[2=>37,"abc"=>95];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
<?= $L1i +$L1s ?>:<?= $L1k ?>:<?= $L1v ?><br/> <?php }} ?>
</div>
</body>
</html>





