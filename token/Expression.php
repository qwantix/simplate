<?php
namespace simplate\token;
use simplate as s;
/**
 * Expression
 * Can be used as php
 */
class Expression extends Token 
{
	
	/**
	 * Array of token
	 *
	 * @var array
	 */
	private $aToken;
	/**
	 * Alternative expression
	 *
	 * @var Expression
	 */
	private $altExpression;
	/**
	 * Array of Filters
	 *
	 * @var array(Variable)
	 */
	private $aFilters;
	/**
	 * Array map of back reference
	 *
	 * @var array
	 */
	private $mBackReference;
	public function __set($name,$value)
	{
		return $value;
	}
	/**
	 * Return array map of back reference
	 *
	 * @return array
	 */
	public function getBackReference()
	{
		return $this->mBackReference;
	}
	
	/** 
	 * Parse
	 *
	 * @param string $content
	 * @param int $pos
	 * @return int current position
	 */
	public function parse($content,$pos = 0)
	{
		$beginPos = $pos;
		$aStack = array();
		$aOut = array();
		$aOperators = array(
			//
			'&&' => 0,
			'||' => 0,
			//
			'==' => 1,
			'>=' => 1,
			'<=' => 1,
			'!=' => 1,
			'>' => 1,
			'<' => 1,
			//
			'+' => 2,
			'-' => 2,
			//
			'/' => 3,
			'*' => 3,
			//
			'&' => 3,
			'|' => 3,
			'%' => 3,
			//
			'!' => 4
		);

		$endOfExp = false;
		while(!$endOfExp)
		{
			/*//Get next char
			while($content[$pos] == ' ' || $content[$pos] == "\t" || $content[$pos] == "\n")
				{$pos++;}*/
			$pos = s\Util::getNextNotWhiteChar($content,$pos);
			$char = $content[$pos];
			
			switch($char)
			{
				
				case '+':
				case '-':
				case '*':
				case '/':
				case '&':
					if($char == '&' && $content[$pos+1] == '&')
					{
						$char = '&&';
						$pos++;
					}
				case '|':
					if($char == '|' && $content[$pos+1] == '|')
					{
						$char = '||';
						$pos++;	
					}
				case '=':
					if($char == '=' && $content[$pos+1] == '=')
					{
						$char = '==';
						$pos++;	
					}
					elseif($char == '=')
					{
						$this->returnParseError($pos,'Illegal operator =');
					}
						//throw new Exception('Illegal operator = at '.$pos);
				case '>':
					if($content[$pos] == '>' && $content[$pos+1] == '=')
					{
						$char = '>=';
						$pos++;	
					}
				case '<':
					if($char == '<' && $content[$pos+1] == '=')
					{
						$char = '<=';
						$pos++;	 
					}
				
				case '%':
				case '!':
					if($char == '!' && $content[$pos+1] == '=')
					{
						$char = '!=';
						$pos++;
					}
					/*elseif($char == '!')
						throw new Exception('Illegal operator ! at '.$pos);*/
					//Operator..
					$n = 1;
					/*var_dump($this->simplate->getFilename(),$this->simplate->getLineAtIndex($this->parent->start), $this->simplate->getIndexInLine($this->parent->start));
					//var_dump($this->parent);
					var_dump($aStack);*/
					$aStackSize = sizeof($aStack);
					while(
						$aStackSize>0 && $aStackSize>=$n
						&& $aStack[$aStackSize-$n]->type == ExpressionToken::OPERATOR
						&& $aOperators[$char] <= $aOperators[$aStack[$aStackSize-$n]->value]
						
					)
					{
						$aOut[] = array_pop($aStack);
						$n++;
						$aStackSize = sizeof($aStack);
					}
					$aStack[] = new ExpressionToken($char,ExpressionToken::OPERATOR);
					break;
				case '(':
					$aStack[] = new ExpressionToken($char,ExpressionToken::PARENTHESIS);
					break;
				case ')':
					
					while(sizeof($aStack) == 0 || $aStack[sizeof($aStack)-1]->type != ExpressionToken::PARENTHESIS)
						if(sizeof($aStack)>0)
							$aOut[] = array_pop($aStack); //Pop all operator until left parenthesis
						else
						{
							//End loop
							for($i=sizeof($aStack)-1;$i>=0;$i--) //Push Stack to out
								$aOut[] = $aStack[$i];
							$this->aToken = $aOut;
							$endOfExp = true;
							break;
						}
					
					array_pop($aStack); //Pop left parenthesis
					break;
					
				case '{': //evaluation token
					
					$tag = new Tag($this);
					$pos = $tag->parse($content,$pos) - 1;
					$aOut[] = new ExpressionToken($tag,ExpressionToken::VALUE);
					break;	
					
				//Back referance synthax @1 or $1 or \1
				case '$': 
				case '@':
				case '\\':
					//Search index
					$posi = $pos;
					
					while(is_numeric($content[$posi+1]))
						$posi++;
					
					if($posi === $pos)
						return $this->returnMissing($pos+1,"missing back reference index after '$char'");
					$index = (int)substr($content,$pos+1,$posi-($pos));
					
					$char .= $index;
					
					$pos = $posi;
					//Register reference
					$this->mBackReference[$char] = $index;
					
					$oRefVar = new Variable($this);
					$oRefVar->create($char);
				
				
					
				default:
					//Check var here
					//TODO: A finir...
					
					
					$oValue = new Value($this);
					$npos = $oValue->parse($content,$pos);
					
					if($npos == -1)
					{	//isn't SImplateValue
						
						
						if(!isset($oRefVar) || is_null($oRefVar))
						{
							//May be more chance with Variable?
							$oVar = new Variable($this);
							$npos = $oVar->parse($content,$pos);
						}
						else
						{
							//if ref var
							$oVar = $oRefVar;
							$oRefVar = null;
							$npos = $pos;
						}
						
						if($npos == -1)
						{
							for($i=sizeof($aStack)-1;$i>=0;$i--) //Push Stack to out
								$aOut[] = $aStack[$i];
							$this->aToken = $aOut;
							//Out of parsing Loop here
							//$endOfExp = true;
							break 2;
							
						}
						else
							$aOut[] = new ExpressionToken($oVar,ExpressionToken::VALUE);
						$npos--; //Fix offset
					}
					else
					{
						$aOut[] = new ExpressionToken($oValue,ExpressionToken::VALUE);
						$npos--; //Fix offset
					}
					$pos = $npos;
					break;
			}
			if(!$endOfExp)
				$pos++;
		}
		
		if($content[$pos] == ':') // alternative value
		{
			$this->altExpression = new Expression($this);
			$tmpPos = $this->altExpression->parse($content,$pos+1);
			if($tmpPos>0)
				$pos = $tmpPos;
		}
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
		
		return $pos; 
		//return $posEnd;
	}

