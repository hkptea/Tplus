<?php

if ( ! class_exists('Tplus') ) {
	include 'Tplus.php';
}

class Tpl {	
	private static function config() {
		return [
			/**
				(1/3) template directory 
			*/
			'HtmlRoot' => './html/',

			/**
				(2/3) template script directory 
				      which needs write-permission of web server. 
			*/
    		'HtmlScriptRoot' => './html.php/',

			/**
				(3/3) script check
					true : check  template file and script it if necessary.
					false: ignore template file and just use script file.

					TIP: use your code for checking server mode.
					e.g. 'ScriptCheck' => $GLOBALS['server_mode']=='development' ? true : false;
			*/
			'ScriptCheck' => true
		];
	}

	public static function get($path, $vals=[]) {
		$_ = self::_();
		$_->assign($vals);
		return $_->fetch($path);
	}

	public static function _() {

		return new Tplus(static::config());
	}
}


class TplValWrapper extends TplusValWrapper {

	protected $val;

	public function shuffle() {
		if (is_array($this->val)) {
			return shuffle($this->val);
		}
		return str_shuffle((string)$this->val);
	}

	public function average() {
		if (is_array($this->val)) {
			return array_sum($this->val) / count($this->val);
		}
		throw new TplusRuntimeError(
			'average() method called on unsupported type '.gettype($this->val)
		);
	}

	public function format($decimals, $decimal_separator, $thousands_seperator) {
		if (is_array($this->val)) {
			$arr = [];
			foreach ($this->val as $n) {
				$arr[]=number_format((float)$n, $decimals, $decimal_separator, $thousands_seperator);
			}
			return $arr;
		}
		return number_format((float)$this->val, $decimals, $decimal_separator, $thousands_seperator);
	}
}

class TplLoopHelper extends TplusLoopHelper {

	protected $i;
	protected $s;
	protected $k;
	protected $v;

	public function isEven() {
		return $this->i % 2 ? true : false;
	}
	public function isLast() {
		return $this->i + 1 == $this->s;
	}
}

/** end */