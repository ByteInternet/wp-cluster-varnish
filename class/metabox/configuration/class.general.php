<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Configuration_General_Metabox extends XLII_Cache_Singleton
{
	const METABOX_NAME = 'configuration-general';
	
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
		add_meta_box(self::METABOX_NAME, __('General', 'xlii-cache'), array($this, 'render'), 'cache-configuration', 'normal');
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
					<label for = "general-expire"><?php _e('Cache expiration', 'xlii-cache'); ?></label>
                </th>
				<td>
					<input type = "text" id = "general-expire" name = "general[expire]" value = "<?php echo !empty($opt['general']['expire']) && intval($opt['general']['expire']) > 0 ? intval($opt['general']['expire']) : ''; ?>" />
					<p class = "description"><small>
					<?php 
					if(!empty($opt['general']['expire']) && intval($opt['general']['expire']) > 0)
					{
						
						$time = array();
						$seconds = intval($opt['general']['expire']);
						
						if($days = floor($seconds / 86400))
						{
							$time[]   = sprintf(_n('1 day', '%s days', $days, 'xlii-cache'), $days);
							$seconds -= $days * 86400;
						}
						
						if($hours = floor($seconds / 3600))
						{	
							$time[]   = sprintf(_n('1 hour', '%s hours', $hours, 'xlii-cache'), $hours);
							$seconds -= $hours * 3600;
						}
						
						if($minutes = floor($seconds / 60))
							$time[] = sprintf(_n('1 minute', '%s minutes', $minutes, 'xlii-cache'), $minutes);
						if($seconds % 60)
							$time[] = sprintf(_n('1 second', '%s seconds', $seconds % 60, 'xlii-cache'), $seconds % 60);
						
						echo '[' . implode(', ', $time) . ']<br />';
					}
					
					
					_e('Amount of seconds the cache is valid, leave empty to prevent expiration.', 'xlii-cache'); 
					?></small></p>
				</td>
            </tr>
            <tr>
                <th>
					<label for = "general-flushing"><?php _e('Limit flushing', 'xlii-cache'); ?></label>
                </th>
				<td>
					<input type = "text" id = "general-flushing" name = "general[flushing]" value = "<?php echo !empty($opt['general']['flushing']) ? esc_attr($opt['general']['flushing']) : ''; ?>" />
					<p class = "description"><small><?php _e('Automaticly flush the entire cache if the url count exceeds the specified maximum, leave blanc to ignore.', 'xlii-cache'); ?></small></p>
				</td>
            </tr>
            <tr>
                <th>
					<label for = "general-pagination"><?php _e('Limit pagination', 'xlii-cache'); ?></label>
                </th>
				<td>
					<input type = "text" id = "general-pagination" name = "general[pagination]" value = "<?php echo esc_attr($opt['general']['pagination']); ?>" />
					<p class = "description"><small><?php _e('Limit the maximum page within pagination based urls. Fewer pages preform better for under-powered servers.', 'xlii-cache'); ?></small></p>
				</td>
            </tr>
            <tr>
                <th>
					<label for = "options-additional"><?php _e('Options', 'xlii-cache'); ?></label>
                </th>
				<td>
					<textarea cols = "80" rows = "5" name = "options[additional]" id = "options-additional"><?php echo isset($opt['options']['additional']) ? esc_textarea(implode("\n", $opt['options']['additional'])) : ''; ?></textarea>
					<p class = "description"><small><?php _e('Flush the entire cache upon changes within the specified options. Please specify one option per line.', 'xlii-cache'); ?></small></p>
				</td>
            </tr>
            <tr>
                <th>
					<label for = "general-https-indifferent"><?php _e('HTTPS Indifferent', 'xlii-cache'); ?></label>
                </th>
				<td>
					<input type = "checkbox" id = "general-https-indifferent" name = "general[https_indifferent]" <?php checked(true, XLII_Cache_Manager::option('general.https_indifferent', true)); ?> />
					<?php _e('Neglect caching difference between https and http.', 'xlii-cache'); ?>
				</td>
            </tr>
            <?php /*<tr>
                <th>
					<label for = "options-cookies"><?php _e('Cookies', 'xlii-cache'); ?></label>
                </th>
				<td>
					<textarea cols = "80" rows = "5" name = "options[cookies]" id = "options-cookies"><?php echo isset($opt['options']['cookies']) ? esc_textarea(implode("\n", $opt['options']['cookies'])) : ''; ?></textarea>
					<p class = "description"><small><?php _e('Exclude the cache from being stored if the following cookies are present. Please specify one option per line, regular expressions allowed.', 'xlii-cache'); ?></small></p>
				</td>
            </tr>*/ ?>
            <tr>
                <th>
					<label for = "options-exclude"><?php _e('Exclude', 'xlii-cache'); ?></label>
                </th>
				<td>
					<textarea cols = "80" rows = "5" name = "options[exclude]" id = "options-exclude"><?php echo isset($opt['options']['exclude']) ? esc_textarea(implode("\n", $opt['options']['exclude'])) : ''; ?></textarea>
					
					<p class = "description"><small>
						<?php _e('Exclude the following pages from being cached. Please specify one URL per line, regular expressions allowed.', 'xlii-cache'); ?><br />
						<?php _e('The submitted options are matched against the full page url including query parameters.', 'xlii-cache'); ?>
					</small></p>
				</td>
            </tr>
            <tr class = "suboption">
                <th>
					<label for = "exlusion-test"><?php _e('Test url:', 'xlii-cache'); ?></label>
                </th>
				<td>
					<input type = "text" id = "exclusion-test" class = "regular-text match-field" data-source = "options-exclude" /> <button class = "button-primary" id = "exclusion-submit"><?php _e('Check', 'xlii-cache'); ?></button>
					<div class = "match-result" style = "display:none;"><strong><?php _e('Match:', 'xlii-cache'); ?></strong> <span><?php _e('None', 'xlii-cache'); ?></span></div>
					<p class = "description"><small>
						<?php _e('Enter a test url to check wether it is excluded using the exclusion field', 'xlii-cache'); ?><br />
						<?php _e('Need help with regex? Check <a href = "https://www.debuggex.com/" target = "_blank">this</a> awesome link', 'xlii-cache'); ?>
					</small></p>
				</td>
            </tr>
            <tr>
                <th>
					<label for = "options-statuscode"><?php _e('Statuscode', 'xlii-cache'); ?></label>
                </th>
				<td>
					<label>
						<input type = "checkbox" id = "options-statuscode" name = "options[statuscode]" value="200"<?php checked(true, !empty($opt['options']['statuscode']) && $opt['options']['statuscode'] == 200); ?> />
						<?php _e('Only cache page with the statuscode 200', 'theme'); ?>
					</label>
				</td>
            </tr>
            <tr>
                <th>
					<label for = "options-revalidate"><?php _e('Must revalidate', 'xlii-cache'); ?></label>
                </th>
				<td>
					<label>
						<input type = "checkbox" id = "options-revalidate" name = "options[revalidate]"<?php checked(true, !empty($opt['options']['revalidate'])); ?> />
						<?php _e('The server determines wether a 304 is returned, used to enhance browser caching', 'theme'); ?>
					</label>
				</td>
            </tr>

            <tr>
                <th>
					<label for = "compress-html"><?php _e('Compress html', 'xlii-cache'); ?></label>
                </th>
				<td>
					<label>
						<input type = "checkbox" id = "options-compress-html" name = "options[compress-html]"<?php checked(true, !empty($opt['options']['compress-html'])); ?> />
						<?php _e('Try compressing the HTML output', 'theme'); ?>
					</label>
				</td>
            </tr>
        </table>
		<?php
	}
	
	/**
	 * Save our custom metabox
	 * 
	 * @param	array $config The new configuration options
	 * @return	array
	 */	
	public function save(array $config)
	{
		// Process general data
		if(isset($_POST['general']) && is_array($_POST['general']))
		{
			$config['general'] = array();
			
			if(!empty($_POST['general']['flushing']))
			{	
				$config['general']['flushing'] = intval($_POST['general']['flushing']);
				$config['general']['flushing'] = $config['general']['flushing'] > 0 ? $config['general']['flushing'] : 0;
			}
				
			if(!empty($_POST['general']['pagination']))
				$config['general']['pagination'] = intval($_POST['general']['pagination']);
				
			if(!empty($_POST['general']['expire']) && intval($_POST['general']['expire']) >= 0)
				$config['general']['expire'] = intval($_POST['general']['expire']);
				
			$config['general']['https_indifferent'] = isset($_POST['general']['https_indifferent']);
		}
		
		// Process option data
		if(isset($_POST['options']) && is_array($_POST['options']))
		{
			$config['options'] = array();
			
			foreach(array('additional', 'cookies', 'exclude') as $field)
			{
				if(!empty($_POST['options'][$field]))
				{
					$config['options'][$field] = preg_split('/(,|\n|\r)/', $_POST['options'][$field]);
					$config['options'][$field] = array_map('trim', $config['options'][$field]);
					$config['options'][$field] = array_map('sanitize_text_field', $config['options'][$field]);
					$config['options'][$field] = array_filter($config['options'][$field]);
				}
			}

			$config['options']['statuscode'] = isset($_POST['options']['statuscode']) ? intval($_POST['options']['statuscode']) : false;
			$config['options']['revalidate'] = !empty($_POST['options']['revalidate']);
			$config['options']['compress-html'] = !empty($_POST['options']['compress-html']);
		}
		
		return $config;
	}
}