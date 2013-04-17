<?php
namespace simplate\token;
use simplate as s;
/**
 * Class manage block tokens
 *
 */
class Block extends Token
{
	/**
	 * Position exterieure
	 *
	 * @var SectionToken
	 */
	public $oStartSectionToken = null;
	
	/**
	 * Position interieure
	 *
	 * @var Section
	 */
	public $oEndSectionToken = null;
	
	/**
	 * Children block collection
	 *
	 * @var array
	 */
	public $aBlock;
	
	/**
	 * Collection of Tag contained in block
	 *
	 * @var array of Token
	 */
	public $aTag;
	/**
	 * Collection of string
	 *
	 * @var array
	 */
	public $aString;
	/**
	 * Collection of Token
	 *
	 * @var array
	 */
	private $aToken;
	/*
	 * Indicate if block is root block
	 * 
	 * @var bool
	 */
	private $isRoot = false;
	/**
	 * Next block
	 *
	 * @var Block
	 */
	private $alternateBlock;
	
	/**
	 * Parse
	 *
	 * @param string $content
	 * @param int $pos
	 */
	public function parse($content,$pos = 0)
	{
		
		//TODO: Add error if block as not end or else do not begin by while by example !
		$o = null;
		//If block has been construct with the Section, $pos is the end of section
		$pos = $this->oStartSectionToken==null ? $pos : $this->oStartSectionToken->end;	
		
		$lastpos = $pos;
		$continue = false;
		$def = SectionDef::GetInstance();
		do 
		{
			$continue = false;
			
			//If $o is a section found in previous loop
			if($o !== null)
			{
				//Parse var before Section
				$this->parseTags($content,$lastpos,$o->start);
				//If it's a BLOCK, process as block 
				if($def->isBlock($o->type))
				{
					//Creating Block with Section
					$oBlock = new Block($this,$o);
					$pos = $oBlock->parse($content);
					
					//Add to token collection
					$this->aToken[] = $oBlock;
					
				}
				else
				{
					//Add this section in Token collection
					$this->aToken[] = $o;
				}
				$lastpos = $pos;		
			}
			if($pos !== -1)
			{
				
				$o = new Section($this);
				$tpos = $pos;
				do
				{
					if($tpos === -1)
					{
						$tpos = $o->getParseError()->getErrorIndex();
						$o->clearError();
					}
					if($tpos !== -1)
						//Search next simplate section
						$tpos = $o->parse($content,$tpos);
					else
						break;
					
				}
				while($tpos === -1);
				$pos = $tpos;
				
				if($this->oStartSectionToken->type == Section::TYPE_IGNORE && $o->type != Section::TYPE_END)
				{
					
					//Ignore all section until end
					$o = null; 
					$continue = true;
					
				}
				
				
			}
		}
		while( 
				$continue || 
					(!$continue && 
						(	$this->isRoot || 
							(!$this->isRoot && !$def->hasEndType($this->oStartSectionToken->type,$o->type)))
					&& $pos !== -1)
			);
			
		if(!$this->isRoot && $o->type != Section::TYPE_END)
		{
			$this->alternateBlock = new Block($this,$o);
			$pos = $this->alternateBlock->parse($content);
			
		}

		$this->oEndSectionToken = $o;
		//Parse var after block

		$this->parseTags($content,$lastpos,$o->start === false?strlen($content):$o->start);
		return $pos;
	}

	protected function parseTags($content,$begin,$end)
	{
		if($begin === -1/* ||($this->parent != null && $end-$begin==0)*/)
			return;
		if($end-$begin<=0)
		{
			if($begin == 0 && $this->parent == null)
				$this->aString[] = '';
			return;
		}
		$s = substr($content,$begin,$end-$begin);
		
		if($this->oStartSectionToken->type == Section::TYPE_IGNORE)
		{
			$this->aString = array($s);
			return;
		}
		$pos = 0;
		$lastpos = 0;
		$o = null;
		do
		{
			if($o !== null)
			{
				$this->aString[] = substr($s,$lastpos,$o->start-$lastpos);
				$this->aTag[] = $o;
				$this->aToken[] = $o;
				$lastpos = $pos;
			}
			$o = new Tag($this);
			$pos = $o->parse($s,$pos);	
			
		}while($pos!== -1);
		
		$this->aString[] = substr($s,$lastpos,($end-$begin)-$lastpos);
	}
	
	private $calls = 0;
	
