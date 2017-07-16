<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Observer class for post changes
 */

class XLII_Cache_Option_Observer extends XLII_Cache_Singleton
{
	private $_blacklist;
	
	/**
	 * Setup the default post observer
	 */
	protected function __construct()
	{
		// Initial blacklist
		
		$this->_blacklist = array(
			'siteurl', 'home', 'blogname', 'blogdescription',
			'posts_per_page', 'date_format', 'time_format', 'links_updated_date_format',
			'permalink_structure', 'blog_charset', 'active_plugins', 'stylesheet', 'template',
			'comments_per_page', 'comment_order', 'page_for_posts', 'page_on_front', 'current_theme', 
			'rewrite_rules', 'timezone_string', 'blog_public', 'gmt_offset'
		);
		
		$opt = XLII_Cache_Manager::option('options.additional');
		
		if($opt && is_array($opt))
		{
			$this->_blacklist = array_merge($this->_blacklist, $opt);
			$this->_blacklist = array_unique($this->_blacklist);
		}
		
		// -- Option hooks
		add_action('updated_option', array($this, '_observeOption'));
		add_action('deleted_option', array($this, '_observeOption'));
		add_action('added_option', array($this, '_observeOption'));
		
		add_action('update_site_option', array($this, '_observeOption'));
		add_action('delete_site_option', array($this, '_observeOption'));
		add_action('add_site_option', array($this, '_observeOption'));
	}
	
	/**
	 * Return an array of the blacklisted options
	 * 
	 * @return	array
	 */
	public function getBlacklist()
	{
		return apply_filters('option_cache_blacklist', $this->_blacklist);
	}
	
	/**
	 * Flush the cache on the alteration of specific options
	 * 
	 * @access	private
	 * @param	string $option_name The name of the option being modified.
	 */
	public function _observeOption($option_name)
	{
		if(strpos($option_name, 'wpseo') === 0 || strpos($option_name, 'widget_') === 0 || strpos($option_name, 'theme_') === 0)
			$flush = true;
		else
			$flush = in_array($option_name, $this->getBlacklist());
	
		if(apply_filters('option_cache_flush', $flush, $option_name))
		{
			cache_flush();
			
			do_action('option_cache_flushed', $option_name);
		}
	}
}