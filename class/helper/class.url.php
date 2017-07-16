<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Helper class used to fetch the page urls.
 */

class XLII_Cache_Url_Helper
{	
    /**
     * Retrieve the slug for the specified post type
     * 
     * @param	WP_Post|int $post The post used as context.
     * @return	string
     */
    private function _getPostSlug($post) 
	{
		$pt = is_object($post) ? $post->post_type : get_post_type($post);
		$pt = get_post_type_object($pt);
		
		if(!$pt->has_archive)
			return '';
			
		global $wp_rewrite;
		
		$slug  = !empty($pt->rewrite['with_front']) ? substr( $wp_rewrite->front, 1 ) : $wp_rewrite->root;
		$slug .= $pt->has_archive === true ? $pt->rewrite['slug'] : $pt->has_archive;
		
		return $slug;
    }
	
    /**
     * Returns the amount of posts found in the specified archive.
     *
     * @param	enum $post_type The post type to narrow the archive down to.
     * @param 	int $year = 0 Specific year to narrow the archive count down to.
     * @param	int $month = 0 Specific month to narrow the archive count down to.
     * @param	int $day = 0 Specific date to narrow the archive count down to.
     * @return	int
     */
    protected function _getArchivePostTypeCount($post_type, $year = 0, $month = 0, $day = 0) 
	{
		global $wpdb;

		$post_type = is_numeric($post_type) ? get_post_type($post_type) : $post_type;
		$post_type = is_object($post_type) ? $post_type->post_type : $post_type;

		$sql = array(
			'post_type = "' . $post_type .'"',
			'post_status = "publish"'
		);

		if ($year)
			$sql[] = sprintf('YEAR(post_date) = %d', $year);

		if ($month)
			$sql[] = sprintf('MONTH(post_date) = %d', $month);

		if ($day)
			$sql[] = sprintf('DAY(post_date) = %d', $day);
			
		$sql = implode(' AND ', $sql);
		$sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s', $wpdb->posts, $sql);

		return (int) $wpdb->get_var($sql);
    }
	
	/**
	 * Return a paged variation of the url
	 * 
	 * @param	int $post_id The post to base the comment url on.
	 * @param	int $page The pagenumer to generate
	 * @return	string
	 */
	protected function _getCommentUrl($post_id, $page)
	{
		if (isset($GLOBALS['post']) && is_object($GLOBALS['post']))
    		$tmp_post = clone $GLOBALS['post'];

        $GLOBALS['post'] = get_post($post_id);

        $url = get_comments_pagenum_link($page, 0);
		$url = str_replace('#comments', '', $url);

        if (isset($tmp_post)) 
            $GLOBALS['post'] = $tmp_post;
		else
			unset($GLOBALS['post']);

        return $url;
	}
	
	/**
	 * Append paged urls
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	string $base The base url.
	 * @param	int $count = null The amount of pages to generate.
	 * @return	XLII_Cache_Post_Observer
	 */
    protected function _getPagedUrls(array &$urls, $base, $count = null) 
	{
		if($count === null)
		{
			$total = wp_count_posts()->publish;
			$count = get_option('posts_per_page');
			$count = $total && $count ? ceil($total / $count) : 0;
		}
		
		$count = apply_filters('cache_paged_limit', $this->_limitPageCount($count));
		$base  = strpos($base, 'http') !== false ? $this->_stripBase($base) : $base;
			
        for ($page = 2; $page <= $count; ++$page) 
            $urls[] = $this->_getPageUrl($base, $page);

		return $this;
    }

	/**
	 * Return a paged variation of the url
	 * 
	 * @param	string $path The path to base the pagination on.
	 * @param	int $page The pagenumer to generate
	 * @return	string
	 */
	protected function _getPageUrl($path, $page)
	{
		// -- Rewrite uri
		$tmp_uri = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = $path;
		
		// -- Rewrite screen
		if(is_admin())
		{	
			static $dummy;
			
			if(!$dummy)
				$dummy = new XLII_Cache_Dummy_Screen();
			
			$tmp_screen = isset($GLOBALS['current_screen']) ? $GLOBALS['current_screen'] : false;
			$GLOBALS['current_screen'] = $dummy;
		}
		
		$url = get_pagenum_link($page);

		// -- Restore screen / uri
		$_SERVER['REQUEST_URI'] = $tmp_uri;
		
		if(isset($tmp_screen))
		{
			if($tmp_screen)
				$GLOBALS['current_screen'] = $tmp_screen;
			else
				unset($GLOBALS['current_screen']);
		}
		
		return $url;
	}
	
	/**
	 * Append the urls for the daily archives
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post The contextual post the archive is based on.
     * @return	XLII_Cache_Url_Helper
     */
	public function getUrlsArchiveDaily(array &$urls, $post) 
	{
		return $this->_getUrlsArchiveDate($urls, $post, 'day');
	}
	
