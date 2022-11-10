<?php
/**
    ------------------------------------------------------------------------------
    Tplus 1.0
    Released
    Source code and manual:
    Community:


    The MIT License (MIT)
    
    Copyright: (C) 2022 Hyeonggil Park

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
    ------------------------------------------------------------------------------
*/

namespace Tplus;

class Scirpter {
    
    private static $currentLine = 1;

    public static $userCode;
    public static $valWrapper;
    public static $loopHelper;

    public static function script($htmlPath, $scriptPath, $sizePad, $header, $config, $test=null) {
        if ($test) {
            return call_user_func_array([self::class, $test['func']], $test['args']);
        }
    
        self::$valWrapper = '\\'.(empty($config['ValWrapper']) ? 'TplValWrapper' : $config['ValWrapper']);
        self::$loopHelper = '\\'.(empty($config['LoopHelper']) ? 'TplLoopHelper' : $config['LoopHelper']);
        try {
            self::$userCode = self::getHtml($htmlPath);        
            self::saveScript($config['HtmlScriptRoot'], $scriptPath, $sizePad, $header, self::parse()); 
        } catch(SyntaxError $e) {
            if ($test) {
                throw new \ErrorException($e->getMessage(), 0, E_PARSE, realpath($htmlPath), $currentLine);                
            }
            self::reportSyntaxError($e->getMessage(), $htmlPath, self::$currentLine);
        } catch(FatalError $e) {
            //@todo remove duplication
            if ($test) {
                throw new \ErrorException($e->getMessage(), 0, E_PARSE, realpath($htmlPath), $currentLine);                
            }
            self::reportSyntaxError($e->getMessage(), $htmlPath, self::$currentLine);
        }
    }

    private static function saveScript($scriptRoot, $scriptPath, $sizePad, $header, $script) {
        $scriptRoot = preg_replace('~\\\\+~', '/', $scriptRoot);

        if (!is_dir($scriptRoot)) {
            throw new FatalError('script root '.$scriptRoot.' does not exist');
        }
        if (!is_readable($scriptRoot)) {
            throw new FatalError('script root '.$scriptRoot.' is not readable. check read-permission of web server.');
        }
        if (!is_writable($scriptRoot)) {
            throw new FatalError('script root '.$scriptRoot.' is not writable. check write-permission of web server.');
        }

        $filePerms  = fileperms($scriptRoot);
        $scriptPath = preg_replace('~\\\\+~', '/', $scriptPath);
        $scriptRelPath  = substr($scriptPath, strlen($scriptRoot));
        $scriptRelPathParts = explode('/', $scriptRelPath);
        $filename = array_pop($scriptRelPathParts);
        $path = $scriptRoot;
        
        foreach ($scriptRelPathParts as $dir) {
            $path .= '/'.$dir;
            if (!is_dir($path)) {
                if (!mkdir($path, $filePerms)) {
                    throw new FatalError('fail to create directory '.$path.' check permission or unknown problem.');
                }
            }
        }

        $headerPostfix = ' */ ?>'."\n";
        $headerSize = strlen($header) + $sizePad + strlen($headerPostfix);
        $scriptSize = $headerSize + strlen($script);
        $header .= str_pad((string)$scriptSize, $sizePad, '0', STR_PAD_LEFT) . $headerPostfix;
        $script = $header . $script;
        $scriptFile = $path.'/'.$file;
        
        if (!file_put_contents($scriptFile, $script, LOCK_EX)) {
            throw new FatalError('fail to write file '.$scriptFile.' check permission or unknown problem.');
        }
        if (!chmod($path.'/'.$file, $filePerms)) {
            throw new FatalError('fail to set permission of file '.$scriptFile.' check permission or unknown problem.');
        }
    }

    public static function decreaseUserCode($parsedUserCode) {
        self::$userCode = substr(self::$userCode, strlen($parsedUserCode));
        self::$currentLine += substr_count($parsedUserCode,"\n");
    }

