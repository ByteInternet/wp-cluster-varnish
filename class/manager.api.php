<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Setup the manager for the external API
 */

class XLII_Cache_API_Manager extends XLII_Cache_Singleton
{
	const REQUIRED_CAP = XLII_Cache_Manager::REQUIRED_CAP;
	
	/**
	 * Setup the default manager object
	 */
	protected function __construct()
	{
		add_action('wp_ajax_cache-flush-blog', array($this, '_apiCacheFlushBlog'));
		add_action('wp_ajax_cache-flush-network', array($this, '_apiCacheFlushNetwork'));
		add_action('wp_ajax_cache-flush-page', array($this, '_apiCacheFlushPage'));
		add_action('wp_ajax_cache-flush-object', array($this, '_apiCacheFlushObject'));
		
		add_action('wp_ajax_cache-proxy', array($this, '_apiCacheProxy'));
		add_action('wp_ajax_cache-request-url', array($this, '_apiCacheRequestUrls'));
	}
	
	/**
	 * Flush the entire cache
	 * 
	 * @access	private
	 */
	public function _apiCacheFlushBlog()
	{
		// Preform action
		if(current_user_can(self::REQUIRED_CAP))
			XLII_Cache::flush();
		
		// Redirect user
		$location = empty($_REQUEST['redirect']) ? wp_get_referer() : $_REQUEST['redirect'];
		$location = $location ? $location : site_url('/');
		
		wp_redirect($location);
	}
	
	/**
	 * Flush the entire network cache
	 * 
	 * @access	private
	 */
	public function _apiCacheFlushNetwork()
	{
		// Preform action
		if(current_user_can(self::REQUIRED_CAP))
		{
			foreach(wp_get_sites() as $blog)
			{
				switch_to_blog($blog['blog_id']);
				
				XLII_Cache::flush(true);
			
				restore_current_blog();
			}
		}
		
		// Redirect user
		$location = empty($_REQUEST['redirect']) ? wp_get_referer() : $_REQUEST['redirect'];
		$location = $location ? $location : site_url('/');
		
		wp_redirect($location);
	}
	
	/**
	 * Flush the page cache
	 * 
	 * @access	private
	 */
	public function _apiCacheFlushPage()
	{
		// Redirect user
		$location = empty($_REQUEST['redirect']) ? wp_get_referer() : $_REQUEST['redirect'];
		$location = $location ? $location : site_url('/');
		
		// Preform action
		if(current_user_can(self::REQUIRED_CAP))
			XLII_Cache::delete($location);
		
		wp_redirect($location);
	}
	
	
	/**
	 * Flush the object cache
	 * 
	 * @access	private
	 */
	public function _apiCacheFlushObject()
	{
		// Preform action
		if(current_user_can(self::REQUIRED_CAP) && !empty($_REQUEST['object_id']))
		{
			$id = intval($_REQUEST['object_id']);
			
			switch(isset($_REQUEST['object']) ? $_REQUEST['object'] : '')
			{
				case 'post':
					XLII_Cache_Post_Observer::getInstance()->flush($id);
				
					break;
				
				case 'term':
					global $wpdb;
					
					$term = $wpdb->get_row($wpdb->prepare('SELECT term_id, taxonomy FROM ' . $wpdb->term_taxonomy . ' WHERE term_taxonomy_id = %d', $id));
					
					if($term)
						XLII_Cache_Term_Observer::getInstance()->flush($term->term_id, $id, $term->taxonomy);
				
					break;
					
				
				case 'user':
					XLII_Cache_User_Observer::getInstance()->flush($id);
				
					break;
				
			}
		}
		
		// Redirect user
		$location = empty($_REQUEST['redirect']) ? wp_get_referer() : $_REQUEST['redirect'];
		$location = $location ? $location : site_url('/');
		
		wp_redirect($location);
	}


