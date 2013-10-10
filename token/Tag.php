<?php
namespace simplate\token;
use simplate as s;
/**
 * Tag is a container of var
 * can be used :
 *  Only :	{MyVar}
 * 	With alternating value : {MyVar:"String replace if not MyVar"}
 *  Combined alterning value : {MyVar:MyAlterningVar:"String replace if not MyVar and MyAlterningVar"}
 *  With conditional tag : {:Texte begin{MyVar}Texte After:}
 */
class Tag extends Token
{
	private $t_start;
	private $t_end;
	private $t_conditional;
	
	/* Start of tag
	 *
	 * @var int
	 */
	public $start;
	/**
	 * End of tag
	 *
	 * @var int
	 */
	public $end;
	/**
	 * exp of tag
	 *
	 * @var Expression
	 */
	public $exp;
	/**
	 * Array of sub tag in case where this tag is tag container
	 * 
	 * @var array of tag
	 */
	public $aSubTag;
	/**
	 * Array of strings in case where this tag is tag container
	 *
	 * @var array of string
	 */
	public $aString;
	/**
	 * Flag indicate if the var is tag container
	 *
	 * @var bool
	 */
	public $isTagContainer = false;
	
	public $isComment = false;
	
	private $isEscaped = false;
	
	public $string;
	/**
	 * Read only system with php magic method
	 * @ignore 
	 */
	public function __set($name,$value)
	{
		return $value;
	}
	
	/**
	 * Find token
	 *
	 * @param string $content
	 * @param int $pos
	 * @param boolean $breakOnConditionnalTag
	 * @param int $conditionnalTagPos;
	 * 
	 * @return int Begin pos
	 */
	public function find($content,$pos = 0,$breakOnEndTag = false, &$EndTagPos = null)
	{
		$l = strlen($content);
		while($pos<$l)
		{
			$c = $content{$pos};
			switch($c)
			{
				case $this->t_end:
					if($breakOnEndTag)
					{
						$EndTagPos = $pos;
						return -1;
					}
				case $this->t_start:
					if($pos > 0 && $content[$pos-1] === '\\')
					{
						$this->isEscaped = true;
						$pos--;
						
					}
					return $pos;
			}
			$pos++;
		}
		return -1;
	}
	/**
	 * Parse
	 *
	 * @param string $content
	 * @param int $pos
	 */
	public function parse($content,$pos = 0)
	{
		
		
		//Searching next tag
		$this->start = $this->find($content,$pos);
		//If not found return
		if($this->start === -1)
			return $this->returnNotFound($pos);
		//check comment
		if($content[$this->start+1] === "*" && $content[$this->start+2] === "*")
		{
			$this->end = strpos($content,$this->t_end,$this->start);
			$this->end += 1;
			$this->isComment = true;
			return $this->end;
		}
		if($this->isEscaped)
		{
			$pos = $this->searchSubTags($content,$this->start+1);
		}
		else
		{
			//Strip white space
			$pos = $this->exp->parse($content,$this->start+1 );
			if($pos === -1)
				return $this->returnMissing($this->start+1,"Missing var");
	
			$pos = s\Util::getNextNotWhiteChar($content,$pos);
			
			switch($content[$pos])
			{
				case $this->t_conditional:
					$pos = $this->searchSubTags($content,$pos);
					break;
				case $this->t_end:
					break;
					
			}
		}
		$this->end = $pos+1 ; //Add offset 1, char "}"
		
		//$this->string = substr($content,$this->start,$this->end-$this->start);
		return $this->end;
	}
	private function searchSubTags($content, $pos)
	{
		//Process search other tags in string
		$lastpos = $pos+1;
		$conditionnalTagPos = $pos;
		$endTagPos = $pos;
		$o = null;
		do
		{
			
			if($o !== null && $o->parse($content,$pos) !== -1)
			{
				$this->aString[] = substr($content,$lastpos,$o->start-$lastpos);
				$this->aSubTag[] = $o;
				$lastpos = $o->end;
			}
			$o = new Tag($this);
			$pos = $o->find($content,$lastpos,true,$endTagPos);
			
		}
		while($pos !== -1);
		
		$this->aString[] = substr($content,$lastpos,$endTagPos-$lastpos);
		$this->isTagContainer = true;
		
		$pos = $endTagPos; 
		return $pos;
	}
	public function generate(s\Scope $scope)
	{
		$s = '';
		if($this->isTagContainer)
		{
			$values = array();
			$bDisplay = true;
			if($this->exp->isEmpty())
			{
				foreach($this->aSubTag as $i=>$tag)
				{
					$values[$i] = $tag->generate($scope);
					if(s\Util::isEmpty($values[$i]))
						return $s;
				}
				
			}
			else
			{
				$mBackRef = $this->exp->getBackReference();
				$nBackRef = sizeof($mBackRef);
				if($nBackRef>0)
				{
					if($nBackRef > sizeof($this->aSubTag))
						return $this->returnGenerationError("Mismatch back references");
					//Setting back reference
					foreach($mBackRef as $ref=>$index)
					{
						$index -= 1;
						if(!isset($this->aSubTag[$index]))
							return $this->returnGenerationError("Unknow back reference $ref");
						
						$values[$index] = $this->aSubTag[$index]->generate($scope);
						
						$sd->{$ref} = $values[$index];
							
					}
				}
				$bDisplay = !s\Util::isEmpty($this->exp->generate($scope));
			}
			if($bDisplay)
			{ 
				if(sizeof($this->aString)>0)
					$s = $this->aString[0]; // . ( $this->aSubTag[0]->isTestTag ? '' : $vFirstTag ) . $this->aString[1];
				
				for($i = 0; $i<sizeof($this->aSubTag);$i++)
					$s .= (array_key_exists($i,$values) ? $values[$i] : $this->aSubTag[$i]->generate($scope)) . $this->aString[$i+1];
				
			}
		}
		elseif(!$this->isComment)
		{
			$s = $this->exp->generate($scope);
		}
		if($this->isEscaped)
			$s = '{'.$s.'}';
		return $s;
	}
	public function __construct(Token $parent)
	{
		parent::__construct($parent);
		
		$sc = $this->parser->scheme;
		
		$this->t_start = $sc->tagStart;
		$this->t_end = $sc->tagEnd;
		$this->t_conditional = $sc->tagConditional;
		
		$this->exp = new Expression($this);
		$this->aString = array();
		$this->aSubTag = array();
		
	}
	public function __destruct()
	{
		parent::__destruct();
		if(!empty($this->aSubTag))
			foreach($this->aSubTag as $o)
				$o->destroy();
		!empty($this->exp) && $this->exp->destroy();
		unset(
			$this->exp,
			$this->aSubTag,
			$this->aString,
			$this->string
		);
		
	}
	public function __sleep()
	{
		return array_merge(array("exp","aSubTag","aString","isTagContainer","isComment","isEscaped"),parent::__sleep());
	}
}
