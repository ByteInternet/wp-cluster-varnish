<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Redis implementation of a cache manager.
 */

class XLII_Cache_Redis extends XLII_Cache_Instance
{
	const CONNECTION_TIMEOUT = 1.5;
	
	private $_redis;
	private $_meta;
	
	/**
	 * Setup a default redis object.
	 */
	protected function __construct($server = null, $port = null, $password = null)
	{
		try
		{
			// Load configuration
			$server = $server ? $server : XLII_Cache_Manager::option('engine.redis.server');
			$port = $port ? $port : XLII_Cache_Manager::option('engine.redis.port');
			$password = $password ? $password : XLII_Cache_Manager::option('engine.redis.password');
			
			if(!$server)
				throw new Exception('No server specified');
			
			// Use C# PHP Redis module
			if(class_exists('Redis'))
			{
				$this->_redis = new Redis();
				$this->_meta  = array(
					'scope' => null
				);
			
				// Connect with redis
				@$this->_redis->connect($server, $port, self::CONNECTION_TIMEOUT);
				$this->_redis->auth($password);
				$this->_redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP); 
			}
			
			// Fallback on credis library
			else
			{
				require_once CACHE_PLUGIN_DIR . '/lib/credis.php';
				
				$this->_redis = new Credis_Client($server, $port, self::CONNECTION_TIMEOUT);
				
				if($password)
					$this->_redis->auth($password);
			}
			
			
			// Change scope / domain
			$this->setScope('site');	
		}
		catch(Exception $e)
		{
			$this->_redis = null;
		}
	}
	
	/**
	 * Returns wether the cache engine is availible on this server.
	 * 
	 * @return	bool
	 */ 
	public function availible()
	{
		return null;//class_exists('Redis');
	}
	
	/**
	 * Execute an action on the cache engine
	 * 
	 * @param	string $method The method to execute
	 * @param	array $params An array containing parameters to pass trough.
	 * @param	void $default = false The default value to return.
	 * @return	void
	 */
	protected function _call($method, array $params, $default = false)
	{
		if(!$this->isValid())
			return $default;
		
		try
		{
			return call_user_func_array(array($this->_redis, $method), $params);
		}
		catch(Exception $e)
		{
			return $default;
		}
	}
	
	/**
	 * Process the submitted configration options
	 * 
	 * @param	array $conf An array containing the new configuration options.
	 * @return	array
	 */
	static public function _configurationProcess(array $conf)
	{
		$data = isset($_POST['redis']) && is_array($_POST['redis']) ? array_filter($_POST['redis']) : array();
		$data = shortcode_atts(array(
					'server' => 'localhost',
					'port' => 6379,
					'password' => ''
				), $data);
		
		$conf['redis'] = $data;
		
		return $conf;
	}
	
	/**
	 * Render additional configuration options for the redis engine
	 * 
	 * @access	private
	 */
	static public function _configurationRender()
	{
		$path = str_replace(ABSPATH, '', CACHE_PLUGIN_DIR . '/pre-load/cache.generic.php');
		
		?>
		<input type="text" name="prevent_autofill" id="prevent_autofill" value="" style="display:none;" />
		<input type="password" name="password_fake" id="password_fake" value="" style="display:none;" />

		
		<table class="form-table engine-section engine-<?php echo __CLASS__  ?>">
			<tr>
				<th>
					<label for = "redis-server"><?php _e('Server', 'xlii-cache'); ?></label>
				</th>
				<td>
					<input type = "text" name = "redis[server]" id = "redis-server" value = "<?php echo esc_attr(XLII_Cache_Manager::option('engine.redis.server')); ?>" placeholder="localhost" />
				</td>
			</tr>
			<tr>
				<th>
					<label for = "redis-port"><?php _e('Port', 'xlii-cache'); ?></label>
				</th>
				<td>
					<input type = "text" name = "redis[port]" id = "redis-port" value = "<?php echo esc_attr(XLII_Cache_Manager::option('engine.redis.port')); ?>" placeholder="6379" />
				</td>
			</tr>
			<tr>
				<th>
					<label for = "redis-password"><?php _e('Password', 'xlii-cache'); ?></label>
				</th>
				<td>
					<input type = "password" name = "redis[password]" id = "redis-password" value = "<?php echo esc_attr(XLII_Cache_Manager::option('engine.redis.password')); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php _e('Pre load', 'xlii-cache'); ?></th>
				<td>
					<?php self::_configurationPreload(); ?>
				</td>
			</tr>
		</table>
		<?php
	}
	
	/**
	 * Delete the page cache, inner helper method of @see delete.
	 * 
	 * @param	array $keys The key the cache attribute is referred by.
	 * @return	bool
	 */ 
	protected function _delete(array $keys)
	{
		$count = 0;
		
		foreach($keys as $url)
		{
			 if($this->_call('hDel', array($this->_scope(), $url)))
				$count++;
		}
		
		return $count;
	}
	
	/**
	 * Returns wether this page exists within the cache, inner helper method of @see exists.
	 * 
	 * @param	string $key The key the cache attribute is referred by.
	 * @return	bool|null
	 */ 
	protected function _exists($key)
	{
		$exists = $this->_call('hExists', array($this->_scope(), $key), null);
		$exists = $exists === null ? parent::_exists($key) : $exists;
		
		return $exists;
	}
	
	/**
	 * Flush the entire cache.
	 * 
	 * @return	bool
	 */ 
	public function flush()
	{
		return $this->flushScope();
	}
	
	/**
	 * Flush all data accross all domains.
	 * 
	 * @return	bool
	 */ 
	public function flushAll()
	{
		//flushAll
		
		return $this->_call('flushDB');
	}
	
	/**
	 * Flush the active scope.
	 * 
	 * @return	bool
	 */ 
	public function flushScope()
	{
		$success = function_exists('apply_filters') ? apply_filters('cache_flush_all', null, $this) : null;
		$success = $success === null ? $this->_call('del', array($this->_scope())) : $success;
		
		return $success;
	}
	
	/**
	 * Return the cache object referred by the given key, inner helper method of @see get.
	 * 
	 * @param	string $key The key the cache attribute is referred by.
	 * @return	void|false
	 */ 
	protected function _get($key)
	{
		if(!$value = $this->_call('hGet', array($this->_scope(), $key)))
			return $value;
		
		else if(is_a($this->_redis, 'Redis') || !$tmp = @unserialize($value))
			return $value;
		
		else
			return $tmp;
	}
	
	/**
	 * Returns wether the cache connection is valid
	 * 
	 * @return	bool
	 */
	public function isValid()
	{
	 	return $this->_redis !== null;
	}
	
	/**
	 * Return the label the engine is referred by
	 * 
	 * @return	string
	 */ 
	public function label()
	{
		return __('Redis', 'xlii-cache');
	}
	
	/**
	 * Return the scope of the page request.
	 * 
	 * @return	string
	 */
	protected function _scope()
	{
		return $this->_meta['scope'];
	}
	
	/**
	 * Store cache data under the given key, inner helper method of @see set.
	 * 
	 * @param	string $key The key the cache attribute is referred by.
	 * @param	void &$value The value to store within the cache.
	 * @return	bool
	 */ 
	protected function _set($key, &$value)
	{
		if(!is_a($this->_redis, 'Redis') && (is_object($value) || is_array($value)))
			return $this->_call('hSet', array($this->_scope(), $key, @serialize($value)));
		else
			return $this->_call('hSet', array($this->_scope(), $key, $value));
	}
	
	/**
	 * Set the scope used for all cache data, returns the old prefix.
	 * 
	 * @param	string $scope The new cache scope.
	 * @return	string
	 */ 
	public function setScope($scope)
	{
		$tmp = $this->_meta['scope'];
		$this->_meta['scope'] = $scope;
		
		return $tmp;
	}
}