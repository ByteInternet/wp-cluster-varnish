<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Manager used for singleton instances
 */

abstract class XLII_Cache_Singleton
{
	static private $_instance = array();
	
	/**
	 * Enforce protected constructor.
	 */
	protected function __construct() { }
	
	/**
	 * Return the singleton instance
	 * 
	 * @return	Pie_Singleton
	 */
	static public function getInstance()
	{
		$class = strtolower(get_called_class());
		
		if($class == __CLASS__)
			wp_die('Unable to call protected method getInstance on ' . __CLASS__);
		
		if(!isset(self::$_instance[$class]))
			self::$_instance[$class] = new $class();
		
		return self::$_instance[$class];
	}
	
	/**
	 * Initialize the module
	 * 
	 * @return	Pie_Singleton
	 */
	static public function init()
	{
		return call_user_func(array(get_called_class(), 'getInstance'));
	}
}