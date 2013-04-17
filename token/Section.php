<?php
namespace simplate\token;
use simplate as s;

class Section extends Token
{
	//Composed
	const TYPE_FOREACH = 'FOREACH';
	const TYPE_IF = 'IF';
	const TYPE_ELSE = 'ELSE';
	const TYPE_ELSEIF = 'ELSEIF';
	const TYPE_END = 'END';
	const TYPE_IGNORE = 'IGNORE';
	const TYPE_BLOCK = 'BLOCK';
	const TYPE_SCHEME = 'SCHEME';
	
	//Single
	const TYPE_INCLUDE = 'INCLUDE';
	const TYPE_CALL = 'CALL';

	
	/**
	 * Start of section
	 *
	 * @var int
	 */
	public $start;
	/**
	 * End of block
	 *
	 * @var int
	 */
	public $end;
	/**
	 * Type of section
	 *
	 * @var string
	 */
	public $type;
	/**
	 * Name of section
	 *
	 * @var string
	 */
	public $name;
	/**
	 * Variable object
	 *
	 * @var Expression
	 */
	public $var;
	
	public $args;
	
	public $parameters;
	
	/**
	 * Array of Filters
	 *
	 * @var array(Variable)
	 */
	private $filters;
	
	public function __set($name,$value)
	{
		return $value;
	}
	
	/**
	 * Parse
	 *
	 * @param string $content
	 * @param int $pos
	 */
	public function parse($content,$pos = 0)
	{
		$sc = $this->parser->scheme;
		//Searching <!--
		$this->start = strpos($content,$sc->sectionStart,$pos);
		
		if($this->start === -1 || $this->start === false)
			return $this->returnNotFound();
		
		//Searching type of block : BLOCK, INCLUDE, END, ...
		$startType = s\Util::getNextNotWhiteChar($content,$this->start+4);
		if($startType === -1)
			return $this->returnNotFound($this->start+4);
		
		//Searching end of type
		$endType = s\Util::findEndWord($content,$startType);
		if($endType === -1)
			return $this->returnNotFound($startType);
			
		//Getting type
		$this->type = strtoupper(substr($content,$startType,$endType-$startType));
		
		//Checking type
		$def = SectionDef::GetInstance();
		if(!$def->exists($this->type))
			return $this->returnNotFound($endType);
		
		
		if($def->hasName($this->type))
		{
			$pos = s\Util::getNextNotWhiteChar($content,$endType);
			$posEndName = s\Util::findEndWord($content,$pos);
			//Getting name
			$this->name = trim(substr($content,$pos,$posEndName-$pos));
			if($def->nameRequired($this->type) && empty($this->name))
				return $this->returnMissing($posEndName,"Missing name in $this->type section");
			$pos = $posEndName;
		}
		else
			$pos = $endType;
		
		$lastPos = $pos;
		
		if($def->hasArgs($this->type))
		{
			//Searching paramaters
			$posParenthesis = s\Util::findNextIfKeyword($content,$pos,'(');
			$argNotFound = false;
			if($posParenthesis !== -1)
			{		
				$pos = $posParenthesis;
				$continue = false;
				$this->args = array();
				$arg = null;
				do
				{
					//Searching expression
					$arg = new Expression($this);
					$tmpPos = $arg->parse($content,$pos);
					$this->args[] = $arg;
					if($tmpPos === -1 || $pos == $tmpPos)
						$argNotFound = true;
					else
					{
						$pos = $tmpPos === -1?$endType:$tmpPos+1;
						$commaPos = s\Util::getNextNotWhiteChar($content,$pos-1);
						$continue = $content[$commaPos] === ",";
					}
				}
				while($continue);
				$this->var = $this->args[0];
			}
			else
				$argNotFound = true;
			
			if($argNotFound)
				return $this->returnMissing($posEndName,"Missing argument in $this->type section");
		}
		else
		{
			$pos = $lastPos;
		
			if($def->hasParams($this->type))
			{
				
				//Searching paramaters
				$posParenthesis = s\Util::findNextIfKeyword($content,$pos,'(');
				$this->parameters = array();
				if($posParenthesis !== -1)
				{		
					$pos = $posParenthesis;
					
					do
					{
						//Searching param
						$b = s\Util::getNextNotWhiteChar($content,$pos);
						if($b == -1)
							break;
						if($content[$b] == ")")
						{
							$pos = $b+1;
							break;
						}
						elseif($content[$b] == ",")
						{
							$pos = $b+1;
							continue;
						}
						$e = s\Util::findEndWord($content,$b);
						$this->parameters[] = substr($content,$b,$e-$b);
						$pos = $e;
					}
					while(true);
				}
	
				
				if($def->paramRequired($this->type) && sizeof($this->parameters) == 0)
					return $this->returnMissing($posEndName,"Missing parameters in $this->type section");
			}
	
		}
		//Add index for pass ) 
		//$pos++;
		if($def->hasFilters($this->type))
		{
			//Searching filters
			$pos = s\Util::getNextNotWhiteChar($content,$pos);
			$tmpPos = $pos;
			while($content[$tmpPos] == '#' && $tmpPos > 0) // Filter 
			{
				$oFilter = new Variable($this);
				$tmpPos = $oFilter->parse($content,$tmpPos+1);
				if($tmpPos>0)
				{
					$this->aFilters[] = $oFilter;
					//Get next char
					$tmpPos = s\Util::getNextNotWhiteChar($content,$tmpPos);
					$pos = $tmpPos;
				}
			}
			
		}
		//Find end section -->
		$this->end = s\Util::findNextIfKeyword($content,$pos,$sc->sectionEnd);
		if($this->end == -1)
			return $this->returnParseError($pos,"end of section $this->type not found");
		if($this->type == self::TYPE_SCHEME)
		    $this->parser->pushScheme($this->name);
		return $this->end;
	}
	
