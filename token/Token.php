<?php
namespace simplate\token;
use simplate as s;

abstract class Token
{
	private static $NextTokenId = 0;
	private $token_id;
	protected $parent;
	protected $parser;
	private $level = 0;
	
	public function getLevel()
	{
		return $this->level;
	}
	
	public function parse($content,$pos = 0)
	{
		//Defined in children
	}
	public function generate(s\Scope $sd)
	{
		//Defined in children
	}
	
	public function setParser(s\Parser $parser)
	{
		$this->parser = $parser;
	}
	
	public function __construct(Token $parent = null)
	{
		$this->parent = $parent;
		if($parent)
		{
			$this->level = $parent->level+1;
			$this->parser = $parent->parser;
		}
		$this->token_id = self::$NextTokenId++;
	}
	
	//--------------------------------------
	// Errors
	//--------------------------------------
	/**
	 * Parse error
	 *
	 * @var ParserError
	 */
	private $parserError;
	/**
	 * Return parse error
	 *
	 * @param ParserError $err
	 * @return -1
	 */
	protected function returnParserError(ParserError $err)
	{
		$this->parserError = $err;
		
		if($err->getErrorLevel()>0)
		{
			$errmsg = $err->getErrorMessage();
			$msg = "Simplate parse error";
			if(!empty($errmsg))
				$msg .= ', '.$errmsg;
			
			if($this->parser->getIndexInLine($err->getErrorIndex()) > 0)
				$msg .= ', at char '.$this->parser->getIndexInLine($err->getErrorIndex());
			
			throw new \ErrorException($msg,0,$err->getErrorLevel(),
										$this->parser->getSimplate()->getFilename(),$this->parser->getLineAtIndex($err->getErrorIndex()));
		}
		return -1;
	}
	/**
	 * Return generate error
	 *
	 * @param ParserError $err
	 * @return -1
	 */
	protected function returnGenerationError($message)
	{
		throw new \ErrorException("Simplate generation error, ".$message,0,1,
										$this->parser->getSimplate()->getFilename());
		return -1;
	}
	/**
	 * Return not found error
	 *
	 * @param int index
	 * @return -1;
	 */
	protected function returnNotFound($index = -1)
	{
		return $this->returnParserError(new ParserError($index,ParserError::TYPE_NOT_FOUND));
	}
	protected function returnMissing($index,$message)
	{
		return $this->returnParserError(new ParserError($index,ParserError::TYPE_MISSING,1,$message));
	}
	protected function returnParseError($index,$message)
	{
		return $this->returnParserError(new ParserError($index,ParserError::TYPE_ERROR,1,$message));
	}
	
	public function getParseError()
	{
		return $this->parserError;
	}
	public function clearError()
	{
		$this->parserError = null;
	}
	
	private $_destroyed = false;
	public function destroy()
	{
		if($this->_destroyed)
			return;
		
		$this->__destruct();
		
		
	}
	
	public function __destruct()
	{
		$this->_destroyed = true;
		unset($this->parent,$this->parser,$this->parserError);
	}
	
	public function __sleep()
	{
		return array(
			"\0simplate\\token\\Token\0token_id",
			"\0simplate\\token\\Token\0level",
			'parent'
		);
		//return array("\0simplate\Token\0token_id","\0Token\0level","parent","simplate");
	}
	
	
}


class ParserError
{
	const TYPE_NOT_FOUND = "not found";
	const TYPE_MISSING = "missing";
	const TYPE_ERROR = "error";
	
	const LEVEL_NONE = 0;
	const LEVEL_NOTICE = 1;
	const LEVEL_WARN = 2;
	const LEVEL_FATAL = 3;
	
	private $currentIndex = -1;
	private $type = "";
	private $message = "";
	private $level = 0;
	
	public function getErrorIndex()
	{
		return $this->currentIndex;
	}
	public function getErrorType()
	{
		return $this->type;
	}
	public function getErrorMessage()
	{
		return $this->message;
	}
	public function getErrorLevel()
	{
		return $this->level;
	}
	
	function __construct($currentIndex,$type = "", $level = 0, $message = "")
	{
		$this->currentIndex = $currentIndex;
		$this->type = $type;
		$this->level = $level;
		$this->message = $message;
	}
}
