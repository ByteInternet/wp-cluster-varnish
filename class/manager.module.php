<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Setup the default cache extenstion 
 */

class XLII_Cache_Manager extends XLII_Cache_Singleton
{
	static private $_options;
	
	const REQUIRED_CAP = 'administrator';
	const OPTION_NAME = 'xlii_cache';
	
	const DEFAULT_EXPIRATION = 2592000; // 30 days
	
	private $_statuscode = 200;
	
	/**
	 * Setup the default manager object
	 */
	protected function __construct()
	{
		spl_autoload_register(array(&$this, '__autoload'));
	}
	
	/**
	 * Autoloader used to load cache classes dynamicly.
	 * 
	 * @access	private
	 * @param	string $class The name of the class being loaded.
	 * @return	bool
	 */
	public function __autoload($class)
	{
		if(stripos($class, 'xlii_cache') === false)
			return false;
			
		if($file = str_replace('xlii_cache', '', strtolower($class)))
		{
			$file = explode('_', trim($file, '_'));
			
			if(count($file) == 3)
				$file = $file[2] . '/' . $file[0] . '/class.' . $file[1] . '.php';
			else if(count($file) == 2)
				$file = $file[1] . '/class.' . $file[0] . '.php';
			else
				$file = 'cache/class.' . $file[0] . '.php';
		}
		else
		{
			$file = 'cache/class.abstract.php';
		}
		
		if(file_exists($file = dirname(__FILE__)  . '/' . $file) && require_once $file)
			return class_exists($class);
		else
			return false;
	}
	
	/**
	 * Action to preform upon enablement of the plugin
	 * 
	 * @access	private
	 */
	public function __activate()
	{
		$this->_writeConfig(array(), get_option(self::OPTION_NAME));
	}
	
	/**
	 * Action to preform upon disablement of the plugin
	 * 
	 * @access	private
	 */
	public function __deactivate()
	{
		$this->_writeConfig(array(), array(), false);
	}
	
	/**
	 * Keep track of the flushed pages
	 * 
	 * @access	private
	 */ 
	public function __shutdown()
	{
		if(!$queue = XLII_Cache::getQueue())
			return;
			
		if(!$user = get_current_user_id())
			return;
			
		if($data = get_option(self::OPTION_NAME . '_' . $user))
		{
			if(!is_array($data))
			{	
				$queue = $data;
			}
			else if(is_array($queue))
			{
				$queue = array_unique(array_merge($data, $queue));
		
				asort($queue);
			}
		}
		
		update_option(self::OPTION_NAME . '_' . $user, $queue);
	}
	
	/**
	 * Keep track of a changing status code
	 * 
	 * @access	private
	 * @param	string $status The generated status header
	 * @param	int $code The new status code.
	 * @return	string
	 */
	public function _changeStatuscode($status, $code)
	{
		if(!$this->hasStatuscodeMatch($this->_statuscode = $code))
			$this->_headers();
		
		return $status;
	}
	
	/**
	 * Returns wether the user contains a cookie which is excluded from caching
	 * 
	 * @return	bool
	 */
	public function hasCookieMatch()
	{
		if(!$list = XLII_Cache_Manager::option('options.cookies'))
			return false;
			
		if(array_intersect($list, $match = array_keys($_COOKIE)))
			return true;
		
		foreach($list as $regex)
		{
			foreach($match as $key)
			{
				if(preg_match('#' . preg_quote($regex, '#') . '#', $key))
					return true;
			}
		}
		
		return false;
	}
	
	
	/**
	 * Returns wether the user accessed a page which is excluded from caching
	 * 
	 * @return	bool
	 */
	public function hasPageMatch()
	{
		if(!$list = XLII_Cache_Manager::option('options.exclude'))
			return false;
		
		if(in_array($match = add_query_arg(null, null), $list))
			return true;
		
		foreach($list as $regex)
		{
			if(preg_match('#' . preg_quote($regex, '#') . '#', $match))
				return true;
		}
		
		return false;
	}
	
