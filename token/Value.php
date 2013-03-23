<?php
namespace simplate\token;
use simplate as s;

class Value extends Token
{
	private $type;
	private $value;
	private function checkConst($content,$pos,$const)
	{
		return strtolower(substr($content,$pos,strlen($const))) === /* strtolower */($const) ? strlen($const)+$pos : -1;
	}
	public function parse($content,$pos = 0)
	{
		$posNextToken = s\Util::getNextNotWhiteChar($content,$pos);
		$valueSetted = false;
		switch($content[$posNextToken])
		{
			case '"':
			case "'":
			case "{":
			case "[":
				$offset = s\Util::getNextIfChar($content,$posNextToken+1,$content[$posNextToken]);
				$posNextToken++;
				$this->type = 'string';
				break;
			case "T":
			case "t":
				$offset = $this->checkConst($content,$pos,'true');
				$this->type == "bool";
				$this->value = true;
				$valueSetted = true;
				
				break;
			case "F":
			case "f":
				$offset = $this->checkConst($content,$pos,'false');
				$this->type == "bool";
				$this->value = false;
				$valueSetted = true;
				break;
			case "N":
			case "n":
				$offset = $this->checkConst($content,$pos,'null');
				$this->value = null;
				$valueSetted = true;
				break;
			default: 
				$offset = s\Util::findEndMask($content,$posNextToken,'0123456789.');
				if($offset>$posNextToken)
					$this->type = 'number';
				else
					return -1;
		}
		if($offset === -1)
			return -1;
		if(!$valueSetted)	
			$this->value = substr($content,$posNextToken,$offset-$posNextToken);
		if($this->type=='string')
			$offset++; //pass the " or ' caracter
		return $offset;
	}
	public function generate(s\Scope $scope)
	{
		return $this->value;
	}
	public function __toString()
	{
		return (string)$this->value;
	}
	
	public function __construct(Token $parent)
	{
		parent::__construct($parent);
	}
	public function __destruct()
	{
		parent::__destruct();
		
		unset(
			$this->value,
			$this->type
		);
		
	}
	public function __sleep()
	{
		return array_merge(array("type","value"),parent::__sleep());
	}
}
