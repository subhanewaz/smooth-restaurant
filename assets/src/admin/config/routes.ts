import { lazy, type ComponentType } from 'react';
import { __ } from '@wordpress/i18n';

export interface RouteConfig {
	path: string;
	label: string;
	component: ComponentType;
}

const Dashboard = lazy( () => import( '../pages/Dashboard' ).then( m => ( { default: m.Dashboard } ) ) );
const MenuItems = lazy( () => import( '../pages/MenuItems' ).then( m => ( { default: m.MenuItems } ) ) );
const Orders = lazy( () => import( '../pages/Orders' ).then( m => ( { default: m.Orders } ) ) );
const Reservations = lazy( () => import( '../pages/Reservations' ).then( m => ( { default: m.Reservations } ) ) );
const Settings = lazy( () => import( '../pages/Settings' ).then( m => ( { default: m.Settings } ) ) );
const Shortcodes = lazy( () => import( '../pages/Shortcodes' ).then( m => ( { default: m.Shortcodes } ) ) );
const AboutUs = lazy( () => import( '../pages/AboutUs' ).then( m => ( { default: m.AboutUs } ) ) );

export const routes: RouteConfig[] = [
	{ path: '/', label: __( 'Dashboard', 'smooth-restaurant' ), component: Dashboard },
	{ path: '/menu-items', label: __( 'Menu Items', 'smooth-restaurant' ), component: MenuItems },
	{ path: '/orders', label: __( 'Orders', 'smooth-restaurant' ), component: Orders },
	{ path: '/reservations', label: __( 'Reservations', 'smooth-restaurant' ), component: Reservations },
	{ path: '/shortcodes', label: __( 'Shortcodes', 'smooth-restaurant' ), component: Shortcodes },
	{ path: '/settings', label: __( 'Settings', 'smooth-restaurant' ), component: Settings },
	{ path: '/about-us', label: __( 'About Us', 'smooth-restaurant' ), component: AboutUs },
];