	/**
	 * Returns wether the active statuscode expected the allowed codes.
	 * 
	 * @param	int $code = null The status code to redirect with.
	 * @return	bool
	 */
	public function hasStatuscodeMatch($code = null)
	{
		if(!$list = XLII_Cache_Manager::option('options.statuscode'))
			return true;
		
		// -- extract code from page
		if(!$code)
			$code = $this->_statuscode;
		
		return in_array($code, (array)$list);
	}
	
	/**
	 * Print the caching headers in some cases
	 * 
	 * @access	private
	 */
	public function _headers()
	{
		$headers = array();
		
		if(!isset($GLOBALS['wp_query']))
			return;
		
		if(is_search())
			$headers = wp_get_nocache_headers();
		
		else if(is_singular() && ($obj = get_queried_object()) && !empty($obj->post_password))
			$headers = wp_get_nocache_headers();
		
		else if(is_user_logged_in())
			$headers = wp_get_nocache_headers();
		
		else if(!empty($_POST))
			$headers = wp_get_nocache_headers();
			
		else if($this->hasCookieMatch())
			$headers = wp_get_nocache_headers();
		
		else if($this->hasPageMatch())
			$headers = wp_get_nocache_headers();
	
		else if(!$this->hasStatuscodeMatch())
			$headers = wp_get_nocache_headers();
	
		else
		{
			if(XLII_Cache::is('varnish'))
			{
				$expire = XLII_Cache_Manager::option('general.expire');
				$expire = $expire ? $expire : self::DEFAULT_EXPIRATION;
				
				$headers['Cache-Control'] = 'public, max-age=' . $expire . ', must-revalidate';
				$headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $expire) . ' GMT';
				
				// Should disable browser cache
				$headers['Vary'] = 'Cookie';
			}
			else if(!XLII_Cache_Manager::option('options.revalidate'))
			{
				$expire = XLII_Cache_Manager::option('general.expire');
				$expire = $expire ? $expire : self::DEFAULT_EXPIRATION;
			
				$headers['Cache-Control'] = 'public, max-age=' . $expire . ', must-revalidate';
				$headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $expire) . ' GMT';
			}
			else
			{
				$headers['Cache-Control'] = 'public, max-age=0, must-revalidate';
			}
		}	
		
		// Print all generated headers
		foreach( $headers as $name => $field_value )
			@header("{$name}: {$field_value}");
	}
	
	/**
	 * Append no-cache headers upon a redirect
	 * 
	 * @access	private
	 * @param	int $status The status code for the redirect
	 * @param	string $location The location to redirect to
	 * @return	string 
	 */
	public function _headerRedirect($status, $location)
	{
		if($location && !$this->hasStatuscodeMatch($status))
			nocache_headers();
		
		return $status;
	}
	
	/**
	 * Return the page status code.
	 * 
	 * @return	int
	 */
	public function getStatuscode()
	{
		return $this->_statuscode;
	}
	
	/**
	 * (Temporary) import the specified dataset as the active options
	 * 
	 * @param	array $data An array containing the data options
	 */
	static public function import(array $data)
	{
		self::$_options = $data;
	}

	/**
	 * Return the cache configuration options
	 * 
	 * @param	string $key = null A particular key from the options to retrieve.
	 * @param	void $default = null The default value to return.
	 * @return	void
	 */
	static public function option($key = null, $default = null)
	{
		if(function_exists('get_option'))
		{
			$opt = get_option(self::OPTION_NAME, false);
		
			if($opt === false)
			{
				update_option(self::OPTION_NAME, $opt = array(
					'general' => array(
						'pagination' => 10,
						'flushing' => 50
					),
				
					'options' => array(
						'statuscode' => 200,
						'revalidate' => true,
						'compress-html' => true
					),
				
					'post' => array(
						'enabled' => true,
						'feed' => array(get_default_feed()),
						'purge' => array(
							'post' => array('term' => true, 'archive' => true),
							'global' => array('front' => true, 'posts' => true)
						)
					),
				
					'term' => array(
						'enabled' => true,
						'feed' => array(get_default_feed()),
						'purge' => array(
							'post' => array('archive' => true),
							'global' => array('front' => true, 'posts' => true),
							'term' => array('ancestors' => true)
						)
					),
				
					'comment' => array(
						'enabled' => false,
						'type' => array(
							'comment'
						)
					)
				));
			}
		}
		else
		{
			$opt = !empty(self::$_options) ? self::$_options : array();
		}
		
		if(!$key)
			return $opt;
		
		$key = explode('.', $key);
		
		foreach($key as $k)
		{
			if(!isset($opt[$k]))
				return $default;
			
			if(!is_array($opt[$k]))
				return $opt[$k];
				
			$opt = $opt[$k];
		}
		
		
		return $opt;
	}
	
	/**
	 * Setup the module after WP has been loaded
	 * 
	 * @access	private
	 */
	public function setup()
	{
		register_shutdown_function(array($this, '__shutdown'));
		
		register_activation_hook(CACHE_PLUGIN_DIR . '/build.plugin.php', array($this, '__activate'));
		register_deactivation_hook(CACHE_PLUGIN_DIR . '/build.plugin.php', array($this, '__deactivate'));
		
		XLII_Cache::init();
		
		self::$_options = false;
		
		// -- Register observers
		XLII_Cache_Post_Observer::init();
		XLII_Cache_Term_Observer::init();
		XLII_Cache_Option_Observer::init();
		XLII_Cache_Comment_Observer::init();
		XLII_Cache_User_Observer::init();
		
		// Register API
		XLII_Cache_API_Manager::init();
		
		// -- Register behaviour
		add_action('template_redirect', array($this, '_headers'));
		add_filter('status_header', array($this, '_changeStatuscode'), 10, 2);
		add_filter('wp_redirect_status', array($this, '_headerRedirect'), 1000, 2);
		
		add_filter('cache_flush_all', array($this, '_updateEtag'));
		add_action('update_option_' . self::OPTION_NAME, array($this, '_writeConfig'), 100, 2);
		
		// -- Register helper
		add_action('plugins_loaded', array('XLII_Cache_WPML_Helper', 'init'));
		add_action('init', array($this, '_setupAdmin'));
		
		// -- Register cache extensions
		XLII_Cache_Redis::register();
		XLII_Cache_File::register();
		XLII_Cache_Varnish::register();
	}
	
	/**
	 * Only setup the admin section if user is logged in
	 * 
	 * @access	private
	 */
	public function _setupAdmin()
	{
		// Register admin (also extended frontend so always init in case user is logged in)
		if(!is_user_logged_in())
			return;
			
		XLII_Cache_Admin_Menubar::init();
		XLII_Cache_Admin_Configuration::init();
		XLII_Cache_Admin_Warmer::init();
	}
	
	/**
	 * Update the etag parameter upon a flush
	 * 
	 * @access 	private
	 * @param	bool|null $flushing Indicate wether the flush should proceed.
	 */
	public function _updateEtag($flushing)
	{
		if($flushing !== false)
		{
			$opt = get_option(self::OPTION_NAME, array());
			$opt['etag'] = time();
			
			update_option(self::OPTION_NAME, $opt);
		}
		
		return $flushing;
	}
	
	/**
	 * Write the new option configuration to the config file (used for pre-loading)
	 * 
	 * @access	private
	 * @param	array $old An array containing the previous cache configuration.
	 * @param	array $new An array containing the new cache configuration.
	 * @param	bool $enabled = true Inner helper used to enable/disable the module.
	 */
	public function _writeConfig($old, $new, $enabled = true)
	{
		if($contents = file_get_contents(CACHE_PLUGIN_DIR. '/resource/cache.config.tmpl'))
		{
			$contents = str_replace('%ENABLED%', $enabled ? 'true' : 'false', $contents);
			$contents = str_replace('%EXPORT%', var_export($new, true), $contents);
			
			file_put_contents(CACHE_PLUGIN_DIR. '/pre-load/config.php', $contents);
		}
	}
}