<?php

class Tplus {
    
    const SCRIPT_SIZE_PAD = 9;

    private $config;
    private $vals=[];

    public function __construct($config) {
        $this->config = $config;
    }

    public function assign($vals) {
        $this->vals = array_merge($this->vals, $vals);
        return '';
    }

    public function fetch($path) {
        $scriptPath = $this->config['HtmlScriptRoot'].$path.'.php';
        
        if ($this->config['ScriptCheck']) {
            $this->checkScript($path, $scriptPath);

        } else if (!is_file($scriptPath)) {
            trigger_error(
                "Tpl config 'ScriptCheck' is off and Tplus cannot find <b> ".$scriptPath.'</b>',
                E_USER_ERROR
            );
        }

		ob_start();
        $V = &$this->vals;
		include $scriptPath;
        $fetch = ob_get_contents();
        ob_end_clean();
        return $fetch;
    }

    private function script($htmlPath, $scriptPath) {
        include_once dirname(__file__).'/TplusScripter.php';
        \Tplus\Scripter::script(
            $htmlPath, 
            $scriptPath, 
            self::SCRIPT_SIZE_PAD, 
            $this->scriptHeader($htmlPath), 
            $this->config
        );
    }

    private function checkScript($path, $scriptPath) {
        $htmlPath = $this->config['HtmlRoot'].$path;

        if (!$this->isScriptValid($htmlPath, $scriptPath)) {
            //$this->script($this->config, $htmlPath, $scriptPath);
            $this->script($htmlPath, $scriptPath);
        }
    }
    
    private function isScriptValid($htmlPath, $scriptPath) {        
		if (!is_file($htmlPath)) {
			trigger_error(
                "Tpl config 'ScriptCheck' is on and Tplus cannot find <b> ".$htmlPath.'</b>', 
                E_USER_ERROR
            );
		}
        if (!is_file($scriptPath)) {
            return false;
        }

        return $this->isScriptUpdated($htmlPath, $scriptPath);
    }

    private function isScriptUpdated($htmlPath, $scriptPath) {
		$headerExpected = $this->scriptHeader($htmlPath);
        $headerWritten = file_get_contents(
            $scriptPath, false, null, 0, 
            strlen($headerExpected) + self::SCRIPT_SIZE_PAD
        );

        return (
            strlen($headerWritten) > self::SCRIPT_SIZE_PAD
            and $headerExpected == substr($headerWritten, 0, -self::SCRIPT_SIZE_PAD)
            and filesize($scriptPath) == (int)substr($headerWritten,-self::SCRIPT_SIZE_PAD) 
        );
    }
    private function scriptHeader($htmlPath) {
		$fileMTime = @date('Y-m-d H:i:s', filemtime($htmlPath));
		return '<?php /* Tplus 1.0 Beta 2 '.$fileMTime.' '.realpath($htmlPath).' ';
    }
}

class TplusValWrapper {
    
    static protected $instance;

    public static function _o($val) {
        if (is_object($val)) {
            return $val;
        }
        if (empty(static::$instance)) {
            static::$instance = new static;
        }
        static::$instance->val = $val;
        return static::$instance;
    }

    protected function iterate() {
		$args = func_get_args();
		$method = array_shift($args);
		$arr = [];
		foreach ($this->val as $el) {
			$arr[] = call_user_func_array([static::_o($el), $method], $args);
		}
		return $arr;
	}

    public function esc() {
        return htmlspecialchars($this->val);
    }

    public function nl2br() {
        return nl2br($this->val);
    }

    public function toUpper() {
        return strtoupper($this->val);
    }

    public function toLower() {
        return strtolower($this->val);
    }

    public function ucfirst() {
        return ucfirst($this->val);
    }

    public function substr($a, $b=null) {
        //echo '@@'.$this->val.'##';
        //echo '@@'.$a.':'.$b.';'.substr($this->val, $a, $b).'##';
        $s = $this->val;
        return substr($s, $a, $b);
    }

    public function concat() {
        return $this->val . implode('',func_get_args());
    }

    
    //format round ceil floor
}

class TplusLoopHelper {

    static protected $instance;

    public static function _o($i, $s, $k, $v) {
        if (empty(static::$instance)) {
            static::$instance = new static;
        }
        static::$instance->i = $i;
        static::$instance->s = $s;
        static::$instance->k = $k;
        static::$instance->v = $v;
        return static::$instance;
    }
}

class TplusRuntimeError extends Exception {
    
}