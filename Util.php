<?php
namespace simplate;

class Util
{
	/**
	 * Searche needle in haystack from offset
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param int $offset
	 */
	public static function strfind($haystack,$needle,$offset = 0)
	{
		$pos = $offset;
		$pos = self::getNextNotWhiteChar($haystack,$offset);
		$length = strlen($needle);
		$find = 0;
		for($i=0;$i<$length;$i++)
			if($haystack[$pos+$i] == $needle[$i])
				$find++;
			else
				break;
		return $find == $length?$pos:-1;
	}
	/**
	 * Return next char not white
	 *
	 * @param string $content
	 * @param int $pos
	 * @return int
	 */
	public static function getNextNotWhiteChar($content,$pos)
	{
		$len = strlen($content);
		//Get next char
		while($pos < $len && ($content[$pos] === ' ' || $content[$pos] === "\t" || $content[$pos] === "\n" || $content[$pos] === "\0"))
			{$pos++;}
		return $pos;
	}
	/**
	 * Find the end word
	 *
	 * @param string $content
	 * @param int $offset
	 * @return int position
	 */
	public static function findEndWord($content,$offset,$charsBreak = null)
	{
		if($charsBreak === null)
		{ //Accelearte query
			//while($content[$offset] !== ' ' && $content[$offset] !== "\t" && $content[$offset] !== "\n"){$offset++;}
			while(stripos('abcdefghijklmnopqrstuvwxyz0123456789_',$content[$offset]) !== false){$offset++;}
			return $offset;
		}
		else
		{
			while(strpos($charsBreak,$content[$offset]) === false){$offset++;}
			return $offset;
		}
		
	}
	
	/**
	 * Find next position if find keyword
	 *
	 * @param string $content
	 * @param int $offset
	 * @param string $needle
	 * @return int
	 */
	public static function findNextIfKeyword($content,$offset,$needle)
	{
		$offset = self::getNextNotWhiteChar($content,$offset);
		$len = strlen($needle);
		for($i=0;$i<$len;$i++)
			if(strtolower($content[$offset+$i]) !== strtolower($needle[$i]))
				return -1;
		return $offset+$i;
	}
	public static function getNextIfChar($content,$offset,$char)
	{
		$len = strlen($content);
		while($offset < $len && $content[$offset] != $char){$offset++; }
		return $offset;
	}
	/**
	 * Return position in content when the mask is no valid
	 *
	 * @param string $content
	 * @param int $offset
	 * @param string $mask
	 * @return int
	 */
	public static function findEndMask($content,$offset,$mask)
	{
		while(strpos($mask,$content[$offset])!==false){$offset++;}
		return $offset;
	}
	/**
	 * Check if value is empty
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function isEmpty($value)
	{
		return $value === false || $value === null || $value === '' || (is_array($value) && !count($value));
	}
	public static function Log($action,$pos,$file,$line)
	{
		echo '> '.$action.'('.$pos.') at '.basename($file).':'.$line."\n";
	}
	
	private static $timeById = array();
	public static function InitChrono()
	{
		self::$timeById[] = microtime(1);
		return sizeof(self::$timeById)-1;
	}
	public static function GetChrono($id)
	{
		return (microtime(1) - self::$timeById[$id]) * 1000;
	}
}
