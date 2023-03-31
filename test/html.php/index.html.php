<?php /* Tplus 1.0.3 2023-03-31 22:06:49 D:\Work\Tplus\test\html\index.html 000007861 */ ?>
<html>
<head>
    <title>welcome!</title>
	<style>
		td {border-top:1px solid gray}
	</style>
</head>
<body>
<table>


	
	<?= $V["lsadkfj"] ?>

<tr>
	<th>
		문서 항목 번호
	</th>
	<th>
		실행 결과
	</th>
	<th>
		결과 코드
	</th>
</tr>
<tr>
	<td>7.</td>
	<td><?= $V["foo"] ?> <?= bar() ?></td>
	<td>hello~ good good</td>
</tr>
<tr>
	<td>9.1</td>
	<td>
	<?php $L1=range(1,2);if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?php $L2=range(3,5);if (is_array($L2) and !empty($L2)) {$L2s=count($L2);$L2i=-1;foreach($L2 as $L2k=>$L2v) { ++$L2i; ?>
			   <?= $L1v ?> x <?= $L2v ?> = <?= $L1v*$L2v ?> <br/>
		<?php }} ?>
	<?php }} ?>
	</td>
	
	<td>
		1 x 3 = 3 <br/>
		1 x 4 = 4 <br/>
		1 x 5 = 5 <br/>
					2 x 3 = 6 <br/>
		2 x 4 = 8 <br/>
		2 x 5 = 10 <br/>
	</td>
</tr>

<tr>
	<td>9.2.1.</td>
	<td>
		<ul>
			<?php $L1=$V["country"];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
			<li>
				<?= $L1v["name"] ?> <?= $L1v["pop"] ?> million
				<ul>
				<?php $L2=$L1v["city"];if (is_array($L2) and !empty($L2)) {$L2s=count($L2);$L2i=-1;foreach($L2 as $L2k=>$L2v) { ++$L2i; ?>
				<li>
					<?= $L2v["name"] ?> <?= $L2v["pop"] ?> million (<?= $L2v["pop"]/$L1v["pop"]*100 ?>%)
				</li>
				<?php }} ?>
				</ul>
			</li>
			<?php }} ?>
		</ul>
			
	</td>
	
	<td>
		<ul>
			<li>
	South Korea 50 million
	<ul>
					<li>
		Seoul	10 million (20%)
	</li>
					<li>
		Sejong	0.3 million (0.6%)
	</li>
					</ul>
</li>
			<li>
	Republic of Maldives 0.4 million
	<ul>
					<li>
		Male	0.15 million (37.5%)
	</li>
					</ul>
</li>
		</ul>
	</td>
</tr>
<tr>
	<td>9.2.2.</td>
	<td>
	<?php $L1=$V["foo1"];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?php $L2=$V["bar1"];if (is_array($L2) and !empty($L2)) {$L2s=count($L2);$L2i=-1;foreach($L2 as $L2k=>$L2v) { ++$L2i; ?>		
			<?= $L1v ?> x <?= $L2v ?> = <?= $L1v*$L2v ?> <br/>
		<?php }} ?>
	<?php }} ?>
	</td>
	<td>
					
		1 x 3 = 3 <br/>
				
		1 x 4 = 4 <br/>
			
		1 x 5 = 5 <br/>
						
		2 x 3 = 6 <br/>
			
		2 x 4 = 8 <br/>
			
		2 x 5 = 10 <br/>
	</td>
</tr>
<tr>
	<td>10.1.</td>
	<td>
	<?php $L1=['a'=>'apple','b'=>'banana','c'=>'cherry'];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?= $L1i +1 ?>/<?= $L1s ?>. <?= $L1k ?>: <?= $L1v ?> <br/>
	<?php }} ?>
	</td>
	<td>
		1/3. a: apple <br/>
		2/3. b: banana <br/>
		3/3. c: cherry <br/>
	</td>
</tr>

