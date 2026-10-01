/**
 * Shared types for the Free tables admin screen.
 */

export type TableState = 'free' | 'seated' | 'ordered' | 'needs_bill';

export interface Table {
	id: number;
	label: string;
	seats: number;
	state: TableState;
	next_states: TableState[];
	qr_url: string;
}

export interface ArchivedTable {
	id: number;
	archived: boolean;
}
