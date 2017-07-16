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
		// Render information
	
		$fields = apply_filters('xlii_cache_configuration_fields', array(), $opt);
	
		$fields['engine'] = array(
			'icon'  => '<span class="dashicons dashicons-admin-generic"></span>',
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
		
		echo '</div>';
	
		echo '<div id="major-publishing-actions" class = "clearfix">' .
				'<button class = "button button-primary" name = "action" value = "save">' . __('Save', 'xlii-cache') . '</button>' .
		 	 '</div>';
	}
}