<?php
/* Include the Mphp library classes */
require '../lib/Mphp.php';

/*
  To evaluate an expression without an instance you can use the static Core::evaluate method supplying a string as the
  first argument.
*/
echo Mphp\Core::evaluate("1 - (33 * 3 / 9) * -1");

/* Optionally provide a boolean second argument to get debug output. Defaults to false. */
echo "\n\n", Mphp\Core::evaluate("1 - (33 * 3 / 9) * -1",true);

/* You can also instantiate an Mphp Core object */
$mphp1 = new Mphp\Core;
$mphp2 = new Mphp\Core;

echo $mphp1->compute("1 + 1"), "\n"; // The Core::compute method may not be called statically
echo $mphp2->compute("2 + 2"), "\n";

/* You can also use the Parser class directly */
$expression = "-(-(42 ^ 3 / 3717 % (56 - 14)))";
$parser = new Mphp\Parser($expression);

$parser->Tokenize(); // This will call the tokenizer to tokenize the code supplied to the parser
$parser->Lex();      // Perform a lexical analysis of our tokens
$parser->Executor(); // This is our basically our runtime environment where the stack frames are executed

/* To get the result from the parser use the Parser::getResult() method */
echo $parser->getResult(), "\n";

/*
  These methods can be chained to get the result in a single statement and the public Parser::$code property may be
  reused to execute additional expressions from the same parser object.
*/
$parser->code = "1 / 0";

try {
  $parser->Tokenize()->Lex()->Executor();
} catch(Exception $e) {
  echo "{$e->getMessage()}\n"; // All Parser methods may throw exceptions in the event of errors
}

$parser->code = "7 * 6";
echo "The answer to life, the universe, and everything is " .
     $parser->Tokenize()->Lex()->Executor()->getResult() .
     "!\n";

