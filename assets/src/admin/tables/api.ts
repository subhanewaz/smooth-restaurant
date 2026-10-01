/**
 * Rest bindings for the Free tables admin screen.
 *
 * Uses `@wordpress/api-fetch`, so the REST root URL and nonce come from
 * WordPress itself: no localized data is passed from PHP.
 */
import apiFetch from '@wordpress/api-fetch';
import type { ArchivedTable, Table, TableState } from './types';

const BASE = '/smooth/v1/tables';

/**
 * List active tables.
 *
 * @return Promise resolving to the active tables.
 */
export function listTables(): Promise< Table[] > {
	return apiFetch< Table[] >( { path: BASE } );
}

/**
 * Create a table.
 *
 * @param label Table label.
 * @param seats Seat count.
 * @return Promise resolving to the created table.
 */
export function createTable( label: string, seats: number ): Promise< Table > {
	return apiFetch< Table >( {
		path: BASE,
		method: 'POST',
		data: { label, seats },
	} );
}

/**
 * Move a table to one of its valid next states.
 *
 * @param id    Table id.
 * @param state Target state, taken from the table's `next_states`.
 * @return Promise resolving to the updated table.
 */
export function setTableState(
	id: number,
	state: TableState
): Promise< Table > {
	return apiFetch< Table >( {
		path: `${ BASE }/${ id }/state`,
		method: 'POST',
		data: { state },
	} );
}

/**
 * Archive a table.
 *
 * @param id Table id.
 * @return Promise resolving to the archive acknowledgement.
 */
export function archiveTable( id: number ): Promise< ArchivedTable > {
	return apiFetch< ArchivedTable >( {
		path: `${ BASE }/${ id }`,
		method: 'DELETE',
	} );
}