	public function generate(s\Scope $scope)
	{
		
		switch($this->type)
		{
			case self::TYPE_INCLUDE:
				$oSpl = new \Simplate();
				$oSpl->open($this->var->generate($scope)); //TODO when not / use relative to this template path
				$oSpl->setCallback($scope->simplate->getCallback());
				$oSpl->setFilters($scope->simplate->getFilters());
				$oSpl->setScope($scope->sub());
				return $oSpl->generate();
				break;
			case self::TYPE_CALL:
				$scope = $scope->sub();
				
				$blockName = $this->name;//(string)$this->var->generate($sd);
				
				if($blockName == "")
				{
					//Searching previous block
					$parent = $this->parent;
					while(!($parent instanceof Block) || 
						(
						$parent instanceof Block &&
						$parent->oStartSectionToken->type != Section::TYPE_FOREACH &&
						$parent->oStartSectionToken->type != Section::TYPE_BLOCK
						)
					    )
						$parent = $parent->parent;
					    
					$block = $parent;
				}
				else
				{
					$block = Block::GetBlock($blockName);
					
					if($block==null)
						throw new Exception("Simplate2: block '$blockName' not found");
						
				}
				if(SectionDef::GetInstance()->hasParams($block->oStartSectionToken->type))
				{
					if(sizeof($block->oStartSectionToken->parameters) != sizeof($this->args))
					{
						throw new Exception("Simplate2: mismatch parameters in '$blockName'");
					}
					
					
					$args = array();
					foreach($block->oStartSectionToken->parameters as $i=>$prm)
					{
						$v = $this->args[$i]->generate($scope);
						$args[$prm] = $v;
					}
					foreach($args as $k=>$v)
					    $scope->data->$k = $v;
				}
				else if($this->var)
				{
				    $block->oStartSectionToken->var = $this->var;
				    
				}
				//define("DD", true);
				return $block->generate($scope);//die("bye");
				break;
		}
	}
	public function __construct(Token $parent)
	{
		parent::__construct($parent);
	}
	public function __destruct()
	{
		parent::__destruct();
		if(!empty($this->args))
			foreach($this->args as $o)
				$o->destroy();
		
		!empty($this->var) && $this->var->destroy();
		unset(
			$this->args,
			$this->parameters,
			$this->var
		);
		
	}
	public function __sleep()
	{
		return array_merge(array("type","name","var","args","parameters"),parent::__sleep());
	}
}

class SectionDef
{
	private static $_instance;
	/**
	 * Return instance of SectionDef
	 *
	 * @return SectionDef
	 */
	public static function GetInstance()
	{
		if(is_null(self::$_instance))
			self::$_instance = new self();
		return self::$_instance;
	}
	
