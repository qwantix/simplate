<?php
namespace simplate;

/**
 *
 * @author Brice Dauzats
 */
abstract class Scheme
{
    static private $schemes = array();
    static public function Register($name,Scheme $sc)
    {
	self::$schemes[$name] = $sc;
    }
    static public function Get($name)
    {
	return self::$schemes[$name];
    }
    
    public $sectionStart = '<!--';
    public $sectionEnd = '-->';
    
    public $tagStart = '{';
    public $tagEnd = '}';
    public $tagConditional = '?';
}

class HtmlScheme extends Scheme
{
    
}
class CssScheme extends Scheme
{
    
}
class JavascriptScheme extends Scheme
{
    
}

Scheme::Register('html', new HtmlScheme);
Scheme::Register('css', new CssScheme);
Scheme::Register('javascript', new JavascriptScheme);