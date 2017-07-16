<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Warmer_Throttle_Metabox extends XLII_Cache_Singleton
{ 
	const METABOX_NAME = 'warmer-throttle';
	
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
		add_meta_box(self::METABOX_NAME, __('Throttle', 'xlii-cache'), array($this, 'render'), 'cache-warmer', 'side');
	}
	
	/**
	 * Render our custom metabox
	 * 
	 */	
	public function render()
	{	
 	   	echo '<div class="misc-pub-section slider-container">' .
				'<strong>' . __('Urls per batch', 'xlii-cache') . '</strong></br>' .
				'<input class="url-slider batch-size" type="range" min="0" max="10" step="1" value="5" / >' .

				'<div class = "slider-range">' .
					'<span class="slider-min">0</span>' .
					'<span class="slider-value">5</span>' . 
					'<span class ="slider-max">10</span>' .
				'</div>' . 
			 '</div>';

		echo '<div class="misc-pub-section slider-container">' .
			 	'<strong>' . __('Timeout per batch in seconds', 'xlii-cache') . '</strong></br>' . 
				'<input class="timeout-slider batch-timeout" type="range" min="0" max="10" step="1" value="3" / >' .
				
				'<div class = "slider-range">' .
					'<span class="slider-min">0</span>' .
					'<span class="slider-value">3</span>' . 
					'<span class ="slider-max">10</span>' .
				'</div>' . 
			 '</div>';
	}
}