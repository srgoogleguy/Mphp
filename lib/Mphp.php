<?php
/**
    @name:        Mphp
    @description: An operator precedence parser written in PHP that can parse simple/complex mathematical expressions.
    @version:     1.0.0
    @author:      Sherif Ramadan
    @URL:         http://sheriframadan.com/mphp
*/
namespace Mphp;

class Parser
{

  public $code = '';
  private $tokenArray = array();
  private $opArray = array();
  private $stackFrame = array();
  private $result = 0;
  const T_NUMBER        = 1;
  const T_DECIMAL       = 2;
  const T_WHITE_SPACE   = 4;
  const T_OPEN_BRACE    = 6;
  const T_CLOSE_BRACE   = 8;
  const T_ADD           = 16;
  const T_NEG           = 24;
  const T_SUB           = 32;
  const T_MUL           = 64;
  const T_MOD           = 128;
  const T_DIV           = 256;
  const T_EXP           = 512;
  const OPERATOR_BINARY = 1024;
  const OPERATOR_UNARY  = 2048;
  const RESULT          = -1;

  public function __construct($code)
  {
    if (!is_string($code))
    {
      $type = gettype($code);
      throw new \Exception("Parser expects first argument of the construct to be a string: {$type} given.");
    }
    $this->code = $code;
  }
  
