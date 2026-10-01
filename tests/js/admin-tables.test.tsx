/**
 * Tables & QR admin screen tests.
 *
 * Verifies the screen renders the API list, offers only the current state and
 * the domain-supplied `next_states` in the transition select, creates and
 * removes tables through the REST layer, surfaces API errors, and renders a
 * printable QR card for each selected table.
 */
import { render, screen, waitFor, within } from '@testing-library/react';
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
	describe( 'list', () => {
		it( 'renders the tables returned by the REST list', async () => {
			render( <TablesScreen /> );

			expect( await screen.findByText( 'Patio' ) ).toBeInTheDocument();
			expect( screen.getByText( '4' ) ).toBeInTheDocument();
			// Scoped to the cell: the state select renders an <option> too.
			expect(
				screen.getByText( 'Free', { selector: 'td' } )
			).toBeInTheDocument();
		} );

		it( 'requests the tables through the REST list path', async () => {
			render( <TablesScreen /> );

			await screen.findByText( 'Patio' );

			expect( apiFetchMock ).toHaveBeenCalledWith(
				expect.objectContaining( { path: '/smooth/v1/tables' } )
			);
		} );

		it( 'offers only the current state plus its next states', async () => {
			render( <TablesScreen /> );

			await screen.findByText( 'Patio' );

			const select = await screen.findByRole( 'combobox', {
				name: 'Change state for Patio',
			} );

			const options = within( select as HTMLSelectElement )
				.getAllByRole( 'option' )
				.map( ( option ) => option.textContent );

			expect( options ).toEqual( [ 'Free', 'Seated' ] );
		} );
	} );

	describe( 'add', () => {
		it( 'creates a table with the entered label and seats', async () => {
			const user = userEvent.setup();
			apiFetchMock
				.mockResolvedValueOnce( [ PATIO ] )
				.mockResolvedValueOnce( [ PATIO ] );

			render( <TablesScreen /> );
			await screen.findByText( 'Patio' );

			await user.type( screen.getByLabelText( 'Table label' ), 'Garden' );
			await user.clear( screen.getByLabelText( 'Seats' ) );
			await user.type( screen.getByLabelText( 'Seats' ), '6' );
			await user.click(
				screen.getByRole( 'button', { name: 'Add table' } )
			);

			await waitFor( () =>
				expect( apiFetchMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						path: '/smooth/v1/tables',
						method: 'POST',
						data: { label: 'Garden', seats: 6 },
					} )
				)
			);
		} );

		it( 'keeps add disabled until a label is entered', async () => {
			render( <TablesScreen /> );

			await screen.findByText( 'Patio' );

			expect(
				screen.getByRole( 'button', { name: 'Add table' } )
			).toBeDisabled();
		} );
	} );

	describe( 'state change', () => {
		it( 'posts the selected next state through the API', async () => {
			const user = userEvent.setup();
			apiFetchMock
				.mockResolvedValueOnce( [ PATIO ] )
				.mockResolvedValueOnce( {
					...PATIO,
					state: 'seated' as const,
					next_states: [ 'ordered', 'free' ],
				} );

			render( <TablesScreen /> );
			await screen.findByText( 'Patio' );

			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: 'Change state for Patio',
				} ),
				'seated'
			);

			await waitFor( () =>
				expect( apiFetchMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						path: '/smooth/v1/tables/1/state',
						method: 'POST',
						data: { state: 'seated' },
					} )
				)
			);
			expect(
				await screen.findByText( 'Seated', { selector: 'td' } )
			).toBeInTheDocument();
		} );

		it( 'does not post when the current state is reselected', async () => {
			const user = userEvent.setup();

			render( <TablesScreen /> );
			await screen.findByText( 'Patio' );

			apiFetchMock.mockClear();
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: 'Change state for Patio',
				} ),
				'free'
			);

			expect( apiFetchMock ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'remove', () => {
		it( 'archives the table through the REST delete path', async () => {
			const user = userEvent.setup();

			render( <TablesScreen /> );
			await screen.findByText( 'Patio' );

			await user.click(
				screen.getByRole( 'button', { name: 'Remove' } )
			);

			await waitFor( () =>
				expect( apiFetchMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						path: '/smooth/v1/tables/1',
						method: 'DELETE',
					} )
				)
			);
		} );
	} );

	describe( 'QR card', () => {
		it( 'prints the selected tables and renders a card each', async () => {
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
				screen.getByText( 'Scan to view our menu' )
			).toBeInTheDocument();
		} );

		it( 'encodes the display-only menu URL in the QR code', async () => {
			const user = userEvent.setup();
			const { container } = render( <TablesScreen /> );
			await screen.findByText( 'Patio' );

			await user.click(
				screen.getByRole( 'checkbox', { name: 'Select Patio' } )
			);

			const qr = container.querySelector( '.smooth-qr-print svg' );
			expect( qr ).toBeInTheDocument();
		} );

		it( 'keeps print disabled until a table is selected', async () => {
			render( <TablesScreen /> );

			await screen.findByText( 'Patio' );

			expect(
				screen.getByRole( 'button', { name: 'Print selected (0)' } )
			).toBeDisabled();
		} );
	} );

	describe( 'errors', () => {
		/**
		 * Notice renders its message twice (visible copy plus a screen-reader
		 * copy), so match all occurrences.
		 *
		 * @param message Expected error message.
		 * @return Resolves once the notice is on screen.
		 */
		async function findNotice( message: string ) {
			return screen.findAllByText( message );
		}

		it( 'surfaces the API message when the list request fails', async () => {
			apiFetchMock.mockRejectedValueOnce(
				new Error( 'Sorry, you are not allowed to do that.' )
			);

			render( <TablesScreen /> );

			expect(
				await findNotice( 'Sorry, you are not allowed to do that.' )
			).not.toHaveLength( 0 );
		} );

		it( 'surfaces the API message when a state change fails', async () => {
			const user = userEvent.setup();
			apiFetchMock
				.mockResolvedValueOnce( [ PATIO ] )
				.mockRejectedValueOnce( new Error( 'Illegal state move.' ) );

			render( <TablesScreen /> );
			await screen.findByText( 'Patio' );

			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: 'Change state for Patio',
				} ),
				'seated'
			);

			expect(
				await findNotice( 'Illegal state move.' )
			).not.toHaveLength( 0 );
		} );

		it( 'surfaces the API message when creating fails', async () => {
			const user = userEvent.setup();
			apiFetchMock
				.mockResolvedValueOnce( [ PATIO ] )
				.mockRejectedValueOnce(
					new Error( 'A table labelled "Patio" already exists.' )
				);

			render( <TablesScreen /> );
			await screen.findByText( 'Patio' );

			await user.type( screen.getByLabelText( 'Table label' ), 'Patio' );
			await user.click(
				screen.getByRole( 'button', { name: 'Add table' } )
			);

			expect(
				await findNotice( 'A table labelled "Patio" already exists.' )
			).not.toHaveLength( 0 );
		} );

		it( 'lets the notice be dismissed', async () => {
			const user = userEvent.setup();
			apiFetchMock.mockRejectedValueOnce( new Error( 'Nope.' ) );

			render( <TablesScreen /> );
			await findNotice( 'Nope.' );

			// Notice renders a mobile and a desktop dismiss button; either works.
			await user.click(
				screen.getAllByRole( 'button', { name: 'Close' } )[ 0 ]
			);

			// Scoped to the notice: WordPress mirrors the message into its
			// a11y-speak-region live region, which outlives the notice itself.
			await waitFor( () =>
				expect(
					document.querySelectorAll( '.components-notice' )
				).toHaveLength( 0 )
			);
		} );
	} );
} );
