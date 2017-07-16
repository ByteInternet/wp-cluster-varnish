<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Helper class used to modify the flushing behaviour on WPML blogs
 */

class XLII_Cache_WPML_Helper extends XLII_Cache_Singleton
{
	/**
	 * Setup the default helper object
	 */
	public function __construct()
	{
		if(!defined('ICL_SITEPRESS_VERSION') || !version_compare(ICL_SITEPRESS_VERSION, '3.1.8.4', '>='))
			return;
		
		add_action('cache_form_display', array($this, '_adminDisplay'));
		add_filter('cache_form_process', array($this, '_adminProcess'));
		
		if(!XLII_Cache_Manager::option('wpml.enabled'))
			return;
		
		add_filter('pre_update_option', array($this, '_sanitize'), 10, 2);
		
		add_filter('cache_label_flushed', array($this, '_label'));
		
		add_filter('cache_flush', array($this, '_cleanup'));
		add_filter('cache_flush_all', array($this, '_flush'));
	}
	
	/**
	 * Append a new display section in the form
	 * 
	 * @access	private
	 * @param	object $view Contextual view object used to render the page
	 */ 
	public function _adminDisplay($view)
	{
		
			$title  = __('WPML', 'xlii-cache');
			$title .= '<span class = "status"><input type = "checkbox" name = "wpml[enabled]" id = "wpml-enable" value = "1" ' . checked(true, XLII_Cache_Manager::option('wpml.enabled'), false) . ' />' .
						'<label for = "wpml-enable"></label>' .
					  '</span>';
	
			echo $view->metaboxHeader($title) .
			
				 '<p>' . __('WPML support is still in an experimental phase, the module is only tested on a customized WPML installation. ' . 
						 	'We advise to only activate this module if you experience issues regarding the cache and WPML.', 'xlii-cache') . 
				 '</p>' .
				
				$view->metaboxFooter();
		
	}
	
	/**
	 * Process the admin section
	 * 
	 * @access	private
	 * @param	array $data An array containing the processed data
	 * @return	array
	 */ 
	public function _adminProcess(array $data)
	{
		$data['wpml'] = array(
			'enabled' => !empty($_POST['wpml']) && is_array($_POST['wpml']) && !empty($_POST['wpml']['enabled'])
		);
		
		return $data;
	}
	
	/**
	 * Cleanup the listing of keys in a language context
	 * 
	 * @access	private
	 * @param	array $keys An array containing the keys to flush
	 * @return	array
	 */
	public function _cleanup(array $keys)
	{
		global $sitepress;

		// -- Make sure the langauge context is fixed
		$search = home_url();
		
		$replace = rtrim($sitepress->get_current_language(), '/');
		$replace = rtrim($sitepress->language_url($replace), '/');
		
		foreach($keys as &$url)
		{
			if(strpos($url, '.*') === false)
				$url = str_replace($search, $replace, $url);
		
			if($url && substr_count($url, $replace) >= 2)
			{
				if(($pos = strpos($url, '?')) == false || strpos($url, $replace, 1) <= $pos) 
					$url = substr($url, strlen($replace) + 1);
			}
		}
	
		return $keys;
	}
	
	/**
	 * Action to preform upon flushing of the entire blog
	 * 
	 * @param	bool $success Indicate wether the flushing is succesfull.
	 */
	public function _flush($success)
	{
		global $sitepress;
		
		// -- Gather language base urls
		$urls = $sitepress->get_active_languages();
		
		foreach($urls as &$url)
			$url = $sitepress->language_url($url['code']);
			
		sort($urls);
		
		// Remove redundant urls
		foreach($urls as $key => &$value)
		{
			if(empty($compare) || strpos($value, $compare) !== 0)
			{
				$compare = $value;
				$value .= '/.*';
			}
			else
			{
				unset($urls[$key]);
			}
		}
		
		return XLII_Cache::delete($urls, true);
	}
	
	/**
	 * Modify the label of the flushed content page
	 * 
	 * @param	string $label The modified label.
	 * @return	string
	 */
	public function _label($label)
	{
		global $sitepress;
		
		$base = $sitepress->get_current_language();
		$base = $sitepress->language_url($base);
		
		$label = str_replace($base, '', $label);
		$label = str_replace('.*', '', $label);
		
		return $label;
	}
	
	/**
	 * Sanitize the option value before it is being stored
	 * 
	 * @param	void $value The value to store in the option.
	 * @param	string $option The name the option is referred by.
	 * @return	void
	 */
	public function _sanitize($value, $option)
	{
		if(strpos($option, XLII_Cache_Manager::OPTION_NAME . '_') !== false && is_array($value))
			return $this->_cleanup($value);
		else
			return $value;
	}
}