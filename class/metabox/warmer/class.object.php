<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * 
 */


class XLII_Cache_Warmer_Object_Metabox
{ 
	const METABOX_NAME = 'warmer-object';
	
	private $_context;
	private $_type;
	private $_title;
	
	/**
	 * Setup the offer metabox.
	 */
	protected function __construct($context)
	{
		$this->_context = $context = sanitize_title($context);
 	   	$parts  = explode('-', $context, 2);
		$mapper = array(
			'post' => __('Posts', 'xlii-cache'),
			'term' => __('Taxonomy', 'xlii-cache'),
			'taxonomy' => __('Taxonomy', 'xlii-cache'),
			'user' => __('User', 'xlii-cache')
		);
		
		
		if(empty($parts[0]) || empty($mapper[$parts[0]]))
			return;
		
	    $title = $mapper[$parts[0]];

		if (!empty($parts[1])) 
		{
			switch($parts[0])
			{
				case 'term':
					global $wpdb;
				
					$term = !empty($parts[1]) ? intval($parts[1]) : 0;
					$term = $wpdb->get_row($wpdb->prepare('SELECT term_id, taxonomy FROM ' . $wpdb->term_taxonomy . ' WHERE term_taxonomy_id = %d', $term));
			
					if(!empty($term) && ($tax = get_taxonomy($term->taxonomy)) && $term = get_term($term->term_id, $term->taxonomy))
						$title .= ' <small>[' . $tax->labels->singular_name . '] [' . $term->name . ']</small>';
					else
						continue;
			
					break;
			
				case 'taxonomy':
					if($tax = get_taxonomy($parts[1]))
						$title .= ' <small>[' . $tax->labels->singular_name . ']</small>';
					else
						continue;
			
					break;
			
				case 'post':
					if(is_numeric($parts[1]) && $post = get_post($parts[1]))
					{
						$type   = get_post_type_object($post->post_type);
						$title .= ' <small>[' . $type->labels->singular_name . '] [' . esc_html($post->post_title) . ']</small>';
					}
					else
					{
						continue;
					}
			
					break;
			
				case 'user':
					if(is_numeric($parts[1]) && $user = get_userdata($parts[1]))
						$title .= ' <small>[' . $user->display_name . ']</small>';
					else
						continue;
			
					break;
			}	
	    } 
	
		$this->_title = $title;
		$this->_type = $parts[0];
		
		add_action('add_cache_warmer_meta_boxes', array($this, '_register'));
	}
		
	/**
	 * Register the metabox
	 * 
	 * @access	private
	 */
	public function _register()
	{
		$title = '<span>' . $this->_title . '</span>' .
					'<div class = "url-count url-count-' . $this->_context . '">' .
					'<span class = "count">0</span>/<span class = "total">0</span>' .
				 '</div>';
		
		add_meta_box(self::METABOX_NAME . '-' . $this->_type, $title, array($this, 'render'), 'cache-warmer', 'normal');
	}
	
	/**
	 * Render our custom metabox
	 * 
	 */	
	public function render()
	{	
		
		echo '<table id="table-' . $this->_context . '" class = "wp-list-table widefat fixed table-urls table-' . $this->_context . '">' .
				'<thead>' .
					'<tr>' .
						'<th class = "column-path">' . __('Url', 'xlii-cache') . '</th>' .
						'<th class = "column-statuscode">' . __('Status', 'xlii-cache') . '</th>' .
						'<th class = "column-load-time">' . __('Load time', 'xlii-cache') . '</th>';

		do_action('cache_request_columns_' . $this->_type, $this->_context);
		do_action('cache_request_columns', $this->_context);


		echo  		'</tr>' .
				'</thead>' .
				'<tbody>';

		echo 	'</tbody>' .
			 '</table>';
	}
	
	/**
	 * Initialize
	 */
	static public function init()
	{
		$contexts = !empty($_GET['context']) ? (array)$_GET['context'] : array('post', 'taxonomy','user');
		
		
		foreach($contexts as $context)
			new self($context);
	}
}