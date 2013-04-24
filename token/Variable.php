<?php
namespace simplate\token;
use simplate as s;

/**
 * Variable represent any var
 */
class Variable extends Token
{
	const MASK = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
	/**
	 * Name of var
	 *
	 * @var string
	 */
	public $sName = '';

	/**
	 * Level of var
	 * Indicate how many up level
	 * if < 0, top level
	 * @var int
	 */
	public $nLevel=0;

	/**
	 * Property of var
	 *
	 * @var Variable
	 */
	private $prop;

	/**
	 * Indique si la valeur doit etre inversé
	 *
	 * @var bool
	 */
	private $bReverse = false;

	/**
	 * Indique si la variable doit etre interprétée comme un booléen
	 *
	 * @var bool
	 */
	private $bBoolean = false;

	/**
	 * Indique si la variable est une fonction
	 *
	 * @var bool
	 */
	private $bFunction = false;

	/**
	 * Tableau de paramètres passé à la fonction
	 *
	 * @var array
	 */
	private $aParam = array();

	private $isKeyword = false;
	
	public function __set($name,$value)
	{
		return $value;
	}
	public function create($name)
	{
		$this->sName = $name;
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
		$posStart = s\Util::getNextNotWhiteChar($content,$pos);
		$firstChar = $content{$posStart};
		//first phase of var : Reverse token
		/*
			Depreaced, view in Expression
			if( $firstChar === '!' )
			{
				$posStart++;
				$this->bBoolean = true;
				$this->bReverse = true;
				$firstChar = $content[$posStart];
			}*/
		//Second phase : the Var

		$posEnd = s\Util::findEndMask($content,$posStart,self::MASK);
		if($posEnd === $posStart)
			return -1;
		$posNextToken = s\Util::getNextNotWhiteChar($content,$posEnd);
		$char = $content[$posNextToken];
		$this->sName = substr($content,$posStart,$posEnd-$posStart);
		
		$this->isKeyword = in_array(strtolower($this->sName), array('parent','simplate','this','top','scope'));
			
		//Third phase : Attribute
		do
		{
			switch($char)
			{

			case '(': //Begin function params

				$this->bFunction = true;
				$cpos = $posNextToken+1;
				if($content[$cpos] !== ')')
				while(true)
				{
					$oExp = new Expression($this);
					/*$oVal = new Value();
							$oVar = new Variable();*/
					$cpostmp = $cpos;

					if(($cpostmp = $oExp->parse($content,$cpos)) !== -1)
					$this->aParam[] = $oExp;
					/*
							if(($cpostmp = $oVal->parse($content,$cpos)) !== -1)
								$this->aParam[] = $oVal;
							elseif(($cpostmp = $oVar->parse($content,$cpos)) !== -1)
								$this->aParam[] = $oVar;*/
					else
					return -1;
					$cpos = $cpostmp;
					$cpos = s\Util::getNextNotWhiteChar($content,$cpos);

					if($content[$cpos] === ',')
					{
					$cpos++;
					continue;
					}
					elseif($content[$cpos] === ')')
					{
					$cpos++;
					break;
					}
					else
					return -1;
				}
				else
				$cpos++; //If char is ) then adjust pos
				$posNextToken = $posEnd = $cpos;
				$char = $content[$posEnd];
				break; //Loop for search dot property

			case '.': //Begin Property

				$o = new Variable($this);
				$posEnd = $o->parse($content,$posNextToken+1);
				$this->prop = $o;

			default:
				return $posEnd; //Out loop here
				break;
			}
		}while(true);
		return $posEnd;
	}

