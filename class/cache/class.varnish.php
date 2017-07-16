<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Varnish implementation of a cache manager.
 */

class XLII_Cache_Varnish extends XLII_Cache_Instance
{
	/**
	 * Returns wether the cache engine is availible on this server.
	 * 
	 * @return	bool
	 */ 
	public function availible()
	{
		if(!empty($_SERVER['HTTP_X_VARNISH']))
		{
			$valid = true;
		}
		
		// -- byte always specifies the HTTP_X_VARNISH server var
		else if(isset($_SERVER['BYTE_ACCOUNT']) || defined('CACHE_DEBUG') && CACHE_DEBUG)
		{
			$valid = false;
		}
		else
		{
			$valid = null;
		}
		
		if(function_exists('apply_filters'))
	 		return apply_filters('cache_varnish_availible', $valid);
		else
			return $valid;
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
			$response = $this->_request($url);

			if(!is_wp_error($response) && $response['response']['code'] === 200) 
				$count++;
		}
		
		return $count;
	}
	
	/**
	 * Return the cache object referred by the given key, inner helper method of @see get.
	 * 
	 * @param	string $key The key the cache attribute is referred by.
	 * @return	void|false
	 */ 
	protected function _get($key)
	{
		return false;
	}
	
	/**
	 * Return the label the engine is referred by
	 * 
	 * @return	string
	 */ 
	public function label()
	{
		return __('Varnish', 'xlii-cache');
	}
	
	/**
	 * Execute the purge requests.
	 * 
	 * @param	string $url The url of the page to purge.
	 * @return	WP_Error|array
	 */
	private function _request($url)
	{
		$parse_url = @parse_url($url);

		if (!$parse_url || !isset($parse_url['host']))
			return new WP_Error('http_request_failed', 'Unrecognized URL format ' . $url);

		$host = $parse_url['host'];
		$port = (isset($parse_url['port']) ? (int) $parse_url['port'] : 80);
		$path = (!empty($parse_url['path']) ? $parse_url['path'] : '/');
		$query = (isset($parse_url['query']) ? $parse_url['query'] : '');
		$request_uri = $path . ($query != '' ? '?' . $query : '');

		$varnish_host = $parse_url['host'];
		$varnish_port = 80;

		// if url host is the same as varnish server - we can use regular
		// wordpress http infrastructure, otherwise custom request should be 
		// sent using fsockopen, since we send request to other server than
		// specified by $url 
		if ($host == $varnish_host && $port == $varnish_port)
			return wp_remote_request($url, array('method' => 'PURGE', 'sslverify' => false));

		$request_headers_array = array(
			sprintf('PURGE %s HTTP/1.1', $request_uri),
			sprintf('Host: %s', $host),
			'Connection: close'
		);

		$request_headers = implode("\r\n", $request_headers_array);
		$request = $request_headers . "\r\n\r\n";

		$errno = null;
		$errstr = null;
		$fp = @fsockopen($varnish_host, $varnish_port, $errno, $errstr, 10);
		if (!$fp)
			return new WP_Error('http_request_failed', $errno . ': ' . $errstr);

		@stream_set_timeout($fp, 60);

		@fputs($fp, $request);

		$response = '';
		while (!@feof($fp))
			$response .= @fgets($fp, 4096);
	
		@fclose($fp);

		list($response_headers, $contents) = explode("\r\n\r\n", $response, 2);
        if (!preg_match('~^HTTP/1.[01] (\d+)~', $response_headers, $matches))
        	return new WP_Error('http_request_failed', 'Unrecognized response header' . $response_headers);

		return array('response' => array('code' => (int)$matches[1]));	
	}
	
	/**
	 * Store cache data under the given key, inner helper method of @see set.
	 * 
	 * @param	string $key The key the cache attribute is referred by.
	 * @param	void $value The value to store within the cache.
	 * @return	bool
	 */ 
	protected function _set($key, &$value)
	{
		return false;
	}
}