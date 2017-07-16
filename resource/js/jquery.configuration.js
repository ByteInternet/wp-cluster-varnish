/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Management interface for the configuration page
 */


window.jQuery(function ( $ ) {
	
	$('.postbox .screen-reader-text').remove()
	
	$(document).on('change', '.wp-admin .hndle .status input', function(){
		$(this).parents('.hndle')
					.siblings('.inside')
					.css('opacity', $(this).is(':checked') ? 1 : 0.5)
	})

	$('.wp-admin .hndle .status input').trigger('change')


	$(document).on('click', '.handlediv', function(){
		$(this).parent().toggleClass('closed')
	})

	$(document).on('change', '#cache-engine', function(){
		$('.engine-section')
			.hide()
			.filter('.engine-' + $(this).val())
			.show()
	})

	$('#cache-engine').trigger('change')

	$(document).on('click', '.code-sample .tab-nav a', function(e){
		e.preventDefault()

		$(this).parent()	
				.addClass('active')
				.siblings().removeClass('active')

		$(this).parents('.code-sample')
				.find('code')
				.removeClass('active')
				.filter('.' + $(this).attr('href').substr(1))
					.addClass('active')
	})

	$('.code-sample .tab-nav').each(function(){ $('li:eq(0) a', this).click() })
	
	// Example tester
	$('.match-field').each(function(){
		
		var $element = $(this)
		var $source = $('#' + $element.data('source'))
		var $match = $element.siblings('.match-result')
		
		var no_match = $match.find('span').text()
		
		$element.siblings('button').on('click', function(e){
			e.preventDefault()
			
			var url = $element.val()
			var result = no_match
			
			if(!url || !$source.val())
				return $match.hide();
			
			$.each($source.val().split("\n"), function(k, match){
				
				if(new RegExp(match, 'i').test(url))
				{
					result = match
					
					return false;
				}
			})
		
		
			$match.show().find('span').html(result)
		})
		
	})
	
});