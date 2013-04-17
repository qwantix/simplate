<?php
namespace simplate;

/**
 * @author Brice Dauzats
 */
class Scope
{
	public $parent;
	public $data;
	public $index;
	public $simplate;
	
	public function sub(Data $data = null, $index = 0)
	{
		$o = new self($data == null ? $this->data:$data, $this);
		$o->index = $index;
		return $o;
	}
	public function pop()
	{
		return $this->parent;
	}
	public function parent($upTo = 0)
	{
		return $upTo <= 0 ? $this->parent : $this->parent($upTo-1);
	}
	public function top()
	{
		return $this->parent ? $this->parent->top() : $this;
	}
	public function __construct(Data $data, Scope $parent = null)
	{
		$this->data = $data;
		$this->parent = $parent;
		if($parent !== null)
		{
			$this->simplate = $parent->simplate;
		}
	}
}
