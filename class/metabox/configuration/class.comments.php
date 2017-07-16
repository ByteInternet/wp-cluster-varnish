<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Configuration_Comments_Metabox extends XLII_Cache_Singleton
{
	const METABOX_NAME = 'configuration-comments';
	
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
		$title  = __('Purge policy - comments', 'xlii-cache');
		$title .= '<span class = "status"><input type = "checkbox" name = "comment[enabled]" id = "comment-enable" value = "1" ' . checked(true, XLII_Cache_Manager::option('comment.enabled'), false) . ' />' .
					'<label for = "comment-enable"></label>' .
				  '</span>';
		
		add_meta_box(self::METABOX_NAME, $title, array($this, 'render'), 'cache-configuration', 'normal');
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
					<?php _e('Comment types', 'xlii-cache'); ?>
                </th>
				<td>
					<ul>
					<?php
						global $wpdb;
					
						$types = $wpdb->get_col('SELECT DISTINCT comment_type FROM ' . $wpdb->comments);
						$types = array_filter($types);
						
						array_unshift($types, 'comment');
						array_unshift($types, 'pingback');
						array_unshift($types, 'trackback');
						
						$types = array_unique($types);
						
						foreach($types as $type)
						{
							echo '<li>' .	
									'<label>' .
										'<input type = "checkbox" value = "' . $type . '" name = "comment[type][]" ' . checked(in_array($type, $opt['comment']['type']), true, false) . ' /> ' . 
									 	$type . 
									'</label>' .
								 '</li>';
						}
						
					?>
					</ul>
					<p class = "description"><small><?php _e('Specify the comment types to allow purgin on.', 'xlii-cache'); ?></small></p>
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
		$config['comment'] = array(
			'enabled' => false,
			'type' => array()
		);
	
		if(isset($_POST['comment']) && is_array($_POST['comment']))
		{
			$config['comment']['enabled'] = !empty($_POST['comment']['enabled']);
			
			if(isset($_POST['comment']['type']))
			{
				$config['comment']['type'] = (array)$_POST['comment']['type'];
				$config['comment']['type'] = array_map('sanitize_title', $config['comment']['type']);
			}
		}
		
		return $config;
	}
}