    public static function getValWrapperMethods() {
        return self::getMethods(self::$valWrapper);
    }
    public static function getLoopHelperMethods() {
        return self::getMethods(self::$loopHelper);
    }
    private static function getMethods($class, $message) {
        static $methods = [];
        if (empty($methods[$class])) {
            $methods[$class] = [];
            if (!class_exists($class)) {
                //@todo "There is no ... class $class which contains method ....()"
                throw new FatalError('--- class "'.substr($class, 1).'" does not exist.');
            }
            $reflectionMethods = (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($reflectionMethods as $m) {
                if (!$m->isStatic()) {
                    $methods[$class][] = strtolower($m->name);
                }
            }
        }
        return $methods[$class];
    }

    
    private static function parse() {
        $foundScriptTag = self::findScriptTag();
        if ($foundScriptTag) {
            throw new SyntaxError('PHP tag not allowed. <b>'.$foundScriptTag.'</b>');
        };

        $resultScript='';
        while (self::$userCode) {
            [$parsedUserCode, $userCodeBeforeTag, $htmlLeftCmnt, $leftTag, $escape, $command]
                = self::findLeftTagAndCommand();
        
            $resultScript .= $userCodeBeforeTag;

            self::decreaseUserCode($parsedUserCode);

            if (!$leftTag) { 
                break;
            }

            if ($escape) {
                $resultScript .= $htmlLeftCmnt.$leftTag.substr($escape, 1).$command;

            } else if ('*' === $command) {
                self::removeComment();

            } else {
                $resultScript .= Statement::script($command);

            }            
        }
        return $resultScript;
    }

    private static function getHtml($htmlPath) {
        $html = file_get_contents($htmlPath);
        
        // remove UTF-8 BOM
        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html);

        // set to unix new lines
        return str_replace("\r", '' ,$html);
    }

    private static function findScriptTag($html) {
        $scriptTagPattern = ini_get('short_open_tag') ? '~(<\?)~i' : '~(<\?(php\s|=))~i';
        // @note <% and <script language=php> removed since php 7.0
        // @todo check short_open_tag if whitespace is mandatory after <?  in v5.x.

        $split = preg_split(
            $scriptTagPattern,
            $html, 
            2, 
            PREG_SPLIT_DELIM_CAPTURE
        );

        if (1 < count($split)) {
            self::$currentLine += substr_count($split[0], "\n");
            $foundScriptTag = $split[1];
            return $foundScriptTag;
        }
        return '';
    }

