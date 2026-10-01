/**
 * Smooth admin entrypoint.
 *
 * Mounts the React screen declared by the server-rendered root element
 * (`<div id="smooth-admin-root" data-screen="tables">`). Assets are
 * enqueued by AssetsProvider's smooth admin screen gate.
 */
import { createRoot } from 'react-dom/client';
import { TablesScreen } from '@/admin/tables/TablesScreen';
import './index.scss';

/**
 * Mount every not-yet-mounted Smooth admin screen.
 */
function mount(): void {
	document
		.querySelectorAll< HTMLElement >( '[data-screen="tables"]' )
		.forEach( ( node ) => {
			if ( node.dataset.smoothMounted === 'true' ) {
				return;
			}
			node.dataset.smoothMounted = 'true';
			createRoot( node ).render( <TablesScreen /> );
		} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
