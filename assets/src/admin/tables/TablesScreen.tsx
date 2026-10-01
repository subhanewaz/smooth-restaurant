/**
 * Free tables admin screen.
 *
 * Lists active tables, creates them, walks each table through the
 * domain-supplied `next_states` graph, archives them, and prints QR cards.
 * State transitions are never hard-coded: the buttons come from the API's
 * `next_states` for the table.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import type { FormEvent } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useReactToPrint } from 'react-to-print';
import { archiveTable, createTable, listTables, setTableState } from './api';
import { TableCard } from './TableCard';
import type { Table, TableState } from './types';

const STATE_LABELS: Record< TableState, string > = {
	free: __( 'Free', 'smooth-restaurant' ),
	seated: __( 'Seated', 'smooth-restaurant' ),
	ordered: __( 'Ordered', 'smooth-restaurant' ),
	needs_bill: __( 'Needs bill', 'smooth-restaurant' ),
};

/**
 * Human-readable label for a table state.
 *
 * @param state Table state.
 * @return Translated label.
 */
function stateLabel( state: TableState ): string {
	return STATE_LABELS[ state ] ?? state;
}

/**
 * Extract a message from an unknown thrown value.
 *
 * @param error Thrown value.
 * @return Error message.
 */
function errorMessage( error: unknown ): string {
	if ( error && typeof error === 'object' && 'message' in error ) {
		return String( ( error as { message: unknown } ).message );
	}

	return __( 'Something went wrong.', 'smooth-restaurant' );
}

/**
 * Render the tables screen.
 *
 * @return The screen element.
 */
export function TablesScreen() {
	const [ tables, setTables ] = useState< Table[] >( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ label, setLabel ] = useState( '' );
	const [ seats, setSeats ] = useState( 2 );
	const [ busy, setBusy ] = useState( false );
	const [ selected, setSelected ] = useState< number[] >( [] );
	const printRef = useRef< HTMLDivElement >( null );

	const reload = useCallback( async () => {
		try {
			setError( null );
			setTables( await listTables() );
		} catch ( thrown ) {
			setError( errorMessage( thrown ) );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		void reload();
	}, [ reload ] );

	const print = useReactToPrint( {
		content: () => printRef.current,
		documentTitle: __( 'Smooth table cards', 'smooth-restaurant' ),
		pageStyle: '@page { size: A4 portrait; margin: 12mm; }',
	} );

	const handleCreate = async ( event: FormEvent ) => {
		event.preventDefault();
		setBusy( true );
		try {
			await createTable( label, seats );
			setLabel( '' );
			await reload();
		} catch ( thrown ) {
			setError( errorMessage( thrown ) );
		} finally {
			setBusy( false );
		}
	};

	const handleState = async ( table: Table, state: TableState ) => {
		setBusy( true );
		try {
			const updated = await setTableState( table.id, state );
			setTables( ( current ) =>
				current.map( ( row ) =>
					row.id === updated.id ? updated : row
				)
			);
		} catch ( thrown ) {
			setError( errorMessage( thrown ) );
		} finally {
			setBusy( false );
		}
	};

	const handleArchive = async ( table: Table ) => {
		setBusy( true );
		try {
			await archiveTable( table.id );
			setSelected( ( current ) =>
				current.filter( ( id ) => id !== table.id )
			);
			await reload();
		} catch ( thrown ) {
			setError( errorMessage( thrown ) );
		} finally {
			setBusy( false );
		}
	};

	const toggleSelected = ( id: number ) => {
		setSelected( ( current ) =>
			current.includes( id )
				? current.filter( ( value ) => value !== id )
				: [ ...current, id ]
		);
	};

	const selectedTables = tables.filter( ( table ) =>
		selected.includes( table.id )
	);

	return (
		<div className="smooth-tables">
			<h1 className="smooth-tables__title">
				{ __( 'Tables', 'smooth-restaurant' ) }
			</h1>

			{ error && <p className="smooth-tables__error">{ error }</p> }

			<form className="smooth-tables__create" onSubmit={ handleCreate }>
				<input
					className="smooth-tables__label"
					type="text"
					value={ label }
					placeholder={ __( 'Table label', 'smooth-restaurant' ) }
					onChange={ ( event ) => setLabel( event.target.value ) }
				/>
				<input
					className="smooth-tables__seats"
					type="number"
					min={ 1 }
					value={ seats }
					onChange={ ( event ) =>
						setSeats( Number( event.target.value ) )
					}
				/>
				<button type="submit" disabled={ busy || label.trim() === '' }>
					{ __( 'Add table', 'smooth-restaurant' ) }
				</button>
			</form>

			{ loading ? (
				<p>{ __( 'Loading tables…', 'smooth-restaurant' ) }</p>
			) : (
				<table className="smooth-tables__list">
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Select', 'smooth-restaurant' ) }
							</th>
							<th scope="col">
								{ __( 'Table', 'smooth-restaurant' ) }
							</th>
							<th scope="col">
								{ __( 'Seats', 'smooth-restaurant' ) }
							</th>
							<th scope="col">
								{ __( 'State', 'smooth-restaurant' ) }
							</th>
							<th scope="col">
								{ __( 'Actions', 'smooth-restaurant' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ tables.map( ( table ) => (
							<tr key={ table.id }>
								<td>
									<input
										type="checkbox"
										checked={ selected.includes(
											table.id
										) }
										aria-label={ sprintf(
											/* translators: %s: table label. */
											__(
												'Select %s',
												'smooth-restaurant'
											),
											table.label
										) }
										onChange={ () =>
											toggleSelected( table.id )
										}
									/>
								</td>
								<td>{ table.label }</td>
								<td>{ table.seats }</td>
								<td className="smooth-tables__state">
									{ stateLabel( table.state ) }
								</td>
								<td className="smooth-tables__actions">
									{ table.next_states.map( ( next ) => (
										<button
											key={ next }
											type="button"
											disabled={ busy }
											onClick={ () =>
												void handleState( table, next )
											}
										>
											{ stateLabel( next ) }
										</button>
									) ) }
									<button
										type="button"
										className="smooth-tables__archive"
										disabled={ busy }
										onClick={ () =>
											void handleArchive( table )
										}
									>
										{ __( 'Archive', 'smooth-restaurant' ) }
									</button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			<button
				type="button"
				className="smooth-tables__print"
				disabled={ selectedTables.length === 0 }
				onClick={ print }
			>
				{ sprintf(
					/* translators: %d: number of selected tables. */
					__( 'Print selected (%d)', 'smooth-restaurant' ),
					selectedTables.length
				) }
			</button>

			<div className="smooth-print-area" ref={ printRef }>
				{ selectedTables.map( ( table ) => (
					<TableCard key={ table.id } table={ table } />
				) ) }
			</div>
		</div>
	);
}