	public function generate(s\Scope $scope)
	{
		$aStack = array(); 
		$nTok = sizeof($this->aToken);
		if($nTok == 1 && $this->aToken[0]->type == ExpressionToken::VALUE )
			$value = $this->aToken[0]->value->generate($scope);
		else
		{
			for($i=0;$i< $nTok; $i++)
			{
				$token = $this->aToken[$i];
				if($token->type == ExpressionToken::OPERATOR)
				{
					$v2 = (string)array_pop($aStack);
					if($token->value !== '!')
						$v1 = (string)array_pop($aStack);
					switch($token->value)
					{
						case '+':
							if((is_numeric($v1) || is_bool($v1)) &&  (is_numeric($v2)  || is_bool($v2)) )
								$v = $v1 + $v2;
							else
								$v = $v1 . $v2;
							break;
						case '-':
							$v = $v1 - $v2;
							break;
						case '*':
							$v = $v1 * $v2;
							break;
						case '/':
							$v = $v1 / $v2;
							break;
						case '&':
							$v = $v1 & $v2;
							break;
						case '&&';
							$v = $v1 && $v2;
							break;
						case '|':
							$v = $v1 | $v2;
							break;
						case '||':
							$v = $v1 || $v2;
							break;
						case '==':
	        				$v = $v1 == $v2;
							break;
						case '>':
							$v = $v1 > $v2;
							break;
						case '>=':
							$v = $v1 >= $v2;
							break;
						case '<':
							$v = $v1 < $v2;
							break;
						case '<=':
							$v = $v1 <= $v2;
							break;
						case '!=':
							$v = $v1 != $v2;
							break;
						case '%':
							$v = $v1 % $v2;
							break;
						case '!':
							$v = !$v2;
							break;
						default:
							throw new Exception('Generation error, invalid operator '.$v.'');
					}
					$aStack[] = $v;
				}
				else
				{
					 $aStack[] = $token->value->generate($scope);
				}
			}
			//Value is at top of stack
			$value = array_pop($aStack);
		}
		//If empty getting alernative expression
		if($this->altExpression !== null && s\Util::isEmpty($value))
			$value = $this->altExpression->generate($scope);
		//Apply filters
		if(sizeof($this->aFilters)>0)
		{
			$f = $scope->simplate->getFilters();
			foreach($this->aFilters as $filterFunction)
				$value = $filterFunction->applyAsFilter($f,$value,$scope);
		}
		return $value;
	}
	/**
	 * Check if this expression is empty
	 *
	 * @return bool
	 */
	public function isEmpty()
	{
		return empty($this->aToken);
	}
	public function __construct(Token $parent)
	{
		parent::__construct($parent);
		$this->aStack = array();
		$this->mBackReference = array();
	}
	public function __destruct()
	{
		parent::__destruct();
		if(!empty($this->aFilters))
			foreach($this->aFilters as $o)
				$o->destroy();
		!empty($this->altExpression) && $this->altExpression->destroy();
		unset(
			$this->aToken,
			$this->altExpression,
			$this->mBackReference
		);
		
	}
	public function __sleep()
	{
		return array_merge(array("aToken","altExpression","aFilters","mBackReference"),parent::__sleep());
	}
}

class ExpressionToken
{
	const VALUE = 0;
	const OPERATOR = 1;
	const PARENTHESIS = 3;
	public $value;
	public $type;//0:Value, 1:Operator, 2:Parenthesis
	public function __construct($value,$type)
	{
		$this->value = $value;
		$this->type = $type;
	}
}
