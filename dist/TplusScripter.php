<?php
/**
    ------------------------------------------------------------------------------
    Tplus 1.0 Draft
    Released 2022-11-11

    
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

class Scripter {
    
    private static $currentLine = 1;

    public static $valWrapper;
    public static $loopHelper;
    public static $userCode;

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
            self::reportError('Tplus Syntax Error: ', $e->getMessage(), $htmlPath, self::$currentLine);
        } catch(FatalError $e) {
            //@todo remove duplication
            if ($test) {
                throw new \ErrorException($e->getMessage(), 0, E_PARSE, realpath($htmlPath), $currentLine);                
            }
            self::reportError('Tplus Scripter Fatal Error: ', $e->getMessage(), $htmlPath, self::$currentLine);
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
        $scriptFile = $path.'/'.$filename;
        
        if (!file_put_contents($scriptFile, $script, LOCK_EX)) {
            throw new FatalError('fail to write file '.$scriptFile.' check permission or unknown problem.');
        }
        if (!chmod($path.'/'.$filename, $filePerms)) {
            throw new FatalError('fail to set permission of file '.$scriptFile.' check permission or unknown problem.');
        }
    }

    public static function decreaseUserCode($parsedUserCode) {
        self::$userCode = substr(self::$userCode, strlen($parsedUserCode));
        self::$currentLine += substr_count($parsedUserCode,"\n");
    }

    public static function valWrapperMethods() {
        return self::getMethods(self::$valWrapper);
    }
    public static function loopHelperMethods() {
        return self::getMethods(self::$loopHelper);
    }
    private static function getMethods($class) {
        static $methods = [];
        if (empty($methods[$class])) {
            $methods[$class] = [];
            if (!class_exists($class)) {
                //@todo "There is no ... class $class which contains method ....()"
                throw new FatalError('--- class "'.substr($class, 1).'" does not exist.');
            }
            $reflectionMethods = (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC);
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
                = self::findLeftTagAndCommand(self::$userCode);
        
            $resultScript .= $userCodeBeforeTag;

            self::decreaseUserCode($parsedUserCode);

            if (!$leftTag) { 
                break;
            }

            if ($escape) {
                $resultScript .= $htmlLeftCmnt.$leftTag.substr($escape, 1).$command;

            } else if ('*' === $command) {
                $comment = self::getComment(self::$userCode);
                self::decreaseUserCode($comment);

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

    private static function findScriptTag() {
        $scriptTagPattern = ini_get('short_open_tag') ? '~(<\?)~i' : '~(<\?(php\s|=))~i';
        // @note <% and <script language=php> removed since php 7.0
        // @todo check short_open_tag if whitespace is mandatory after <?  in v5.x.

        $split = preg_split(
            $scriptTagPattern,
            self::$userCode,
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

    private static function findLeftTagAndCommand($userCode) {
        $pattern =
        '~
            (.*?)
            (<!--\s*)?
            (\[)
            (\\\\*)
            ([=@?:/*])
        ~xs';

        if (preg_match($pattern, $userCode, $matches)) {
            return $matches; 
        }

        return [$userCode, $userCode, '', '', '', ''];
    }

    private static function reportError($title, $message, $htmlPath, $currentLine)
    {
        $htmlPath = realpath($htmlPath);
        $messageTitle = 'Tplus Syntax Error: ';
        if (ini_get('log_errors')) {
            error_log($title. $message.' in '.$htmlPath.' on line '.$currentLine);
        }
        if (ini_get('display_errors')) {
            echo '<b>'.$title.'</b>'.htmlspecialchars($message).' in <b>'.$htmlPath.'</b> on line <b>'.$currentLine.'</b>';
            // ? ob_end_flush();
        }
        exit;
    }

    private static function getComment($userCode) {
        $pattern =
        '~  
            ^.*?
            \*\]
            (?:\s*-->)?
        ~xs';
        
        return preg_match($pattern, $userCode, $matches)
            ? $matches[0]
            : $userCode;
    }
}

class SyntaxError extends \Exception {

}
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
		return  (func_num_args() > 0) 
            ? array_count_values($this->items)[func_get_arg(0)]
            : count($this->items) 
        ;
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

        if ($command === '=') {
            return self::parseEcho();
        }
        switch($command) {
            case '/': $script = self::parseEnd();       break;
            case '@': $script = self::parseLoop();      break;
            case '?': $script = self::parseBranch();    break;
            case ':':
                $prevCommand = self::$commandStack->peek();
                if (!$prevCommand or in_array($prevCommand, [':', '@:', '$:'])) {  
                    // @if $prevCommand is empty or in [else, loop else, default],                    
                    throw new SyntaxError("Unexpected ':' command");
                }
                switch($prevCommand[0]) {                    
                    case '?': $script = self::parseElse();      break;
                    case '$': $script = self::parseCase();      break;
                    case '@': $script = self::parseLoopElse();  break;
                }
        }

        self::parseRightTag();
        return '<?php '.$script.' ?>';
    }

    private static function parseEcho() {
        $expression = Expression::script();
        self::parseRightTag();
        return '<?= '.$expression.' ?>';
    }

    private static function parseEnd() {
        if (self::$commandStack->isEmpty()) {
            throw new SyntaxError('Unexpected end tag [/]');
        }
        $script = ('@' === self::$commandStack->peek()) ? '}}' : '}';
        while(!in_array(self::$commandStack->pop(), ['@', '?', '$']));

        return $script;
    }
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

    private static function parseLoop() {
        self::$commandStack->push('@');
        $expressionScript = Expression::script();


        ['a'=>$a, 'i'=>$i, 's'=>$s, 'k'=>$k, 'v'=>$v] = self::loopNames(self::loopDepth());

        return $a.'='.$expressionScript.';'
            .'if (is_array('.$a.') and !empty('.$a.')) {'
                .$s.'=count('.$a.');'
                .$i.'=-1;'
                .'foreach('.$a.' as '.$k.'=>'.$v.') {'
                    .' ++'.$i.';';
    }

    private static function parseBranch() {
        $expressionScript = Expression::script(true);
        if (self::caseExists()) {
            $statementScript = 'switch ('.$expressionScript.') { ';
            self::$commandStack->push('$');
            do {
                $statementScript .= 'case '.Expression::script(true).': ';
            } while (self::caseExists());

        } else {
            self::$commandStack->push('?');
            $statementScript = 'if ('.$expressionScript.') {';
        }

        return $statementScript; 
    }
    private static function parseCase() {
        if (self::expressionExists()) {
            $statementScript = 'break; ';
            do {
                $statementScript .= 'case '.Expression::script(true).': ';
            } while (self::caseExists());
            return $statementScript;

        } else {
            self::$commandStack->push('$:');
            return 'break; default:';
        }
    }

    private static function caseExists() {
        return preg_match('/^\s*\:/', Scripter::$userCode);
    }
    private static function expressionExists() {
        return !preg_match('/^\s*\]/', Scripter::$userCode);
    }

    private static function parseLoopElse() {
        self::$commandStack->push('@:');
        return '}} else {';
    }
    private static function parseElse() {
        if (self::expressionExists()) {
            return '} else if ('.Expression::script().') {';
        }

        self::$commandStack->push(':');    // else
        return '} else {';
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
        return $names[$depth];
    }
    public static function loopDepth() {
        return self::$commandStack->count('@');
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
            'Name'      =>'[\p{L}_][\p{L}\p{N}_]*',
            'Number'    =>'(?:\d+(?:\.\d*)?)(?:[eE][+\-]\d+)?',
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
            'BraceOpen'=>'\{',
            'BracketOpen'=>'\[',        
        ],
        self::CLOSE => [
            'ParenthesisClose'=>'\)',
            'BraceClose'=>'\}',
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


/*class Opener {
    const FUNC  = 1;    //  func(
    const PAR   = 2;    //  (
    const IDXB  = 4;    //  arr{
    const IDXC  = 8;    //  arr[
    const JSNB  = 16;   //  {
    const JSNC  = 32;   //  [
    const T_IF  = 64;   //  ?
}*/