	/**
 	 * Use php to send a http request without sending any cookies
	 * $_POST['urls']: array of urls
	 */
	public function _apiCacheProxy() 
	{
		$_POST = $_REQUEST;
		
		//Only administrators should be able to do this
		if(!current_user_can(self::REQUIRED_CAP))
			return;

		$urls = (array)$_POST['urls'];
		$output = array();

		//Loop through all the urls
		foreach($urls as $i => $url)
		{
			$url = get_home_url(urldecode($url));

			//send request without cookies
			$start = explode(' ', microtime());
			$start = array_map('floatval', $start);
		
			$r = wp_remote_get($url, array('cookies'=> array(),'timeout'=>5));
			
			//ERROR HANDLING
			if(!is_wp_error( $r ))
			{
				$header = wp_remote_retrieve_headers($r);
				
				if(empty($header['cache-control']) || strpos($header['cache-control'], 'no-cache') === false)
				{
					$end = explode(' ', microtime());
					$end = array_map('floatval', $end);
				
					$time = ($end[1] - $start[1]) + ($end[0] - $start[0]);
					$time = round($time * 1000);
		
					$output[$i] = array(
						'column-statuscode' => wp_remote_retrieve_response_code($r),
						'column-load-time' => $time . ' ms',
						'state' => 'ok'
					);
				}
				else
				{
					$output[$i] = array(
	        			'error' => sprintf(__('<strong>Error occured</strong>: %s', 'xlii-cache'), __('No-cache headers supplied', 'xlii-pack')),
						'state' => 'no-cache'
	        		);
				}
	        }
	        else
	        {
	        	$output[$i] = array(
	        		'error' => sprintf(__('<strong>Error occured</strong>: %s', 'xlii-cache'), $r->get_error_message()),
					'state' => 'error'
	        	);
			}
			
			$output[$i] = apply_filters('cache_request', $output[$i], $url, $r);
		}

		echo json_encode($output);
		wp_die();
	}


	/** 
	 * Prints an array of links based on the arguments provided
	 *
	 *	$_POST['type']: type of links, 'post','taxonomy' or 'users
	 * 	$_POST['args']: arguments used for the query, for taxonomies these are the arguments for get_terms()
	 *	$_POST['offset']: offset
	 *	$_POST['amount']: amount of links to get, for posts this has to be some amount, because otherwise offset is ignored
	 */
	public function _apiCacheRequestUrls()
	{
		//Only administrators should be able to do this
		if(!current_user_can(self::REQUIRED_CAP))
			return;

		$args = wp_parse_args($_POST, array(
					'type' => 'post',
					'object' => null,
					
					'offset' => 0,
					'limit' => 50
				));

		$args['offset'] = intval($args['offset']);
		$args['limit'] = intval($args['limit']);


		$links = array();
		$base = home_url();

		//-- Handle different types

		//All posts on blog
		switch($args['type'])
		{
			case 'post':
				
				if(!$args['object'])
				{	
					$list = get_posts(array(
									'post_type' => get_post_types(array('public' => true)),
									'post_status' => 'publish', 
									
									'offset' => $args['offset'],
									'posts_per_page' => $args['limit']
								));

					foreach($list as $entity)
						$links[] = str_replace($base, '', get_permalink($entity->ID));
				
				}
				else
				{
            		$helper = XLII_Cache_Post_Observer::getInstance();
            		$links = $helper->getUrls($args['object']);
				}
			
				break;
		
			case 'term':
			
				global $wpdb;
				
				$term = !empty($args['object']) ? intval($args['object']) : 0;
				$term = $wpdb->get_row($wpdb->prepare('SELECT term_id, taxonomy FROM ' . $wpdb->term_taxonomy . ' WHERE term_taxonomy_id = %d', $term));
					
				if($term)
					$links[] = XLII_Cache_Term_Observer::getInstance()->getUrls($term->term_id, $term->taxonomy);
			
				break;
			
			case 'taxonomy':
				$list = get_terms($args['object'] ? $args['object'] : get_taxonomies(array('public' => true)), array(
							'offset' => $args['offset'], 
							'number' => $args['limit']
						));

				foreach($list as $entity)
					$links[] = str_replace($base, '', get_term_link($entity));
			
				break;
			
			case 'user':
				
				if(!$args['object'])
				{	
					$list = get_users(array(
								'offset' => $args['offset'],
								'number' => $args['limit']
							));

					foreach($list as $entity)
						$links[] = str_replace($base, '', get_author_posts_url($entity->ID));
				
				}
				else
				{
            		$helper = XLII_Cache_User_Observer::getInstance();
            		$links = $helper->getUrls($args['object']);
				}
			
				break;
		}

		echo json_encode($links);
		wp_die();
	}
}