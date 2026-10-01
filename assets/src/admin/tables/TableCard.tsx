/**
 * Printable QR card for a single table.
 *
 * Sized for A4 two-up printing (see the print stylesheet): a large label
 * plus a ~55mm QR code that encodes the table's display-only menu URL.
 */
import { QRCodeSVG } from 'qrcode.react';
import type { Table } from './types';

interface TableCardProps {
	table: Table;
}

/**
 * Render one table's printable card.
 *
 * @param props Table card props.
 * @return The card element.
 */
export function TableCard( props: TableCardProps ) {
	const { table } = props;

	return (
		<div className="smooth-print-card">
			<div className="smooth-print-card__label">{ table.label }</div>
			<div className="smooth-print-card__qr">
				<QRCodeSVG value={ table.qr_url } size={ 208 } />
			</div>
			<p className="smooth-print-card__hint">Scan to view the menu</p>
		</div>
	);
}
