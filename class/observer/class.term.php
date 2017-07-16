<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Observer class used to detect changes in terms.
 */

class XLII_Cache_Term_Observer extends XLII_Cache_Singleton
{
	private $_flushed;
	private $_changes;
	
	/**
	 * Setup the default term observer
	 */
	protected function __construct()
	{
		// -- Define attributes
		$this->_flushed = array();
		$this->_changes = array();
		
		if(!XLII_Cache_Manager::option('term.enabled'))
			return;
		
		// -- Term observation
		add_action('edit_terms', array($this, '_observeModifications'), 0, 2);
		
		// -- Term modifications
		add_action('edited_term',  array($this, 'flush'), 20, 3);
		add_action('created_term', array($this, 'flush'), 20, 3);
		add_action('delete_term',  array($this, 'flush'), 0, 3);
	}
	
	/**
	 * Flush the term cache upon mutations
	 * 
	 * @access	private
	 * @param	int $term_id The id the term is identified with.
	 * @param	int $tt_id The term taxonomy id of the term-taxonomy relation.
	 * @param	string $taxonomy The contextual taxonomy.
	 * @param	bool $force = false Indicate wether to force flush the terms
	 * @return	bool
	 */
	public function flush($term_id, $tt_id, $taxonomy, $force = false)
	{
		if(is_global_cache_flushed())
			return true;
	
		if(isset($this->_flushed[$tt_id]))
			return $this->_flushed[$tt_id];
			
		$this->_flushed[$tt_id] = false;
	
		$changes = $this->_getTermChanges($tt_id);
		
		if(!($state = $this->shouldFlush($term_id, $taxonomy, $changes)) && !$force)
			return false;
	
		else if($state === 2)
			return cache_flush();
		
		$urls = $this->getUrls($term_id, $taxonomy);
			
		if(in_array('permalink', $changes))
		{
			$search = get_term_link(intval($term_id), $taxonomy);
			$replace = $changes['previous']['permalink'];
			
			// -- we can replace the new permalinks with the old ones since the new onces haven't been cached yet
			foreach($urls as &$url)
				$url = str_replace($search, $replace, $url);
		}
		
		// Flush cache
		$this->_flushed[$tt_id] = cache_flush($urls);
		
		do_action('term_cache_flushed', $term_id, $taxonomy, $urls);
			
		// Flush related terms
		if(is_taxonomy_hierarchical($taxonomy))
		{
			$siblings = array();
			$helper = new XLII_Cache_Url_Helper();
			
			if(XLII_Cache_Manager::option('term.purge.term.children'))
				$siblings = array_merge($siblings, $this->_getTermChildren($term_id, $taxonomy));
			
			if(XLII_Cache_Manager::option('term.purge.term.ancestors'))
				$siblings = array_merge($siblings, $this->_getTermAncestors($term_id, $taxonomy));
			
		
			foreach($siblings as $sibling)
			{
				$success = $helper->getUrlsTerm($urls, $sibling, $taxonomy);
				
				if($success == 2)
					return $success;
			}
		}
	
		return true;
	}
	
	/**
	 * Return the all term ancestors.
	 * 
	 * @param	int $term_id The id the term is referred by.
	 * @param	enum $taxonomy The contextual taxonomy.
	 * @return	array
	 */
	protected function _getTermAncestors($term_id, $taxonomy)
	{	
		$parents = array();
		$term = get_term($term_id, $taxonomy);
		
		while($term->parent)
		{
			$parents[] = $term->parent;
			$term = get_term($term->parent, $taxonomy);
		}
		
		return $parents;
	}
	
	/**
	 * Return all changes made to the term.
	 * 
	 * @param	int $tt_id The id the term taxonomy is referred by.
	 * @param	enum $taxonomy = null Contextual taxonomy.
	 * @return	array
	 */
	private function _getTermChanges($tt_id, $taxonomy = null)
	{
		$changes = array();
		
		if($taxonomy && $term = get_term($tt_id, $taxonomy))
			$tt_id = $term->term_taxonomy_id;
		
		if(isset($this->_changes[$tt_id]))
		{
			$term = get_term($this->_changes[$tt_id]['term_id'], $this->_changes[$tt_id]['taxonomy']);
			
			if($this->_changes[$tt_id]['permalink'] != get_term_link($term, $term->taxonomy))
				$changes[] = 'permalink';
			
			if($this->_changes[$tt_id]['term_title'] != $term->name)
				$changes[] = 'term_title';
				
			$changes['previous'] = $this->_changes[$tt_id];
			
			unset($this->_changes[$tt_id]);
		}
		
		return $changes;
	}
	
	/**
	 * Return a hierical listing of all child terms
	 * 
	 * @param	int $term_id The id the term is referred by.
	 * @param	enum $taxonomy The contextual taxonomy.
	 * @return	array
	 */
	protected function _getTermChildren($term_id, $taxonomy)
	{
		if(!is_taxonomy_hierarchical($taxonomy) || !$hierarchy = _get_term_hierarchy($taxonomy))
			return array();
		
		if(!isset($hierarchy[$term_id]))
			return array();
	
		$children = $hierarchy[$term_id];
		
		foreach($children as $child_id)
			$children = array_merge($children, $this->_getTermChildren($child_id, $taxonomy));
		
		return $children;
	}
	
