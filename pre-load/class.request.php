<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Class used for the cache request
 */

class XLII_Cache_Request
{
	private $_url;
	
	/**
	 * Setup the default request object
	 */
	public function __construct()
	{
		// -- Build the request uri
		$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		
		if(isset($_SERVER['HTTPS']) && in_array(strtolower($_SERVER['HTTPS']), array('on', '1'))) 
			$url = 'https://' . $url;
		
		else if(isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT']))
			$url = 'https://' . $url;
		
		else
			$url = 'http://' . $url;
	
		// -- Clean up query
		$url = explode('?', strtolower($url), 2);
		
		// Clean up query
		if(!empty($url[1]))
		{
			parse_str($url[1], $query);
			
			// -- Remove google params & campaign params
			unset($query['gclid']);
			
			foreach($query as $key => &$void)
			{
				if(strpos($key, 'utm_') === 0)
					unset($query[$key]);
			}
			
			ksort($query);
			
			$url[1] = http_build_query($query);
		}
		
		// -- Concat query
		$this->_url = implode('?', $url);
	}
	
	
	/**
	 * Compress the given output if pissible
	 * 
	 * @param	string $out The output to return.
	 * @return	string
	 */
	public function compress($out)
	{
		if(!XLII_Cache_Manager::option('options.compress-html'))
			return $out;
		
		if (!ini_get('zlib.output_compression') && ini_get('output_handler') != 'ob_gzhandler' && isset($_SERVER['HTTP_ACCEPT_ENCODING'])) 
		{
			header('Vary: Accept-Encoding'); 
			if(stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false && function_exists('gzdeflate'))
			{
				header('Content-Encoding: deflate');
				$out = gzdeflate($out, 3);
			} 
			else if(stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false && function_exists('gzencode')) 
			{
				header('Content-Encoding: gzip');
				$out = gzencode($out, 3);
			}
		}
		
		header('Content-Length: ' . strlen($out));
		
		return $out;
	}
	
	/**
	 * Return the active request url
	 * 
	 * @return	string
	 */
	public function getUrl()
	{
		return $this->_url;
	}
	
	/**
	 * Return the current GMT time
	 * 
	 * @return	int
	 */
	public function gmtime()
	{
		return strtotime(gmdate('Y-m-d H:i:s'));
	}
	
	/**
	 * Generate a new identifiable eTag for the specified url.
	 * 
	 * @param	string $url The url to convert to an etag.
	 * @return	string
	 */
	public function eTag($url)
	{
		$hash = XLII_Cache_Manager::option('etag');
		$hash = $hash ? ':' . $hash : '';
		
		return sha1($url) . $hash;
	}
	
	/**
	 * Try serving the active cache if it exists, note that this method exits upon success.
	 * 
	 * @return	XLII_Cache_Request
	 */
	public function serve()
	{
		$this->serveBrowser();
		$this->serveCache();
		
		return $this;
	}
	
	/**
	 * Try serving the cache from the browser, note that this method exits upon success.
	 * 
	 * @return	XLII_Cache_Request
	 */
	public function serveBrowser()
	{
		// Check if browser cached
		$header = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false;
		$hash = $this->eTag($this->getUrl());

		// Require the mod_expires and mod_headers apache module
		if($header == $hash)
		{
			header('Cache-Control: public');
			header('HTTP/1.1 304 Not Modified');
			
			header('X-Cache-Age: Unknown');
			header('X-Cache-Origin: Browser');
		
			exit;
		}
	
		return $this;
	}
	
	/**
	 * Try serving the cache from the server cache, note that this method exits upon success.
	 * 
	 * @return	XLII_Cache_Request
	 */
	public function serveCache()
	{
		if(!$data = XLII_Cache::get($this->getUrl()))
			return $this;
		
		if(empty($data['content']))
			return $this;

		// -- Send caching headers
		header('Cache-Control: public');

		header('ETag: ' . $this->eTag($this->getUrl()));
		header('Expires: ' . gmdate('D, d M Y H:i:s', $data['expires']) . ' GMT');
		header('Cache-Control: public, ' . (XLII_Cache_Manager::option('options.revalidate') ? 'max-age=0, must-revalidate' : 'max-age=' . ($data['expires'] - $this->gmtime()) ));
	
		header('X-Cache-Age: ' . ($this->gmtime() - $data['date']));
		header('X-Cache-Origin: Server Cache');
		
		// -- Send headers
		foreach($data['headers'] as $header)
			header($header);
			
		// -- Output content
		echo $this->compress($data['content']);
		
		exit;
	}
	
	/**
	 * Determine wether this page should be cached
	 * 
	 * @return	bool
	 */
	public function shouldCache()
	{	
		if(XLII_Cache_Manager::getInstance()->getStatuscode() !== 200)
			return false;
			
		if(!$headers = headers_list())
			return true;
		
		foreach($headers as $header)
		{
			$header = explode(':', strtolower($header), 2);
			
			switch($header[0])
			{
				case 'cache-control':
					if(strpos($header[1], 'no-cache') !== false)
						return false;
				
					break;
					
				case 'expires':
					$date = gmdate('Y-m-d H:i:s', strtotime(trim($header[1])));
					
					if(!$date || $date <= gmdate('Y-m-d H:i:s'))
						return false;
						
					break;
			}
		}
		
		return true;
	}
	
	/**
	 * Indicate wether the cache request should run, used for early opt out
	 * 
	 * @return	bool
	 */
	public function shouldRun()
	{
		// Exclude post requests
		if(!empty($_POST) || !XLII_Cache::isValid())
			return false;
		
		// Exclude command line calls from caching
		if(function_exists('php_sapi_name') && php_sapi_name() == 'cli')
			return false;	
		
		// Exclude wp pages
		if(!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-') !== false)
			return false;
		
		// Exclude logged in users
		foreach($_COOKIE as $key => &$void)
		{
			if(strpos($key, 'wordpress_logged_in') === 0)
				return false;
		}
		
		// Possible for caching purposes
		return true;
	}
	
	/**
	 * Start tracking the page request for caching purposes
	 * 
	 * @return	XLII_Cache_Request
	 */
	public function track()
	{
		ob_start(array($this, '_track'));
		
		return $this;
	}
	
	/**
	 * Callback of @see track, used once all page data has been tracked
	 * 
	 * @access	private
	 * @param	string $buffer The generated page output
	 * @return	string
	 */
	public function _track($buffer)
	{	
		if($this->shouldCache())
			$this->store($buffer);
		
		return $this->compress($buffer);
	}
	
	/**
	 * Store the specified output into the cache
	 * 
	 * @param	string $content The contents to store within the cache.
	 * @param	array $headers An array containing the page headers.
	 * @return	bool
	 */
	public function store($content, &$headers = null)
	{
		// Prepare cache
		if(empty($headers))
			$headers = headers_list();
		
		$expires = null;
		
		foreach($headers as $i => $entity)
		{
			$header = explode(':', strtolower($entity), 2);
			
			if(strpos($header[0], 'cache-control') !== false && preg_match('/max-age=([0-9]+)/i', $header[1], $match))
			{
				$date = $this->gmtime() + $match[1];
				$expires = !$expires || $date < $expires ? $date : $expires;
			}
			else if(strpos($header[0], 'expires') !== false)
			{
				$date = gmdate('Y-m-d H:i:s', strtotime(trim($header[1])));
				$date = $date ? strtotime($date) : false;
				
				if($date)
					$expires = !$expires || $date < $expires ? $date : $expires;
			}
			
			if(preg_match('/^(Set-Cookie|Cache-Control|Pragma|Expires)/i', $entity))
				unset($headers[$i]);
		}
		
		@header('X-Cache-Origin: Build');
	
		return XLII_Cache::set($this->getUrl(), array(
					'expires' => $expires ? $expires : $this->gmtime() + 86400, 
					'date' => $this->gmtime(),
					'headers' => $headers, 
					'content' => $content
			   ));
	}
}
