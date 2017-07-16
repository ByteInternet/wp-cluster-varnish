<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Abstract cache class
 */

abstract class XLII_Cache
{
	static private $_instance;
	static private $_queue;
	static private $_flush;
	
	/**
	 * Generic static methods used as calltrough
	 * 
	 * @param	string $method The method to call on the cache object.
	 * @param	array $arguments An array containing arguments passed to the cache object.
	 * @return	void
	 */
	static public function __callStatic($method, $arguments)
	{
		$obj = self::getInstance();
		
		return call_user_func_array(array($obj, $method), $arguments);
	}
	
	/**
	 * Delete the page cache.
	 * 
	 * @param	string|array $key The key the cache attribute is referred by.
	 * @param	bool $force = false Indicate wether to force flush the specified key.
	 * @return	bool
	 */ 
	static public function delete($key, $force = false)
	{
		if(self::$_flush || $force)
			return self::getInstance()->delete($key);
			
		if(self::$_queue !== true)
		{
			if(is_array($key))
				self::$_queue = array_merge(self::$_queue, $key);
			else
				self::$_queue[] = $key;
				
			if(($max = XLII_Cache_Manager::option('general.flushing')) && $max > 0)
			{
				if(count(self::$_queue) >= $max)
					self::$_queue = true;
			}
		}
		
		return true;
	}
	
	/**
	 * Flush the entire cache.
	 * 
	 * @param	bool $force = false Indicate wether to force flush
	 * @return	bool
	 */ 
	static public function flush($force = false)
	{
		// -- Cache has already been flushed succesfully
		if(!$force && self::$_queue === true)
			return true;
		
		if(!self::getInstance()->flush())
			return false;
		
		return (self::$_queue = true);
	}
	
	/**
	 * Called upon destructing the class, flush all registered urls
	 * 
	 * @return	bool
	 */
	static public function flushQueue()
	{
		if(!($flush = self::getQueue()) || !$obj = self::getInstance())
			return true;
		
		self::$_queue = array();	
		
		// Full flushing is executed directly, so return true if succesfull
		if($flush === true)
			return true; 
		
		else 
			return $obj->delete($flush);
	}
	
	/**
	 * Return the active cache manager instance.
	 * 
	 * @return	XLII_Cache_Instance	
	 */
	static public function getInstance()
	{
		if(self::$_instance === null)
		{
			if(($class = XLII_Cache_Manager::option('engine.type')) && class_exists($class))
				self::$_instance = call_user_func(array($class, 'getInstance'));
			else
				self::$_instance = XLII_Cache_Varnish::getInstance();
		}
		
		return self::$_instance;
	}
	
	/**
	 * Return a listing of all cache engines
	 * 
	 * @param	enum $filter = 'all' Optional filter to narrow the engines down (all|availible).
	 * @return	array
	 */
	static public function getEngines($filter = 'all')
	{
		$engines = array();
		$engines = apply_filters('cache_engines', $engines);
	
		if($filter == 'availible' && (!defined('CACHE_DEBUG') || !CACHE_DEBUG))
		{
			foreach($engines as $key => $engine)
			{
				if($engine->availible() === false)
					unset($engines[$key]);
			}
		}
		
		return $engines;
	}
	
	/**
	 * Return the generated queue.
	 * 
	 * @return	array|true
	 */
	static public function getQueue()
	{
		return self::$_queue === true ? true : array_unique(self::$_queue);
	}

	/**
	 * Initialize the cache manager
	 * 
	 * @access	private
	 */
	static public function init()
	{
		self::$_queue = array();
		self::$_flush = defined('CACHE_QUEUE') && !CACHE_QUEUE;
		
		register_shutdown_function(array(__CLASS__, 'flushQueue'));
	}
	
	/**
	 * Returns wether the current cache is running on the specified engine
	 * 
	 * @param	enum $engine The engine to check for.
	 * @return	bool
	 */
	static public function is($engine)
	{
		if($active = XLII_Cache_Manager::option('engine.type'))
			return stripos($active, $engine) !== false;
		else
			return false;
	}
}