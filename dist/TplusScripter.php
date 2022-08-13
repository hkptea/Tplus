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

    public static $userCode;
    private static $currentLine = 1;

    public static function script($htmlPath, $scriptPath, $scriptRoot, $scriptSizePad, $test=null) {
        if ($test) {
            return call_user_func_array([self::class, $test['func']], $test['args']);
        }
    
        self::$userCode = self::getHtml($htmlPath);
        self::saveScriptResult($scriptPath, self::parse()); 
    }

    public static function decreaseUserCode($parsedUserCode) {
        self::$userCode = substr(self::$userCode, strlen($parsedUserCode));
        self::$currentLine += substr_count($parsedUserCode,"\n");
    }
    //public static function increaseCurrLine($parsedUserCode) {}
    
    private static function parse($isTest=false) {
        try {

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
        } catch(SyntaxError $e) {
            if ($isTest) {
                throw new \ErrorException($e->getMessage(), 0, E_PARSE, realpath($htmlPath), $currentLine);                
            }
            self::reportSyntaxError($e->getMessage(), $htmlPath, self::$currentLine);
        }
        return $resultScript;
    }
        
    private static function saveScriptResult($scriptPath, $scriptResult) {

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

        if (preg_match($pattern, self::$userCode, $match)) {
            return $match; 
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
        
        if (preg_match($pattern, self::$userCode, $match)) {
            self::decreaseUserCode($match[0]);

        } else {
            self::$userCode = '';

        }
    }
}

class SyntaxError extends \Exception {}



class Collection {
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
}
class Stack extends Collection {
    public function push($item) {
        $this->items[] = $item;
    }
    public function pop() {
        return array_pop($this->items);
    }
}
class Queue extends Collection {
    public function enqueue($item) {
        $this->items[] = $item;
    }
    public function dequeue() {
        return array_shift($this->items);
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
    private static $userCodeAfterCommand;

    public static function script($command, $test=null) {        
        //Scripter::$userCode = $userCodeAfterCommand;
    
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
        while(!in_array(self::commandStack()->pop(), ['@', '?']));

        self::parseRightTag();

        return $scriptCode;
    }

    private static function parseEcho() {

        $expressionScript = Expression::script();
        self::parseRightTag();
        return '<?php echo '.$expressionScript.';?>';
    }

    private static function parseLoop() {
        self::$commandStack->push('@');
        $loopDepth = self::$commandStack->count('@');
        //[$scriptExpression, $linesInStatementAndRightTag] = Expression::script();
        $expressionScript = Expression::script();

        [$arr, $i, $s, $k, $v] = self::nameLoop($loopDepth);

        $statementScript = $arr.'='.$expressionScript.';'
            .'if ( is_array('.$arr.') and !empty('.$arr.') ) {'
                .$s.'=count('.$arr.');'
                .$i.'=-1;'
                .'foreach('.$arr.' as '.$k.'=>'.$v.') {'
                    .' ++'.$i.';';
        $statementScript = '<?php '.preg_replace('#\s+#s', '', $statementScript).'?>';

        return $statementScript;
        //return [$scriptCode, $linesInStatementAndRightTag];
    }

    private static function parseBranch() {

        // @if nextUserCode is : , parseCase()
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
    
        if (!preg_match($pattern, Scripter::$userCode, $match)) {
            throw new SyntaxError('Tag not correctly closed.');
        }
    
        Scripter::decreaseUserCode($match[0]);
    }
    public static function nameLoop($depth, $keyword='') {
        $arr = '$LOOP_'.$depth;
        $names = [$arr, 'i'=>$arr.'_I', 's'=>$arr.'_S', 'k'=>$arr.'_K', 'v'=>$arr.'_V'];
        return $keyword ? $names[$keyword] : array_values($names);
    }
}



/**  Expression */


const INIT      = -1;

// DOT|OPERAND|OPERATOR|O_OPERATOR|OPEN|CLOSE|UNARY|BI_UNARY

const SPACE     = 0;
const DOT       = 1;
const OPERAND   = 2;
const OPERATOR  = 4;
const O_OPERATOR= 8;
const OPEN      = 16;
const CLOSE     = 32;
const UNARY     = 64;
const BI_UNARY  = 128;

const TOKEN = [
    SPACE => [
        'Space'  =>'\s+'
    ],
    DOT => [
        'Dot'   =>'\.+'
    ],
    OPERAND => [
        'Reserved'  =>'(?:true|false|null|this)(?![\p{L}p{N}_])',
        'Name'      =>'[\p{L}_][\p{L}p{N}_]*',
        'Integer'   =>'\d+(?![\p{L}p{N}_])',
        'Number'    =>'(?:\d+(?:\.\d*)?(?:[eE][+\-]?\d+)',
        'Quoted'    =>'(?:"(?:\\\\.|[^"])*")|(?:\'(?:\\\\.|[^\'])*\')',
    ],
    OPERATOR => [
        'Xcrement'  => '\+\+|--',
        'Comparison'=> '===?|!==?|<|>|<=|>=',       // check a == b == c
        'Logic'     => '&&|\|\|',                   
        'Elvis'     => '\?:|\?\?',
        'ArithOrBit'=> '[%*/&|\^]|<<|>>',           // check quoted
    ],
    O_OPERATOR => [
        'TernaryIf' => '\?',                        
        'TernaryElseOrKVDelim'=>':',                 
        'Comma'     => ',',
    ],
    OPEN => [
        'ParenthesisOpen'=>'\(',
        'BraceOpen'=>'{',
        'BracketOpen'=>'\[',        
    ],
    CLOSE => [
        'ParenthesisClose'=>'\)',
        'BraceClose'=>'}',
        'BracketClose'=>'\]',   
    ],
    BI_UNARY => [
        'Plus'  => '\+',
        'Minus' => '-'
    ],
    UNARY => [
        'Unary' =>'~|!',
    ],
];

class Expression {

