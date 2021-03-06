<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */

class XLII_Cache_Configuration_Posts_Metabox extends XLII_Cache_Singleton
{
	const METABOX_NAME = 'configuration-posts';
	
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
		$title  = __('Purge policy - posts', 'xlii-cache');
		$title .= '<span class = "status"><input type = "checkbox" name = "post[enabled]" id = "post-enable" value = "1" ' . checked(true, XLII_Cache_Manager::option('post.enabled'), false) . ' />' .
					'<label for = "post-enable"></label>' .
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
					<?php _e('Purge', 'xlii-cache'); ?>
                </th>
				<td>
					<ul>
					<?php
						$options = array(
							'feed.comment' => __('Comment feed', 'xlii-cache'),
							'archive.daily' => __('Daily archive pages', 'xlii-cache'),
							'post.comment' => __('Post comment pages', 'xlii-cache'),
							
							'feed.author' => __('Author feed', 'xlii-cache'),
							'archive.monthly' => __('Monthly archive pages', 'xlii-cache'),
							'post.author' => __('Post author pages', 'xlii-cache'),
							
							'feed.terms' => __('Term feed', 'xlii-cache'),
							'archive.yearly' => __('Yearly archive pages', 'xlii-cache'),
							'post.term' => __('Post term pages', 'xlii-cache'),
							
							'feed.blog' => __('Blog feed', 'xlii-cache'),
							'global.front' => __('Front page', 'xlii-cache'),
							'global.posts' => __('Posts page', 'xlii-cache'),
							
							'feed.postarchive' => __('Posttype archive feed', 'xlii-cache'),
							'post.archive' => __('Posttype archive', 'xlii-cache'),
							'global.all' => __('General cache', 'xlii-cache'),
							
							'archive.term' => __('Term archives', 'xlii-cache'),
						);
					
						foreach($options as $key => $label)
						{
							echo '<li style = "width:33%;float:left;">' .	
									'<label>' .
										'<input type = "checkbox" name = "post[purge][' . str_replace('.', '][', $key) . ']" value = "on" ' . checked(XLII_Cache_Manager::option('post.purge.' . $key, false), true, false) . ' /> ' . 
										$label . 
									'</label>' .
								 '</li>';
						}
						
					?>
					</ul>
					<br style = "clear:left;" />
					<p class = "description"><small><?php _e('Specified the actions to be executed upon post mutations.', 'xlii-cache'); ?></small></p>
				</td>
            </tr>
            <tr>
                <th>
					<?php _e('Feed types', 'xlii-cache'); ?>
                </th>
				<td>
					<ul>
					<?php
						$feeds = $GLOBALS['wp_rewrite']->feeds;
						$default = get_default_feed();
						$opt['post']['feed'] = !empty($opt['post']['feed']) ? $opt['post']['feed'] : array();
					
						foreach($feeds as $feed)
						{
							echo '<li>' .	
									'<label>' .
										'<input type = "checkbox" value = "' . $feed . '" name = "post[feed][]" ' . checked(in_array($feed, $opt['post']['feed']), true, false) . ' /> ' . 
										$feed . ($feed == $default ? ' (' . __('default', 'xlii-cache') . ')' : '') . 
									'</label>' .
								 '</li>';
						}
						
					?>
					</ul>
					<p class = "description"><small><?php _e('Specify the feed types to flush upon purging.', 'xlii-cache'); ?></small></p>
				</td>
            </tr>
            <tr>
                <th>
					<label for = "post-additional"><?php _e('Additional pages', 'xlii-cache'); ?></label>
                </th>
				<td>
					<textarea cols = "80" rows = "5" name = "post[additional]" id = "post-additional"><?php echo isset($opt['post']['additional']) ? esc_textarea(implode("\n", $opt['post']['additional'])) : ''; ?></textarea>
					<p class = "description"><small><?php _e('Additional pages to purge upon post purging. Please specify only one URL per line.', 'xlii-cache'); ?></small></p>
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
		$key = 'post';
		
		$config[$key] = array(
			'purge' => array(),
			'feed' => array()
		);
		
		if(isset($_POST[$key]) && is_array($_POST[$key]))
		{
			$config[$key]['enabled'] = !empty($_POST[$key]['enabled']);
		
			if(isset($_POST[$key]['feed']))
			{
				$config[$key]['feed'] = (array)$_POST[$key]['feed'];
				$config[$key]['feed'] = array_map('sanitize_text_field', $config[$key]['feed']);
			}
		
			if(!empty($_POST[$key]['additional']))
			{
				$config[$key]['additional'] = preg_split('/(\n|\r)/', $_POST[$key]['additional']);
				$config[$key]['additional'] = array_map('trim', $config[$key]['additional']);
				$config[$key]['additional'] = array_filter($config[$key]['additional']);
			}
		
			if(isset($_POST[$key]['purge']))
			{
				$config[$key]['purge'] = (array)$_POST[$key]['purge'];
			
				$this->_processPurge($config[$key]['purge']);
			}
		}
		
		return $config;
	}
	
	/**
	 * Process the purging data
	 * 
	 * @param	array &$config The data object to cleanup.
	 */
	protected function _processPurge(array &$config)
	{
		foreach($config as &$val)
		{
			if(is_array($val))
				$this->_processPurge($val);
			else
				$val = (bool)$val;
		}
	}
}