  public function Tokenize()
  {
    $this->tokenArray = array();
    $offset = $cursor = $expressionGroup = 0;
    $line = 1;
    $len = strlen((string) $this->code);
    $escape = false;
    
    while ($cursor < $len)
    {
      
      if (!empty($this->tokenArray))
      {
        list($lastToken) = array_slice($this->tokenArray, -1);
        $lastTokenKey = key(array_slice($this->tokenArray, -1, null, true));
      } else {
        $lastToken = array();
        $lastTokenKey = null;
      }
      if ($escape)
      {
        /* Handle escape sequence characters here */
      }
      
      switch (ord($this->code[$cursor]))
      {
        case 48:
        case 49:
        case 50:
        case 51:
        case 52:
        case 53:
        case 54:
        case 55:
        case 56:
        case 57:
                if (!empty($lastToken) && 
                    ($lastToken['tokenName'] == Parser::T_NUMBER || $lastToken['tokenName'] == Parser::T_DECIMAL))
                {
                  $this->tokenArray[$lastTokenKey]['token'] .= $this->code[$cursor];
                } else {
                  $this->tokenArray[] = array(
                                              'token'     => $this->code[$cursor],
                                              'tokenName' => Parser::T_NUMBER,
                                              'offset'    => $offset,
                                              'line'      => $line,
                                             );
                }
                break;

        case 46:
                if (!empty($lastToken) && $lastToken['tokenName'] == Parser::T_NUMBER)
                {
                  $this->tokenArray[$lastTokenKey]['token']     .= $this->code[$cursor];
                  $this->tokenArray[$lastTokenKey]['tokenName']  = Parser::T_DECIMAL;
                  /* Lookahead rule: no trailing dots allowed */
                  if ($cursor + 1 < $len && (!in_array(ord($this->code[$cursor+1]), range(48,57))))
                  {
                    $offset += 2;
                    throw new \Exception("Parse Error: Unexpected Token '{$this->code[$cursor+1]}' at offset $offset " .
                                        "on line $line. Expecting T_NUMBER.");
                  } elseif ($cursor + 1 >= $len && (!in_array(ord($this->code[$cursor+1]), range(48,57)))) {
                    $offset += 2;
                    throw new \Exception("Parse Error: Unexpected END at offset $offset on line $line. ".
                                        "Expecting T_NUMBER.");
                  }
                } else {
                  throw new \Exception("Parse Error: Unexpected Token '{$this->code[$cursor]}' at offset $offset on" .
                                      " line $line.");
                }
                break;

        case  9:
        case 10:
        case 13:
        case 32:
                if (!empty($lastToken) && $lastToken['tokenName'] == Parser::T_WHITE_SPACE)
                {
                  $this->tokenArray[$lastTokenKey]['token'] .= $this->code[$cursor];
                } else {
                  $this->tokenArray[] = array(
                                              'token'     => $this->code[$cursor],
                                              'tokenName' => Parser::T_WHITE_SPACE,
                                              'offset'    => $offset,
                                              'line'      => $line,
                                             );
                }
                if ($this->code[$cursor] == "\n")
                {
                  $line++;
                  $offset = 0;
                }
                break;

        case 40:
                $this->tokenArray[] = array(
                                            'token'     => $this->code[$cursor],
                                            'tokenName' => Parser::T_OPEN_BRACE,
                                            'offset'    => $offset,
                                            'line'      => $line,
                                           );                
                $expressionGroup++;
                break;
        case 41:
                /* We may not close a brace unless there is at least one expression group open */
                if ($expressionGroup - 1 < 0)
                {
                  $tokenName = $this->getTokenName(Parser::T_OPEN_BRACE);
                  throw new \Exception("Parse Error: Unexpected Token '{$tokenName}' at offset $offset on line $line.");
                }
                $this->tokenArray[] = array(
                                            'token'     => $this->code[$cursor],
                                            'tokenName' => Parser::T_CLOSE_BRACE,
                                            'offset'    => $offset,
                                            'line'      => $line,
                                           );                
                $expressionGroup--;                
                break;


        case 43:
                $this->tokenArray[] = array(
                                            'token'     => $this->code[$cursor],
                                            'tokenName' => Parser::T_ADD,
                                            'offset'    => $offset,
                                            'line'      => $line,
                                           );                
                break;

        case 45:
                /*
                  A negation operator is only allowed if any of the the following conditions are true:
                    - There are no prior tokens in the token array
                    - All prior tokens in the token array are white space tokens
                  AND all of the following conditions are also true:
                    - The last token that isn't a white space token is an operator token or an open brace token
                */
                $filtered = array_filter($this->tokenArray, array($this,'filterNonActiveTokens'));
                $lastFiltered = array_pop($filtered);
                if (empty($lastFiltered) ||
                    ($lastFiltered['tokenName'] >= Parser::T_ADD && $lastFiltered['tokenName'] <= Parser::T_EXP) ||
                    ($lastFiltered['tokenName'] == Parser::T_OPEN_BRACE))
                {
                  $this->tokenArray[] = array(
                                              'token'     => $this->code[$cursor],
                                              'tokenName' => Parser::T_NEG,
                                              'offset'    => $offset,
                                              'line'      => $line,
                                             );
                } else {                
                  $this->tokenArray[] = array(
                                              'token'     => $this->code[$cursor],
                                              'tokenName' => Parser::T_SUB,
                                              'offset'    => $offset,
                                              'line'      => $line,
                                             );
                }
                break;

        case 42:
                $this->tokenArray[] = array(
                                            'token'     => $this->code[$cursor],
                                            'tokenName' => Parser::T_MUL,
                                            'offset'    => $offset,
                                            'line'      => $line,
                                           );                
                break;

        case 37:
                $this->tokenArray[] = array(
                                            'token'     => $this->code[$cursor],
                                            'tokenName' => Parser::T_MOD,
                                            'offset'    => $offset,
                                            'line'      => $line,
                                           );                
                break;

        case 47:
                $this->tokenArray[] = array(
                                            'token'     => $this->code[$cursor],
                                            'tokenName' => Parser::T_DIV,
                                            'offset'    => $offset,
                                            'line'      => $line,
                                           );                
                break;

        case 94:
                $this->tokenArray[] = array(
                                            'token'     => $this->code[$cursor],
                                            'tokenName' => Parser::T_EXP,
                                            'offset'    => $offset,
                                            'line'      => $line,
                                           );                
                break;

        default:
                throw new \Exception("Parse Error: Unknown Token '{$this->code[$cursor]}' at offset $offset on " .
                                    "line $line.");
      }
      
      $cursor++;
      $offset++;
      
    }
    
    /* Do not exit the parser successfully if we still have an unclosed expressionGroup */
    if ($expressionGroup)
    {
      $tokenName = $this->getTokenName(Parser::T_CLOSE_BRACE);
      throw new \Exception("Parse Error: Unexpected END at offset $offset on line $line. Expecting Token '$tokenName'.");
    }
    
    return $this;
  }
  
