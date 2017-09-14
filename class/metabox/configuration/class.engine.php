<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Configuration_Engine_Metabox extends XLII_Cache_Singleton
{
	const METABOX_NAME = 'configuration-engine';
	
	/**
	 * Setup the default manager object
	 */
	protected function __construct()
	{
		add_filter('save_cache_options', array($this, 'save'));
		add_action('add_cache_meta_boxes', array($this, '_register'));
	}
	
	/**
	 * Register the metabox
	 * 
	 * @access	private
	 */
	public function _register()
	{	
		add_meta_box(self::METABOX_NAME, __('Engine', 'xlii-cache'), array($this, 'render'), 'cache-configuration', 'normal');
	}

	/**
	 * Render our custom metabox
	 * 
	 * @param	array $opt An array containing the configuration options
	 */	
	public function render(array $opt)
	{	
		?>
		<table class="form-table">
	    	<tr>
                <th>
					<label for = "cache-engine"><?php _e('Cache engine', 'xlii-cache'); ?></label>
                </th>
				<td>
					<?php
					
					if($engines = XLII_Cache::getEngines('availible'))
					{
						echo '<select name = "engine[type]" id = "cache-engine" style = "min-width:160px;">';
					
						$active = get_class(XLII_Cache::getInstance());
						
						foreach($engines as $engine)
							echo '<option value = "' . get_class($engine) . '"' . selected(get_class($engine), $active, false) . '>' . $engine->label() . '</option>';
						
						echo '</select>';
					}
					else
					{
						echo __('Oh dear, it seems that you don\'t have any caching engines availible. Please ask your technical administrator to install one of the following cache engines on your platform.', 'xlii-cache');
						echo '<ul style = "list-style:inherit;padding-left:20px;">';
				
						foreach(XLII_Cache::getEngines() as $engine)
							echo '<li>' . $engine->label() . '</li>';
				
						echo '</ul>';
					}
					?>
				</td>
			</tr>
		</table>
			
		<?php do_action('cache_configuration_engine_form');
	}
	
	/**
	 * Save our custom metabox
	 * 
	 * @param	array $config The new configuration options
	 * @return	array
	 */	
	public function save(array $config)
	{		
		$class = !empty($_POST['engine']) && is_array($_POST['engine']) && !empty($_POST['engine']['type']) ? $_POST['engine']['type'] : false;
		$class = $class && class_exists($class) && is_subclass_of($class, 'XLII_Cache_Instance') ? $class : 'XLII_Cache_Varnish';
		
		$config['engine'] = array('type' => $class);
		
		$config['engine'] = apply_filters('cache_form_process_engine_' . $class, $config['engine']);
		$config['engine'] = apply_filters('cache_form_process_engine', $config['engine']);
		
		return $config;
	}	
}