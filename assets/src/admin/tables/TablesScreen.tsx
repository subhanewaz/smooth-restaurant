/**
 * Tables & QR admin screen.
 *
 * Lists active tables, creates them, walks each table through the
 * domain-supplied `next_states` graph, removes them, and prints QR cards.
 * Transition targets are never hard-coded: the select is built from the API's
 * `next_states` for that table, plus the current state so the control always
 * has a valid selection.
 */
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useReactToPrint } from 'react-to-print';
import { archiveTable, createTable, listTables, setTableState } from './api';
import { QrCard } from './QrCard';
import type { Table, TableState } from './types';
import './tables.scss';

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

interface StateOption {
	value: string;
	label: string;
}

/**
 * Select options for one table: the current state plus its next states.
 *
 * @param table Table row.
 * @return Select options.
 */
function stateOptions( table: Table ): StateOption[] {
	const values = [ table.state, ...table.next_states ];

	return values.map( ( value ) => ( {
		value,
		label: stateLabel( value as TableState ),
	} ) );
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
	const [ seats, setSeats ] = useState( '2' );
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

	const handleCreate = async ( event: React.FormEvent ) => {
		event.preventDefault();
		setBusy( true );
		try {
			await createTable( label, Number( seats ) );
			setLabel( '' );
			await reload();
		} catch ( thrown ) {
			setError( errorMessage( thrown ) );
		} finally {
			setBusy( false );
		}
	};

	const handleState = async ( table: Table, state: string ) => {
		if ( state === table.state ) {
			return;
		}
		setBusy( true );
		try {
			const updated = await setTableState(
				table.id,
				state as TableState
			);
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

	const handleRemove = async ( table: Table ) => {
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

	const selectedTables = useMemo(
		() => tables.filter( ( table ) => selected.includes( table.id ) ),
		[ selected, tables ]
	);

	return (
		<div className="smooth-tables">
			<h1 className="smooth-tables__title">
				{ __( 'Tables & QR', 'smooth-restaurant' ) }
			</h1>

			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Card className="smooth-tables__create">
				<CardBody>
					<form onSubmit={ handleCreate }>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Table label', 'smooth-restaurant' ) }
							value={ label }
							onChange={ setLabel }
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="number"
							min={ 1 }
							label={ __( 'Seats', 'smooth-restaurant' ) }
							value={ seats }
							onChange={ setSeats }
						/>
						<Button
							variant="primary"
							type="submit"
							disabled={ busy || label.trim() === '' }
						>
							{ __( 'Add table', 'smooth-restaurant' ) }
						</Button>
					</form>
				</CardBody>
			</Card>

			{ loading ? (
				<Spinner />
			) : (
				<table className="widefat striped smooth-tables__list">
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
								<td>
									<div className="smooth-tables__actions">
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ sprintf(
												/* translators: %s: table label. */
												__(
													'Change state for %s',
													'smooth-restaurant'
												),
												table.label
											) }
											hideLabelFromVision
											disabled={ busy }
											value={ table.state }
											options={ stateOptions( table ) }
											onChange={ ( value: string ) =>
												void handleState( table, value )
											}
										/>
										<Button
											isDestructive
											variant="link"
											disabled={ busy }
											onClick={ () =>
												void handleRemove( table )
											}
										>
											{ __(
												'Remove',
												'smooth-restaurant'
											) }
										</Button>
									</div>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			<Button
				className="smooth-tables__print"
				variant="secondary"
				disabled={ selectedTables.length === 0 }
				onClick={ print }
			>
				{ sprintf(
					/* translators: %d: number of selected tables. */
					__( 'Print selected (%d)', 'smooth-restaurant' ),
					selectedTables.length
				) }
			</Button>

			<div className="smooth-qr-print" ref={ printRef }>
				{ selectedTables.map( ( table ) => (
					<QrCard key={ table.id } table={ table } />
				) ) }
			</div>
		</div>
	);
}
