<?php
namespace simplate;

class Data
{
	public $aDatas;
	/*
	 * This follow property are public for accelerate
	 */
	private $_parent;
	/*private $_top;
	private $_simplate;
	private $_this;
	private $_scope;*/
	
	private $_value = "";
	
	private $_name;
	private $_index;
	
	private function &resolveName($name,$i = null,$autoIncrement = false)
	{
		/*if($i === null)
			$i = 0;*/
		if(!array_key_exists($name,$this->aDatas))
			$this->aDatas[$name] = array();
		if($i < 0)
		{
			$n = count($this->aDatas[$name]);
			if(-$n>$i)
				throw new Exception("Offset error in Simplate Data, try to add value of tag '$name' at offset $i");
			$i = count($this->aDatas[$name]) + $i;
		}
		if($i === null)
			$i = $autoIncrement?count($this->aDatas[$name]):0;
		if(!array_key_exists($i,$this->aDatas[$name]))
		{
			$this->aDatas[$name][$i] =  new Data($this,$name,$i);
		}	
		return $this->aDatas[$name][$i];
		
	}
	public function count($name)
	{
		return sizeof($this->aDatas[$name]);
	}
	/**
	 * Import data into simplateData
	 *
	 * @param mixed $oFrom
	 */
	public function import($oFrom)
	{
		if(is_object($oFrom) && get_class($oFrom) == 'SimpleXMLElement')
			$this->importXML($oFrom);
		elseif(is_array($oFrom))
			$this->importArray($oFrom);
		elseif(is_object($oFrom))
			$this->importObject($oFrom);
		else
		    $this->_value = $oFrom;
	}
	public function importSimplateData(Data $sd)
	{
		$this->aDatas = array_merge($this->aDatas,$sd->aDatas);
	}
	/**
	 * Import datas from SimpleXML Object
	 *
	 * @param SimpleXMLElement $oFrom
	 */
	public function importXML(\SimpleXMLElement $oFrom)
	{
		//Import attribute
		foreach($oFrom->attributes() as $k=>$v)
		{
			$this->__set($k,(string)$v);
		}
		
		//Import children
		$aIndex = array();
		foreach($oFrom->children() as $k=>$v)
		{
			if(sizeof((array)$v->children()) === 0 && sizeof((array)$v->attributes()) === 0)
			{
				//Set as var
				$this->__set($k,(string)$v);
				continue;
			}
			if(!array_key_exists($k,$aIndex))
				$aIndex[$k] = 0;
			else
				$aIndex[$k]++;
			$oSD = $this->__call($k,array($aIndex[$k]));
			$oSD->import($v);
			if(isset($v['id']))
			{
				$this->__set('_'.$v['id'],null);
				$this->{'_'.$v['id']}->import($v);
			}
		}
	}
	/**
	 * Import each field from object to var of simplate data
	 *
	 * @param object $object
	 */
	public function importObject($object)
	{
		foreach($object as $property => $value)
		{
			$o = $this->aDatas[$property][0] =  new Data($this,$property);
			$o->_value = $value;
		}
		$this->_value = $object;
	}
	/**
	 * Import from object collection, generaly DB result
	 *
	 * @param array $objectCollection
	 */
	public function importFromObjectCollection(array $objectCollection)
	{
		$len = sizeof($objectCollection);
		$baseNode = $this->parent;
		for($i = 0; $i<$len; $i++)
			$baseNode->aDatas[$this->_name][$i]->importObject($objectCollection[$i]);
	}
	/**
	 * Import an array
	 *
	 * @param array $array
	 */
	public function importArray(array $array)
	{
		$a = $array;
		$o = null;
		foreach($a as $k=>$v)
		{
			
			if(is_array($v))
				foreach($v as $sk=>$sv)
				{
					$o = $this->resolveName($k,$sk);
					if(is_array($sv))
						$o->importArray($sv);
					else
						$o->_value = $sv;
				}
			else
			{
				$this->$k = $v;
			}
		}
	}

