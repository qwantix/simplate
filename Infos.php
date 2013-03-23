<?php
namespace simplate;

class Infos
{
	private static $_simplateCollection = array();
	public static function Add(\Simplate $inst)
	{
		self::$_simplateCollection[] = $inst;
	}
	public static function Remove(\Simplate $inst)
	{
		// Erreur comparaison d'objet
		//if(($key = array_search($inst,self::$_simplateCollection)) !== false)
			//array_splice(self::$_simplateCollection,$key,1);
	}
	public static function Dump()
	{
	   foreach(self::$_simplateCollection as $inst)
	   {
			echo "<pre>";
			$inf = $inst->getInfos();
			echo $inst->getFilename();
			echo "   - parsing : {$inf->parsing_time} ms";
			echo "   - generation : {$inf->generation_time} ms";

			echo "</pre>";
	   }
	}
	public static function GetAll()
	{
	   $infos = array();
	   foreach(self::$_simplateCollection as $inst)
	      $infos[$inst->getFilename()] = $inst->getInfos();
		return $infos;
	}
	public $parsing_time = 0;
	public $generation_time = 0;
	
	public $setting_cache_time = 0;
	public $getting_cache_time = 0;
	
	public function __construct(\Simplate $inst)
	{
		self::Add($inst);
	}
}
