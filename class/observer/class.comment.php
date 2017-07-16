<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Observer class for comment changes
 */

class XLII_Cache_Comment_Observer extends XLII_Cache_Singleton
{
	/**
	 * Setup the default comment observer
	 */
	protected function __construct()
	{
		if(!XLII_Cache_Manager::option('comment.enabled'))
			return;
		
		add_action('transition_comment_status', array($this, '_observeStatus'), 20, 3);
		
		// -- Comment changes
		add_action('comment_post', array($this, 'flush'), 20);
		add_action('edit_comment', array($this, 'flush'), 20);
		add_action('delete_comment', array($this, 'flush'), 0);
	}
	
	/**
	 * Flush the comment matching the specified ID.
	 * 
	 * @param	int $comment_id The id the comment is referred by.
	 * @return	bool
	 */
	public function flush($comment_id)
	{
		if(!$comment = get_comment($comment_id))
			return false;
			
		if(intval($comment->comment_approved) !== 1)
			return false;
			
		$types = XLII_Cache_Manager::option('comment.type');
		$type = $comment->comment_type;
		$type = $type ? $type : 'comment';
		
		if(!$types || !in_array($type, $types))
			return false;
		
		return cache_flush_post($comment->comment_post_ID);
	}
	
	/**
	 * Observe modifications made to the comment status
	 * 
	 * @param	enum $new_status The new comment status
	 * @param	enum $old_status The old comment status
	 * @param	stdClass $comment_id The id the comment is referred by.
	 * @access	private
	 */
	public function _observeStatus($new_status, $old_status, $comment)
	{
		if($old_status != 'approved' && $new_status != 'approved')
			return;
		
		$types = XLII_Cache_Manager::option('comment.type');
		$type = $comment->comment_type;
		$type = $type ? $type : 'comment';
		
		if($types && in_array($type, $types))
			cache_flush_post($comment->comment_post_ID);
	}
}