class Expression {

    /**
       f p i j I J ?
       function (  parenthesis (  indexer {[  json {[  ternary if ? 
       Above 7 openers create new expression object.
       : and , are not openers but create new expression object.
       in case of colon(:) and comma(,), opener remains as ? or {[
     */
    private $opener = '';
    private $scriptedTokens = [];
    private $KVDelim = false;
    private $ternaryCount = 0;
    private $wrappingStartIndex  = -1;
    private $onMethodChaining = false;
    private static $oneStepFinished = false;

    public static function script($caseCmdPossible=false, $test=null) {

        if ($test) {
            return call_user_func_array([new self(), $test['func']], $test['args']);
        }
       
        $expression = new Expression();
        $expression->parse($caseCmdPossible);
        return $expression->assembleScriptTokens();
    }

    private function parse($caseCmdPossible, $parentOpener='', $parentTCount=0, $thread=1) {

        $this->scriptedTokens = [];

        $prevTokenGroup = 0;
        $prevTokenName  = '';
        $userCode = '';
        $isUnaryAttached= false;         
        NameDot::initChain();
        
        //echo 'thread up to '. $thread."\n";

        for (;;) {
            //echo '<'.$parentOpener.'> thread: '.$thread."\n";
            if ($this->isExpressionFinished($caseCmdPossible, $parentOpener, $parentTCount, $prevTokenGroup)) {
                //echo 'thread down to '. ($thread-1)."\n";
                break;
            }

            self::$oneStepFinished = false;
            $token = null;

            foreach (Token::GROUPS as $currTokenGroup => $tokenNames) {
                foreach ($tokenNames as $currTokenName => $pattern) {
                    $pattern = '#^('.$pattern.')#s';
                    if (!preg_match($pattern, Scripter::$userCode, $matches)) {
                        continue;
                    }

                    $token = $matches[0];
                    
                    
                    /*if ($token == "'foo'") {
                        print_r($this->scriptedTokens);
                    }*/
                    $userCode .= $token;
                    Scripter::decreaseUserCode($token);

                    if ($currTokenGroup === Token::SPACE) {
                        //echo '[SPACE] '."\n";
                        continue 3;
                    }

                    //echo 'token: '.$token."\n";

                    if ( in_array($prevTokenGroup, [Token::OPERAND,Token::CLOSE]) ) {  
                        //echo $prevTokenGroup;
                        if ( $currTokenGroup & (Token::OPERAND|Token::UNARY) ) {
                            //@todo Token::CLOSE before Token::OPEN should be processed by according method.
                            throw new SyntaxError('[012]Unexpected '.$token);
                        }
                    } else {    
                            
                        //@if $prevTokenGroup is DOT|OPERATOR|O_OPERATOR|OPEN|UNARY|BI_UNARY
                        if ( $currTokenGroup & (Token::OPERATOR|Token::O_OPERATOR|Token::CLOSE) ) {
                            //@note If $currTokenGroup is CLOSE, $prevTokenGroup is always OPERAND. 
                            throw new SyntaxError('[011]Unexpected '.$token);
                        }
                    }
                    if ($prevTokenGroup === Token::DOT and $currTokenName !== 'Name') {
                        throw new SyntaxError('[010]Unexpected '.$token);
                    }
                   
                    break 2;
                }
            }
            if (is_null($token)) {
                throw new SyntaxError('Invalid expression: '.$userCode);
            }
            if ($currTokenGroup !== Token::CLOSE) {
                $this->setWrappingStartIndex($currTokenGroup, $prevTokenGroup);
            }
            
            $this->scriptedTokens[] = $this->{'parse'.$currTokenName}(
                                                $token, $prevTokenGroup, $prevTokenName, $isUnaryAttached);
            
            if ($currTokenGroup & (Token::UNARY|Token::BI_UNARY)) {
                $isUnaryAttached = $this->isUnaryAttached($isUnaryAttached, $prevTokenGroup, $currTokenGroup);
                
            } else if ($currTokenGroup & (Token::OPEN|Token::O_OPERATOR)) {
                //echo 'start new thread ['.$this->opener.'] ['.$token.']   '.Scripter::$userCode."\n";
                $this->parseNewExpression($thread);
                $currTokenGroup = Token::OPERAND;
            }
            
            $prevTokenGroup = $currTokenGroup;
            $prevTokenName  = $currTokenName;
        }
    } 
    private function assembleScriptTokens() {
        $result = '';
        foreach ($this->scriptedTokens as $scriptedToken) {
            $result .= is_object($scriptedToken) 
                ? $scriptedToken->assembleScriptTokens() 
                : $scriptedToken;
        }
        return $result;
    }
    private function parseNewExpression($thread) {
        $expression = new Expression();
        
        $expression->parse(false, $this->opener, $this->ternaryCount, $thread+1); //@question: how about returning $expression.
        $this->scriptedTokens[] = $expression;
    }
    private function isExpressionFinished($caseCmdPossible, $parentOpener, $parentTCount, $prevTokenGroup) {
        $userCode = Scripter::$userCode;
        if (! $userCode ) {
            throw new SyntaxError('Template file ends without tag close.');
        }

        if ( !($prevTokenGroup & Token::OPERAND|Token::CLOSE) ) {
            return false;
        } 

        /*if ($this->opener === '?') {
            if ($this->ternaryCount !== 0) {
                return false;
            }
            if (preg_match('~^\s*\)|\}|\]~', $userCode)) {
                return true;
            }
        }*/
        /*if ($parentOpener == '?' and $parentTCount == 0) {
            if (preg_match('~^\s*\:|\,|\?|\)|\}|\]~', $userCode)) {
                self::$oneStepFinished = true;
                return true;
            }
        }*/

        if ($parentOpener) {            
            if (self::$oneStepFinished) {
                return false;
            }
            if ($parentOpener == '?') {//} and $parentTCount == 0) {
                if (preg_match('~^\s*(\:|\,|\?|\)|\}|\])~', $userCode, $matches)) {
                    //echo '[end of exp in ternary]<'.$matches[1].">\n\n";
                    //self::$oneStepFinished = true;
                    return true;
                }
            }
            switch ($parentOpener) {
                case 'f': $p = '\)|\,';     break;
                case 'p': $p = '\)';        break;
                case 'i': $p = '\}';        break;
                case 'j': $p = '\}|\:|\,';  break;
                case 'I': $p = '\]';        break;
                case 'J': $p = '\]|\:|\,';  break;
                case '?': $p ='\?|\:';
            }

            if (preg_match('~^\s*('.$p.')~', $userCode, $matches)) {
                if (!$prevTokenGroup) {
                    switch ($parentOpener) {
                        case 'f': $finished = $matches[1]==')';   break;
                        case 'j': $finished = $matches[1]=='}';   break;
                        case 'J': $finished = $matches[1]==']';   break;
                        default : $finished = false;
                    }
                } else {
                    $finished = true;
                }
                if ($finished) {
                    self::$oneStepFinished = true;
                    return true;
                }
            }
        }

        if (!in_array($this->opener,['I', 'J']) and preg_match('/^\s*\]/', $userCode)) {
            return true;
        }

        if (preg_match('/^\s*\:/', $userCode)) {
            if ($this->opener == '?' and $this->ternaryCount > 0) {
                return false;
            }
            if (in_array($this->opener, ['j', 'J'])) {
                return false;
            }
            if (!$parentOpener and $caseCmdPossible) {
                return true;
            }
            throw new SyntaxError('[009]Unexpected :');
        }

        return false;      
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


    public function insertValWrapper() {
        if ($this->wrappingStartIndex === -1) {
            throw new FatalError('TplusScripter internal bug: fails to parsing val wrapping method.');
        }
        array_splice($this->scriptedTokens, $this->wrappingStartIndex, 0, Scripter::$valWrapper.'::_o(');
    }
    private function setWrappingStartIndex($currTokenGroup, $prevTokenGroup) {
        if ($this->wrappingStartIndex === -1 ) {
            if ($currTokenGroup & (Token::OPERAND|Token::OPEN)) {
                //@note dot(.) has higher priority than unary operator.
                $this->wrappingStartIndex = count($this->scriptedTokens);
            } else if ($currTokenGroup == Token::DOT and $prevTokenGroup!=Token::CLOSE) {
                $this->wrappingStartIndex = count($this->scriptedTokens);
            }
        } else {
            //if ($currTokenGroup & (Token::OPERATOR|Token::O_OPERATOR|Token::UNARY|Token::BI_UNARY)) {
            if ($currTokenGroup & (Token::OPERATOR|Token::UNARY|Token::BI_UNARY)) { // CLOSE -> chaining possible

                $this->wrappingStartIndex = -1;
            }
        }
    }
    private function parseDot($token, $prevTokenGroup, $prevTokenName) {
        if ($prevTokenGroup===Token::CLOSE
            or $prevTokenGroup===Token::OPERAND && $prevTokenName!=='Name') {

            $this->onMethodChaining = true;
            return;
        }

        if (NameDot::chain() and strlen($token) > 1) { // abc.. xyz...
            throw new SyntaxError('[008]Unexpected '.implode('', NameDot::chain()).$token);
        }
        NameDot::addToChain($token);
    }
    private function parseName($token) { // variable, function, object, method, array, key, namespace, class
        if (in_array($token,['true', 'false', 'null', 'this'])) {
            return $this->parseReserved($token);
        }
        if ($this->onMethodChaining) {
            if (!preg_match('/^\s*\(/', Scripter::$userCode)) {
                throw new SyntaxError('[007]unexpected'. '.'.$token);
            }
            $this->insertValWrapper();
            $this->onMethodChaining = false;
            return ')->'.$token;
        }

        return NameDot::parse($token, $this);
    }
    private function parseReserved($token) { // true|false|null|this
        if (NameDot::chain()) {
            throw new SyntaxError('"'.$token.'" is reserved word.');
        }
        if ($token !== 'this') { 
            return $token;
        }
        NameDot::addToChain('this');
    }


    private function parseParenthesisOpen($token, $prevTokenGroup, $prevTokenName) {
        $this->opener = ($prevTokenName === 'Name') ? 'f' : 'p';
        return $token;
    }
    private function parseBraceOpen($token, $prevTokenGroup, $prevTokenName) {
        if ($prevTokenName === 'Name') {
            $this->opener = 'i';
        } else {
            $this->opener = 'j';     // indexer or json
        }
        return '[';
    }
    private function parseBracketOpen($token, $prevTokenGroup, $prevTokenName) {
        $this->opener = ($prevTokenName === 'Name') ? 'I' : 'J';
        return $token;
    }
    private function parseTernaryIf() {
        $this->opener = '?';
        $this->ternaryCount++;
        return '?';
    }

    private function parseParenthesisClose($token) {
        $this->_parseClose($token, ['f','p']);
        return $token;
    }
    private function parseBraceClose($token) {
        $this->_parseClose($token, ['i','j']);
        return ']';
    }
    private function parseBracketClose($token) {
        $this->_parseClose($token, ['I','J']);
        return ']';
    }
    private function _parseClose($token, $openers) {
        //echo $this->opener;
        //print_r($openers);
        if (!in_array($this->opener, $openers)) {
            //echo $this->opener;
            throw new SyntaxError('[006]Unexpected '.$token);
        }
        if (in_array($this->opener, ['j', 'J'])) {
            $this->KVDelim = false;
        }
        $this->opener = '';
    }
    private function parseTernaryElseOrKVDelim() {
        if ($this->opener === '?') {
            if (!$this->ternaryCount) {
                throw new SyntaxError('[005]Unexpected "ternary else :"');
            }
            $this->ternaryCount--;
            return ':';
        }
        if (in_array($this->opener, ['j', 'J'])) {
            
            if ($this->KVDelim) {
                throw new SyntaxError('[004]Unexpected '.$token);
            }
            $this->KVDelim = true;
            return '=>';
        }
        throw new SyntaxError('[003]Unexpected ":"');   
    }
    private function parseComma($token) {
        if (in_array($this->opener, ['j', 'J'])) {
            $this->KVDelim = false;
            return ',';
        }
        if ($this->opener == 'f') {
            return ',';
        }
        throw new SyntaxError('[002]Unexpected ","');
    }
 
    private function parseNumber($token) {
        return $token;
    }

    private function parseQuoted($token, $prevTokenGroup, $prevTokenName, $isUnaryAttached) {
        if ($isUnaryAttached) {
            // @policy: String literal cannot follows unary operators.            
            throw new SyntaxError('[001]Unexpected '.$token);
        }
        if ($prevTokenName == 'Plus') {
            // @policy: Binary plus operator before string literal is changed to concat operator.
            array_pop( $this->scriptedTokens );
            array_push($this->scriptedTokens, '.');
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


class NameDot {
    private static $chain;  // Name chain is composed of only name and dot.
    private static $expression;

    public static function chain() {
        return self::$chain;
    }
    public static function addToChain($token) {
        self::$chain .= $token;
    }
    public static function initChain() {
        self::$chain = '';
    }

    public static function parse($token, $expression=null) {
        self::addToChain($token);
        self::$expression = $expression;

        if (preg_match('/^\s*\./', Scripter::$userCode)) { // chain not ends.
            return '';
        }

        if (self::$chain[0]==='.') {
            return self::parseLoopMember();
        }

        if (preg_match('/^this\.?/', self::$chain)) {
            return self::parseThis();
        }
        if (preg_match('/^GET\.?/', self::$chain)) {
            return self::parseGET();
        }

        if (preg_match('/^\s*\(/', Scripter::$userCode)) {
            return self::parseFunction();
        }

        return self::parseVariable();
    }

    private static function parseGET() {
        $names = explode('.', self::$chain);
        if (count($names)!=2) {
            throw new SyntaxError('Unexpected code: '.self::$chain);
        } 
        if (self::isFunc()) {
            throw new SyntaxError('Unexpected code: '.self::$chain.'()');
        }
        
        self::initChain();
        return '$_GET["'.$names[1].'"]';
    }
    
    private static function parseThis() {
        $names = explode('.', self::$chain);
        
        self::initChain();

        if (! self::isFunc() ) {
            if (count($names) === 1) {
                return '$this';
            }
            throw new SyntaxError('access of object property is not allowd.'); // this.prop
        }
        if (count($names) !== 2) {
            throw new SyntaxError('access of object property is not allowd.');  // this.prop.foo
        }
        return '$this->'.$names[1];     // this.method()
    }

    private static function parseLoopMember() {  // @todo  h keword.
        preg_match('/^(\.+)(.+)$/s', self::$chain, $matches);
        $dots  = $matches[1];
        $names = explode('.', $matches[2]);
        $loopDepth = strlen($dots);

        if ($loopDepth > Statement::loopDepth()) {
            throw new SyntaxError('depth of loop member "'.self::$chain.'" is not correct.');
        }

        if (in_array($names[0], ['i', 's', 'k', 'v'])) {
            if (count($names)===1) {
                if (self::isFunc()) {
                    throw new SyntaxError('Unexpected '.self::$chain.'("');
                }
                self::initChain();
                return Statement::loopName($loopDepth, $names[0]);
            }
            if (count($names)===2) {
                if (self::isFunc()) {
                    if (self::isWrapper($names[1])) {
                        self::$expression->insertValWrapper();
                        self::initChain();
                        return Statement::loopName($loopDepth, $names[0]).')->'.$names[1];
                    } else {
                        if (in_array($names[0], ['i', 's', 'k'])) { 
                            throw new FatalError('TplusValWrapper doesn\'t have method '.$names[1].'()');
                        }
                        self::initChain();
                        return Statement::loopName($loopDepth, 'v').'->'.$names[1];
                    }
                } else {
                    if (in_array($names[0], ['i', 's', 'k'])) { 
                        throw new SyntaxError('Unexpected "'.self::$chain.'"');
                    }
                    self::initChain();
                    return Statement::loopName($loopDepth, 'v').'["'.$names[1].'"]';
                }
            }
            if (count($names)>2) { 
                if (in_array($names[0], ['i', 's', 'k'])) { 
                    throw new SyntaxError('Unexpected "'.self::$chain.'"');
                }
                
                $keyword = array_shift($names); //remove 'v'

                return self::parseV($names, $loopDepth);

            }
        }
        if (!in_array($names[0], ['i', 's', 'k', 'v', 'h'])) {

            return self::parseV($names, $loopDepth);
        } 

        if ($names[0] === 'h' and count($names) === 2 and self::isFunc()) { // loop helper method
            $helperMethod = strtolower($names[1]);
            if (!in_array($helperMethod, Scripter::loopHelperMethods())) {
                throw new FatalError('loop helper method '.$helperMethod.'() is not defined.');
            }
            ['a'=>$a, 'i'=>$i, 's'=>$s, 'k'=>$k, 'v'=>$v] = Statement::loopNames($loopDepth);
            
            self::initChain();

            return Scripter::$loopHelper.'::_o('.$i.','.$s.','.$k.','.$v.')->'.$names[1];
        }
    }

    private static function isFunc() {
        return preg_match('/^\s*\(/', Scripter::$userCode);
    }
    private static function isWrapper($method) {
        return in_array(strtolower($method), Scripter::valWrapperMethods());
    }
    private static function parseV($names, $loopDepth) {
        $script = Statement::loopName($loopDepth, 'v');
        if (self::isFunc()) {
            $method = array_pop($names);
            foreach ($names as $name) {
                $script .= '["'. $name.'"]';
            }
            self::initChain();
            if (self::isWrapper($method)) {
                self::$expression->insertValWrapper();
                return $script.')->'.$method;
            } else {
                return $script.'->'.$method;
            }
        } else {
            foreach ($names as $name) {
                $script .= '["'. $name.'"]';
            }
            self::initChain();
            return $script;
        }
    }

    /*private static function loopMember($names, $depth, $isMethod) {

        if ($isMethod) {
            $method = array_pop($names);
        }

        if (!empty($names) and $names[0]==='v') {
            array_shift($names);
        }

        $script = Statement::loopName($depth, 'v');

        foreach ($names as $name) {
            $script .= '["'.$name.'"]';
        }

        self::initChain();

        return $isMethod ? $script.'->'.$method : $script;
    }*/

    private static function parseFunction() {
        // 1. function
        if (!strstr(self::$chain, '.')) {
            $func_name = self::$chain;
            if (self::isConstantName($func_name)) { //@
                throw new SyntaxError($func_name.'() has constant name.');
            }
            if (!function_exists($func_name)) {
                throw new SyntaxError('function '.$func_name.'() is not defined.');
            }
            self::initChain();
            return $func_name;
        }
        
        // 2. function in namespace  or  method  or  wrapper method
        $names = explode('.', self::$chain);
        foreach($names as $name) {
            if (self::isConstantName($name)) {
                throw new SyntaxError(self::$chain.'() has constant name.');
            }
        }

        $func  = array_pop($names);
        $name  = array_pop($names);
        $namespaces = empty($names) ? '' : '\\'.implode('\\', $names);

        // 2.1. namespace\functions
        $namespace = $name;
        $fullFunction = $namespaces.'\\'.$namespace.'\\'.$func;
        if (function_exists($fullFunction)) {
            self::initChain();
            return $fullFunction;
        }

        // 2.2. namespace\class::method
        if (self::isClassName($name)) {
            $class = $name;
            $fullClass = $namespaces.'\\'.$class;
            if (!class_exists($fullClass)) {
                throw new FatalError($fullClass.' does not exist');
            }
            if (!method_exists($fullClass, $func)) {
                throw new FatalError('static method '.$func.'() does not exist in '.$fullClass);
            }
            self::initChain();
            return $fullClass.'::'.$func;
        } 

        // 3. dynamic method
        $script = '$V';
        $names = explode('.', self::$chain);
        $method = array_pop($names);

        
        while ($name = array_shift($names)) {
            $script .= '["'.$name.'"]';
        }

        self::initChain();
        // 3.1. wrapper method
        if (in_array($method, Scripter::valWrapperMethods())) {
            self::$expression->insertValWrapper();
            return $script.')->'.$method;
        }
        // 3.2. object method
        return $script.'->'.$method;
    }

    private static function parseVariable() {        
        $names = explode('.', self::$chain);
        $frontNames = [];
        while ($name = array_shift($names)) {
            $frontNames[] = $name;
            if (self::isConstantName($name)) {
                self::initChain();
                return self::parseConstantChain($frontNames, $names);
            }
        }

        $names = explode('.', self::$chain);
        $var = '$V';
        foreach ($names as $name) {
            $var .= '["'.$name.'"]';
        }
        self::initChain();
        return $var;
    }

    private static function parseConstantChain($frontNames, $backNames) {
        $constant = array_pop($frontNames);
        $path = '';
        foreach($frontNames as $name) {
            $path .= '\\'.$name;
        }
        if (defined($path.'\\'.$constant)) {
            return self::constantChain($path.'\\'.$constant, $backNames);
        } 
        if ($path and defined($path.'::'.$constant)) {
            return self::constantChain($path.'::'.$constant, $backNames);
        }
        throw new FatalError(
            empty($path)
            ? 'constant "'.$constant.'" is not defined.'
            : 'Neither '.$path.'\\'.$constant.' nor '.$path.'::'.$constant.' is defined.'
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
    private static function isConstantName($token) {
        return preg_match('/\p{Lu}/u', $token) and !preg_match('/\p{Ll}/u', $token);
    }

    private static function isClassName($token) {
        return preg_match('/^\p{Lu}/u', $token) and preg_match('/\p{Ll}/u', $token);
    }
}