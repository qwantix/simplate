<?php

/**
 *  
 *                  
	 ____  _                 _       _  
	/ ___|(_)_ __ ___  _ __ | | __ _| |_ ___ 
	\___ \| | '_ ` _ \| '_ \| |/ _` | __/ _ \
	 ___) | | | | | | | |_) | | (_| | ||  __/
	|____/|_|_| |_| |_| .__/|_|\__,_|\__\___
	                  |_|ENGINE {By QwantiX}
			Fast & Powerful Template Engine
 ----------------------------------------------------
	Copyright 2008. Brice Dauzats, 01/05/2008 
	
	qwantix {"at"} gmail.com
	
 *  
 * 
 */
/**************************************
	REQUIRED CLASSES
***************************************/

#Debug
#require_once 'debug.php';
# Util class
require_once 'Util.php';
require_once 'Infos.php';

# Datas Collection class
require_once 'Data.php';
require_once 'Scope.php';

# Callback & Filters class
require_once 'Callback.php';
require_once 'Filters.php';

# Tokens managers
require_once 'IToken.php';

require_once 'token/Token.php';

require_once 'token/Value.php';
require_once 'token/Variable.php';
require_once 'token/Expression.php';
require_once 'token/Section.php';
require_once 'token/Block.php';
require_once 'token/Tag.php';

require_once 'Parser.php';
require_once 'Scheme.php';

use simplate as s;
use simplate\token as t;	

////////////////////////////////////////

class Simplate
{
	public static $VERSION = 2.1;
	
	public static $EnabledCache = false;
	/**
	 * Filename
	 *
	 * @var string
	 */
	private $filename;
	/**
	 * Internal template name
	 *
	 * @var string
	 */
	private $internalFilename;
	private $content;
	private $oSimplateData;
	private $oSimplateCallback;
	private $oSimplateFilters;
	private $oScope;
	/**
	 * Path of cache
	 *
	 * @var string
	 */
	private $pathCache;
	
	public $enabledCache;
	
	public $infos;
	
	public function open($filename)
	{
		$this->content = file_get_contents($filename);
		$this->filename = $filename;
		$this->internalFilename = 'file-'.md5($filename);
	}
	public function setContent($content)
	{
		$this->content = $content;
		$this->internalFilename = 'content-'.md5($content);
	}
	public function getContent()
	{
	    return $this->content;
	}
	public function setCachePath($path, $create = false)
	{
		if(!$this->enabledCache)
			return;
		if(file_exists($path))
			if(is_writable($path))
				$this->pathCache = $path;
			else
				throw new ErrorException("$path not writable!");
		else if($create)
		{
		    if(!mkdir($path,0777,true))
			throw new ErrorException("Unable to create cache dir $path");
		    $this->pathCache = $path;
		}
		else
			throw new ErrorException("$path doesn't' exist!");
	}
	public function setData(s\Data $sd)
	{
		$this->oSimplateData = $sd;
		$this->oSimplateData->setSimplate($this);
		$aEnv = array(
			'this'=> $this
		);
		//Var Simplate
		$this->oSimplateData->Simplate = (object)$aEnv;
	}
	public function getData()
	{
		return $this->oSimplateData;
	}
	public function setCallback(s\Callback $sc)
	{
		$this->oSimplateCallback = $sc;
	}
	public function getCallback()
	{
		return $this->oSimplateCallback;
	}
	public function setFilters(s\Filters $sf)
	{
		$this->oSimplateFilters = $sf;
	}
	public function getFilters()
	{
		return $this->oSimplateFilters;
	}
	public function setScope(s\Scope $s)
	{
		$this->oScope = $s;
	}
	public function getScope()
	{
		return $this->oScope;
	}
	public function generate(s\Data $sd = null)
	{
		if($sd !== null)
			$this->setData($sd);
		/*if(empty($this->pathCache))
			$this->setCachePath(dirname(__FILE__).'/../cache');*/
		
		if($this->enabledCache)
		{
			$cacheFilename = $this->getCacheFilename();
			if(!file_exists($cacheFilename) 
				|| filemtime($this->filename) > filemtime($cacheFilename))
				{
					$oRoot = $this->parse();
					$this->setCache($oRoot);
				}
				else
					$oRoot = $this->getCache();
		}
		else
			$oRoot = $this->parse();
	
		//Generate...
		$ic = s\Util::InitChrono();
		if(!$this->oScope)
			$this->oScope = new s\Scope($this->oSimplateData);

		$this->oScope->simplate = $this;
		$s = $oRoot->generate($this->oScope);
		$this->infos->generation_time = s\Util::GetChrono($ic);
		$oRoot->destroy();
		unset($oRoot);
		return $s;
	}
	private function parse()
	{
		$ic = s\Util::InitChrono();
		$parser = new \simplate\Parser($this);
		$o = $parser->parse();
		//$o = $this->parseBlock($this->content);
		$this->infos->parsing_time = s\Util::GetChrono($ic);
		
		return $o;
	}
	/*private function parseBlock($content)
	{
		$pos = 0;
		$aBlock = array();
		
		//Consider template as Block
		$o = new t\Block();
		$o->setSimplate($this);
		$o->parse($content,$pos);
		return $o;

	}*/
	
	
	public function getFilename()
	{
		return $this->filename;
	}
	public function getLiveGenerationTime()
	{
		return s\Util::GetChrono();
	}
	public function getInfos()
	{
		return $this->infos;
	}
	public function __construct($filename = null)
	{
		$this->oSimplateCallback = new s\Callback();
		$this->oSimplateFilters = new s\Filters();
		$this->infos = new s\Infos($this);
		$this->enabledCache = self::$EnabledCache;
		if(!empty($filename))
			$this->open($filename);
		
	}
	
	public function __destruct()
	{
		s\Infos::Remove($this);
		unset(
			$this->filename,
			$this->internalFilename,
			$this->content,
			$this->oSimplateData,
			$this->oSimplateCallback,
			$this->oSimplateFilters,
			$this->pathCache,
			$this->infos
		);
	}
	
	public function destroy()
	{
		$this->__destruct();
		unset($this);
	}
	///////////////////////////////////
	//		Cache
	///////////////////////////////////
	private function getCacheFilename()
	{
		$path = "";
		if(empty($this->pathCache))
		{
			$dir = realpath (dirname($this->filename));
			if(1 || is_writable($dir))
			{
				if(!file_exists($dir.'/.simplatecache'))
				{
					if(!mkdir($dir.'/.simplatecache'))
						throw new Exception("Unable to create cache directory in directory '$dir'");
				}
				$path = $dir.'/.simplatecache';
			}
			else
				throw new Exception("Unable to create cache in directory '$dir', access denied");
		}
		else
			$path = $this->pathCache;
		if($path[strlen($path)-1] != "/")
			$path .= '/';
		return $path.$this->internalFilename;
	}
	private function setCache($data)
	{
		$ic = s\Util::InitChrono();
		file_put_contents($this->getCacheFilename(),serialize($data));
		$this->infos->setting_cache_time = s\Util::GetChrono($ic);
	}
	private function getCache()
	{
		$ic = s\Util::InitChrono();
		$o = unserialize(file_get_contents($this->getCacheFilename()));
		$this->infos->getting_cache_time = s\Util::GetChrono($ic);
		return $o;
	}
	
	public function __sleep()
	{
		return array("filename","internalFilename","pathCache","enabledCache","infos");
	}
	
}

