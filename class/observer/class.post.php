<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Observer class for post changes
 */

class XLII_Cache_Post_Observer extends XLII_Cache_Singleton
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
		
		if(!XLII_Cache_Manager::option('post.enabled'))
			return;
		
		// -- Post modifications
		add_action('save_post', array($this, 'flush'), 20);
		add_action('wp_trash_post', array($this, 'flush'), 20);
		add_action('untrashed_post', array($this, 'flush'), 20);
		
		add_action('before_delete_post', array($this, 'flush'));
		
		add_action('pre_post_update', array($this, '_observeModifications'), 0);
		
		// -- Term modifications
		add_action('add_term_relationship', array($this, '_observePostTerm'), 20, 2);
		add_action('delete_term_relationships', array($this, '_observePostTerm'), 20, 2);
	}
	
	/**
	 * Flush the post matching the specified ID.
	 * 
	 * @param	WP_Post|int $post_id The id the post is referred by.
	 * @param	bool $force = false Indicate wether to force flush the page
	 * @return	bool
	 */
	public function flush($post_id, $force = false)
	{
		$post_id = $post_id ? $post_id : get_the_ID();
		$post_id = is_object($post_id) ? $post_id->ID : $post_id;
		
		if(is_global_cache_flushed())
			return true;

		if(!$post_id)
			return false;
			
		if(isset($this->_flushed[$post_id]))
			return $this->_flushed[$post_id];
		
		$this->_flushed[$post_id] = false;
		
		$changes = $this->_getPostChanges($post_id);
			
		if(!($state = $this->shouldFlush($post_id, $changes)) && !$force)
			return false;
	
		else if($state === 2)
			return cache_flush();
			
		// Gather urls
		$urls = $this->getUrls($post_id, in_array('new', $changes));
		
		if(in_array('permalink', $changes))
		{
			$search = get_permalink($post_id);
			$replace = $changes['previous']['permalink'];
			
			// -- we can replace the new permalinks with the old ones since the new onces haven't been cached yet
			foreach($urls as &$url)
				$url = str_replace($search, $replace, $url);
		}
		
		// Flush cache
		$this->_flushed[$post_id] = cache_flush($urls);
		
		do_action('post_cache_flushed', $post_id, $urls);
		
		// Flush child pages
		if(is_post_type_hierarchical(get_post_type($post_id)) && (in_array('post_title', $changes) || in_array('permalink', $changes)))
		{
			$children = get_posts(array(
				'fields' => 'ids',
				'post_type' => 'any',
				'post_status' => array('inherit', 'any'),
				'posts_per_page' => -1,
				'post_parent' => $post_id
			));
			
			foreach($children as $child_id)
			{
				$success = $this->flush($child_id, $force);
				
				if($success === 2)
					return $success;
			}
		}
		
		return true;
	}
	
	/**
	 * Return a listing of all urls related to the specified post.
	 * 
	 * @param	int $post_id The id the post is referred by.
	 * @param	bool $new = false Indicate wether this is a new post or not.
	 * @return	array
	 */
	public function getUrls($post_id, $new = false)
	{
		$post_id = $post_id ? $post_id : get_the_ID();
		$post_id = is_object($post_id) ? $post_id->ID : $post_id;
		
		if(!$post_id)
			return array();
		
		$urls = array();
		
		$helper = new XLII_Cache_Url_Helper();
		
		// -- Feeds
		
		if(XLII_Cache_Manager::option('post.purge.feed.comment'))
			$helper->getUrlsCommentFeed($urls, $post_id, XLII_Cache_Manager::option('post.feed'));
			
		if(XLII_Cache_Manager::option('post.purge.feed.author'))
			$helper->getUrlsAuthorFeed($urls, $post_id, XLII_Cache_Manager::option('post.feed'));
			
		if(XLII_Cache_Manager::option('post.purge.feed.postarchive'))
			$helper->getUrlsPostArchiveFeed($urls, get_post_type($post_id), XLII_Cache_Manager::option('post.feed'));
			
		if(XLII_Cache_Manager::option('post.purge.feed.term'))
		{
			$taxonomies = get_post_taxonomies($post_id);
			$terms = wp_get_post_terms($post_id, $taxonomies);
			
			foreach($terms as $term)
				$helper->getUrlsTermFeed($urls, $term->term_id, $term->taxonomy, XLII_Cache_Manager::option('term.feed'));
		}
		
		// -- Archives
		
		if(XLII_Cache_Manager::option('post.purge.archive.daily'))
			$helper->getUrlsArchiveDaily($urls, $post_id);
			
		if(XLII_Cache_Manager::option('post.purge.archive.monthly'))
			$helper->getUrlsArchiveMonthly($urls, $post_id);
			
		if(XLII_Cache_Manager::option('post.purge.archive.yearly'))
			$helper->getUrlsArchiveYearly($urls, $post_id);
						
		if(XLII_Cache_Manager::option('post.purge.archive.term'))
		{
			$taxonomies = get_post_taxonomies($post_id);
			$terms = wp_get_post_terms($post_id, $taxonomies);
			
			foreach($terms as $term)
				cache_flush_term($term->term_id, $term->taxonomy);
		}
		
		// -- Post related
		
		if(XLII_Cache_Manager::option('post.purge.post.comment') && !$new)
			$helper->getUrlsComments($urls, $post_id);
			
		if(XLII_Cache_Manager::option('post.purge.post.archive'))
			$helper->getUrlsPostArchive($urls, $post_id);
			
		if(XLII_Cache_Manager::option('post.purge.post.author'))
			$helper->getUrlsAuthor($urls, get_post($post_id)->post_author);
			
		// -- Extend with general urls
		
		if(XLII_Cache_Manager::option('post.purge.global.front'))
			$helper->getUrlsFrontpage($urls, $post_id);
			
		if(XLII_Cache_Manager::option('post.purge.global.posts') && get_post_type($post_id) == 'post')
			$helper->getUrlsPostPage($urls, $post_id);
		
		if($append = XLII_Cache_Manager::option('post.additional'))
			$urls = array_merge($urls, $append);
		
		$helper->getUrlsGlobal($urls);
		
		if(!$new)
			$helper->getUrlsPost($urls, $post_id, true);
			
		return apply_filters('post_cache_urls', array_unique($urls), $post_id);
	}

	/**
	 * Return all changes made to the post.
	 * 
	 * @param	int $post_id The id the post is referred by.
	 * @return	array
	 */
	private function _getPostChanges($post_id)
	{
		$changes = array();
		
		if(isset($this->_changes[$post_id]) && $post = get_post($post_id))
		{
			if($this->_changes[$post_id]['permalink'] != get_permalink($post_id))
				$changes[] = 'permalink';
			
			if($this->_changes[$post_id]['post_title'] != $post->post_title)
				$changes[] = 'post_title';
				
			$changes['previous'] = $this->_changes[$post_id];
			
			unset($this->_changes[$post_id]);
		}
		
		return $changes;
	}
	
	/**
	 * Observe modifications in the post terms
	 * 
	 * @param	int $post_id The id the post is referred by.
	 * @param	int|array $tt_ids The id the term taxonomy is referred by.
	 * @access	private
	 */
	public function _observePostTerm($post_id, $tt_ids)
	{	
		if(!$this->shouldFlush($post_id))
			return false;
			
		global $wpdb;
		
		$terms = $wpdb->get_results( 
					'SELECT t.term_id, tt.taxonomy ' .
						'FROM ' . $wpdb->terms . ' AS t ' .
						'INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON t.term_id = tt.term_id ' .
				  	'WHERE tt.term_taxonomy_id IN (' . implode(',', (array)$tt_ids) . ')'
				);
			
		foreach($terms as $term)
		{
			if(cache_flush_term($term->term_id, $term->taxonomy) === 2)
				return;
		}
	}
	
	/**
	 * Observe modifications made to an existing post
	 * 
	 * @param	int $post_id The id the post is referred by.
	 * @access	private
	 */
	public function _observeModifications($post_id)
	{
		if(isset($this->_changes[$post_id]))
			return;
			
		$this->_changes[$post_id] = array(
			'permalink' => get_permalink($post_id),
			'post_title' => get_post($post_id)->post_title
		);
		
		if(is_post_type_hierarchical(get_post_type($post_id)))
		{
			$children = get_posts(array(
				'fields' => 'ids',
				'post_type' => 'any',
				'post_status' => array('inherit', 'any'),
				'posts_per_page' => -1,
				'post_parent' => $post_id
			));
			
			foreach($children as $child_id)
				$this->_observeModifications($child_id);
		}
	}

	/**
	 * Returns wether the given post should be flushed
	 * 
	 * @param	WP_Post|int $post_id The id the post is referred by.
	 * @param	array $changes = array() Additional parameter used to indicate the type of changes applied. 
	 * @return	enum|false
	 */
	public function shouldFlush($post_id, array $changes = null)
	{
		global $wpdb;
			
		$post_id = $post_id ? $post_id : get_the_ID();
		$post_id = is_object($post_id) ? $post_id->ID : $post_id;
		
		$changes = $changes === null ? $this->_getPostChanges($post_id) : $changes;
		
		if(!$post_id)
			return false;
	
		if(get_post_status($post_id) != 'publish')
			return apply_filters('post_cache_flush', false, $post_id, 'post-not-public');
		
		if(XLII_Cache_Manager::option('post.purge.global.all'))
			return apply_filters('post_cache_flush', 2, $post_id, 'opt-based-flush');
		
		$post_type = get_post_type($post_id);
		$post_type = $post_type ? get_post_type_object($post_type) : false;
		
		if($post_type && $post_type->name == 'nav_menu_item')
			return apply_filters('post_cache_flush', 2, $post_id, 'is-menu-item');
		
		else if(!$post_type || !$post_type->public)
			return apply_filters('post_cache_flush', false, $post_id, 'posttype-not-public');

		if(current_theme_supports('menus') && (in_array('permalink', $changes) || in_array('post_title', $changes)))
		{	
			$menu_ref = $wpdb->get_var($wpdb->prepare(
							'SELECT COUNT(1) ' .
								'FROM ' . $wpdb->postmeta . ' AS m1 ' .
								'LEFT JOIN ' . $wpdb->postmeta . ' AS m2 ON m2.post_id = m1.post_id AND m2.meta_key = "_menu_item_type" AND m2.meta_value = "post_type" ' .
							'WHERE ' .
								'm1.meta_key = "_menu_item_object_id" AND m1.meta_value = %d', 
							
							$post_id
						));
			
			if($menu_ref)
				return apply_filters('post_cache_flush', 2, $post_id, 'has-menu-item');
		}
		
		return apply_filters('post_cache_flush', 1, $post_id, 'valid');
	}
}