/**
 * Free tables admin screen tests.
 *
 * Verifies the screen renders the API list, exposes only the domain-supplied
 * `next_states` as transition buttons, and triggers the print action for the
 * selected tables.
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { useReactToPrint } from 'react-to-print';
import { TablesScreen } from '@/admin/tables/TablesScreen';
import type { Table } from '@/admin/tables/types';

jest.mock( '@wordpress/api-fetch' );
jest.mock( 'react-to-print' );

const mockPrint = jest.fn();
const apiFetchMock = apiFetch as jest.MockedFunction< typeof apiFetch >;
const useReactToPrintMock = useReactToPrint as jest.MockedFunction<
	typeof useReactToPrint
>;

const PATIO: Table = {
	id: 1,
	label: 'Patio',
	seats: 4,
	state: 'free',
	next_states: [ 'seated' ],
	qr_url: 'http://example.test/menu/?table=Patio',
};

beforeEach( () => {
	jest.clearAllMocks();
	useReactToPrintMock.mockReturnValue( mockPrint );
	apiFetchMock.mockResolvedValue( [ PATIO ] );
} );

describe( 'TablesScreen', () => {
	it( 'renders tables returned by the REST list', async () => {
		render( <TablesScreen /> );

		expect( await screen.findByText( 'Patio' ) ).toBeInTheDocument();
		expect( screen.getByText( '4' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Free' ) ).toBeInTheDocument();
	} );

	it( 'exposes only the domain-supplied next states as actions', async () => {
		render( <TablesScreen /> );

		await screen.findByText( 'Patio' );

		expect(
			screen.getByRole( 'button', { name: 'Seated' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Ordered' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Needs bill' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Free' } )
		).not.toBeInTheDocument();
	} );

	it( 'requests the next state through the API when a transition is clicked', async () => {
		const user = userEvent.setup();
		apiFetchMock.mockResolvedValueOnce( [ PATIO ] ).mockResolvedValueOnce( {
			...PATIO,
			state: 'seated',
			next_states: [ 'ordered', 'free' ],
		} );

		render( <TablesScreen /> );
		await screen.findByText( 'Patio' );

		await user.click( screen.getByRole( 'button', { name: 'Seated' } ) );

		await waitFor( () =>
			expect( screen.getByText( 'Seated' ) ).toBeInTheDocument()
		);
		expect( apiFetchMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/smooth/v1/tables/1/state',
				method: 'POST',
				data: { state: 'seated' },
			} )
		);
	} );

	it( 'prints the selected tables', async () => {
		const user = userEvent.setup();
		render( <TablesScreen /> );
		await screen.findByText( 'Patio' );

		await user.click(
			screen.getByRole( 'checkbox', { name: 'Select Patio' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Print selected (1)' } )
		);

		expect( mockPrint ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByText( 'Scan to view the menu' )
		).toBeInTheDocument();
	} );
} );