	/**
	 * Append the urls for the generic date archive
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post The contextual post the archive is based on.
	 * @param	enum $depth = 'year' Indicate the archive depth.
     * @return	XLII_Cache_Url_Helper
     */
	protected function _getUrlsArchiveDate(array &$urls, $post, $depth = 'year')
	{
		if(!is_object($post))
		{
			$post = $post ? $post : get_the_ID();
			$post = get_post($post);
		}
		
		if(!$post)
			return $this;
			
		$slug = $this->_getPostSlug($post);
		$date = strtotime($post->post_date);
		
		$post_year = gmdate('Y', $date);
		$post_month = $depth == 'day' || $depth == 'month' ? gmdate('m', $date) : false;
		$post_day = $depth == 'day' ? gmdate('d', $date) : false;
		
		$total = $this->_getArchivePostTypeCount($post, $post_year, $post_month, $post_day);
		$count = get_option('posts_per_page');
		$count = $count && $total ? ceil($total / $count) : 1;
		
		$count = apply_filters('cache_archive_limit', $this->_limitPageCount($count), $post, $total);
		
		if($post_day !== false)
			$base = get_day_link($post_year, $post_month, $post_day);
		else if($post_month !== false)
			$base = get_month_link($post_year, $post_month);
		else
			$base = get_year_link($post_year);
			
		$base = $slug . $this->_stripBase($base);
	
		for ($page = 1; $page <= $count; $page++)
			$urls[] = $this->_getPageUrl($base, $page);
		
		$urls = apply_filters('cache_url_archive_' . $depth, $urls, $post->ID);
		
		return $this;
	}

	/**
	 * Append the urls for the monthly archives
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post The contextual post the archive is based on.
     * @return	XLII_Cache_Url_Helper
     */
	public function getUrlsArchiveMonthly(array &$urls, $post) 
	{
		return $this->_getUrlsArchiveDate($urls, $post, 'month');
	}
	
	/**
	 * Append the urls for the yearly archives
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post The contextual post the archive is based on.
     * @return	XLII_Cache_Url_Helper
     */
	public function getUrlsArchiveYearly(array &$urls, $post) 
	{
		return $this->_getUrlsArchiveDate($urls, $post, 'year');
	}

	/**
     * Append the urls for the author.
     * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $user_id The id the author is referred by.
     * @return	XLII_Cache_Url_Helper
     */
    public function getUrlsAuthor(array &$urls, $user_id)
	{
		$user_id = is_object($user_id) ? $user_id->ID : $user_id;	
		
		$total = count_user_posts($user_id);
		$count = get_option('posts_per_page');
		$count = $count && $total ? ceil($total / $count) : 1;
		
		$count = apply_filters('cache_auhtor_limit', $this->_limitPageCount($count), $user_id, $total);
		
		$base = get_author_posts_url($user_id);
		$base = $this->_stripBase($base);
		
		for ($page = 1; $page <= $count; $page++)
			$urls[] = $this->_getPageUrl($base, $page);
			
		$urls = apply_filters('cache_url_author', $urls, $user_id);
			
		return $this;
	}

	/**
     * Append the urls for the author feed.
     * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $user_id The id the author is referred by.
	 * @param	enum $feed The feed type to return.
     * @return	XLII_Cache_Url_Helper
     */
    public function getUrlsAuthorFeed(array &$urls, $user_id, $feed = '' )
	{
		$user_id = is_object($user_id) ? $user_id->ID : $user_id;	
		$feed = $feed ? $feed : get_default_feed();
		$feed = (array)$feed;
	
		foreach($feed as $type)
		{
			if (get_option('permalink_structure')) 
			{
				$url  = get_author_posts_url($user_id);
				$url .= $type != get_default_feed() ? 'feed/' . $type : 'feed';

				$urls[] = user_trailingslashit(trailingslashit($url), 'feed');
			} 
			else 
			{
				$urls[] = add_query_arg(array('feed' => $type, 'author' => $user_id), home_url());
			}
		}

        return $this;
    }

	/**
     * Append all comment urls related to a post
     * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post_id The id the post is referred by.
     * @return	XLII_Cache_Url_Helper
     */
    public function getUrlsComments(array &$urls, $post_id) 
	{
		$post_id = $post_id ? $post_id : get_the_ID();
		$post_id = is_object($post_id) ? $post_id->ID : $post_id;
		
		if(!$post_id)
			return $this;
	
		$total = get_comments_number($post_id);
		$count = get_option('comments_per_page');
		$count = $total && $count ? ceil($total / $count) : 0;
		
		$count = apply_filters('cache_comment_limit', $count, $post_id, $total);
		
		for ($page = 1; $page <= $count; $page++) 
			$urls[] = $this->_getCommentUrl($post_id, $page);
			

		$urls = apply_filters('cache_url_comments', $urls, $post_id);
		
		return $this;
    }

