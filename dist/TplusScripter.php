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

        if (1 < count($split)) { // @if php tag found
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
                    // @if nothing or in [else, loop else, default]
                    // irrelevant to expression
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

        //return [$scriptCode,  $linesInRightTag];
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


const SPACE     = 0;
const OPERAND   = 1;
const OPERATOR  = 2;
const UNARY     = 4;
const OPEN      = 8;
const CLOSE     = 16;

const TOKEN = [
    SPACE => [
        'Space'  =>'\s+'
    ],
    OPERAND => [
        'Reserved'=>'(?:true|false|null)(?![\p{L}p{N}_])',
        'Name'=>'[\p{L}_][\p{L}p{N}_]*',
        'Integer'=>'\d+(?![\p{L}p{N}_])',
        'Number'=>'(?:\d+(?:\.\d*)?(?:[eE][+\-]?\d+)',
        'Quoted'=>'(?:"(?:\\\\.|[^"])*")|(?:\'(?:\\\\.|[^\'])*\')',
    ],
    OPERATOR => [
        'Comparison'=> '===?|!==?|<<?|>>?|<=|>=', // to pairStack  comparision flag on
        'Logic'     => '&&|\|\|',                   // comparision flag off
        'TernaryIf' => '\?',                        // comparision flag off
        'TernaryElseOrKeyEnd'=>':',                 // comparision flag off
        'ArithOrBit'=> '[%*/&|\^]', // can follows quoted?? <<
        'Elvis'     => '\?:|\?\?',
        'Comma'     => ',',
        'Increment' => '\+\+|--', // throw
    ],
    UNARY => [
        'Unary' =>'~|!',
        'Plus'  => '\+',    // Unary or Binary
        'Minus' => '-',     // Unary or Binary
        'Dot'   =>'\.+'     // Unary or Binary
    ],
    OPEN => [
        'ParenthesisOpen'=>'\(',
        'IndexerOrJsonOpen'=>'[\[{]'
        
    ],
    CLOSE => [
        'ParenthesisClose'=>'\)',
        'IndexerOrJsonClose'=>'[\]}]'
    ]
];

class Expression {

    /**
        $pairStack's items
            F   (function opened)    
            J   (JSON [)
            [   (Indexer [)   
            {   (Indexer {)   
            (
            ?   (Ternary Operator)
    */
    private static $pairStack;

    /**
            F   (function closed)   )[123]  ).123   ).abc   ).abc(    (
            J   (JSON closed)
            I   (Indexer closed)    ][   ].abc   ].123   }{   }.abc   }.123 
            :   (need following operand)
            ...
    */
    private static $prevToken;
    private static $caseAvailable;

    private $scriptTokens;
    private $dotNameQ;

    public static function script($caseAvailable=false, $test=null) {
        self::$caseAvailable = $caseAvailable;

        if ($test) {
            //self::$tokenStack = $test['tokenStack'];
            return call_user_func_array([$this, $test['func']], $test['args']);
        }

        self::$pairStack = new Stack;
        self::$unaryPlusMinus = false;
        
        
        $expression = new Expression();
        $expression->parse($test);
        return $expression->assembleScriptTokens();
    }
    
    private function parse() {


        $this->dotNameQ = new DotNameQ;
        $this->scriptTokens = [];

        $prevTokenGroup = 0;
        $prevTokenName  = '';
        
        for (;;) {
            if (!Scripter::$userCode) {
                throw new SyntaxError('Template file ends without tag close.');
            }
            if ($this->isExpressionCompleted($prevTokenGroup)) {
                if (']' === Scripter::$userCode[0]) {
                    break;
                }
                if (':' === Scripter::$userCode[0]) {
                    if ($caseAvailable) {
                        break;
                    } else {
                        throw new SyntaxError('Unexpected :');
                    }
                }
            }

            $token = null;
            foreach (self::TOKEN as $tokenGroup => $tokenNames) {
                foreach ($tokenNames as $tokenName => $pattern) {
                    $pattern = '#^('.$pattern.')#s';
                    if (!preg_match($pattern, Scripter::$userCode, $match)) {
                        continue;
                    }

                    $token = $match[1];
                    Scripter::decreaseUserCode($token);

                    if (SPACE === $tokenGroup) {
                        Scripter::decreaseUserCode($token);
                        continue 3;
                    }
                    if (OPERAND === $tokenGroup
                        and  $prevTokenGroup & (OPERAND|CLOSE)) {
                        throw new SyntaxError('Unexpected '.$token);
                    }
                    if (OPERATOR === $tokenGroup 
                        and  !$prevTokenGroup || $prevTokenGroup&(OPERATOR|OPEN|UNARY)) {
                        throw new SyntaxError('Unexpected '.$token);
                    }
                    if ('Unary' == $tokenName  and  $prevTokenGroup & (OPERAND|CLOSE)) {
                        throw new SyntaxError('Unexpected '.$token);
                    }
                   
                    break 2;
                }
            }
            if (is_null($token)) {
                throw new SyntaxError('Invalid template expression.');
            }

            $scriptToken = $this->{'parse'.$tokenName}($token, $prevTokenGroup, $prevTokenName);

            if ($prevTokenName == 'Plus' and $tokenName == 'Quoted') {
                array_pop($this->scriptTokens);
                $this->scriptTokens[] = '.';
            }

            $this->scriptTokens[] = $scriptToken;

            if (self::$unaryPlusMinus and !in_array($tokenName, ['Plus', 'Minus'])) {
                self::$unaryPlusMinus = false;
            }

            if ( !$this->dotNameQ->isEmpty() and !in_array($tokenName, ['Dot', 'Name']) ) {
                $this->scriptTokens[] = $this->dotNameQ->flush();
            }
            
            $prevTokenGroup = $tokenGroup;
            $prevTokenName  = $tokenName;
        }

        //return self::$linesInExpression;
        //return [implode('', $this->scriptTokens), self::$linesInExpression];
        //return $this->assembleScript();
    } 

    private function assembleScriptTokens() {
        $result = '';
        foreach ($this->scriptTokens as $scriptToken) {
            $result .= is_object($scriptToken) 
                ? $scriptToken->assembleScriptTokens() 
                : $scriptToken;
        }
    }


    //if (!self::$userCode and )

    private function isExpressionCompleted($prevTokenGroup) {
        return (
            !empty($this->scriptTokens)
            and self::$pairStack->isEmpty()
            and $prevTokenGroup & (OPERAND|CLOSE)
        );
    }
 
    private function parseName($token) {
        // name of variable / class / namespace / function / method / array element
        
        $this->dotNameQ->enqueue($token);        
    }
    private function parseDot($token, $prevTokenGroup, $prevTokenName) {
        if ($prevTokenGroup & (OPERAND|CLOSE)) {
            //@if dot of method or dot of array element
            if (strlen($token) > 1) {
                throw new SyntaxError('Unexpected '.$token);
            }
            
            if ( $prevTokenGroup == CLOSE ) {   //@if dot after pair close
                $this->dotNameQ->describe(DotNameQ::CHAIN);

            } else { //@if $prevTokenGroup is OPERAND
                if ($prevTokenName !== 'Name') {//@if prev token is Number or Quoted,
                    throw new SyntaxError('Unexpected '.$token);
                }
            }

        } else { //@if dot of loop element
            if ($prevTokenName == 'Dot') {
                throw new SyntaxError('Unexpected '.$token);
            }

            $this->dotNameQ->describe(DotNameQ::LOOP);
        }

        $this->dotNameQ->enqueue($token);
    }

    private static function parseNumber($token) {
        return $token;
    }

    private static function parseQuoted($token, $prevTokenGroup, $prevTokenName) {
        if (self::$unaryPlusMinus) {    //@todo when to reset?
            throw new SyntaxError('Unexpected '.$token);
        }
        return $token;
    }

    private static function parsePlus($token, $prevTokenGroup, $prevTokenName) {
        if ($prevTokenGroup & (OPERAND|CLOSE) and $prevTokenName == 'Quoted') {
            return '.';
        }

        return _parsePlusMinus($token, $prevTokenGroup);
    }
    private static function parseMinus($token, $prevTokenGroup) {
        return _parsePlusMinus($token, $prevTokenGroup);
    }
    private static function _parsePlusMinus($token, $prevTokenGroup) {
        if ($prevTokenGroup & (OPERATOR|UNARY|OPEN) ) {
            self::$unaryPlusMinus = true;
            return ' '.$token;
        }
        return $token;
    }
}



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