    private static function findLeftTagAndCommand() {
        $pattern =
        '~
            (.*?)
            (<!--\s*)?
            (\[)
            (\\\\*)
            ([=@?:/*])
        ~xs';

        if (preg_match($pattern, self::$userCode, $matches)) {
            return $matches; 
        }

        return [self::$userCode, self::$userCode, '', '', '', ''];
    }

    private static function reportSyntaxError($message, $htmlPath, $currentLine)
    {
        $htmlPath = realpath($htmlPath);
        if (ini_get('log_errors')) {
            error_log($message.' in '.$htmlPath.' on line '.$currentLine);
        }
        if (ini_get('display_errors')) {
            echo htmlspecialchars($message).' in <b>'.$htmlPath.'</b> on line <b>'.$currentLine.'</b>';
            // ? ob_end_flush();
        }
        exit;
    }

    private static function removeComment() {
        $pattern =
        '~  
            ^.*?
            \*\]
            (?:\s*-->)?
        ~xs';
        
        if (preg_match($pattern, self::$userCode, $matches)) {
            self::decreaseUserCode($matches[0]);

        } else {
            self::$userCode = '';

        }
    }
}

class SyntaxError extends \Exception {}
class FatalError extends \Exception {}




class Stack {
    protected $items = [];

    public function peek() {
        return end($this->items);
    }
	public function isEmpty() {
		return empty($this->items);
	}
	public function count() {
		return func_num_args() > 0
            ? array_count_values($this->items)[func_get_arg(0)]
            : count($this->items) ;
	}
    public function push($item) {
        $this->items[] = $item;
    }
    public function pop() {
        return array_pop($this->items);
    }
}



class Statement {
    /**
        $commandStack's items
            ?   (if)
            :   (else)
            @   (loop)

        // following are not user command.
            $   (switch)
            $:  (default)  
            @:  (loop else)
        // (case) and (else if) not needed.
    */
    private static $commandStack;

    public static function script($command, $test=null) {        
    
        if ($test) {
            self::$commandStack = $test['commandStack'];
            return call_user_func_array(${$test['func']}, $test['args']);
        }
    
        if (!isset(self::$commandStack)) {
            self::$commandStack = new Stack;
        }
        switch($command) {
            case '/': return self::parseEnd();
            case '=': return self::parseEcho();
            case '@': return self::parseLoop();
            case '?': return self::parseBranch();
            case ':':
                $prevCommand = $commandStack->peek();
                if (!$prevCommand or in_array($prevCommand, [':', '@:', '$:'])) {  
                    // @if $prevCommand is empty or in [else, loop else, default],                    
                    throw new SyntaxError("Unexpected ':' command");
                }
                switch($prevCommand[0]) {
                    case '@': return self::parseLoopElse();
                    case '?': return self::parseIfElse();
                    case '$': return self::parseCase();
                }
        }
    }

    public static function commandStack() {
        return self::$commandStack;
    }

    private static function parseEnd() {
        if (self::commandStack()->isEmpty()) {
            throw new SyntaxError('Unexpected end tag [/]');
        }
        $scriptCode = ('@' == self::commandStack()->peek()) ? '<?php }} ?>' : '<?php } ?>';
        while(!in_array(self::commandStack()->pop(), ['@', '?', '$']));

        self::parseRightTag();

        return $scriptCode;
    }

    private static function parseEcho() {
        $expressionScript = Expression::script();
        self::parseRightTag();
        return '<?= '.$expressionScript.';?>';
    }

    private static function parseLoop() {
        self::$commandStack->push('@');
        $expressionScript = Expression::script();

        [$a, $i, $s, $k, $v] = self::loopNames(self::loopDepth());

        $statementScript = $a.'='.$expressionScript.';'
            .'if (is_array('.$a.') and !empty('.$a.')) {'
                .$s.'=count('.$a.');'
                .$i.'=-1;'
                .'foreach('.$a.' as '.$k.'=>'.$v.') {'
                    .' ++'.$i.';';
        
        return '<?php '.$statementScript.'?>';
    }

    private static function parseBranch() {

        // @if userCode[0] is : , parseCase()
    }
    private static function parseLoopElse() {}
    private static function parseIfElse() {}
    private static function parseCase() {}

    private static function parseRightTag() {
        $pattern = 
        // @note pcre modifier 'x' means that white-spaces in pattern are ignored.
        // @note pcre modifier 's' means that dot(.) contains newline.
        '~  
            ^\s*
            \]
            (?:\s*-->)?
        ~xs';
    
        if (!preg_match($pattern, Scripter::$userCode, $matches)) {
            throw new SyntaxError('Tag not correctly closed.');
        }
    
        Scripter::decreaseUserCode($matches[0]);
    }
    public static function loopName($depth, $keyword='') {
        return self::loopNames($depth)[$keyword];
    }
    public static function loopNames($depth) {
        static $names=[];
        if (empty($names[$depth])) {
            $name = '$L'.$depth;  // $L1, $L1i, $L2i, $L1s, ...
            $names[$depth] = ['a'=>$name, 'i'=>$name.'i', 's'=>$name.'s', 'k'=>$name.'k', 'v'=>$name.'v'];
        }
        $names[$depth];
    }
    public static function loopDepth() {
        self::$commandStack->count('@');
    }
}

class Token { // DOT|OPERAND|OPERATOR|O_OPERATOR|OPEN|CLOSE|UNARY|BI_UNARY
    const SPACE     = 0;
    const DOT       = 1;
    const OPERAND   = 2;
    const OPERATOR  = 4;
    const O_OPERATOR= 8;
    const OPEN      = 16;
    const CLOSE     = 32;
    const UNARY     = 64;
    const BI_UNARY  = 128;

    const GROUPS = [
        self::SPACE => [
            'Space'  =>'\s+'
        ],
        self::DOT => [
            'Dot'   =>'\.+'
        ],
        self::OPERAND => [
            'Reserved'  =>'(?:true|false|null|this)(?![\p{L}p{N}_])',
            'Name'      =>'[\p{L}_][\p{L}\p{N}_]*',
            //'Integer'   =>'\d+(?![\p{L}\p{N}_])',
            'Number'    =>'(?:\d+(?:\.\d*)?(?:[eE][+\-]?\d+)',
            'Quoted'    =>'(?:"(?:\\\\.|[^"])*")|(?:\'(?:\\\\.|[^\'])*\')',
        ],
        self::OPERATOR => [
            'Xcrement'  => '\+\+|--',
            'Comparison'=> '===?|!==?|<=?|>=?',       // check a == b == c
            'Logic'     => '&&|\|\|',                   
            'Elvis'     => '\?:|\?\?',
            'ArithOrBit'=> '[%*/&|\^]|<<|>>',           // check quoted
        ],
        self::O_OPERATOR => [
            'TernaryIf' => '\?',                        
            'TernaryElseOrKVDelim'=>':',                 
            'Comma'     => ',',
        ],
        self::OPEN => [
            'ParenthesisOpen'=>'\(',
            'BraceOpen'=>'{',
            'BracketOpen'=>'\[',        
        ],
        self::CLOSE => [
            'ParenthesisClose'=>'\)',
            'BraceClose'=>'}',
            'BracketClose'=>'\]',   
        ],
        self::BI_UNARY => [
            'Plus'  => '\+',
            'Minus' => '-'
        ],
        self::UNARY => [
            'Unary' =>'~|!',
        ],
    ];
}

class Expression {

    /**
       f p i j I J ?  
       function (  parenthesis (  indexer {[  json {[  ternary if ?
       Above 7 openers create new expression object.
       : and , are not openers but are tokens belonging to an opener and create new expression object.
     */
    private $opener = '';
    private $scriptTokens = [];
    private $KVDelim = false;
    private $wrappingStartIndex  = -1;

    public static function script($caseAvailable=false, $test=null) {

        if ($test) {
            return call_user_func_array([new self(), $test['func']], $test['args']);
        }
       
        $expression = new Expression();
        $expression->parse($caseAvailable);
        return $expression->assembleScriptTokens();
    }

    private function parse($caseAvailable=false, $recursive=false, $parentOpener='') {

        $this->scriptTokens = [];

        $prevTokenGroup = 0;
        $prevTokenName  = '';
        $isUnaryAttached= false;         
        Name::initChain();
        
        for (;;) {
            if ($this->isExpressionCompleted($caseAvailable, $recursive, $parentOpener, $prevTokenGroup)) {
                break;
            }

            $userCode = '';
            $token = null;
            foreach (Token::GROUPS as $currTokenGroup => $tokenNames) {
                foreach ($tokenNames as $currTokenName => $pattern) {
                    $pattern = '#^('.$pattern.')#s';
                    if (!preg_match($pattern, Scripter::$userCode, $matches)) {
                        continue;
                    }

                    $token = $matches[1];
                    $userCode .= $token;
                    Scripter::decreaseUserCode($token);

                    if ($currTokenGroup === Token::SPACE) {
                        continue 3;
                    }
                    if ( $prevTokenGroup & (Token::OPERAND|Token::CLOSE) ) {  
                        if ( $currTokenGroup & (Token::OPERAND|Token::UNARY) ) {
                            //@todo&note CLOSE before OPEN is processed by according method.
                            throw new SyntaxError('Unexpected '.$token);
                        }
                    } else {    
                        //@if $prevTokenGroup is DOT|OPERATOR|O_OPERATOR|OPEN|UNARY|BI_UNARY
                        if ( $currTokenGroup & (Token::OPERATOR|Token::O_OPERATOR|Token::CLOSE) ) {
                            //@note If $currTokenGroup is CLOSE, $prevTokenGroup is always OPERAND. {#REF}
                            throw new SyntaxError('Unexpected '.$token);
                        }
                    }
                    if ($prevTokenGroup === Token::DOT and $currTokenName !== 'Name') {
                        throw new SyntaxError('Unexpected '.$token);
                    }
                   
                    break 2;
                }
            }
            if (is_null($token)) {
                throw new SyntaxError('Invalid expression: '.$userCode);
            }

            if ($currTokenGroup !== Token::CLOSE) {
                $this->setWrappingStartIndex($currTokenGroup);
            }

            $this->scriptTokens[] 
                = $this->{'parse'.$currTokenName}($token, $prevTokenGroup, $prevTokenName, $isUnaryAttached);               

            if ($currTokenGroup & (Token::UNARY|Token::BI_UNARY)) {
                $isUnaryAttached = $this->isUnaryAttached($isUnaryAttached, $prevTokenGroup, $currTokenGroup);

            } else if ($currTokenGroup & (Token::OPEN|Token::O_OPERATOR)) {
                $this->startNewExpression();
                $currTokenGroup = OPERAND;
            }

            $prevTokenGroup = $currTokenGroup;
            $prevTokenName  = $currTokenName;
        }
    } 

    private function setWrappingStartIndex($currTokenGroup) {
        if ($this->wrappingStartIndex === -1 ) {
            if ($currTokenGroup & (Token::OPERAND|Token::OPEN|Token::DOT)) {
                //@note dot(.) is higher than unary operator in priority.
                $this->wrappingStartIndex = count($this->scriptTokens);
            }
        } else {
            if ($currTokenGroup & (Token::OPERATOR|Token::O_OPERATOR|Token::UNARY|Token::BI_UNARY)) { // exclude CLOSE
                $this->wrappingStartIndex = -1;
            }
        }
    }
    
    private function assembleScriptTokens() {
        $result = '';
        foreach ($this->scriptTokens as $scriptToken) {
            $result .= is_object($scriptToken) 
                ? $scriptToken->assembleScriptTokens() 
                : $scriptToken;
        }
        return $result;
    }

    private function startNewExpression() {
        $expression = new Expression();
        $this->scriptTokens[] = $expression;
        $expression->parse(false, true, $this->opener);
    }

    private function isExpressionCompleted() {
        if (!Scripter::$userCode) {
            throw new SyntaxError('Template file ends without tag close.');
        }
        if (!$this->isExpressionValid($prevTokenGroup, $parentOpener)) {
            return false;
        }
        if ($recursive) {
            if ( strstr( ')}]:,', Scripter::$userCode[0] ) ) {
                return true;
            }
            return false;
        }
        if (Scripter::$userCode[0] === ']') {
            return true;
        }
        if (Scripter::$userCode[0] === ':') {
            if ($caseAvailable) {
                return true;
            }
            throw new SyntaxError('Unexpected :');
        }
        return false;      
    }
    private function isExpressionValid($prevTokenGroup, $parentOpener) {
        if (!$prevTokenGroup && strstr('fjJ', $parentOpener)) {
            return true;
        }
        return (
            $prevTokenGroup & (Token::OPERAND|Token::CLOSE)
            and empty($this->opener)   
        );
    }
    private function isUnaryAttached($isUnaryAttached, $prevTokenGroup, $currTokenGroup) {
        if ($isUnaryAttached) {
            return ($currTokenGroup & (Token::UNARY|Token::BI_UNARY)) ? true : false;
        }
        if ($currTokenGroup === Token::UNARY) {
            return true;
        } 
        if ($currTokenGroup === Token::BI_UNARY) {
            // [ UNARY|OPERAND|CLOSE  |  OPERATOR|BI_UNARY|OPEN or 0 ]
            // if $prevTokenGroup is UNARY, true has been already returned.
            // if $prevTokenGroup is OPERAND|CLOSE, + - are binary operator.
            // if $prevTokenGroup is OPERATOR|O_OPERATOR|BI_UNARY|OPEN or 0,
            //      + and - are unary operator.
            return ($prevTokenGroup & (Token::OPERAND|Token::CLOSE)) ? false : true;
        }
        return false;
    }      

    private function parseReserved($token) {        
        if ($token !== 'this') {
            return $token;
        }
        if (Name::chain()) {
            throw new SyntaxError('"this" is reserved word for tplus object');
        }
        Name::addToChain('this');
        return '';
    }

    private function parseName($token) {

        return Name::parse($token);
    }

    private function parseDot($token) {
        if (Name::chain() and strlen($token) > 1) {
            throw new SyntaxError('Unexpected '.$token);
        }
        Name::addToChain($token);
        return '';
    }

    private function parseParenthesisOpen($token, $prevTokenGroup, $prevTokenName) {
        $this->opener = ($prevTokenName === 'Name') ? 'f' : 'p';
        return $token;
    }
    private function parseBraceOpen($token, $prevTokenGroup, $prevTokenName) {
        $this->opener = ($prevTokenName === 'Name') ? 'i' : 'j';     // indexer or json
        return '[';
    }
    private function parseBracketOpen($token, $prevTokenGroup, $prevTokenName) {
        $this->opener = ($prevTokenName === 'Name') ? 'I' : 'J';
        return $token;
    }
    private function parseTernaryIf() {
        return '?';
    }

    private function parseParenthesisClose($token) {
        $this->_parseClose($token, 'fp');
        return $token;
    }
    private function parseBraceClose($token) {
        $this->_parseClose($token, 'ij');
        return $token;
    }
    private function parseBraketClose($token) {
        $this->_parseClose($token, 'IJ');
        return $token;
    }
    private function _parseClose($token, $openers) {
        if (false === strstr($openers, $this->opener)) {
            throw new SyntaxError('Unexpected '.$token);
        }
        if (strstr('jJ', $this->opener)) {
            $this->KVDelim = false;
        }
        $this->opener = '';
    }
    private function parseTernaryElseOrKVDelim() {
        if ($this->opener === '?') {
            $this->opener = '';
            return ':';
        }
        if (strstr('jJ', $this->opener)) {
            if ($this->KVDelim) {
                throw new SyntaxError('Unexpected '.$token);
            }
            $this->KVDelim = true;
            return ':';
        }
        throw new SyntaxError('Unexpected ":"');   
    }
    private function parseComma($token) {
        if (strstr('jJ', $this->opener)) {
            $this->KVDelim = false;
        }
        return ',';
    }
 
    private function parseNumber($token) {
        return $token;
    }

    private function parseQuoted($token, $prevTokenGroup, $prevTokenName, $isUnaryAttached) {
        if ($isUnaryAttached) {
            // @policy: String literal cannot follows unary operators.
            throw new SyntaxError('Unexpected '.$token);
        }
        if ($prevTokenName == 'Plus') {
            // @policy: Binary plus operator before string literal is changed to concat operator.
            array_pop( $this->scriptTokens );
            array_push($this->scriptTokens, '.');
        }
        return $token;
    }

    private function parseUnary($token) {
        return $token; //return ' '.$token;
    }

    private function parsePlus($token, $prevTokenGroup, $prevTokenName) {
        return ($prevTokenName == 'Quoted') ? '.' : ' +';
    }
    private function parseMinus() {
        return ' -';
    }

    private function parseXcrement($token) {
        throw new SyntaxError('Increment ++ or decrement -- operator are not allowd.');
    }
    private function parseComparison($token) {
        return $token;  // === == !== != < > <= >=      @todo check a == b == c
    }
    private function parseLogic($token) {
        return $token;  // && \\
    }
    private function parseElvis($token) {
        return $token;  // ?: ??
    }
    private function parseArithOrBit($token) {
        return $token;  // % * / & ^ << >>              @todo check if operand is string
    }
}


class Name {
    private static $chain;

    public static function chain() {
        return self::$chain;
    }
    public static function addToChain($token) {
        self::$chain .= $token;
    }
    public static function initChain() {
        self::$chain = '';
    }

    public static function parse($token) {
        self::addToChain($token);

        if (preg_match('/^\s*\.', Scipter::$userCode)) {
            return '';
        }

        if (self::$chain[0]==='.') {
            return $this->parseLoopMember();
        }

        if (preg_match('/^this\.?/', self::$chain)) {
            return $this->parseThis();
        }

        if (preg_match('/^\s*\(', Scipter::$userCode)) {
            return $this->parseFunction();
        }

        return parseVariable();
    }
    
    private static function parseThis() {
        $names = explode('.', self::$chain);
        
        self::initChain();

        if (!preg_match('/^\s*\(', Scipter::$userCode)) {
            if (count($names) === 1) {
                return '$this';
            }
            throw new SyntaxError('access of object property is not allowd.');
        }
        if (count($names) !== 2) {
            throw new SyntaxError('access of object property is not allowd.');
        }
        return '$this->'.$names[1];
    }
    private static function parseLoopMember($token) {
        preg_match('/^(\.+)(.+)$/s', self::$chain, $matches);
        $dots  = $matches[1];
        $names = $matches[2];
        $loopDepth = strlen($dots);

        if ($loopDepth > Statement::loopDepth()) {
            throw new SyntaxError('depth of loop member "'.self::$chain.'" is not correct.');
        }

        if (preg_match('/^\s*\(', Scipter::$userCode)) {  // function                
            if ($token === $names and preg_match('/^p{Lu}/', $token)) { // loop helper method
                if (!in_array(strtolower($token), Scripter::getLoopHelperMethods())) {
                    throw new FatalError('loop helper method '.$token.'() is not defined.');
                }
                [$a, $i, $s, $k, $v] = Statement::loopNames($loopDepth);
                
                self::initChain();

                return Scripter::$LoopHelper.'::_o('.$i.','.$s.','.$k.','.$v.')->'.$token;   
            } // member object's method
            return loopMember($names, $loopDepth, true);
        } // chain ends.
        return loopMember($names, $loopDepth, false);
    }
    
    private static function loopMember($names, $depth, $isMethod) {
        $names = explode('.', preg_replace('/^v\./', '', $names));
        if ($isMethod) {
            $method = array_pop($names);
        }
        $script = Statement::loopName($loopDepth, 'v');
        foreach ($names as $name) {
            $script .= '["'.$name.'"]';
        }

        self::initChain();

        return $isMethod ? $script.'->'.$method : $script;
    }

    private static function parseFunction() {
        if (!strstr(self::$chain, '.')) { // function.
            $func_name = self::$chain;
            if (self::isConstantName($func_name)) {
                throw new SyntaxError($func_name.'() has constant name.');
            }
            if (!function_exists($func_name)) {
                throw new SyntaxError('function '.$func_name.'() is not defined.');
            }
            self::initChain();
            return $func_name;
        }
        
        // method or function in namespace
        $names = explode('.', self::$chain);
        foreach($names as $name) {
            if (self::isConstantName($name)) {
                throw new SyntaxError(self::$chain.'() has constant name.');
            }
        }
        $func  = array_pop($names);            
        $name  = array_pop($names);
        $namespace = empty($names) ? '' : '\\'.implode('\\', $names);
        self::initChain();

        $fullFunction = $namespace.'\\'.$name.'\\'.$func;
        if (function_exists($fullFunction)) {   // namespace\function
            return substr($fullFunction, 1);
        }

        if (self::isClassName($name)) {    // namespace\class::method
            $fullClass = $namespace.'\\'.$name;
            if (!class_exists($fullClass)) {
                throw new FatalError($fullClass.' does not exist');
            }
            if (!method_exists($fullClass, $func)) {
                throw new FatalError($func.'() does not exist in '.$fullClass);
            }
            return substr($fullClass, 1).'::'.$method;
        } 

        // object method
        $script = '$V';
        $names[] = $name;
        while ($name = array_unshift($names)) {
            $script .= '["'.$$name.'"]';
        }

        return $script.'->'.$func;
    }

    private static function parseConstantChain($frontNames, $backNames) {
        $constant = array_pop($frontNames);
        $path = '';
        foreach($frontNames as $name) {
            $path .= '\\'.$name;
        }
        if (defined($path.'\\'.$name)) {
            return self::constantChain($path.'\\'.$name, $backNames);
        } 
        if ($path and defined($path.'::'.$name)) {
            return self::constantChain($path.'::'.$name, $backNames);
        }
        throw new FatalError(
            empty($path)
            ? 'constant '.$name.' is not defined.'
            : 'Neither '.$path.'\\'.$name.' nor '.$path.'::'.$name.' is defined.'
        );
    }
    private static function constantChain($constant, $backNames) {
        $constantChain = $constant;
        foreach($backNames as $name) {
            if (self::isConstantName($name)) {
                $constantChain .= '['.$name.']';
            } else {
                $constantChain .= '["'.$name.'"]';
            }
        }
        return $constantChain;
    }
    private static function parseVariable() {        
        $names = explode('.', self::$chain);
        $frontNames = [];
        while ($name = array_pop($names)) {
            $frontNames[] = $name;
            if (self::isConstantName($name)) {
                return self::parseConstantChain($frontNames, $names);
            }
        }

        $names = explode('.', self::$chain);
        $var = '$V';
        foreach ($names as $name) {
            $var .= '["'.$name.'"]';
        }
        return $var;
    }
    private static function isConstantName($token) {
        return preg_match('/\P{Lu}/', $token) and !preg_match('/\P{Ll}/', $token);
    }

    private static function isClassName($token) {
        return preg_match('/^\P{Lu}/', $name) and preg_match('/\P{Ll}/', $token);
    }
}