  private function checkExpect($token, $expect, &$expressionGroup)
  {
      if (!in_array($token['tokenName'], $expect)) {
        $expected = array_map(array($this, 'getTokenName'), $expect);
        $or = array_pop($expected);
        $tokenName = $this->getTokenName($token['tokenName']);
        $offset = $token['offset'];
        $line = $token['line'];
        throw new \Exception("Syntax Error: Unexpected Token '$tokenName' at offset $offset on line $line. " .
                            "Expecting: " . implode(', ', $expected) . " or $or.");
      }      
      if ($token['tokenName'] == Parser::T_NUMBER || $token['tokenName'] == Parser::T_DECIMAL)
      {
        /* Expect: T_ADD, T_SUB, T_MUL, T_MOD, T_DIV, T_EXP and optionally T_CLOSE_BRACE if still in expressionGroup */
        $expect = array(
                        Parser::T_ADD,
                        Parser::T_SUB,
                        Parser::T_MUL,
                        Parser::T_MOD,
                        Parser::T_DIV,
                        Parser::T_EXP,
                       );
        if ($expressionGroup)
        {
          $expect[] = Parser::T_CLOSE_BRACE;
        }
      }
      if ($token['tokenName'] == Parser::T_OPEN_BRACE)
      {
        /* Expect: T_NUMBER, T_DECIMAL, T_NEG, T_OPEN_BRACE */
        $expect = array(
                        Parser::T_NUMBER,
                        Parser::T_DECIMAL,
                        Parser::T_NEG,
                        Parser::T_OPEN_BRACE,
                       );
        $expressionGroup++;
      }
      if ($token['tokenName'] == Parser::T_CLOSE_BRACE)
      {
        /* Expect: T_ADD, T_SUB, T_MUL, T_MOD, T_DIV, T_EXP and optionally T_CLOSE_BRACE if still in expressionGroup */
        $expect = array(
                        Parser::T_ADD,
                        Parser::T_SUB,
                        Parser::T_MUL,
                        Parser::T_MOD,
                        Parser::T_DIV,
                        Parser::T_EXP,
                       );
        $expressionGroup--;
        if ($expressionGroup)
        {
          $expect[] = Parser::T_CLOSE_BRACE;
        }
      }
      if ($token['tokenName'] == Parser::T_ADD ||
          $token['tokenName'] == Parser::T_SUB ||
          $token['tokenName'] == Parser::T_MUL ||
          $token['tokenName'] == Parser::T_MOD ||
          $token['tokenName'] == Parser::T_DIV ||
          $token['tokenName'] == Parser::T_EXP)
      {
        /* Expect: T_NUMBER, T_DECIMAL, T_NEG, T_OPEN_BRACE */
        $expect = array(
                        Parser::T_NUMBER,
                        Parser::T_DECIMAL,
                        Parser::T_NEG,
                        Parser::T_OPEN_BRACE,
                       );
      }
      if ($token['tokenName'] == Parser::T_NEG)
      {
        /* Expect: T_NUMBER, T_DECIMAL, T_NEG, T_OPEN_BRACE */
        $expect = array(
                        Parser::T_NUMBER,
                        Parser::T_DECIMAL,
                        Parser::T_NEG,
                        Parser::T_OPEN_BRACE,
                       );
      }
      return $expect;
  }
  
