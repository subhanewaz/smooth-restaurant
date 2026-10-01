/**
 * Smooth admin entrypoint.
 *
 * Mounts the React screen declared by the server-rendered root element
 * (`<div id="smooth-admin-root" data-screen="tables">`). The `data-screen`
 * value mirrors `AdminProvider::SCREEN_TABLES` on the PHP side. Styles are
 * imported by the screen itself and the bundle is enqueued by
 * AssetsProvider's smooth admin screen gate.
 */
import { createRoot } from 'react-dom/client';
import { TablesScreen } from '@/admin/tables/TablesScreen';

const SCREEN_TABLES = 'tables';

/**
 * Mount every not-yet-mounted Smooth admin screen.
 */
function mount(): void {
	document
		.querySelectorAll< HTMLElement >( `[data-screen="${ SCREEN_TABLES }"]` )
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