<tr>
	<td>10.2.1.</td>
	<td>
	<?php $L1=['apple','banana',123=>'cherry'];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?= $L1s -$L1i ?>. <?= $L1k ?>: <?= $L1v ?> <br/>
	<?php }} ?>
	</td>
	<td>
		3. 0: apple <br/>
		2. 1: banana <br/>
		1. 123: cherry <br/>
	</td>
</tr>
<tr>
	<td>10.2.2.</td>
	<td>
	<?php $L1=[1,2];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?php $L2=[3,4,5];if (is_array($L2) and !empty($L2)) {$L2s=count($L2);$L2i=-1;foreach($L2 as $L2k=>$L2v) { ++$L2i; ?>
			   <?= $L1v ?> x <?= $L2v ?> = <?= $L1v*$L2v ?> <br/>
		<?php }} ?>
	<?php }} ?>
	
	</td>
	<td>
		1 x 3 = 3 <br/>
		1 x 4 = 4 <br/>
		1 x 5 = 5 <br/>
		2 x 3 = 6 <br/>
		2 x 4 = 8 <br/>
		2 x 5 = 10 <br/>
	</td>
</tr>
<tr>
	<td>11.</td>
	<td>
		<?= $V["fooo"][3] ?> <?= $V["fooo"][3] ?>
	</td>
	<td>
		4 4	
	</td>
</tr>
<tr>
	<td>12.1.</td>
	<td>
	<?= $V["xx"]->bar() ?><br>
	<?= $V["yy"]["baz"] ?>
	</td>
	<td>
	return from method.<br>
	from array	
	</td>
</tr>
<tr>
	<td>12.2.</td>
	<td>
	<?= \TplValWrapper::_o($V["zz"]->baz())->bar() ?>
	</td>
	<td>
	return from method.	
	</td>
</tr>
<tr>
	<td>12.3.</td>
	<td>
	
	<?php $L1=$V["product"];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?= $L1v->code() ?> <?= $L1v->name() ?> <?= $L1v->price() ?><br/>
	<?php }} ?>

		
	</td>
	<td>
	001 vitamin $100.00<br/>
	002 shoes $123.00<br/>
	</td>
</tr>
<tr>
	<td>13.1.1.</td>
	<td>
		<?= \MY_CONST ?>
	</td>
	<td>
		111
	</td>
</tr>
<tr>
	<td>13.1.2.</td>
	<td>
	<?php $L1=\MY_CONST_ARRAY;if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?><?= $L1k ?>:<?= $L1v ?><br/><?php }} ?>
	</td>
	<td>
	
	a:1<br/>b:2<br/>c:3<br/>	

	</td>
</tr>
<tr>
	<td>13.2.</td>
	<td>
	<?= \Foo::bar() ?><br/>
	<?= \Foo::ITEMS_PER_PAGE ?><br/>
	<?= \Bar\baz\bar() ?><br/>
	<?= \Bar\baz\ITEMS_PER_PAGE ?><br/>
	</td>
	<td>
		from static method.<br/>
		30<br/>
		from namespace function<br/>
		50<br/>	
	</td>
</tr>

<tr>
	<td>13.3.</td>
	<td>
	<?= \Widget\Calender::draw() ?><br/>
	<?= \Widget\Calender::MONTH["march"] ?><br/>
	</td>
	<td>
	달력위젯이 그림<br/>
	3<br/>
	</td>
</tr>

<tr>
	<td>14.1.1.</td>
	<td>
	<?= $this->fetch('sub.html') ?>
	</td>
	<td>
	<div>4 4</div>	

	</td>
</tr>

<tr>
	<td>14.1.2.</td>
	<td>
	<?= \Tpl::get('sub.html',['fooo'=>[3,4,5,6,7,8]]) ?>

	</td>
	<td>
	<div>6 6</div>
	</td>
</tr>
<tr>
	<td>14.2.1</td>
	<td>
	<?= $this->fetch($V["sub"]) ?>
	</td>
	<td>
	<div>4 4</div>
	</td>
</tr>
<tr>
	<td>14.2.2</td>
	<td>
	<?= $V["sub2"] ?>
	</td>
	<td>
	<div>11 11</div>
	</td>
</tr>