  public function Lex()
  {
    $tokens = array_filter($this->tokenArray, array($this,'filterNonActiveTokens'));
    $expect = array(
                    Parser::T_NUMBER,
                    Parser::T_DECIMAL,
                    Parser::T_OPEN_BRACE,
                    Parser::T_NEG,
                   );
    $expressionGroup = 0;
    foreach ($tokens as $token)
    {
      $expect = $this->checkExpect($token, $expect, $expressionGroup);
    }
    if ($token['tokenName'] >= Parser::T_ADD && $token['tokenName'] <= Parser::T_EXP)
    {
      if (!in_array($token['tokenName'], $expect)) {
        $expected = array_map(array($this, 'getTokenName'), $expect);
        $or = array_pop($expected);
        $tokenName = $this->getTokenName($token['tokenName']);
        $offset = $token['offset'];
        $line = $token['line'];
        $offset++;
        throw new \Exception("Syntax Error: Unexpected END at offset $offset on line $line. " .
                            "Expecting: " . implode(', ', $expected) . " or $or.");
      }
    }

    /* If the lexical analyses of the token array is valid we can proceed to create an op array */
    $operatorStack = array();
    $outputStack   = array();
    $expression = false;
    /* Implement shunting yard algorithm */
    foreach ($tokens as $token)
    {
      if ($token['tokenName'] == Parser::T_NUMBER || $token['tokenName'] == Parser::T_DECIMAL)
      {
        $outputStack[] = $token;
      }
      elseif ($token['tokenName'] == Parser::T_OPEN_BRACE)
      {
        $operatorStack[] = $token;
        $expression = true;
      }
      elseif ($token['tokenName'] == Parser::T_CLOSE_BRACE)
      {
        do
        {
          $brace = false;
          $last = array_pop($operatorStack);
          if ($last['tokenName'] == Parser::T_OPEN_BRACE)
          {
            $brace = true;
          } else {
            $outputStack[] = $last;
          }
        } while (!$brace);
      }
      elseif ($token['tokenName'] >= Parser::T_ADD && $token['tokenName'] <= Parser::T_EXP)
      {
        do
        {
          $last = array_slice($operatorStack, -1);
          $last = array_pop($last);
          if ($last &&
              $this->getOperatorPrecedence($token['tokenName']) <= $this->getOperatorPrecedence($last['tokenName']) &&
              $last['tokenName'] !== Parser::T_OPEN_BRACE &&
              $token['tokenName'] !== Parser::T_NEG &&
              !empty($outputStack))
          {
            $outputStack[] = array_pop($operatorStack);
          } else {
            $last = null;
          }
        } while ($last);
        $operatorStack[] = $token;
      }
    }
    /* Pop entire operator stack on to the output stack and we're done */
    while($operatorStack)
    {
      $outputStack[] = array_pop($operatorStack);
    }
    $this->opArray = $outputStack;
    return $this;
  }
  
  public function Executor($showFrames = false)
  {
    $this->stackFrame = array();
    $stack = $this->opArray;
    /* Main executor loop */
    reset($stack);
    do
    {
      $op = current($stack);
      if ($op === null)
      {
        next($stack);
        continue;
      }
      if ($op['tokenName'] < Parser::T_ADD || $op['tokenName'] > Parser::T_EXP)
      {
        next($stack);
        continue;
      } else {
        if ($this->getOperatorType($op['tokenName']) == Parser::OPERATOR_UNARY)
        {
          $op  = current($stack);
          $moved = 0;
          do
          {
            $rhv = prev($stack);
            $moved++;
          } while ($rhv === null || $rhv['tokenName'] > Parser::T_DECIMAL);
          $stack[key($stack)] = null;
          do
          {
            next($stack);
          } while (--$moved);
          $result = $this->Execute($op['tokenName'], $rhv['token']);
          if ($showFrames)
          {
            echo $this->getLastStackFrame(), "\n";
          }
          $stack[key($stack)]['tokenName'] = Parser::RESULT;
          $stack[key($stack)]['token'] = $result;
          continue;            
        } elseif ($this->getOperatorType($op['tokenName']) == Parser::OPERATOR_BINARY)
        {
          $op  = current($stack);
          $moved = 0;
          do
          {
            $rhv = prev($stack);
            $moved++;
          } while ($rhv === null || $rhv['tokenName'] > Parser::T_DECIMAL);
          $stack[key($stack)] = null;
          do
          {
            $lhv = prev($stack);
            $moved++;
          } while ($lhv === null || $lhv['tokenName'] > Parser::T_DECIMAL);
          $stack[key($stack)] = null;
          do
          {
            next($stack);
          } while (--$moved);
          $result = $this->Execute($op['tokenName'], $rhv['token'], $lhv['token']);
          if ($showFrames)
          {
            echo $this->getLastStackFrame(), "\n";
          }
          $stack[key($stack)]['tokenName'] = Parser::RESULT;
          $stack[key($stack)]['token'] = $result;
          continue;
        }
      }
    } while(key($stack) !== null);
    $result = array_filter($stack, array($this, 'filterResult'));
    $result = array_pop($result);
    $this->result = $result['token'];
    return $this;
  }
  