	private $toklen, $strlen;
	public function generate(s\Scope $scope)
	{
		$sb = null;
				
				$type = $this->oStartSectionToken ? $this->oStartSectionToken->type : null;
				
				$val = $this->isRoot?$scope:
						($this->oStartSectionToken->var? $this->oStartSectionToken->var->generate($scope):null);
				
				
				//Precalc count elements, used in generateTokens
				$this->toklen = sizeof($this->aToken); 
				$this->strlen = sizeof($this->aString);
				//var_dump('generate',$type);
				switch($type)
				{
					case Section::TYPE_IF:
					case Section::TYPE_ELSEIF:
						if($val)
							return $this->generateTokens($scope);
						return null;
						break;
					case Section::TYPE_ELSE:
					case Section::TYPE_BLOCK:
					case Section::TYPE_IGNORE:
						$val = $scope;
					default:
						if(is_array($val) || $val instanceof \Traversable)
						{
							foreach($val as $j=>$v)
							{
								if(!($v instanceof s\Data))
								{
									$o = $v;
									$v = new s\Data($scope->data);
									$v->import($o);
								}
								$sb .= $this->generateTokens($scope->sub($v,$j));
							}
						}
						else if($val instanceof s\Scope )
							$sb .= $this->generateTokens($val);
						break;
						
				}
				return $sb;
	}
	public function generateTokens(s\Scope $scope)
	{
				$s = '';
		if($this->strlen>0)
			$s = $this->aString[0]; //Add fisrt string
		$previousEmptyBlock = null; 

		$n=0;
		//var_dump('enter generateTokens');
		for($i = 0; $i<$this->toklen;$i++)
		{
			$tok = $this->aToken[$i];
			if($tok instanceof self )
			{
				$r = $tok->oStartSectionToken->type == Section::TYPE_BLOCK ? "" :
						$tok->generate($scope);
				
				if($r === null && $tok->alternateBlock)
				{
										//var_dump('enter alternate');
										$alt = $tok->alternateBlock;
										while($alt)
										{
											if($r = $alt->generate($scope))
											{
						$s .= $r;
												break;
											}
											$alt = $alt->alternateBlock;
										}
										//var_dump('leave alternate');
				}
				else
				{
					$s .= $r;
					
				}
			}
			else 
			{
				if($tok !== null)
					$s .= $tok->generate($scope);
			}	
			if($n+1<$this->strlen)
				$s .= $this->aString[++$n];		

		}
		if($n+1<$this->strlen)
			$s .= $this->aString[$n+1];
				//var_dump('leave generateTokens');
		return $s;
	}
	public function __construct(Token $parent = null,Section $oStart = null)
	{
		parent::__construct($parent);
		
		//if SImplateSection is null >> is root block
		$this->isRoot = $oStart===null;
		$this->oStartSectionToken = $oStart===null?new Section($this):$oStart;
		$this->oEndSectionToken = new Section($this);
		
		if($oStart !== null)
			self::AddBlock($this);
	}
	public function __destruct()
	{
		if(isset($this->oStartSectionToken) && array_key_exists($this->oStartSectionToken->name,self::$blocks))
			return;
		parent::__destruct();
		!empty($this->oStartSectionToken) && $this->oStartSectionToken->destroy();
		!empty($this->oEndSectionToken) && $this->oEndSectionToken->destroy();
		
		if(!empty($this->aToken))
			foreach($this->aToken as $o)
				$o->destroy();
				
		unset(
			$this->oStartSectionToken,
			$this->oEndSectionToken,
			$this->aBlock,
			$this->aTag,
			$this->aString,
			$this->aToken
		);
	}
	public function __sleep()
	{
		return array_merge(array("oStartSectionToken","oEndSectionToken","aBlock","aToken","aTag","aString","isRoot","alternateBlock"),parent::__sleep());
	}
	public function __wakeup()
	{
		self::AddBlock($this);
	}
	//----------------------------------------
	// Static methods
	//----------------------------------------
	
	static private $blocks = array();
	static public function AddBlock(Block $o)
	{
		$name = $o->oStartSectionToken->name;
		if($name != "")
			if(!array_key_exists($name,self::$blocks))
				self::$blocks[$name] = $o;
			else
				throw new Exception("Simplate2: Block '$name' already declared");
				
	}
	static public function GetBlock($name)
	{
		return self::$blocks[$name];
	}

}
