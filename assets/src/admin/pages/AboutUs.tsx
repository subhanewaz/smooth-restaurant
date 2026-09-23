import { __ } from '@wordpress/i18n';
import { PlaceholderPage } from '../components/PlaceholderPage';

export const AboutUs = () => (
	<PlaceholderPage
		title={ __( 'About Us', 'smooth-restaurant' ) }
		description={ __(
			'Learn more about Smooth Restaurant — a performance-focused, open-source restaurant management plugin.',
			'smooth-restaurant'
		) }
	/>
);
