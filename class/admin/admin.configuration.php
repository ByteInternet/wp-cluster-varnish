<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Admin_Configuration extends XLII_Cache_Singleton
{
	const REQUIRED_CAP = XLII_Cache_Manager::REQUIRED_CAP;
	const OPTION_NAME  = XLII_Cache_Manager::OPTION_NAME;
	
	private $error;
	private $notice;
	
	/**
	 * Setup the admin manager
	 */
	protected function __construct()
	{
		// -- Regsiter admin page
		add_action('xlii_cache_admin_bar_menu', array($this, '_adminbar'), 20, 2);
		add_action('admin_menu', array($this, '_adminmenu'), 5);
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
			
		// -- Add configuration node
		$url = admin_url('options-general.php');
		$url = add_query_arg('page', 'cache-config', $url);
	
		$admin_bar->add_menu( array( 
			'id' => 'varnish-cache-config',
			'parent' => 'varnish-cache',
			'title' => __('Configuration', 'xlii-cache'),
			'href' => $url
		));
	}

	/**
	 * Register our custom admin menu
	 * 
	 * @access	private
	 */
	public function _adminmenu()
	{
		$hook = add_submenu_page('options-general.php', __('Cache', 'xlii-cache'), __('Cache', 'xlii-cache'), self::REQUIRED_CAP, 'cache-config', array($this, '_adminPage'));
	
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
				'<h2>' . __('Cache configuration', 'xlii-cache') . '</h2>' .
				'<div id = "poststuff">';

		// Render notices
		{
			$this->renderResponse();

			if(function_exists('session_status'))
				$session = session_status() === PHP_SESSION_ACTIVE;
			else
				$session = session_id();

			if($session)
				echo '<div class = "notice notice-error"><p>' . __('We detected a PHP session running on your enviorment. Please note that sessions cause no-cache headers to be send thus making Varnish obsolete.', 'xlii-cache') . '</p></div>';

			if(XLII_Cache::isValid() === null)
				echo '<div class = "notice notice-error"><p>' . __('Unable to determine wether the cache instance is running properly.', 'xlii-cache') . '</p></div>';
			else if(XLII_Cache::isValid() === false)
				echo '<div class = "notice notice-error"><p>' . __('The cache instance seems to be disabled, please contact your administrator.', 'xlii-cache') . '</p></div>';
		}

		$opt = XLII_Cache_Manager::option();

		echo '<div id = "post-body" class = "metabox-holder columns-2">' .
				'<div id = "postbox-container" class = "postbox-container meta-box-sortables">';

		do_meta_boxes('cache-configuration', 'normal', $opt);
		do_meta_boxes('cache-configuration', 'advanced', $opt);
	
		echo 	'</div>' .
				'<div id = "postbox-container-1" class = "postbox-container meta-box-sortables">';
		

		do_meta_boxes('cache-configuration', 'side', $opt);
		
		echo	'</div>' .	
			 '</div>';

		echo 	'</div>' .
			 '</form>';

		?>
		<style type = "text/css">
		.wp-admin .hndle .status label:after { color: #a00; content: '<?php _e('Disabled', 'xlii-cache'); ?>'; }
		.wp-admin .hndle .status input:checked + label:after { color: green; content: '<?php _e('Enabled', 'xlii-cache'); ?>'; }
		</style>
		<?php
	}
	
	/**
	 * Load our admin page.
	 * 
	 * @access	private
	 */
	public function _adminLoad()
	{
		// Initialize metaboxes
		XLII_Cache_Configuration_Engine_Metabox::init();
		XLII_Cache_Configuration_General_Metabox::init();
		XLII_Cache_Configuration_Posts_Metabox::init();
		XLII_Cache_Configuration_Taxonomy_Metabox::init();
		XLII_Cache_Configuration_Comments_Metabox::init();
		XLII_Cache_Configuration_Users_Metabox::init();
		XLII_Cache_Configuration_Submit_Metabox::init();
		
		$this->_adminProcess();
	
		// Enque style
		wp_enqueue_style('cache-config', plugins_url('/resource/', dirname(__DIR__)) . 'style/style.configuration.css');
		wp_enqueue_script('cache-config', plugins_url('/resource/', dirname(__DIR__)) . 'js/jquery.configuration.js');
		
		// Run hook for custom extensions, has to go after process due to titles otherwise being rendered incorrect
		do_action('add_cache_meta_boxes');
	}	
	
	/**
	 * Process our admin configuration page.
	 * 
	 * @access	private
	 */
	public function _adminProcess()
	{			
		if(empty($_POST['action']) || $_POST['action'] != 'save')
			return;
			
		$data = apply_filters('save_cache_options', array());
		$data['etag'] = time();

		update_option(self::OPTION_NAME, apply_filters('cache_form_process', $data, $this));
		
		$this->notice = __('Settings saved', 'xlii-cache');
	}
	
	/**
	 * Render the admin response
	 */
	public function renderResponse()
	{
		if(!empty($this->error) && $error = $this->error)
		{	
			$this->notice = is_array($error) ? implode('<br />', array_unique($error)) : $error;
			$this->notice = '<div class = "notice notice-error"><p>' . $this->notice . '</p></div>';
		}
		
		if(!empty($this->notice) && $note = $this->notice)
			echo $note[0] != '<' ? '<div class = "notice notice-success"><p>' . $note . '</p></div>' : $note;	
	}
}