    /**
       f p i j I J ?  
       Above 7 openers create new expression object.
       : and , are not openers but create new expression object.
     */
    private $opener = '';           
    private $scriptTokens = [];
    private $jsonKVDelimOn = false;
    private $chainStartIndex  = INIT;
    private $domainStartIndex = INIT;

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
        
        for (;;) {
            if (!Scripter::$userCode) {
                throw new SyntaxError('Template file ends without tag close.');
            }
            if ($this->isExpressionValid($prevTokenGroup, $parentOpener)) {
                if ($recursive) {
                    if ( strstr( ')}]', Scripter::$userCode[0] ) ) {
                        break;
                    }
                } else {
                    if (Scripter::$userCode[0] === ']') {
                        break;
                    }
                    if (Scripter::$userCode[0] === ':') {
                        if ($caseAvailable) {
                            break;
                        } else {
                            throw new SyntaxError('Unexpected :');
                        }
                    }
                }
            }

            $token = null;
            foreach (self::TOKEN as $currTokenGroup => $tokenNames) {
                foreach ($tokenNames as $currTokenName => $pattern) {
                    $pattern = '#^('.$pattern.')#s';
                    if (!preg_match($pattern, Scripter::$userCode, $match)) {
                        continue;
                    }

                    $token = $match[1];
                    Scripter::decreaseUserCode($token);

                    if ($currTokenGroup === SPACE) {
                        continue 3;
                    }
                    if ( $prevTokenGroup & (OPERAND|CLOSE) ) {  
                        if ($currTokenGroup & (OPERAND|UNARY) ) {
                            //@note CLOSE before OPEN is processed by according method.
                            throw new SyntaxError('Unexpected '.$token);
                        }
                    } else {    
                        //@if $prevTokenGroup is DOT|OPERATOR|O_OPERATOR|OPEN|UNARY|BI_UNARY
                        if ( $currTokenGroup & (OPERATOR|O_OPERATOR|CLOSE) ) {
                            //@note If $currTokenGroup is CLOSE, $prevTokenGroup is always OPERAND. {#REF}
                            throw new SyntaxError('Unexpected '.$token);
                        }
                    }
                    if ($prevTokenGroup === DOT and $currTokenName !== 'Name') {
                        throw new SyntaxError('Unexpected '.$token);
                    }
                   
                    break 2;
                }
            }
            if (is_null($token)) {
                throw new SyntaxError('Invalid expression.');
            }

            $this->setupStartIndex($currTokenGroup, $currTokenName);

            $this->scriptTokens[] 
                = $this->{'parse'.$currTokenName}($token, $prevTokenGroup, $prevTokenName, $isUnaryAttached);    
            
            if ($currTokenGroup & (OPEN|O_OPERATOR)) {
                $expression = new Expression();
                $this->scriptTokens[] = $expression;
                $expression->parse(false, true, $this->opener);
                $currTokenGroup = OPERAND;
            }

            if ($currTokenGroup & (UNARY|BI_UNARY)) {
                $isUnaryAttached = $this->isUnaryAttached($isUnaryAttached, $prevTokenGroup, $currTokenGroup);
            }

            if ($currTokenGroup === OPERAND and $this->jsonKVDelimOn)  {
                $this->jsonKVDelimOn = false;
            }

            $prevTokenGroup = $currTokenGroup;
            $prevTokenName  = $currTokenName;
        }
    } 

