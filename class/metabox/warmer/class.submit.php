<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Warmer_Submit_Metabox extends XLII_Cache_Singleton
{ 
	const METABOX_NAME = 'submitdiv';
	
	/**
	 * Setup the offer metabox.
	 */
	protected function __construct()
	{
		add_action('add_cache_warmer_meta_boxes', array($this, '_register'));
	}
		
	/**
	 * Register the metabox
	 * 
	 * @access	private
	 */
	public function _register()
	{
		add_meta_box(self::METABOX_NAME, __('Progress', 'xlii-cache'), array($this, 'render'), 'cache-warmer', 'side', 'high');
	}
	
	/**
	 * Render our custom metabox
	 * 
	 */	
	public function render()
	{	
		// Render information
	
		$fields = apply_filters('xlii_cache_warmer_fields', array(), $opt);
	
		$fields['timer'] = array(
			'icon'  => '<span class="dashicons dashicons-clock"></span>',
			'label' => __('Time:', 'xlii-cache'),
			'value' => '<span class = "timer">00:00</span>'
		);
		
		$fields['progress'] = array(
			'icon'  => '<span class="dashicons dashicons-backup"></span>',
			'label' => __('Progress:', 'xlii-cache'),
			'value' => '<span class = "progress">0%</span>'
		);
		
		$fields['urls'] = array(
			'icon'  => '<span class="dashicons dashicons-admin-links"></span>',
			'label' => __('Urls:', 'xlii-cache'),
			'value' => '<span class = "url-count"><span class = "count">0</span>/<span class = "total">0</span></span>'
		);
		
		$fields['throttle'] = array(
			'icon'  => '<span class="dashicons dashicons-dashboard"></span>',
			'label' => __('Urls per minute:', 'xlii-cache'),
			'value' => '<span class = "urls-per-minute">0</span>'
		);
	
		echo '<div id="minor-publishing" class = "custom progress-stat">';
		
		foreach($fields as $field)
		{
			echo '<div class = "misc-pub-section">' .
		 			$field['icon'] . ' ' . $field['label'] .
					' <strong>' . $field['value'] . '</strong>' .
			  	 '</div>';
		}
		
		echo '</div>';
	
		echo '<div id="major-publishing-actions" class = "clearfix">' .
        		'<a class="submitdelete deletion pause-toggle action-pause" href="#">' . __('Pause', 'xlii-cache') . '</a>' .
        		'<a class="submitdelete deletion pause-toggle action-continue" href="#">' . __('Continue', 'xlii-cache') . '</a>' .
		 	 '</div>';
	}
}