	/**
     * Append all comment urls related to a post
     * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post_id The id the post is referred by.
	 * @param	enum $feed = '' The feed type.
     * @return	XLII_Cache_Url_Helper
     */
    public function getUrlsCommentFeed(array &$urls, $post_id, $feed = '')
	{
		$post_id = $post_id ? $post_id : get_the_ID();
		$post_id = is_object($post_id) ? $post_id->ID : $post_id;
		
		$feed = $feed ? $feed : get_default_feed();
		$feed = (array)$feed;
		
		foreach($feed as $type)
		{
			if(get_option('permalink_structure'))
			{
	            if(get_option('show_on_front') == 'page' && $post_id == get_option('page_on_front') )
	                $url = _get_page_link($post_id);
	            else
	                $url = get_permalink($post_id);

	            $url  = trailingslashit($type) . 'feed';
				$url .= $type != get_default_feed() ? '/' . $type : '';

	            $urls[] = user_trailingslashit($url, 'single_feed');
			}

			else 
			{
	            if(get_post_type($post_id) == 'page')
	                $urls[] = add_query_arg(array('feed' => $type, 'page_id' => $post_id), home_url());
	            else
	                $urls[] = add_query_arg(array('feed' => $type, 'p' => $post_id), home_url());
	        }
		}

        return $this;
    }
	
	/**
	 * Append all urls for the frontpage
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $ignore = null The of the current page, used to prevent duplicate flushing.
	 * @return	XLII_Cache_Post_Observer
	 */
	public function getUrlsFrontpage(array &$urls, $ignore = null)
	{
		if(get_option('page_on_front') != $ignore)
		{
			$home = $urls[] = get_home_url(null, '/');
			$site = get_site_url(null, '/');
		
			if($site != $home)
				$urls[] = $site;
		
			if(get_option('show_on_front') == 'posts')
				$this->_getPagedUrls($urls, $home);
		}
		
		$urls = apply_filters('cache_url_frontpage', $urls);
		
		return $this;
	}
	
	/**
	 * Append additional urls to remove upon changes.
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post_id The id the post is based on.
	 * @return	XLII_Cache_Post_Observer
	 */
	public function getUrlsGlobal(array &$urls)
	{
		$urls = apply_filters('cache_url_global', $urls);
		
		return $this;
	}
	
	/**
     * Append all urls related to a post
     * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $post_id The id the post is referred by.
     * @return	XLII_Cache_Url_Helper
     */
    public function getUrlsPost(array &$urls, $post_id) 
	{
		$post_id = $post_id ? $post_id : get_the_ID();
		$post_id = is_object($post_id) ? $post_id->ID : $post_id;
		
		if(!$post_id)
			return $this;
		
		if(get_post_status($post_id) != 'publish')
			return $this;
		
		$urls[] = $base = get_permalink($post_id);
		$matches = array();
		
		if(($post = get_post($post_id)) && ($post_pages_number = preg_match_all('/\<\!\-\-nextpage\-\-\>/', $post->post_content, $matches)) > 0) 
		{
			global $wp_rewrite;

			for ($page = 2; $page <= ($post_pages_number + 1); $page++) 
			{
				if (get_option('show_on_front') == 'page' && get_option('page_on_front') == $post->ID )
					$url = trailingslashit($base) . user_trailingslashit($wp_rewrite->pagination_base . '/' . $page, 'single_paged');
				else
					$url = trailingslashit($base) . user_trailingslashit($page, 'single_paged');

				$urls[] = $url;
			}
		}
		
		$urls = apply_filters('cache_url_post', $urls, $post_id);
		
		return $this;
    }

	/**
	 * Append additional urls to remove upon changes.
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	enum $post_type The post type to retrieve the archive urls for.
	 * @return	XLII_Cache_Url_Helper
	 */
	public function getUrlsPostArchive(array &$urls, $post_type)
	{
		$post_type = is_numeric($post_type) ? get_post_type($post_type) : $post_type;
		
		if($link = get_post_type_archive_link($post_type))
		{
			$base = $this->_stripBase($link);
					
			$total = $this->_getArchivePostTypeCount($post_type);
			$count = get_option('posts_per_page');
			$count = $count && $total ? ceil($total / $count) : 1;
			
			$count = apply_filters('cache_postarchive_limit', $this->_limitPageCount($count), $post_type, $total);
			
			for ($page = 1; $page <= $count; $page++)
				$urls[] = $this->_getPageUrl($base, $page);
		
		}
		
		$urls = apply_filters('cache_url_postarchive', $urls, $post_type);
		
		return $this;
	}
	