  public function Execute($operator, $operand1, $operand2 = null)
  {
    switch ($operator)
    {
      case Parser::T_ADD:
                          $r = $operand2 + $operand1;
                          $this->stackFrame[] = "$operand2 + $operand1 = $r";
                          return $r;
      case Parser::T_SUB:
                          $r = $operand2 - $operand1;
                          $this->stackFrame[] = "$operand2 - $operand1 = $r";
                          return $r;
      case Parser::T_NEG:
                          $r = $operand1 * -1;
                          $this->stackFrame[] = "-($operand1) = $r";
                          return $r;
      case Parser::T_MUL:
                          $r = $operand2 * $operand1;
                          $this->stackFrame[] = "$operand2 * $operand1 = $r";
                          return $r;
      case Parser::T_MOD:
                          $r = $operand2 % $operand1;
                          $this->stackFrame[] = "$operand2 % $operand1 = $r";
                          return $r;
      case Parser::T_DIV:
                          $r = $operand2 / $operand1;
                          $this->stackFrame[] = "$operand2 / $operand1 = $r";
                          return $r;
      case Parser::T_EXP:
                          $r = pow($operand2, $operand1);
                          $this->stackFrame[] = "$operand2 ^ $operand1 = $r";
                          return $r;
    }
  }
  
  public function getResult() {
    return $this->result;
  }
  
  public function getTokenName($token)
  {
    switch ($token)
    {
      case Parser::T_NUMBER     :
                                  return 'T_NUMBER';

      case Parser::T_DECIMAL    :
                                  return 'T_DECIMAL';

      case Parser::T_WHITE_SPACE:
                                  return 'T_WHITE_SPACE';

      case Parser::T_OPEN_BRACE :
                                  return 'T_OPEN_BRACE';

      case Parser::T_CLOSE_BRACE:
                                  return 'T_CLOSE_BRACE';

      case Parser::T_ADD        :
                                  return 'T_ADD';

      case Parser::T_SUB        :
                                  return 'T_SUB';

      case Parser::T_NEG        :
                                  return 'T_NEG';

      case Parser::T_MUL        :
                                  return 'T_MUL';

      case Parser::T_MOD        :
                                  return 'T_MOD';

      case Parser::T_DIV        :
                                  return 'T_DIV';

      case Parser::T_EXP        :
                                  return 'T_EXP';

      default                   :
                                  return 'Unknown';
    }
  }

  public function getOperatorPrecedence($operator)
  {
    switch ($operator)
    {
      case Parser::T_ADD        :
                                  return 1;

      case Parser::T_SUB        :
                                  return 1;

      case Parser::T_MUL        :
                                  return 2;

      case Parser::T_DIV        :
                                  return 2;

      case Parser::T_MOD        :
                                  return 2;

      case Parser::T_EXP        :
                                  return 3;

      case Parser::T_NEG        :
                                  return 4;

      case Parser::T_OPEN_BRACE :
                                  return 4;

      case Parser::T_CLOSE_BRACE:
                                  return 4;

      default                   :
                                  return false;
    }
  }
  
  public function getOperatorType($operator)
  {
    switch ($operator)
    {
      case Parser::T_ADD        :
                                  return Parser::OPERATOR_BINARY;

      case Parser::T_SUB        :
                                  return Parser::OPERATOR_BINARY;

      case Parser::T_MUL        :
                                  return Parser::OPERATOR_BINARY;

      case Parser::T_DIV        :
                                  return Parser::OPERATOR_BINARY;

      case Parser::T_MOD        :
                                  return Parser::OPERATOR_BINARY;

      case Parser::T_EXP        :
                                  return Parser::OPERATOR_BINARY;

      case Parser::T_NEG        :
                                  return Parser::OPERATOR_UNARY;

      default                   :
                                  return false;
    }
  }
  
  public function getLastStackFrame() {
    $frame = array_slice($this->stackFrame, -1, null, true);
    $k = key($frame);
    return "[$k] " . array_pop($frame);
  }

  public function getStack() {
    return $this->stackFrame;
  }
  
  public function filterNonActiveTokens($tokens)
  {
    if ($tokens['tokenName'] !== Parser::T_WHITE_SPACE)
    {
      return true;
    }
  }

  public function filterResult($tokens)
  {
    if ($tokens['tokenName'] === Parser::RESULT)
    {
      return true;
    }
  }
  
}
