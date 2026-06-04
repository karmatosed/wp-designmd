import { createRoot } from '@wordpress/element';
import '@wordpress/theme/design-tokens.css';
import './index.css';
import App from './App';

const mountNode = document.getElementById( 'wp-designmd-admin' );

if ( ! mountNode ) {
	// eslint-disable-next-line no-console
	console.error( 'wp-designmd: mount node #wp-designmd-admin not found.' );
} else {
	try {
		createRoot( mountNode ).render( <App /> );
	} catch ( error ) {
		const message = error instanceof Error ? error.message : String( error );
		mountNode.innerHTML = `<div class="notice notice-error"><p>DesignMD failed to load: ${ message }</p></div>`;
		// eslint-disable-next-line no-console
		console.error( 'wp-designmd:', error );
	}
}
