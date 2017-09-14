<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Configuration_Submit_Metabox extends XLII_Cache_Singleton
{ 
	const METABOX_NAME = 'submitdiv';
	
	/**
	 * Setup the offer metabox.
	 */
	protected function __construct()
	{
		add_action('add_cache_meta_boxes', array($this, '_register'));
		add_action('wp_ajax_xlii-cache-status', array($this, '_apiStatus'));
	}
		
	/**
	 * Retrieve the API status trough a cache call
	 * 
	 * @access	private
	 */
	public function _apiStatus()
	{
		if(!current_user_can(XLII_Cache_Admin_Configuration::REQUIRED_CAP))
			wp_die(0);
		
		echo '<ul>';
		
		foreach(XLII_Cache::getStatus() as $key => $state)
		{
			echo '<li class = "state-' . $key . '">' .
					'<span class = "state-icon ' . $state['state'] . '"></span>' .
					
				 	$state['label'];
			
			if(!empty($state['help']))
			{
				echo '<a href = "#" class = "help-block" title = "' . esc_attr($state['help']) . '">' . 
						'<span class="dashicons dashicons-editor-help"></span>' .
					 '</a>';
			}
			
			echo '</li>';
		}
		
		echo '</ul>';
		die;
	}
		
	/**
	 * Register the metabox
	 * 
	 * @access	private
	 */
	public function _register()
	{
		add_meta_box(self::METABOX_NAME, __('Information', 'xlii-cache'), array($this, 'render'), 'cache-configuration', 'side', 'high');
	}
	
	/**
	 * Render our custom metabox
	 * 
	 * @param	array $opt An array containing the configuration options
	 */	
	public function render(array $opt)
	{	
		wp_enqueue_script('jquery-ui-tooltip');
		
		// Render information
	
		$fields = apply_filters('xlii_cache_configuration_fields', array(), $opt);
	
		$fields['engine'] = array(
			'label' => __('Engine:', 'xlii-cache'),
			'value' => XLII_Cache::getInstance() ? XLII_Cache::getInstance()->label() : __('None', 'xlii-cache')
		);
	
		echo '<div id="minor-publishing" class = "custom">';
		
		foreach($fields as $field)
		{
			echo '<div class = "misc-pub-section">' .
		 			$field['icon'] . ' ' . $field['label'] .
					' <strong>' . $field['value'] . '</strong>' .
			  	 '</div>';
		}
		
		// Insert cache status
		echo '<div class = "misc-pub-section cache-status"><p class = "description">' . __('Checking cache status, please wait...', 'xlii-cache') . '</p></div>';
		
		?><script type = "text/javascript">
		window.jQuery(function( $ ){
			$.ajax({
				url: ajaxurl
				, data: { action: 'xlii-cache-status' }
				, success: function ( response ) { $('.cache-status').html( response );  $('.cache-status .help-block').tooltip({ content: function () { return $(this).prop('title'); } }) }
			})
		})
		</script>
		<?php
		
		echo '</div>';
	
		echo '<div id="major-publishing-actions" class = "clearfix">' .
				'<button class = "button button-primary" name = "action" value = "save">' . __('Save', 'xlii-cache') . '</button>' .
		 	 '</div>';
	}
}