{
	const BOX_SELECTOR = '.about-us'; 
	const ERROR_TEXT = 'Could not load the About Us content.';

	const boxes = document.querySelectorAll( BOX_SELECTOR );

	for ( const box of boxes ) {
		fetch( box.dataset.url )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'Request failed' );
				}
				return response.json();
			} )
			.then( ( rows ) => {
				for ( const row of rows ) {
					const title = document.createElement( 'h2' );
					title.textContent = row.title;

					const text = document.createElement( 'p' );
					text.textContent = row.body;
					text.style.whiteSpace = 'pre-line'; 

					box.append( title, text );
				}
			} )
			.catch( () => {
				box.textContent = ERROR_TEXT;
			} );
	}
}
