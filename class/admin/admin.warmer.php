<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Admin_Warmer extends XLII_Cache_Singleton
{
	const REQUIRED_CAP = XLII_Cache_Manager::REQUIRED_CAP;
	const TOS_VERSION = '1.0';
	
	/**
	 * Setup the admin manager
	 */
	protected function __construct()
	{
		// -- Regsiter admin page
		add_action('xlii_cache_admin_bar_menu', array($this, '_adminbar'), 10, 2);
		add_action('admin_menu', array($this, '_adminmenu'));
	}
	
	
	/**
	 * Register the admin bar.
	 * 
	 * @access	private
	 * @param	WP_Admin_Bar $admin_bar The generated admin bar.
	 * @param	enum $context The current page context 
	 */
	public function _adminbar(WP_Admin_Bar $admin_bar, $context)
	{
		if(!current_user_can(self::REQUIRED_CAP))
			return;
			
		$url = admin_url('options-general.php');
		$url = add_query_arg('page', 'cache-builder', $url);
	
		$admin_bar->add_menu( array( 
			'id' => 'varnish-cache-builder',
			'parent' => 'varnish-cache',
			'title' => __('Build blog cache', 'xlii-cache'),
			'href' => $url
		));
		
		if(!empty($context))
		{					
			// extract context from action
			$context = explode('?', $context, 2);
			parse_str($context[1], $context);
			
			$admin_bar->add_menu( array( 
				'id' => 'varnish-cache-build-object',
				'parent' => 'varnish-cache',
				'href' => add_query_arg('context', $context['object'] . '-' . $context['object_id'], $url),
				'title' => __('Build object cache', 'xlii-cache')
			));
		}
	}

	/**
	 * Register our custom admin menu
	 * 
	 * @access	private
	 */
	public function _adminmenu()
	{
		$hook = add_submenu_page(null, __('Cache', 'xlii-cache'), __('Cache', 'xlii-cache'), self::REQUIRED_CAP, 'cache-builder', array($this, '_adminPage'));
	
		add_action('load-' . $hook, array($this, '_adminLoad'));
	}
	
	/**
	 * Render our custom admin page
	 * 
	 * @access	private
	 */
	public function _adminPage()
	{		
		echo '<form method = "post" class = "wrap cache-configuration">' .
				'<h2>' . __('Cache warmer', 'xlii-cache') . '</h2>' .
				'<div id = "poststuff">';
				
		echo '<div class = "notice notice-info"><p>' . __(
				'<span class = "emp">Important:</span> this is a heavy process for you server and your browser. Please note that this can have an impact on the performance of both the server and your computer if not throttled propperly.<br />' .
				'Please note that servers may block you/themself as a result of a mini `DDos` attack if run to often or to fast.<br /><br />' . 
				'In time visitors and bots will warm your cache aswell, this process is <span class = "emp">not</span> required.', 'xlii-cache') . 
			 '</p></div>';
				
		if(get_user_meta(get_current_user_id(), 'cache-builder-tos', true))
		{
			echo '<div id = "post-body" class = "metabox-holder columns-2">' .
					'<div id = "postbox-container" class = "postbox-container meta-box-sortables">';

			do_meta_boxes('cache-warmer', 'normal', '');
			do_meta_boxes('cache-warmer', 'advanced', '');
	
			echo 	'</div>' .
					'<div id = "postbox-container-1" class = "postbox-container meta-box-sortables">';
		

			do_meta_boxes('cache-warmer', 'side', '');
		
			echo	'</div>' .	
				 '</div>';	
		}
		else
		{	
			echo '<div class = "notice notice-info">' . 
					'<h3>' . __('Terms of Service', 'xlii-cache') . '</h3>' .
					'<p>' . 
						__('We strongly recommend to only use this functionality if you are a developer or have a technical background in programming', 'xlii-cache') . '<br />' .
						__('In order to use this functionality you have to comply to the following Terms of Service.', 'xlii-cache') . 
					'</p>' .
					'<ul>' .
						'<li>' . __('The plugin developer is not repsonsible for the outcome/resolve of using the cache warmer', 'xlii-cache') . '</li>' .
						'<li>' . __('The plugin developer is no way obligated to help me if I run into issues', 'xlii-cache') . '</li>' .
						'<li>' . __('If anything goes wrong I know how to solve it myself', 'xlii-cache') . '</li>' .
						'<li>' . __('I fully understand the risks of using the cache warmer', 'xlii-cache') . '</li>' .
						'<li>' . __('I take full responsability for my actions and what it might cause', 'xlii-cache') . '</li>' . 
						'<li>' . __('I understand these terms apply for everyone that has access to my WP account (in case of shared WP account), and take full responsibility', 'xlii-cache') . '</li>' . 
					'</ul>' .
				 '</div>';
				
				
			echo '<div class = "notice notice-warning">' . 
					'<p>' .
						'<label>' .
							'<input type = "checkbox" name = "tos" /> ' . __('I\'ve read, understand and accept the Terms of Service', 'xlii-cache') . 
						'</label>' .
					'</p>' .
				 '</div>';
				
			echo '<input type = "submit" name = "submit" class = "button-primary" value = "' . __('Continue', 'xlii-cache') . '" />';
		}


		echo 	'</div>' .
			 '</form>';
	}
	
	/**
	 * Load our admin page.
	 * 
	 * @access	private
	 */
	public function _adminLoad()
	{
		// Initialize metaboxes
		XLII_Cache_Warmer_Submit_Metabox::init();
		XLII_Cache_Warmer_Throttle_Metabox::init();
		XLII_Cache_Warmer_Object_Metabox::init();
		
		// Run hook for custom extensions
		do_action('add_cache_warmer_meta_boxes');
				
		// Enque resources
		wp_enqueue_style('cache-builder', plugins_url('/resource/', dirname(__DIR__)) . 'style/style.builder.css');
		wp_enqueue_script('cache-builder', plugins_url('/resource/', dirname(__DIR__)) . 'js/jquery.builder.js', array('jquery'));
		
		
		// Process tos 
		if(!empty($_POST['submit']) && !empty($_POST['tos']))
			update_user_meta(get_current_user_id(), 'cache-builder-tos', time() . ':' . self::TOS_VERSION);
	}	
}