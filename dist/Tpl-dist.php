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


class TplusValWrapper extends TplusValWrapperBase {

	protected $val;

	public function shuffle() {
		if (is_array($this->val)) {
			return shuffle($this->val);
		}
		if (!is_string($this->val)) {
			$this->val = (string)$this->val;
		}
		return str_shuffle($this->val);
	}

	public function average() {
		if (is_array($this->val)) {
			return array_sum($this->val) / count($this->val);
		}
		throw new TplusRuntimeError(
			'average() method called on unsupported type '.gettype($this->val)
		);
	}
}

class TplusLoopHelper extends TplusLoopHelperBase {

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