<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * File implementation of a cache manager.
 */

class XLII_Cache_File extends XLII_Cache_Instance
{
	private $_dir;
	
	/**
	 * Setup a default file system object.
	 * 
	 * @param	string $dir = null The path to the cache directory
	 */
	protected function __construct($dir = null)
	{
		$dir = $dir ? $dir : XLII_Cache_Manager::option('engine.filesystem.dir'); 
		$dir = rtrim($dir, '/') . '/';
		
		if(is_dir($dir) && is_writable($dir))
			$this->_dir = $dir;
	}
	
	/**
	 * Returns wether the cache engine is availible on this server.
	 * 
	 * @return	bool
	 */ 
	public function availible()
	{
		return true;
	}
	
	/**
	 * Process the submitted configration options
	 * 
	 * @param	array $conf An array containing the new configuration options.
	 * @return	array
	 */
	static public function _configurationProcess(array $conf)
	{
		$data = isset($_POST['filesystem']) && is_array($_POST['filesystem']) ? array_filter($_POST['filesystem']) : array();
		$data = shortcode_atts(array('dir' => self::getDefaultDir()), $data);
		
		if(!file_exists($data['dir']))
			@mkdir($data['dir']);
		
		$conf['filesystem'] = $data;
		
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
		<table class="form-table engine-section engine-<?php echo __CLASS__  ?>">
			<tr>
				<th>
					<label for = "filesystem-dir"><?php _e('Cache directory', 'xlii-cache'); ?></label>
				</th>
				<td>
					<input type = "text" name = "filesystem[dir]" class = "widefat" id = "filesystem-dir" value = "<?php echo esc_attr(XLII_Cache_Manager::option('engine.filesystem.dir')); ?>" placeholder="<?php echo self::getDefaultDir(); ?>" />
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
		
		if($dir = XLII_Cache_Manager::option('engine.filesystem.dir'))
		{
			$obj = new self($dir);
			
			if(!$obj->isValid())
				echo '<p class = "description"><small><strong style = "color:green;">' . __('Unable to create the cache directory', 'xlii-cache') . '</strong></small></p>';
			else	
				echo '<p class = "description"><small>' . sprintf(__('Cache size: %s, %d files', 'xlii-cache'), $obj->getSize('display', '0b'), count($obj->getFiles())) . '</small></p>';
		}
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
			 if(@unlink($this->_dir . $key))
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
		return file_exists($this->_dir . $key);
	}
	
	/**
	 * Flush the entire cache.
	 * 
	 * @return	bool
	 */ 
	public function flush()
	{
		if(!$this->isValid())
			return false;
		
		$success = true;
		
		foreach($this->getFiles() as $path)
			$success = @unlink($path) && $success;
				
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
		if(!$this->_exists($key))
			return false;
		
		if(!$value = file_get_contents($this->_dir . $key))
			return false;
		
		else if(!$tmp = @unserialize($value))
			return $value;
		
		else
			return $tmp;
	}
	
	/**
	 * Return the default cache directory
	 * 
	 * @return	string
	 */
	static public function getDefaultDir()
	{
		$dir = wp_upload_dir();
		$dir = $dir['basedir'] . '/cache/';
		
		return $dir;
	}
	
	/**
	 * Return an array containing all files within this directory
	 * 
	 * @return	array
	 */
	public function getFiles()
	{
		if(!$this->_dir)
			return array();
			
		$files = scandir($this->_dir);
		
		foreach($files as $i => &$file)
		{
			if($file[0] == '.')
				unset($files[$i]);
			else
				$file = $this->_dir . $file;
		}
		
		return array_filter($files);
	}
	
	/**
	 * Return the size of the file system
	 * 
	 * @param	enum $format = 'display' The format to return the filesize in
	 * @param	void $value = 0 The default value to return.
	 * @return	int|string
	 */
	public function getSize($format = 'display', $default = 0)
	{
		$size = 0;
		
		foreach($this->getFiles() as $path)
			$size += @filesize($path);
		
		if($size)
			return $format == 'display' ? size_format($size) : $size;
		else
			return $default;
	}
	
	/**
	 * Returns wether the cache connection is valid
	 * 
	 * @return	bool
	 */
	public function isValid()
	{
	 	return $this->_dir !== null;
	}
	
	/**
	 * Return the label the engine is referred by
	 * 
	 * @return	string
	 */ 
	public function label()
	{
		return __('File system', 'xlii-cache');
	}
	
	/**
	 * Mutate the key to a generic key.
	 * 
	 * @param	string $key The key the cache attribute is referred by.
	 * @return	string
	 */
	protected function _key($key)
	{
		return strlen($key) . sha1(parent::_key($key));
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
		return @file_put_contents($this->_dir . $key, is_object($value) || is_array($value) ? @serialize($value) : $value);
	}
}