<?php
namespace simplate;

class Filters
{
	private $filters = array();
	public function register($name, $handler)
	{
		$this->filters[$name] = $handler;
		return $this;
	}
	public function exists($name)
	{
		return isset($this->filters[$name]);
	}
	public function call($name, $arguments)
	{
		if(isset($this->filters[$name]))
			return call_user_func_array($this->filters[$name], $arguments);
		return null;
	}
	
	public function __construct()
	{
		$this->register('date', function($value,$format="d/m/Y"){
			$timestamp = strtotime($value);
			if($timestamp === false && is_numeric($value))
				$timestamp = $value;
			if($timestamp==0)
				return '';
			return date($format,$timestamp);
		});
		
		$this->register('currency', function($value,$symbol = ' €'){
			return number_format(floatval($value),2,'.',' ').$symbol;
		});
		
		$this->register('round', function($value,$precision = 0){
			return round($value,$precision);
		});
		
		$this->register('htmlentities', function($value){
			return htmlentities( mb_check_encoding($value,"UTF-8") ? $value : utf8_encode($value), \ENT_COMPAT, 'UTF-8');
		});
		$this->register('urlencode', function($value){
			return urlencode($value);
		});

		$this->register('dump', function($value){
			var_dump($value);
			return $value;
		});
		
		$this->register('json', function($value){
			return json_encode($value);
		});
		
		$this->register('sd', function($value, $parent){
			$sd = new Data($parent);
			$sd->import($value);
			return $sd;
		});
		
		$this->register('formatBytes', function($size){
			$units = array(' B', ' KB', ' MB', ' GB', ' TB');
			for ($i = 0; $size >= 1024 && $i < 4; $i++)
				$size /= 1024;
			return round($size, 2) . $units[$i];
		});
		
		$this->register('join', function($value, $sep){
			if($value instanceof Data)
				$value = $value->val();
			return is_array($value) ? implode($sep, $value) : $value;
		});

		$this->register('checked', function($value){
			return $value ? 'checked="checked"':'';
		});
		$this->register('selected', function($value){
			return $value ? 'selected="selected"':'';
		});
		$this->register('lowercase', function($value){
			return strtolower($value);
		});
		
	}

}
