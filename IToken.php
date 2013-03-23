<?php
namespace simplate;
use simplate as s;

interface IToken
{
	/**
	 * Parse token
	 *
	 * @param string $conten
	 * @param int $pos
	 * 
	 * @return int;
	 */
	public function parse($content,$pos = 0);
	/**
	 * Generate token
	 *
	 * @param s\Data $sd
	 * @return string value generated
	 */
	public function generate(s\Data $sd);
	
	public function __construct(IToken $parent);
}
