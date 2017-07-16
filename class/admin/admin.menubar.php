<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Admin_Menubar extends XLII_Cache_Singleton
{
	const REQUIRED_CAP = XLII_Cache_Manager::REQUIRED_CAP;
	const OPTION_NAME  = XLII_Cache_Manager::OPTION_NAME;
	
	/**
	 * Setup the admin manager
	 */
	protected function __construct()
	{
		// Register admin menu
		add_action('admin_bar_menu', array($this, '_adminbar'), 110);
	}
	
	
	/**
	 * Register the admin bar.
	 * 
	 * @access	private
	 * @param	WP_Admin_Bar $admin_bar The generated admin bar.
	 */
	public function _adminbar(WP_Admin_Bar $admin_bar)
	{
		if(!current_user_can(self::REQUIRED_CAP))
			return;
		
		$valid = XLII_Cache::isValid() !== false || defined('CACHE_DEBUG') && CACHE_DEBUG;
		
		// -- Add primary node
		if($valid)
		{
			$title = __('Cache', 'xlii-cache');
			
			// -- Track auto flushing
			$queue = XLII_Cache::getQueue();
			$queue = !$queue ? get_option(self::OPTION_NAME . '_' . get_current_user_id()) : $queue;
	
			if($queue)
			{
				if(is_array($queue) && count($queue))
				{
					$admin_bar->add_menu( array( 
						'id' => 'varnish-cache-flushed',
						'parent' => 'varnish-cache',
						'title' => __('Flushed pages', 'xlii-cache')
					));
				
					asort($queue);
				
					foreach($queue as $i => $key)
					{
						$label = apply_filters('cache_label_flushed', str_replace(home_url(''), '', $key));
						$label = !$label || $label == '/' ? __('Home', 'theme') : $label;
						$label = substr($label, 0, 37) . (strlen($label) > 40 ? '...' : '');
						
						$admin_bar->add_menu( array( 
							'id' => 'varnish-cache-flushed-' . $i,
							'parent' => 'varnish-cache-flushed',
							'title' => $label,
							'href' => $key
						));
					}	
				
					$title = __('Flushed', 'xlii-cache') . ' <span style = "font-size:0.8em;">(' . count($queue) . ')</span>';
				}
				else if($queue)
				{
					$title = __('Flushed', 'xlii-cache') . ' <span style = "font-size:0.8em;">(' . __('all', 'xlii-cache') . ')</span>';
				}
				
				delete_option(self::OPTION_NAME . '_' . get_current_user_id());
			}
		
			$admin_bar->add_menu( array( 
				'id' => 'varnish-cache',
				'title' => $title
			));
		}
		else
		{
			$admin_bar->add_menu( array( 
				'id' => 'varnish-cache',
				'title' => __('Unable to decect cache', 'xlii-cache')
			));	
		}

		// -- Build support
		
		if(is_network_admin())
		{
			$this->_adminbarNetwork($admin_bar);
		}
		else
		{
			$context = $this->_adminbarFlush($admin_bar);
			
			do_action('xlii_cache_admin_bar_menu', $admin_bar, $context);
		}
	}
	
	/**
	 * Register the flush pages in the admin bar.
	 * 
	 * @access	private
	 * @param	WP_Admin_Bar $admin_bar The generated admin bar.
	 * @return	string
	 */
	protected function _adminbarFlush(WP_Admin_Bar $admin_bar)
	{
		$url = set_url_scheme( (is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$url = add_query_arg('redirect', urlencode($url), admin_url('admin-ajax.php'));
		
		// -- Blog cache
		$admin_bar->add_menu( array( 
			'id' => 'varnish-cache-flush-blog',
			'parent' => 'varnish-cache',
			'href' => add_query_arg('action', 'cache-flush-blog', $url),
			'title' => __('Flush blog cache', 'xlii-cache') 
		));
		
		// -- Singular cache
		if(is_admin()) 
		{
			global $tag, $user_id;
		
			$current_screen = get_current_screen();
			$post = get_post();

			if ($current_screen->base == 'post')
				$object = $post;
			
			else if($current_screen->base == 'edit-tags' && !empty($tag))
				$object = $tag;
				
			else if($current_screen->base == 'user-edit' && !empty($user_id))
				$object = get_user_by('id', $user_id);
				
		} 
		else 
		{
			$object = $GLOBALS['wp_the_query']->get_queried_object();
			
			if(!is_404())
			{
				$admin_bar->add_menu( array( 
					'id' => 'varnish-cache-flush-page',
					'parent' => 'varnish-cache',
					'href' => add_query_arg('action', 'cache-flush-page', $url),
					'title' => __('Flush page cache', 'xlii-cache') 
				));
			}
		}

		if(!empty($object))
		{
			if(!empty($object->post_type) && ($pt = get_post_type_object($object->post_type)) && $pt->public)
			{
				if(XLII_Cache_Manager::option('post.enabled'))
					$action = add_query_arg(array('object' => 'post', 'object_id' => $object->ID), $url);
			} 
			else if (!empty($object->taxonomy) && ($tax = get_taxonomy($object->taxonomy)) && $tax->public)
			{
				if(XLII_Cache_Manager::option('term.enabled'))
					$action = add_query_arg(array('object' => 'term', 'object_id' => $object->term_taxonomy_id), $url);
			}
			else if(is_a($object, 'WP_User'))
			{
				if(XLII_Cache_Manager::option('user.enabled'))
					$action = add_query_arg(array('object' => 'user', 'object_id' => $object->ID), $url);
			}
			
			if(!empty($action))
			{
				$admin_bar->add_menu( array( 
					'id' => 'varnish-cache-flush-object',
					'parent' => 'varnish-cache',
					'href' => add_query_arg('action', 'cache-flush-object', $action),
					'title' => __('Flush object cache', 'xlii-cache')
				));
			}
		}
		
		return empty($action) ? false : $action;
	}
	
	/**
	 * Register the flush network in the admin bar.
	 * 
	 * @access	private
	 * @param	WP_Admin_Bar $admin_bar The generated admin bar.
	 */
	protected function _adminbarNetwork(WP_Admin_Bar $admin_bar)
	{
		$url = set_url_scheme( (is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$url = add_query_arg('redirect', urlencode($url), admin_url('admin-ajax.php'));
		
		// -- Blog cache
		$admin_bar->add_menu( array( 
			'id' => 'varnish-cache-flush-network',
			'parent' => 'varnish-cache',
			'href' => add_query_arg('action', 'cache-flush-network', $url),
			'title' => __('Flush network cache', 'xlii-cache') 
		));
	}
}