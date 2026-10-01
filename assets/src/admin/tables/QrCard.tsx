/**
 * Printable QR card for a single table.
 *
 * Sized for A4 two-up printing (see tables.scss): a large label plus a ~55mm QR
 * code that encodes the table's display-only menu URL. The URL carries no
 * session or token, so a printed card is safe to leave on a table.
 */
import { __ } from '@wordpress/i18n';
import { QRCodeSVG } from 'qrcode.react';
import type { Table } from './types';

interface QrCardProps {
	table: Table;
}

/**
 * Render one table's printable card.
 *
 * @param props Qr card props.
 * @return The card element.
 */
export function QrCard( props: QrCardProps ) {
	const { table } = props;

	return (
		<div className="smooth-qr-card">
			<div className="smooth-qr-card__label">{ table.label }</div>
			<div className="smooth-qr-card__code">
				<QRCodeSVG value={ table.qr_url } size={ 208 } level="M" />
			</div>
			<p className="smooth-qr-card__hint">
				{ __( 'Scan to view our menu', 'smooth-restaurant' ) }
			</p>
		</div>
	);
}
