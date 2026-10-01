import '@testing-library/jest-dom';

// Mock WordPress packages commonly used in tests.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
	_x: ( text: string ) => text,
	_n: ( single: string, plural: string, count: number ) =>
		count === 1 ? single : plural,
	_nx: ( single: string, plural: string, count: number ) =>
		count === 1 ? single : plural,
	sprintf: ( format: string, ...args: unknown[] ) =>
		format.replace( /%[sd]/g, () => String( args.shift() ) ),
} ) );

// Provide minimal matchMedia for jsdom
Object.defineProperty( window, 'matchMedia', {
	writable: true,
	value: jest.fn().mockImplementation( ( query: string ) => ( {
		matches: false,
		media: query,
		onchange: null,
		addListener: jest.fn(),
		removeListener: jest.fn(),
		addEventListener: jest.fn(),
		removeEventListener: jest.fn(),
		dispatchEvent: jest.fn(),
	} ) ),
} );