	public function importObjectCollection($name, array $a)
	{
		$len = sizeof($a);
		for($i = 0; $i<$len; $i++)
		{
			$this->aDatas[$name][$i] = new Data($this,$name,$i);
         $this->aDatas[$name][$i]->importObject($a[$i]);
		}
	}
	public function importQueryResult($name, $r)
	{
		$this->importObjectCollection($name, $r->toArray());
	}
	public function getValue()
	{
		return $this->_value;
	}
	public function val()
	{
	    return $this->_value;
	}
	public function getCollection($name)
	{
		return $this->aDatas[$name];
	}
	/*public function getTop()
	{
		return $this->top;
	}
	public function getParent()
	{
		return $this->parent;
	}
	public function getSimplate()
	{
		return $this->simplate;
	}*/
	public function getName()
	{
		return $this->_name;	
	}
	/*public function getIndex()
	{
		return $this->_index;	
	}*/
	public function getByName($name, $includeKeyword = true)
	{
		/*if($includeKeyword && $this->isKeyword($name))
			return $this->{'_'.$name};
		else */if(array_key_exists($name,$this->aDatas) && array_key_exists(0,$this->aDatas[$name]))
			return $this->aDatas[$name][0];
		return null;
	}
	public function isCollection($prop = null)
	{
		/*if($this->_name == "pagination")
			var_dump($this->aDatas) && exit();*/
		return empty($prop)?sizeof($this->aDatas)>0:array_key_exists($prop, $this->aDatas) && sizeof($this->aDatas[$prop])>1;
	}
	public function dump($return = false)
	{
		$o = array();
		foreach($this->aDatas as $k=>$v)
		{
			if($this->$k->isCollection())
			{
				$a = array();
				foreach($v as $k2=>$v2)
					$a[$k2] = $v2->dump(true);
				$o[$k] = $a;
			}
			else
				$o[$k] = $v[0]->getValue();
		}
		if($return)
			return $o;
		var_dump($o);
		
	}
	
	public function __isset($name)
	{
		return /*$this->isKeyword($name) || */array_key_exists($name,$this->aDatas) ||
				 (is_object($this->_value) && isset($this->_value->$name) ) ||
				 (is_array($this->_value) && isset($this->_value[$name]) );
	}
	public function __call($name,$args)
	{
		return $this->resolveName($name,count($args)?$args[0]:null,true);
	}
	public function __set($name,$value)
	{
	    /*if($this->isKeyword($name))
		return;*/
		$v = &$this->resolveName($name);
		$v->_value = $value;
	}
	
	public function __get($name)
	{	
		/*if($this->isKeyword($name))
		{
			return $this->{'_'.$name};
		}
		else*/
		{
			if(is_object($this->_value) || is_array($this->_value))
			{
				return is_object($this->_value)?$this->_value->$name:$this->_value[$name];
			}
			return $this->resolveName($name);
		}
	}
	
	public function __construct(Data $parent = null,$name = null, $index = 0)
	{
		$this->aDatas = array();
		$this->_parent = $parent;
		$this->_name = $name;
		$this->_index = $index;
		//$this->_this = $this;
		/*if($parent != null)
		{
			$this->_simplate = &$parent->_simplate;
			$this->_top = &$parent->_top;
		}
		else
		{
			$this->_top = &$this;
		}*/
	}
	public function destroy()
	{
		$this->__destruct();
	}
	public function __destruct()
	{
		if(!property_exists($this,"aDatas"))
			return; //Already destroyed!
		if(!empty($this->aDatas))
			foreach($this->aDatas as $a)
				foreach($a as $o)
					$o->destroy();
		
		unset(
			//$this->aDatas,
			$this->_parent,
			$this->_top,
			$this->_simplate,
			$this->_this,
			$this->_value,
			$this->_name,
			$this->_index
		);
	}
	public function __toString()
	{
		return (string)$this->_value;
	}
	
	/**
	 * Method used only by method generate of Simplate
	 * @internal 
	 * @param Simplate $o
	 */
	public function setSimplate(\Simplate $o)
	{
		$this->_simplate = $o;
	}
	
	/*public function isKeyword($name)
	{
		switch($name)
		{
			case 'parent':
			case 'simplate':
			case 'this':
			case 'top':
			case 'scope':
				return true;
				break;
			default:
				return false;
		}
	}*/
}
