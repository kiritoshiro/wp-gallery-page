/* Page Gallery admin screen: page picker, order, featured page and custom images. */
jQuery( function ( $ ) {
	var l10n = window.wpgpAdmin || {};
	var $form = $( '#wpgp-form' );
	if ( ! $form.length ) {
		return;
	}
	var $all = $( '#wpgp-all' );
	var $selected = $( '#wpgp-selected' );
	var $ids = $( '#wpgp-ids' );
	var $big = $( '#wpgp-big' );
	var template = document.getElementById( 'wpgp-chip-template' );
	var dirty = false;
	var frame = null;

	function format( text, value ) {
		return String( text || '' ).replace( '%s', value );
	}

	function chipFor( id ) {
		return $selected.children( '.wpgp-chip' ).filter( function () {
			return String( $( this ).data( 'id' ) ) === String( id );
		} );
	}

	function markDirty() {
		dirty = true;
		$( '.wpgp-dirty' ).prop( 'hidden', false );
	}

	function setThumb( $chip, url ) {
		var $box = $chip.find( '.wpgp-thumb' ).empty();
		if ( url ) {
			$( '<img alt="">' ).attr( 'src', url ).appendTo( $box );
		} else {
			$( '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>' ).appendTo( $box );
		}
	}

	// Hidden fields, counter and the "Featured page" line follow the list.
	function sync() {
		var $chips = $selected.children( '.wpgp-chip' );
		var ids = $chips.map( function () {
			return String( $( this ).data( 'id' ) );
		} ).get();
		$ids.val( ids.join( ',' ) );
		if ( ids.indexOf( String( $big.val() ) ) === -1 ) {
			$big.val( '0' );
		}
		$( '#wpgp-selected-count' ).text( ids.length );
		$( '.wpgp-selected-empty' ).prop( 'hidden', ids.length > 0 );

		var bigId = String( $big.val() );
		var newest = null;
		$chips.each( function () {
			var $chip = $( this );
			var on = String( $chip.data( 'id' ) ) === bigId;
			$chip.toggleClass( 'is-featured', on );
			$chip.find( '.wpgp-star' ).attr( 'aria-pressed', on ? 'true' : 'false' )
				.find( '.dashicons' ).toggleClass( 'dashicons-star-filled', on ).toggleClass( 'dashicons-star-empty', ! on );
			if ( ! newest || Number( $chip.data( 'date' ) ) > Number( newest.data( 'date' ) ) ) {
				newest = $chip;
			}
		} );
		var label;
		if ( bigId !== '0' ) {
			label = format( l10n.chosen, chipFor( bigId ).find( '.wpgp-chip-title' ).text() );
		} else if ( newest ) {
			label = format( l10n.auto, newest.find( '.wpgp-chip-title' ).text() );
			newest.addClass( 'is-auto' ).siblings().removeClass( 'is-auto' );
		} else {
			label = l10n.autoNone;
		}
		if ( bigId !== '0' ) {
			$chips.removeClass( 'is-auto' );
		}
		$( '#wpgp-featured-label' ).text( label );
	}

	function addChip( $item ) {
		var id = $item.find( '.wpgp-toggle' ).val();
		if ( chipFor( id ).length ) {
			return;
		}
		var $chip = $( template.content.firstElementChild.cloneNode( true ) );
		$chip.attr( 'data-id', id ).data( 'id', id );
		$chip.attr( 'data-date', $item.data( 'date' ) ).data( 'date', $item.data( 'date' ) );
		$chip.attr( 'data-thumb', $item.data( 'thumb' ) || '' ).data( 'thumb', $item.data( 'thumb' ) || '' );
		$chip.find( '.wpgp-chip-title' ).text( $item.find( '.wpgp-item-title' ).text() );
		var $status = $item.find( '.wpgp-badge--status' );
		if ( $status.length ) {
			$chip.find( '.wpgp-badge--featured' ).after( $status.clone() );
		}
		$chip.find( '.wpgp-image-url' ).attr( 'name', $chip.find( '.wpgp-image-url' ).attr( 'name' ).replace( '[0]', '[' + id + ']' ) );
		setThumb( $chip, $item.data( 'thumb' ) );
		$selected.append( $chip );
	}

	$all.on( 'change', '.wpgp-toggle', function () {
		var $item = $( this ).closest( '.wpgp-item' );
		if ( this.checked ) {
			addChip( $item );
			$selected.scrollTop( $selected.prop( 'scrollHeight' ) );
		} else {
			chipFor( this.value ).remove();
		}
		sync();
		markDirty();
	} );

	$selected.sortable( {
		handle: '.wpgp-handle',
		items: '> .wpgp-chip',
		axis: 'y',
		placeholder: 'wpgp-chip-placeholder',
		update: function () {
			sync();
			markDirty();
		}
	} );

	$selected.on( 'click', '.wpgp-star', function () {
		var id = String( $( this ).closest( '.wpgp-chip' ).data( 'id' ) );
		$big.val( String( $big.val() ) === id ? '0' : id );
		sync();
		markDirty();
	} );

	$selected.on( 'click', '.wpgp-remove', function () {
		var id = $( this ).closest( '.wpgp-chip' ).data( 'id' );
		$( this ).closest( '.wpgp-chip' ).remove();
		$all.find( '.wpgp-toggle' ).filter( function () {
			return String( this.value ) === String( id );
		} ).prop( 'checked', false );
		sync();
		markDirty();
	} );

	$selected.on( 'click', '.wpgp-image', function () {
		var $chip = $( this ).closest( '.wpgp-chip' );
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		if ( frame ) {
			frame.off( 'select' );
		} else {
			frame = wp.media( {
				title: l10n.chooseImage,
				button: { text: l10n.useImage },
				multiple: false,
				library: { type: 'image' }
			} );
		}
		frame.on( 'select', function () {
			var file = frame.state().get( 'selection' ).first().toJSON();
			var url = file.sizes && file.sizes.large ? file.sizes.large.url : file.url;
			$chip.find( '.wpgp-image-url' ).val( url );
			$chip.addClass( 'has-custom' );
			setThumb( $chip, url );
			markDirty();
		} );
		frame.open();
	} );

	$selected.on( 'click', '.wpgp-reset', function () {
		var $chip = $( this ).closest( '.wpgp-chip' );
		$chip.find( '.wpgp-image-url' ).val( '' );
		$chip.removeClass( 'has-custom' );
		setThumb( $chip, $chip.data( 'thumb' ) );
		markDirty();
	} );

	$( '#wpgp-search' ).on( 'input', function () {
		var term = $.trim( this.value ).toLowerCase();
		var shown = 0;
		$all.find( '.wpgp-item' ).each( function () {
			var match = ! term || String( $( this ).data( 'search' ) ).indexOf( term ) !== -1;
			$( this ).prop( 'hidden', ! match );
			shown += match ? 1 : 0;
		} );
		$all.find( '.wpgp-no-match' ).prop( 'hidden', shown > 0 || ! term );
	} ).on( 'keydown', function ( event ) {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
		}
	} );

	$( '.wpgp-copy' ).on( 'click', function () {
		var $button = $( this );
		var text = String( $button.data( 'copy' ) );
		var done = function () {
			var old = $button.text();
			$button.text( l10n.copied );
			setTimeout( function () {
				$button.text( old );
			}, 1500 );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done );
		}
	} );

	$form.on( 'change input', 'input:not(.wpgp-search):not(.wpgp-toggle), select', markDirty );
	$form.on( 'submit', function () {
		dirty = false;
	} );
	$( window ).on( 'beforeunload', function () {
		return dirty ? l10n.unsaved : undefined;
	} );

	sync();
} );
