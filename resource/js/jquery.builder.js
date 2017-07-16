/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Management interface for the builder page
 */


(function ( $ ) {
	
	// ---------------------------------------------------------------------------------------------
	// -- Timer class
	var Timer = function ( el )
	{
		this.$element = el instanceof jQuery ? el : $(el)
		this.time = 0
		this.interval = null
	} 
	
	// -- Class definition
	Timer.prototype = 
	{
		constructor: Timer
		
		/**
		 * Returns wether the timer is still running 
		 * 
		 * @return	bool
		 */
		, isRunning : function()
		{
			return this.interval !== null
		}
		
		/**
		 * Reset the timer
		 *
		 * @return Timer
		 */
		, reset : function ()
		{
			this.time = 0
			
			return this
		}
		
		/**
		 * Start the timer interval
		 * 
		 * @return	Timer
		 */
		, start : function ()
		{
			if(!this.isRunning())
				this.interval = setInterval(this._update.bind(this), 1000)
			
			return this
		}
		
		/**
		 * Stop/pause the timer interval
		 * 
		 * @return	Timer
		 */
		, stop : function ()
		{
			if(this.isRunning())
			{	
				clearInterval(this.interval)
				this.interval = null
			}
			
			return this
		}
		
		/**
		 * Internal method used to update the timer
		 * 
		 * @access	private
		 */
		, _update : function ()
		{
			this.time++
			
			var hours = Math.floor(this.time / 3600)
			var minutes = Math.floor(this.time / 60) - hours * 60
			var sec = this.time % 60
			
			var time = (hours ? hours + ':' : '') + 
					   (minutes < 10 ? '0' + minutes : minutes) + ':' +
					   (sec < 10 ? '0' + sec : sec)

			
			this.$element.html(time)
		}
	}
	
	// ---------------------------------------------------------------------------------------------
	// -- Dashboard class
	var Dashboard = function ( el )
	{
		this.$element = $(el)
		this.timer = new Timer(this.$element.find('.timer'))
		
		// -- API Interface
		var that = this
		
		this.$element.on('click', '.pause-toggle', function(e){
			e.preventDefault()
			
			that.getState() != 'idle' ? that.stop() : that.start()
		})

		$(document).on('processed.urls.builder', this._update.bind(this))

		// -- Start progressing
		//this.stop()
	} 
	
	// -- Class definition
	Dashboard.prototype = {

		constructor: Dashboard

		/**
		 * Return the current state of the form
		 *
		 * @return    enum
		 */
		, getState: function ()
		{
			var state = this.$element[0].className.match(/state-([a-z]+)/)
			state = state ? state[1] : 'idle'

			return state
		}

		/**
		 * Modify the state of the form
		 *
		 * @param    enum state The new state of the form
		 * @return    Form
		 */
		, setState: function (state)
		{
			this.$element[0].className = this.$element[0].className.replace(/state-[a-z]+/, '')

			if (state && state != 'idle')
				this.$element.addClass('state-' + state)

			return this
		}

		/**
		 * Stop the entire dashboard processing
		 *
		 * @return    Dashboard
		 */
		, start: function ()
		{
			this.timer.start()
			
			this._update()
			this.setState('running')
			
			$(document).trigger('start.builder')

			return this
		}

		/**
		 * Stop the entire dashboard processing
		 *
		 * @return    Dashboard
		 */
		, stop: function ()
		{
			this.timer.stop()
			this.setState('idle')
			
			$(document).trigger('stop.builder')

			return this
		}

		/**
		 * Update dashboard progression
		 *
		 * @access    private
		 * @return    Dashboard
		 */
		, _update: function ()
		{
			var rows = $('table.table-urls tbody tr')

			var total = rows.length
			var processed = rows.filter('.processed').length

			// -- update count
			this.$element.find('.url-count .count').text(processed)
			this.$element.find('.url-count .total').text(total)

			// -- update progress
			if (!processed)
			{
				this.$element.find('.progress').text('0%')
			}
			else if (processed == total)
			{
				this.$element.find('.progress').text('100%')
				this.stop()
			}
			else
			{
				// -- Round progression to 2 decimals
				progress = Math.round((processed / total) * 10000)
				progress /= 100

				this.$element.find('.progress').text(progress + '%')
			}
			
			
			// -- update current speed
			if(processed && this.timer.time)
			{
				var speed = processed / this.timer.time
					speed = Math.floor(speed * 60)
				
				this.$element.find('.urls-per-minute').html(speed)
			}

			return this
		}
	}
	
	// ---------------------------------------------------------------------------------------------
	// -- URLrequest class
	var URLrequest = function( el, dashboard )
	{
		this.$element = $(el)
		this.dashboard = dashboard

		// Options
		this.nodes = 0
		
		this.urls = {
			offset : 0,
			limit : 50
		}

		// Start getting the links
		this._getLinks()
		
		// Attach run command to builder
		$(document).on('start.builder', this.start.bind(this))
	}

	URLrequest.prototype = 
	{
		constructor: URLrequest

		/**
		 * Retrieve all links and add them to the table
		 */
		, _getLinks : function ()
		{
			var type = this.$element.attr('id').replace('table-', '')
				type = type.split('-')
			
			$.ajax({    
				url: ajaxurl,
				
				dataType: 'json', 
				data: {
					action : 'cache-request-url',
					
					type : type[0],
					object : type.slice(1).join('-'),
					
					offset : this.urls.offset,
					limit : this.urls.limit
				},
				
				type: 'post',
				context: this,
				
			    success: function( urls ) 
			    {
					this.urls.offset += urls.length
				
					var columns = ''
					
					this.$element.find('thead th:not(:first-child)').each(function(){
						columns += '<td class = "' + this.className + '"></td>'
					})
					
					for(i = 0; i < urls.length; i++)
			    	{
						this.$element.find('tbody').append(
							'<tr id = "' + this.$element.attr('id') + '-' + (this.urls.offset + i) + '">' + 
								'<td class = "column-path"><strong>' + urls[i] + '</strong></td>' + 
								columns + 
							'</tr>'
						)
			    		
			    	}
				
					this.$element.find('tbody tr:odd').addClass('alternate')
					this.$element.parents('.postbox').find('.url-count .total').html(this.getUrlCount('total'))
			
					// -- Post process, fetch other urls if response equals the limit, otherwise start fetching
					if(urls.length == this.urls.limit)
						this._getLinks()
					else
						this.start()
				} 
			})
		}
		
		/**
		 * Return the url count of this section
		 * 
		 * @param	enum type (processed|processing) Narrow the count down to a specific processing list.
		 * @return	int
		 */
		, getUrlCount : function ( type )
		{
			if(type == 'processed')
				return this.$element.find('tbody tr.processed').length
			else if(type == 'processing')
				return this.$element.find('tbody tr.processing').length
			else
				return this.$element.find('tbody tr').length
		}
		
		/**
		 * Return the amount of urls processed per batch
		 * 
		 * @return	int
		 */
		, getBatchSize : function ()
		{
			var val = $('input.batch-size').val()
				val = val ? parseInt(val) : 5
				val = val ? val : 5
				
			return val
		}
		
		/**
		 * Return the timeout taken between each batch
		 * 
		 * @return	int
		 */
		, getBatchTimeout : function ()
		{
			var val = $('input.batch-timeout').val()
				val = val ? parseInt(val) : 3
				val = val ? val : 3
				
			return val
		}
		
		/**
		 * Returns wether the request is currently in a paused mode
		 * 
		 * @return	bool
		 */
		, isPaused : function ()
		{
			return this.dashboard.getState() != 'running'
		}
		
		/**
		 * Internal function used to loop / run the process
		 * 
		 * @access	private
		 */
		, _loop : function(el)
		{
			var rows = this.$element.find('tbody tr:not(.processing,.processed)').slice(0, this.getBatchSize())
			var urls = []
			
			if(!rows.length)
				return;
			
			this.nodes++
			
			rows.addClass('processing')
			rows.each(function(){
				urls.push($('td:first-child', this).text())
			})

			//The ajax request
			$.ajax({    
				url: ajaxurl
				, dataType : 'json'
				, data: {
					action : 'cache-proxy',
					urls : urls
				}
				
				, type: 'post'
				, context: this
				
				, complete : function ()
				{
					var that = this
					
					rows.removeClass('processing').addClass('processed')
					
					this.$element.parents('.postbox').find('.url-count .count').html(this.getUrlCount('processed'))
					this.$element.trigger('processed.urls.builder')
				
					setTimeout(function(){
						that.nodes--
						that.start()
					}, this.getBatchTimeout() * 1000)
				}
				
			    , success : function ( response ) 
			    {
					$.each(response, function ( index ) {
						
						var row = rows.eq(index)
							row.addClass('state-' + this.state)
							
						if(typeof this.error == 'undefined')
						{
							$.each(this, function( column, value ) {
								$('.' + column, row).html(value)
							})
						}
						else
						{
							// -- remove path column from colspan count
							var col = row.children().eq(1)
								col.html( this.error )
								   .attr('colspan', row.children().length - 1)
								   .addClass('column-error')
							
							col.nextAll().remove()
						}
					})
			    } 
			})
						
			return this
			
		}
	
		/**
		 * Start the processing
		 */
		, start : function ()
		{
			if(this.isPaused())
				return;
				
			for(var i = this.nodes; i < this.getBatchSize(); i++)
				this._loop()
		}
	}
	
	// -- Init module
	$(function()
	{
		var dashboard = new Dashboard('#submitdiv')
		
		$('#postbox-container').find('.table-urls').each(function() {
			new URLrequest(this, dashboard)
		})
	
		$('.slider-container input').on('change input', function(){
			$(this).parents('.slider-container').find('.slider-value').text($(this).val())
		})
	})
	
	
	// -- Toggle support
	$(document).on('click', '.handlediv', function() {
		$(this).parent().toggleClass('closed')
	})

	
}) ( window.jQuery )