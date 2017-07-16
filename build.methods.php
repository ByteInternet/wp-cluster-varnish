<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * File containing the helper methods.
 */

// ------------------------------------------------------------------------------------------------
// Cache management

{
	/**
	 * Flush the cache matching the specified urls, flush entire cache if none specified.
	 * 
	 * @param	array|string|null $urls A listing of urls to flush the cache of.
	 * @return	bool
	 */
	function cache_flush($urls = null)
	{
		return $urls === null ? XLII_Cache::flush() : XLII_Cache::delete($urls);
	}
	
	/**
	 * Cache the specified post
	 * 
	 * @param	WP_Post|int $post_id The id the post is referred by.
	 * @return	bool
	 */
	function cache_flush_post($post_id)
	{
		return XLII_Cache_Post_Observer::getInstance()->flush($post_id);
	}
	
	/**
	 * Cache the specified term
	 * 
	 * @param	int $term_id The id the term is identified with.
	 * @param	string $taxonomy The contextual taxonomy.
	 * @return	bool
	 */
	function cache_flush_term($term_id, $taxonomy)
	{
		if(!$term = get_term($term_id, $taxonomy))
			return false;
		
		return XLII_Cache_Term_Observer::getInstance()->flush($term_id, $term->term_taxonomy_id, $taxonomy);
	}

	/**
	 * Indicate wether the generic cache has been flushed
	 * 
	 * @return	bool
	 */
	function is_global_cache_flushed()
	{
		return XLII_Cache::getQueue() === true;
	}
}