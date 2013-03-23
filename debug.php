<?php
function D()
{
	$args = func_get_args();
	$backtrace = debug_backtrace();
	$prev = $backtrace[0];
	$class = basename($prev["file"]);
	$class = str_replace(".class.php","",$class);
	//echo "\n", $class,"[",$prev["line"],"]: ";
	foreach($args as $o)
		var_dump($o);
}
?>