<tr>
	<td>15.</td>
	<td>
	<?php if ($V["foo2"]) { ?><?= $V["bar2"] ?><?php } else { ?>baz<?php } ?><br/>
	<?= $V["foo2"]?$V["bar3"]:"baz" ?><br/>
	<?= $V["foo"]?:"bar" ?><br/>
	<?= $V["foo3"]??"bar" ?><br/>
	<?= $V["foo3"]?:bar()?:"baz" ?><br/>
	<?= $V["foo"]?($V["bar"]?'foobar':'foo'):'no' ?>
	</td>
	<td>
	Tplus if	<br/>
	ternary operator	<br/>
	hello~	<br/>
	bar<br/>
	good good	<br/>
	foobar
	</td>
</tr>




<tr>
	<td>17. </td>
	<td>
		<?= $this->assign(['foo'=>123]) ?>					
		<?= $this->assign(['foo'=>456,'bar'=>'bbb']) ?>	
	
		<?= $V["foo"] ?> <?= $V["bar"] ?>
	
	</td>
	<td>
		456 bbb
	</td>
</tr>

<tr>
	<td>19.</td>
	<td>

		<?= ucfirst($V["bar"]."baz".$V["caz"]) ?>
	</td>
	<td>
		Bbbbazzzz

	</td>
</tr>

<tr>
	<td></td>
	<td>
		
	</td>
	<td>

	</td>
</tr>

<tr>
	<td>20</td>
	<td>
	<?php $L1=[];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?= $L1v ?>
	<?php }} else { ?>
		foo empty
	<?php } ?>
<br/>
<?php if ($V["fruit"]=='apple'||$V["fruit"]=='cherry') { ?>
	red
<?php } else if ($V["fruit"]=='blueberry'||$V["pants"]=='jeans') { ?>
	blue
<?php } else { ?>
	unkown
<?php } ?>

	</td>
	<td>
		foo empty
		<br/>
		blue
	</td>
</tr>
<tr>
	<td>21.</td>
	
	
	
	<td>
		<?= \TplValWrapper::_o("abcde")->toUpper() ?>

	<?= \TplValWrapper::_o([2,5,8])->average() ?> <br/>

	<?= \TplValWrapper::_o(\TplValWrapper::_o("abcde")->toUpper())->substr(1,3) ?> <br/>

	<?= \TplValWrapper::_o(\TplValWrapper::_o($V["article"])->esc())->nl2br() ?> <br/>

<?php $L1=$V["product"];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
	<?= $L1v->code() ?> <?= \TplValWrapper::_o(\TplValWrapper::_o($L1v->name())->substr(2))->ucfirst() ?> <?= $L1v->price() ?><br/>
<?php }} ?>


	</td>
	<td>
		ABCDE
	5 <br/>

	BCD <br/>

	a &lt; b <br />
 b &gt; c <br/>

	001 Vit $100.00<br/>
	002 Sho $123.00<br/>
	</td>
</tr>
<tr>
	<td>22.</td>
	
	<td>

	<?php $L1=['apple','banana','cherry'];if (is_array($L1) and !empty($L1)) {$L1s=count($L1);$L1i=-1;foreach($L1 as $L1k=>$L1v) { ++$L1i; ?>
		<?= $L1i ?>: <?= $L1v ?>:
		<?= \TplLoopHelper::_o($L1i,$L1s,$L1k,$L1v)->isEven()?"even":"odd" ?> <?php if (\TplLoopHelper::_o($L1i,$L1s,$L1k,$L1v)->isLast()) { ?>--Last<?php } ?><br/>
	<?php }} ?>

	</td>
	<td>
		0: apple:
		odd <br/>
			1: banana:
		even <br/>
			2: cherry:
		odd --Last<br/>
	</td>
</tr>
</table>


<br/>
<br/>
[=SERVER.PHP_SELF]:  <?= $_SERVER["PHP_SELF"] ?> 
<br/>
<br/>
[=GLOBALS.me]:  <?= $GLOBALS["me"] ?> 
<br/>
<br/>
	

</body>
</html>


