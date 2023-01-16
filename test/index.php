<?php
include '../dist/Tpl-dist.php';
const MY_CONST = 111;
const MY_CONST_ARRAY= ['a'=>1, 'b'=> 2, 'c'=>3];
$country = [
	[
		'name'=>'South Korea',
		'pop'=> 50,
		'city'=>[
			[
				'name'=> 'Seoul',
				'pop' => 10,
			],
			[
				'name'=> 'Sejong',
				'pop' => 0.3,

			]
		]
	],
	[
		'name'=>'Republic of Maldives',
		'pop'=> 0.4,
		'city'=>[
			[
				'name'=> 'Male',
				'pop' => 0.15,
			]
		]
	]
];
class xxx {
	public function bar() {
		return 'return from method.';
	}
}
class zzz {
	public function baz() {
		return new xxx();
	}
}
function bar() {
	return 'good good';
}
class Product {
	private $name;
	private $code;
	private $price;
	function __construct($name, $code, $price) {	
		$this->name = $name;
		$this->code = $code;
		$this->price = $price;
	}
	function __call($method, $args=[]) { 
		return $this->{$method} ?? ''; 
	}
	function price($currency='$') {
		return $currency.number_format($this->price,2); 
	}
}

class Foo {
	const ITEMS_PER_PAGE = 30;
	public static function bar() {
		return 'from static method.';
	}
}

$products = [
	new Product('vitamin', '001', 100), 
	new Product('shoes', '002', 123)
];

include 'hello.nsBar.php';
include 'hello.nsWidget.php';

$sub = Tpl::get('sub.html', ['fooo'=>[8,9,10,11,12,13]]);

$vals = [
	'content' => 'hello world',
	'a' => 10,
	'b' => 20,
	'foo'	=> 'hello~',
	'bar'	=> 'bbb',
	'caz'	=> 'zzz',
	'country' => $country,
	'foo1'	=>[1, 2],
	'bar1'	=>[3, 4, 5],
	'foo2'	=>true,
	'foo3'	=>null,
	'bar2'	=>'Tplus if',
	'bar3'	=>'ternary operator',
	'fooo'	=>[1,2,3,4,5],
	'xx'=> new xxx(),
	'yy'=> ['baz'=>'from array'],
	'zz'=> new zzz(),
	'product' => $products,
	'sub'=>'sub.html',
	'sub2'=>$sub,
	'pants'=>'jeans',
	'fruit'=>'lemon',
	'article' => "a < b \n b > c",
];


echo Tpl::get('index.html', $vals);