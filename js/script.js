jQuery( document ).ready( function ( $ )
{

	/**
	 * Split string into multiple values, separated by commas
	 *
	 * @param val
	 *
	 * @return array
	 */
	function my_split( val )
	{
		return val.split( /,\s*/ );
	}

	/**
	 * Extract string Last into multiple values
	 * @param term
	 *
	 */
	function extract_last( term )
	{
		return my_split( term ).pop();
	}

	$( '#recipient' ).autocomplete( {
		source: function ( request, response )
		{
			var data = {
				action: 'get_users',
				term  : extract_last( request.term )
			};
			$.post( ajaxurl, data, function ( r )
			{
				response( r );
			}, 'json' );
		},
		select: function ( event, ui )
		{
			var terms = my_split( this.value );
			terms.pop();
			terms.push( ui.item.value );
			terms.push( "" );
			this.value = terms.join( "," );
			return false;
		}
	});
});