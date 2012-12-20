<?php
include 'lib/Mphp.php';

/**
    Usage:
           include 'lib/Mphp.php'; // include the class
           new \Mphp\Parser($code) // instantiate the parser with the code you want to parse
           ->Tokenize()            // Call the Tokenizer to create the token tree
           ->Lex()                 // Call the Lexer to create the op array
           ->Executor()            // Call the Executor to execute the op array
           ->getResult()           // Get the result after the Executor is done
*/

// Let's try some expressions...

$code[] = '1 + 2';                                                                   // Really simple expression
$code[] = '24 + 111 - 62 % 17';                                                      // Pretty simple
$code[] = '-(3 + -4) * 9';                                                           // A bit more complex
$code[] = '0.4 - (1.005 / ((2 + 11) - (6 * 3 / 9))) ';                               // A lot more complex
$code[] = '(14 - 7 * 2 + 129 - 2^7 * (100 / 25 - (13 % 12)) - -256) * 11 * 11 % 79'; // Kinda crazy
$code[] = '---(8 * -(2^8)) / 2^(-((((-(((-((((9))))))))))))';                        // WTF!
$code[] = '(17 - (4)';                                                               // Parse error...
$code[] = '1.2.3';                                                                   // Parse error
$code[] = '4+';                                                                      // Syntax error...
$code[] = '0 0';                                                                     // Syntax error...

foreach ($code as $i => $c)
{
  try {
    $Parser = new \Mphp\Parser($c);
    echo "$c = " . $Parser->Tokenize()->Lex()->Executor()->getResult() . "\n";
  } catch(Exception $e) {
    echo $e->getMessage() . " code[$i]\n";
  }
}

/**
    Expected output...
    
    1 + 2 = 3
    24 + 111 - 62 % 17 = 124
    -(3 + -4) * 9 = 9
    0.4 - (1.005 / ((2 + 11) - (6 * 3 / 9)))  = 0.30863636363636
    (14 - 7 * 2 + 129 - 2^7 * (100 / 25 - (13 % 12)) - -256) * 11 * 11 % 79 = 42
    ---(8 * -(2^8)) / 2^(-((((-(((-((((9)))))))))))) = 1048576
    Parse Error: Unexpected END at offset 9 on line 1. Expecting Token 'T_CLOSE_BRACE'. code[6]
    Parse Error: Unexpected Token '.' at offset 3 on line 1. code[7]
    Syntax Error: Unexpected END at offset 2 on line 1. Expecting: T_NUMBER, T_DECIMAL, T_NEG or T_OPEN_BRACE. code[8]
    Syntax Error: Unexpected Token 'T_NUMBER' at offset 2 on line 1. Expecting: T_ADD, T_SUB, T_MUL, T_MOD, T_DIV or T_EXP. code[9]
*/

// Now let's try while asking to see the proof of how we reached the result

$Parser->code = "1 + 3 * 5"; // This should execute 3 * 5 before computing the result 1 + 15 = 16 (multiplication takes precedence over addition)
echo $Parser->Tokenize()->Lex()->Executor(true)->getResult(), "\n"; // Calling Parser::Executor(true) gives us a dump of the stack frame
