<?php
namespace simplate;
class Callback
{
	public $simplateData; 
	public $scope;

	public function in($value, $array)
	{
		return is_array($array) ? in_array($value, $array) : $value == $array;
	}
}