    private function setupStartIndex($currTokenGroup, $currTokenName) {
        if ($this->chainStartIndex === INIT and $currTokenGroup & (OPERAND|OPEN|DOT)) {
            $this->chainStartIndex  = count($this->scriptTokens);
        }/* else if ($currTokenGroup & (OPERATOR|BI_UNARY|UNARY)) {   // CLOSE excluded.
            // resetChainStartIndex
            $this->chainStartIndex = INIT;   }*/
        if ($this->domainStartIndex === INIT and $currTokenGroup === DOT || $currTokenName === 'Name') {
            $this->domainStartIndex = count($this->scriptTokens);
        }

    }
    private function setupDomainStartIndex($currTokenGroup) {
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

    private function isExpressionValid($prevTokenGroup, $parentOpener) {
        if (!$prevTokenGroup && strstr('fjJ', $parentOpener)) {
            return true;
        }
        return (
            $prevTokenGroup & (OPERAND|CLOSE)
            and empty($this->opener)   
            and empty($this->ternaryStack)            //and ! empty($this->scriptTokens)            
        );
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
    //private function parseTerna
    private function parseParenthesisClose($token, $prevTokenGroup, $prevTokenName) {
        $this->_parseClose($token, 'fp');
        return $token;
    }
    private function parseBraceClose($token, $prevTokenGroup, $prevTokenName) {
        $this->_parseClose($token, 'ij');
        return ']';
    }
    private function parseBraketClose($token, $prevTokenGroup, $prevTokenName) {
        $this->_parseClose($token, 'IJ');
        return $token;
    }
    private function _parseClose($token, $openers) {
        if (false === strstr($openers, $this->opener)) {
            throw new SyntaxError('Unexpected '.$token);
        }
        $this->opener = '';
    }

    private function parseDot($token, $prevTokenGroup, $prevTokenName) {
        if (strlen($token) > 1) {
            if ($prevTokenGroup & (OPERAND|CLOSE)) {
                throw new SyntaxError('Unexpected '.$token);
            }
        }
    }

    private function parseName($token) {
        // name of variable / class / namespace / function / method / array element
        //$this->dotNameQ->enqueue($token);
        //$this->_setupChainStartIndex();
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
        return ' '.$token;
    }
    private function isUnaryAttached($isUnaryAttached, $prevTokenGroup, $currTokenGroup) {
        if ($isUnaryAttached) {
            return ($currTokenGroup & (UNARY|BI_UNARY)) ? true : false; //$token : '';
        }
        if ($currTokenGroup === UNARY) {
            return true; //$token;
        } 
        if ($currTokenGroup === BI_UNARY) {
            // [ UNARY|OPERAND|CLOSE  |  OPERATOR|BI_UNARY|OPEN .. INIT ]
            // if $prevTokenGroup is UNARY, $token has been already returned.
            // if $prevTokenGroup is OPERAND|CLOSE, + - are binary operator.
            // if $prevTokenGroup is OPERATOR|BI_UNARY|OPEN or INIT, + -  are unary operator.
            return ($prevTokenGroup & (OPERAND|CLOSE)) ? false : true; //'' : $token;
        }
        return false; //'';
    }      
    
    private function parsePlus($token, $prevTokenGroup, $prevTokenName) {
        return ($prevTokenName == 'Quoted') ? '.' : ' +';
    }
    private function parseMinus() {
        return ' -';
    }
}


/*
class DotNameQ extends Queue {
    const LOOP = 1;
    const KEY = 2;
    const METHOD = 4;
    const CLASS_ = 8;
    const CONSTANT = 16;
    const NAMESPACE = 32;
    const CHAIN = 64;

    private $description = 0;

    public function describe($description) {
        $this->description |= $description;
    }

    public function flush() {

    }
}
*/