	public function generate(s\Scope $scope)
	{
	
		$s = '';
		$sd = $scope->data;
		if($this->isKeyword)
		{
			switch(strtolower($this->sName))
			{
			case 'parent':
				$data = $scope->parent()->data;
				break;
			case 'simplate':
				$data = $scope->simplate;
				break;
			case 'this':
				$data = $scope->data;
				break;
			case 'top':
				$data = $scope->top()->data;
				break;
			case 'scope':
				$data = $scope;
				break;
			default:
				throw new \Exception('Unknow keyword '.$this->sName);
			}
		}
		else
			$data = $sd->getByName($this->sName);
		/*if($this->sName == 'plop')
			var_dump($scope->data->preview->row);*/
		if(!$this->bFunction && $data !== null)
		{
			if($this->prop === null)
			{
			if($data instanceof s\Data)
			{
				if($data->isCollection())
				$s = $sd->getCollection($this->sName);
				else
				$s = $data->getValue();
			}
			else
				$s = $data;
			}
			else
			{
			
			$s = $this->prop->applyAsProp($data,$scope,!$this->isKeyword);
			}
		}
		elseif($this->bFunction && $data !== null && is_callable ($data->getValue()))
		{
			$fn = $data->getValue();
			$s = call_user_func_array($fn,$this->generateParams($scope));
		}
		elseif($scope->simplate)
		{
			
			$cb = $scope->simplate->getCallback();
			if($cb && method_exists($cb,$this->sName))
			{
			$cb->scope = $scope;
			$s = $this->applyAsCallback($cb);
			}
		}
		else
		{
			throw new \Exception('Core error : property simplate not defined in s\Data, var: '.$this->sName);
		}
		return $s;
		}
		private function applyAsCallback(s\Callback $sc)
		{
		$a = array();
		for($i=0;$i<sizeof($this->aParam);$i++)
			$a[] = $this->aParam[$i]->generate($sc->scope);
		$o = call_user_func_array(array($sc,$this->sName),$a);
		if(is_array($o))
			return $o;
		//$o = (object)$o;
		if(is_object($o))
			if(is_null($this->prop))
			return $o;
			else
			return $this->prop->applyAsProp($o,$sc->scope);
		return $o;
	}
	/**
	 * Apply var as property
	 *
	 * @param mixed $o
	 *
	 */
	public function applyAsProp($o,s\Scope $scope = null, $isDataObject = false)
	{
		if($o instanceof s\Data && is_null($this->prop) && $o->isCollection($this->sName) )
			return $o->getCollection($this->sName);//Return collection if recursion
		
		if($isDataObject && $o instanceof s\Data)
			$o = $o->getValue();
		if($o instanceof s\Data && $this->bFunction && !method_exists($o, $this->sName))
			$o = $o->getValue();
		
		if(is_array($o))
			$o = (object)$o;
		if(is_object($o))
			if($this->bFunction)
			{
				if(is_callable(array($o,$this->sName))) //Method
					$r = call_user_func_array(array($o,$this->sName),$this->generateParams($scope));
				elseif(is_callable($o->{$this->sName})) //Closure
					$r = call_user_func_array($o->{$this->sName},$this->generateParams($scope));
				else
					return '';
				if(!is_null($this->prop))
					$r = $r ? $this->prop->applyAsProp($r,$scope) : null;
				return $r;
			}
			else if(is_null($this->prop))
				return isset($o->{$this->sName}) ? $o->{$this->sName} : null;
			else
				return isset($o->{$this->sName}) ? $this->prop->applyAsProp($o->{$this->sName},$scope) : null;

		return null;
	}

	/**
	 * Apply this as filter
	 *
	 * @param string $value
	 * @return string
	 */
	public function applyAsFilter(s\Filters $f,$value, s\Scope $scope)
	{
		if($f !== null && $f->exists($this->sName))
		{
			return $f->call($this->sName,array_merge(array($value),$this->generateParams($scope)));
			//call_user_func_array(array($f,$this->sName),array_merge(array($value),$this->generateParams($sd)));
		}
		return $value;
	}
	/**
	 * Generate params
	 *
	 * @param s\Data $sd
	 * @return array
	 */
	private function generateParams(s\Scope $scope = null)
	{
		$a = array();
		$len = sizeof($this->aParam);
		for($i=0;$i<$len;$i++)
			$a[] = $this->aParam[$i]->generate($scope);
		return $a;
	}
	
	public function __construct(Token $parent)
	{
		parent::__construct($parent);
	}
	public function __destruct()
	{
		parent::__destruct();
		if(!empty($this->aParam))
			foreach($this->aParam as $o)
			$o->destroy();
		!empty($this->prop) && $this->prop->destroy();
		unset(
			$this->sName,
			$this->prop,
			$this->aParam
		);

	}
	public function __sleep()
	{
		return array_merge(array("sName","nLevel","prop","bReverse","bBoolean","bFunction","aParam"),parent::__sleep());
	}
}
