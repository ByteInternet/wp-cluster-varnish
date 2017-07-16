<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Observer class for post changes
 */

class XLII_Cache_User_Observer extends XLII_Cache_Singleton
{
	private $_flushed;
	private $_changes;
	
	/**
	 * Setup the default post observer
	 */
	protected function __construct()
	{
		// -- Define attributes
		$this->_flushed = array();
		$this->_changes = array();
		
		if(!XLII_Cache_Manager::option('user.enabled'))
			return;
		
		// -- Post modifications
		add_action('delete_user', array($this, 'flush'), 20);
		
		add_action('profile_update', array($this, '_observeModifications'), 0, 2);
	}
	
	/**
	 * Flush the user matching the specified ID.
	 * 
	 * @param	WP_User|int $user_id The id the user is referred by.
	 * @param	bool $force = false Indicate wether to force flush the user
	 * @return	bool
	 */
	public function flush($user_id, $force = false)
	{
		$user_id = $user_id ? $user_id : false;
		$user_id = is_object($user_id) ? $user_id->ID : $user_id;
		
		if(is_global_cache_flushed())
			return true;

		if(!$user_id)
			return false;
			
		if(isset($this->_flushed[$user_id]))
			return $this->_flushed[$user_id];
		
		$this->_flushed[$user_id] = false;
		
		$changes = $this->_getUserChanges($user_id);
	
		if(!($state = $this->shouldFlush($user_id, $changes)) && !$force)
			return false;

		else if($state === 2)
			return cache_flush();
			
		// Gather urls
		$urls = $this->getUrls($user_id, in_array('new', $changes));
		
		if(in_array('permalink', $changes))
		{
			$search = get_author_posts_url($user_id);
			$replace = $changes['previous']['permalink'];
			
			// -- we can replace the new permalinks with the old ones since the new onces haven't been cached yet
			foreach($urls as &$url)
				$url = str_replace($search, $replace, $url);
		}
		
		// Flush cache
		$this->_flushed[$user_id] = cache_flush($urls);
		
		do_action('user_cache_flushed', $user_id, $urls);
		
		return true;
	}
	
	/**
	 * Return a listing of all urls related to the specified user.
	 * 
	 * @param	int $user_id The id the user is referred by.
	 * @param	bool $new = false Indicate wether this is a new user or not.
	 * @return	array
	 */
	public function getUrls($user_id, $new = false)
	{
		$user_id = $user_id ? $user_id : false;
		$user_id = is_object($user_id) ? $user_id->ID : $user_id;
		
		if(!$user_id)
			return array();
		
		$urls = array();
		
		$helper = new XLII_Cache_Url_Helper();
		
		$helper->getUrlsAuthor($urls, $user_id);
			
		// -- Feeds
		
		if(XLII_Cache_Manager::option('user.purge.feed.author'))
			$helper->getUrlsAuthorFeed($urls, $user_id, XLII_Cache_Manager::option('user.feed'));
			
		// -- Extend with general urls
		
		if(XLII_Cache_Manager::option('user.purge.global.front'))
			$helper->getUrlsFrontpage($urls);
			
		if(XLII_Cache_Manager::option('user.purge.global.posts'))
			$helper->getUrlsPostPage($urls);
		
		if($append = XLII_Cache_Manager::option('user.additional'))
			$urls = array_merge($urls, $append);
		
		$helper->getUrlsGlobal($urls);
		
		return apply_filters('user_cache_urls', array_unique($urls), $user_id);
	}

	/**
	 * Return all changes made to the user.
	 * 
	 * @param	int $user_id The id the user is referred by.
	 * @return	array
	 */
	private function _getUserChanges($user_id)
	{
		$changes = array();
		
		if(isset($this->_changes[$user_id]) && $user = get_user_by('id', $user_id))
		{
			if($this->_changes[$user_id]['permalink'] != get_author_posts_url($user_id))
				$changes[] = 'permalink';
			
			if($this->_changes[$user_id]['display_name'] != $user->display_name)
				$changes[] = 'display_name';
				
			$changes['previous'] = $this->_changes[$user_id];
			
			unset($this->_changes[$user_id]);
		}
		
		return $changes;
	}
	
	/**
	 * Observe modifications made to an existing post
	 * 
	 * @param	int $user_id The id the user is referred by.
	 * @param	WP_User $old An array containing the old user object.
	 * @access	private
	 */
	public function _observeModifications($user_id, $old)
	{
		if(isset($this->_changes[$user_id]))
			return;
		
		$this->_changes[$user_id] = array(
			'permalink' => get_author_posts_url($user_id, $old->user_nicename),
			'display_name' => $old->display_name
		);
	}

	/**
	 * Returns wether the given user should be flushed
	 * 
	 * @param	WP_user|int $user_id The id the user is referred by.
	 * @param	array $changes = array() Additional parameter used to indicate the type of changes applied. 
	 * @return	enum|false
	 */
	public function shouldFlush($user_id, array $changes = null)
	{
		global $wpdb;
			
		$user_id = $user_id ? $user_id : false;
		$user_id = is_object($user_id) ? $user_id->ID : $user_id;
		
		$changes = $changes === null ? $this->_getUserChanges($user_id) : $changes;
		
		if(!$user_id || !$user = get_user_by('id', $user_id))
			return false;
	
		if($user->user_status != 0)
			return apply_filters('user_cache_flush', false, $user_id, 'user-invalid-status');
		
		if(XLII_Cache_Manager::option('user.purge.global.all'))
			return apply_filters('user_cache_flush', 2, $user_id, 'opt-based-flush');
		
		return apply_filters('user_cache_flush', 1, $user_id, 'valid');
	}
}