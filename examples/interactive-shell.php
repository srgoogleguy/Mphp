<?php
if (strtolower(PHP_SAPI) !== 'cli')
{
  echo "This example can only be run from the command line. Please use a CLI PHP SAPI to run this script.\n\n";
  exit;
}
require '../lib/Mphp.php';

$Mphp = new Mphp\Core;
$debug = isset($argv[1]) && $argv[1] == '-d' ? true : false;
print "Welcome to Mphp version 1.1.0 interactive shell!\n";
if ($debug)
{
  print "* Debug mode started!\n";
} else {
  print "Use -d as a command line argument when calling this script to start the shell in debug mode.\n";
}
print "Please enter any mathematical expression below to compute the answer...\n";
print "Type 'exit' at any time to exit the shell.\n\n";
print "> ";

while(trim(strtolower($input = fgets(STDIN))) != 'exit')
{
  try
  {
    $result = $Mphp->compute(rtrim($input,"\r\n"), $debug);
  } catch(Exception $e) {
    print $e->getMessage() . "\n> ";
    continue;
  }
  if ((is_string($result) && $result !== '') || $result !== null)
  {
    print trim($input) . " = " . $result . "\n";
  }
  print "> ";
}