	/**
     * Append the feed urls for the post archive
     * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	enum $post_type The post type to retrieve the archive urls for.
	 * @return	array
     */
    public function getUrlsPostArchiveFeed(array &$urls, $post_type, $feed = '')
	{
        global $wp_rewrite;

		$feed = $feed ? $feed : get_default_feed();
		$feed = (array)$feed;
		
		//$post_type_obj = get_post_type_object( $post_type );
		
		foreach($feed as $type)
		{
			$link = get_post_type_archive_feed_link($post_type, $type);
			
			// if(!$link && $post_type == 'post' && $page = get_option('page_for_posts'))
			// 		{
			// 			$link = get_permalink($page);
			// 			
			// 			if ( get_option( 'permalink_structure' ) && is_array( $post_type_obj->rewrite ) && $post_type_obj->rewrite['feeds'] ) 
			// 			{
			// 				$link = trailingslashit( $link );
			// 				$link .= 'feed/' . ($type != get_default_feed() ? $type . '/' : '');
			// 			} 
			// 			else 
			// 			{
			// 				$link = add_query_arg( 'feed', $type, $link );
			// 			}
			// 			
			// 		}
			
			
			if($link)
				$urls[] = $link;
		}

        return $urls;
    }
	
	/**
	 * Append all urls for the frontpage
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $ignore = null The of the current page, used to prevent duplicate flushing.
	 * @return	XLII_Cache_Post_Observer
	 */
	public function getUrlsPostPage(array &$urls, $ignore = null)
	{
		if(($page = get_option('page_for_posts')) && $page != $ignore)
			$this->_getPagedUrls($urls, $urls[] = get_permalink($page));
		
		$urls = apply_filters('cache_url_postpage', $urls);
		
		return $this;
	}
	
	
	/**
	 * Return all urls for the term feed.
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $term_id The id the term is identified with.
	 * @param	enum $taxonomy The contextual taxonomy.
     * @return	XLII_Cache_Url_Helper
	 */
    public function getUrlsTerm(array &$urls, $term_id, $taxonomy) 
	{
		if(!($term = get_term($term_id, $taxonomy)) || is_wp_error($term))
			return $this;
		
		$base = get_term_link(intval($term->term_id), $term->taxonomy);
		$base = $this->_stripBase($base);
		
		$count = get_option('posts_per_page');
		$count = $count && $term->count ? ceil($term->count / $count) : 1; 
		$count = apply_filters('cache_term_limit', $this->_limitPageCount($count), $term, $term->count);
		
		for ($page = 1; $page <= $count; $page++)
			$urls[] = $this->_getPageUrl($base, $page);
		
		
		$urls = apply_filters('cache_url_term', $urls, $term);
		
		return $this;
    }
	
	/**
	 * Return all urls for the term feed.
	 * 
	 * @param	array &$urls The listing containing the generated urls.
	 * @param	int $term_id The id the term is identified with.
	 * @param	enum $taxonomy = 'category' The taxonomy the term belongs to. 
	 * @param	enum|array $feed = '' The feed type to return.
     * @return	XLII_Cache_Url_Helper
	 */
	public function getUrlsTermFeed(array &$urls, $term_id, $taxonomy = 'category', $feed = '' ) 
	{
		$term_id = is_object($term_id) ? $term_id->term_id : $term_id;	
		$feed = $feed ? $feed : get_default_feed();
		$feed = (array)$feed;
		
		if(!$term = get_term($term_id, $taxonomy))
			return $this;

		foreach($feed as $type)
		{
			if (get_option('permalink_structure')) 
			{
				$url  = get_term_link(intval($term_id), $term->taxonomy);
				$url .= $type != get_default_feed() ? 'feed/' . $type : 'feed';

				$urls[] = user_trailingslashit(trailingslashit($url), 'feed');
			} 
			else 
			{
				if($term->taxonomy == 'category')
				{
					$urls[] = add_query_arg(array('feed' => $type, 'cat' => $term->term_id), home_url());
				}
				else if($term->taxonomy == 'post_tag')
				{
					$urls[] = add_query_arg(array('feed' => $type, 'tag' => $term->term_id), home_url());
				}
				else if($tax = get_taxonomy($term->taxonomy))
				{
					$urls[] = add_query_arg(array('feed' => $type, $tax->query_var => $term->slug), home_url());
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Limit the pagecount to the global maximum
	 * 
	 * @param	int $count The specified page count
	 * @return	bool
	 */
	private function _limitPageCount($count)
	{
		if(($max = intval(XLII_Cache_Manager::option('general.pagination', 0))) && $max >= 1)
			return min($count, $max);
		
		else
			return $count;
	}
	
	/**
	 * Strip the domain from the specified url.
	 * 
	 * @param	string $url The url to retrieve the base from.
	 * @return	string
	 */ 
	private function _stripBase($url)
	{
		return str_replace(home_url('/'), '/', $url);
	}
}

class XLII_Cache_Dummy_Screen {
	function in_admin() { return false; }
}