	/**
	 * Return a listing of all urls related to the specified post.
	 * 
	 * @param	int $term_id The id the term is identified with.
	 * @param	string $taxonomy The contextual taxonomy.
	 * @return	array
	 */
	public function getUrls($term_id, $taxonomy)
	{
		$post_types = get_taxonomy($taxonomy);
		$post_types = $post_types->object_type;
		
		// Gather urls
		$urls = array();
	
		$helper = new XLII_Cache_Url_Helper();
		
		// -- Archives
		
		if(XLII_Cache_Manager::option('term.purge.post.archive'))
		{
			foreach($post_types as $post_type)
				$helper->getUrlsPostArchive($urls, $post_type);
		}
		
		// -- Feeds
		
		if(XLII_Cache_Manager::option('term.purge.feed.postarchive'))
		{
			foreach($post_types as $post_type)
				$helper->getUrlsPostArchiveFeed($urls, $post_type, XLII_Cache_Manager::option('term.feed'));
		}
		
		if(XLII_Cache_Manager::option('term.purge.feed.terms'))
			$helper->getUrlsTermFeed($urls, $term_id, $taxonomy, XLII_Cache_Manager::option('term.feed'));
		
		// -- Extend with general urls
		
		if(XLII_Cache_Manager::option('term.purge.global.front'))
			$helper->getUrlsFrontpage($urls);
			
		if(XLII_Cache_Manager::option('term.purge.global.posts') && in_array('post', $post_types))
			$helper->getUrlsPostPage($urls);
				
		if($append = XLII_Cache_Manager::option('term.additional'))
			$urls = array_merge($urls, $append);
					
		$helper->getUrlsGlobal($urls)
			   ->getUrlsTerm($urls, $term_id, $taxonomy);
	
		return apply_filters('term_cache_urls', array_unique($urls), $term_id, $taxonomy);
	}

	/**
	 * Observe modifications made to an existing post
	 * 
	 * @param	int $term_id The id the term is referred by.
	 * @param	int $taxonomy The contextual taxonomy.
	 * @access	private
	 */
	public function _observeModifications($term_id, $taxonomy)
	{
		$term = get_term($term_id, $taxonomy);
		
		if(!$term || isset($this->_changes[$term->term_taxonomy_id]))
			return;
			
		$this->_changes[$term->term_taxonomy_id] = array(
			'permalink' => get_term_link($term, $taxonomy),
			'term_title' => $term->name,
			'taxonomy' => $taxonomy,
			'term_id' => $term_id
		);
		
		foreach($this->_getTermChildren($term_id, $taxonomy) as $child_id)
			$this->_observeModifications($child_id, $taxonomy);
	}

	/**
	 * Indicate wether the term cache should be flushed
	 * 
	 * @access	private
	 * @param	int $term_id The id the term is identified with.
	 * @param	string $taxonomy The contextual taxonomy.
	 * @param	array $changes = array() Additional parameter used to indicate the type of changes applied. 
	 * @return	bool
	 */
	public function shouldFlush($term_id, $taxonomy, array $changes = null)
	{
		if(!$tax = get_taxonomy($taxonomy))
			return  false;
			
		if(!$tax->public)
			return apply_filters('term_cache_flush', false, $taxonomy, $term_id, 'term-not-public');
			
		if(XLII_Cache_Manager::option('term.purge.global.all'))
			return apply_filters('term_cache_flush', 2, $taxonomy, $term_id, 'opt-based-flush');
				
		$changes = $changes === null ? $this->_getTermChanges($term_id, $taxonomy) : $changes;
		
			
		if(current_theme_supports('menus') && (in_array('permalink', $changes) || in_array('term_title', $changes)))
		{	
			global $wpdb;
			
			$menu_ref = $wpdb->get_var($wpdb->prepare(
							'SELECT COUNT(1) ' .
								'FROM ' . $wpdb->postmeta . ' AS m1 ' .
								'LEFT JOIN ' . $wpdb->postmeta . ' AS m2 ON m2.post_id = m1.post_id AND m2.meta_key = "_menu_item_type" AND m2.meta_value = "taxonomy" ' .
								'LEFT JOIN ' . $wpdb->postmeta . ' AS m3 ON m3.post_id = m1.post_id AND m3.meta_key = "_menu_item_object" AND m3.meta_value = "%s" ' .
							'WHERE ' .
								'm1.meta_key = "_menu_item_object_id" AND m1.meta_value = %d', 
							
							$taxonomy, $term_id
						));
			
			if($menu_ref)
				return apply_filters('term_cache_flush', 2, $taxonomy, $term_id, 'has-menu-item');
		}
		
				
		return apply_filters('term_cache_flush', 1, $taxonomy, $term_id, 'valid');
	}
}