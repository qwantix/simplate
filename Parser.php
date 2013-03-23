<?php
namespace simplate;

/**
 * @author Brice Dauzats
 */

class Parser
{
    private $schemes = array();
    private $simplate;
    public $scheme;
    public function __construct(\Simplate $simplate)
    {
	$this->simplate = $simplate;
	$this->pushScheme('html');
    }
    public function pushScheme($name)
    {
	$sc = Scheme::Get($name);
	$this->schemes[] = $sc;
	$this->scheme = $sc;
    }
    public function popScheme()
    {
	array_pop($this->schemes);
	$this->scheme = $this->schemes[count($this->schemes)-1];
    }
    public function getSimplate()
    {
	return $this->simplate;
    }
    public function parse()
    {
	$o = new token\Block();
	$o->setParser($this);
	$o->parse($this->simplate->getContent(),0);
	return $o;
    }
    
    public function getLineAtIndex($index)
    {
	    return count(explode("\n",substr($this->simplate->getContent(),0,$index)));
    }
    public function getIndexInLine($index)
    {
	    $l = explode("\n",substr($this->simplate->getContent(),0,$index));
	    if(sizeof($l) > 0)
		    return strlen($l[sizeof($l)-1])-sizeof($l);
	    else
		    return 0;
    }
}