	const IDX_NAME = 0;
	const IDX_ARG = 1;
	const IDX_PARAM = 2;
	const IDX_FILTER = 3;
	const IDX_ENDTYPE = 4;
	
	private $struct;
	private $endingTypes;
	
	private function __construct()
	{
		$this->init();
	}
	
	private function init()
	{
		$this->struct = array();
		//				Block name	hasName hasArg	hasParam hasFilter	End type
		$this->struct[Section::TYPE_FOREACH] = 	array(	1,	2,	0,   1,	array(Section::TYPE_END,Section::TYPE_ELSEIF,Section::TYPE_ELSE));
		$this->struct[Section::TYPE_IF] =	array(	0,	2,	0,   0,	array(Section::TYPE_END,Section::TYPE_ELSEIF,Section::TYPE_ELSE));
		$this->struct[Section::TYPE_ELSEIF] =	array(	0,	2,	0,   0,	array(Section::TYPE_END,Section::TYPE_ELSEIF,Section::TYPE_ELSE));
		$this->struct[Section::TYPE_ELSE] =	array(	0,	0,	0,   0,	array(Section::TYPE_END));
		$this->struct[Section::TYPE_END] =	array(	0,	0,	0,   0,	array());
		$this->struct[Section::TYPE_IGNORE] =	array(	0,	0,	0,   0,	array(Section::TYPE_END));
		$this->struct[Section::TYPE_BLOCK] =	array(	2,	0,	1,   1,	array(Section::TYPE_END));
		$this->struct[Section::TYPE_SCHEME] =	array(	2,	0,	0,   0,	array(Section::TYPE_END));
		$this->struct[Section::TYPE_INCLUDE] =	array(	0,	2,	0,   1,	array());
		$this->struct[Section::TYPE_CALL] =	array(	1,	1,	0,   1,	array());
		
		//index ending type
		$this->endingTypes = array();
		foreach($this->struct as $st)
			foreach($st as $endt)
				if(!in_array($endt,$this->endingTypes))
					$this->endingTypes[] = $endt;
	}
	/**
	 * Check if type exists
	 *
	 * @param string $type
	 * @return bool
	 */
	public function exists($type)
	{
		return array_key_exists($type,$this->struct);
	}
	/**
	 * Check if type is begin of block
	 *
	 * @param string $type
	 * @return bool
	 */
	public function isBlock($type)
	{
		return !empty($this->struct[$type][self::IDX_ENDTYPE]);
	}
	/**
	 * Check if type has end type
	 *
	 * @param string $type
	 * @param string $endType
	 * @return bool
	 */
	public function hasEndType($type,$endType)
	{
		return in_array($endType,$this->struct[$type][self::IDX_ENDTYPE]);
	}
	/**
	 * Return if type is end type
	 *
	 * @param string $type
	 * @return bool
	 */
	public function isEnd($type)
	{
		return in_array($type,$this->endingTypes);
	}
	/**
	 * Check if type has name
	 *
	 * @param string $type
	 * @return bool
	 */
	public function hasName($type)
	{
		return $this->struct[$type][self::IDX_NAME] > 0;
	}
	/**
	 * Check if type has params
	 *
	 * @param string $type
	 * @return bool
	 */
	public function hasParams($type)
	{
		return $this->struct[$type][self::IDX_PARAM] > 0;
	}
	/**
	 * Check if type has filter
	 *
	 * @param string $type
	 * @return bool
	 */
	public function hasFilters($type)
	{
		return $this->struct[$type][self::IDX_FILTER] > 0;
	}
	/**
	 * Check if type has expression
	 *
	 * @param string $type
	 * @return bool
	 */
	public function hasArgs($type)
	{
		return $this->struct[$type][self::IDX_ARG] > 0;
	}
	/**
	 * Check if type has name required
	 *
	 * @param string $type
	 * @return bool
	 */
	public function nameRequired($type)
	{
		return $this->struct[$type][self::IDX_NAME] > 1;
	}
	/**
	 * Check if type has arguments required
	 *
	 * @param string $type
	 * @return bool
	 */
	public function argRequired($type)
	{
		return $this->struct[$type][self::IDX_ARG] > 1;
	}
/**
	 * Check if type has params required
	 *
	 * @param string $type
	 * @return bool
	 */
	public function paramRequired($type)
	{
		return $this->struct[$type][self::IDX_PARAM] > 1;
	}
}
