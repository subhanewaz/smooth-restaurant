import { useEffect, useRef, useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { cn } from '../lib/utils';
import { routes } from '../config/routes';
import { Button } from '@/admin/components/ui/button';
import {
	Tooltip,
	TooltipContent,
	TooltipProvider,
	TooltipTrigger,
} from '@/admin/components/ui/tooltip';
import {
	Sheet,
	SheetContent,
	SheetHeader,
	SheetTitle,
} from '@/admin/components/ui/sheet';
import { Separator } from '@/admin/components/ui/separator';
import {
	LayoutDashboard,
	UtensilsCrossed,
	ShoppingBag,
	CalendarDays,
	Settings,
	Code,
	Info,
	ChefHat,
	ArrowLeftToLine,
} from 'lucide-react';

interface SidebarProps {
	collapsed: boolean;
	mobileOpen: boolean;
	onMobileClose: () => void;
}

const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
	'/': LayoutDashboard,
	'/menu-items': UtensilsCrossed,
	'/orders': ShoppingBag,
	'/reservations': CalendarDays,
	'/settings': Settings,
	'/shortcodes': Code,
	'/about-us': Info,
};

export const Sidebar = ( { collapsed, mobileOpen, onMobileClose }: SidebarProps ) => {
	const location = useLocation();
	const navigate = useNavigate();
	const sidebarRef = useRef<HTMLElement>( null );
	const firstItemRef = useRef<HTMLButtonElement>( null );

	// Focus trap for mobile drawer
	useEffect( () => {
		if ( ! mobileOpen ) return;

		const sidebar = sidebarRef.current;
		if ( ! sidebar ) return;

		// Focus first item when opened
		firstItemRef.current?.focus();

		const handleKeyDown = ( e: KeyboardEvent ) => {
			if ( e.key !== 'Tab' ) return;

			const focusable = sidebar.querySelectorAll<HTMLElement>(
				'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
			);
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];

			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last?.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first?.focus();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ mobileOpen ] );

	// Close mobile menu on Escape
	useEffect( () => {
		const handleEscape = ( e: KeyboardEvent ) => {
			if ( e.key === 'Escape' && mobileOpen ) {
				onMobileClose();
			}
		};
		document.addEventListener( 'keydown', handleEscape );
		return () => document.removeEventListener( 'keydown', handleEscape );
	}, [ mobileOpen, onMobileClose ] );

	const handleNavClick = useCallback( ( path: string ) => {
		navigate( path );
		onMobileClose();
	}, [ navigate, onMobileClose ] );

	const navContent = (
		<>
			{/* Logo */}
			<div className={ cn(
				'sr-flex sr-items-center sr-border-b sr-border-sr-border sr-shrink-0',
				collapsed ? 'lg:sr-justify-center sr-h-14 sr-px-2' : 'sr-h-14 sr-px-4'
			) }>
				<div className="sr-flex sr-items-center sr-gap-2 sr-min-w-0">
					<div className="sr-flex sr-size-8 sr-shrink-0 sr-items-center sr-justify-center sr-rounded-sr-md sr-bg-sr-primary">
						<ChefHat className="sr-text-white" />
					</div>
					{ ! collapsed && (
						<span className="sr-truncate sr-whitespace-nowrap sr-text-body sr-font-semibold sr-text-sr-text sr-transition-opacity sr-duration-200 sr-ease-out-expo">
							{ __( 'Smooth Restaurant', 'smooth-restaurant' ) }
						</span>
					) }
				</div>
			</div>

			{/* Navigation */}
			<nav className="sr-flex-1 sr-overflow-y-auto sr-scrollbar-thin sr-px-2 sr-py-4">
				<ul className="sr-flex sr-flex-col sr-gap-1">
					{ routes.map( ( route, index ) => {
						const isActive = location.pathname === route.path;
						const Icon = iconMap[ route.path ];
						return (
							<li key={ route.path }>
								<Tooltip>
									<TooltipTrigger asChild>
										<Button
											ref={ index === 0 ? firstItemRef : undefined }
											variant={ isActive ? 'secondary' : 'ghost' }
											size={ collapsed ? 'icon' : 'default' }
											className={ cn(
												'sr-w-full sr-justify-start sr-gap-3 sr-min-h-[44px]',
												collapsed && 'lg:sr-justify-center lg:sr-px-0'
											) }
											aria-current={ isActive ? 'page' : undefined }
											onClick={ () => handleNavClick( route.path ) }
										>
											<span aria-hidden="true">
												{ Icon ? <Icon /> : <span className="sr-size-5" /> }
											</span>
											{ ! collapsed && (
												<span className="sr-truncate sr-whitespace-nowrap sr-transition-opacity sr-duration-200 sr-ease-out-expo">{ route.label }</span>
											) }
										</Button>
									</TooltipTrigger>
									{ collapsed && (
										<TooltipContent side="right">{ route.label }</TooltipContent>
									) }
								</Tooltip>
							</li>
						);
					} ) }
				</ul>
			</nav>

			<Separator className="sr-bg-sr-border" />

			{/* Back to WordPress */}
			<div className="sr-shrink-0 sr-py-3 sr-px-2">
				<a
					href="/wp-admin"
					className={ cn(
						'sr-flex sr-items-center sr-gap-3 sr-px-3 sr-py-2.5 sr-rounded-sr-md sr-text-label sr-text-sr-text-secondary hover:sr-bg-sr-hover sr-transition-colors sr-duration-150 sr-ease-out-expo sr-focus-ring sr-min-h-[44px]',
						collapsed ? 'lg:sr-justify-center' : ''
					) }
					aria-label={ __( 'Back to WordPress Dashboard', 'smooth-restaurant' ) }
				>
					<span className="sr-shrink-0 sr-flex sr-items-center sr-justify-center sr-text-sr-text-secondary" aria-hidden="true">
						<ArrowLeftToLine />
					</span>
					{ ! collapsed && (
						<span className="sr-truncate sr-whitespace-nowrap sr-transition-opacity sr-duration-200 sr-ease-out-expo">{ __( 'Back to WordPress', 'smooth-restaurant' ) }</span>
					) }
				</a>
			</div>
		</>
	);

	return (
		<>
			{/* Desktop sidebar */}
			<aside
				ref={ sidebarRef }
				className={ cn(
					'sr-fixed sr-top-0 sr-left-0 sr-h-full sr-bg-sr-surface sr-border-r sr-border-sr-border sr-z-50 sr-hidden md:sr-flex sr-flex-col sr-transition-all sr-duration-300 sr-ease-out-expo',
					collapsed ? 'md:sr-w-16' : 'md:sr-w-60'
				) }
				aria-label={ __( 'Main navigation', 'smooth-restaurant' ) }
			>
				<TooltipProvider delay={ 200 }>{ navContent }</TooltipProvider>
			</aside>

			{/* Mobile sheet */}
			<Sheet open={ mobileOpen } onOpenChange={ onMobileClose }>
				<SheetContent side="left" className="sr-w-60 sr-p-0 sr-bg-sr-surface sr-border-sr-border">
					<SheetHeader className="sr-sr-only">
						<SheetTitle>{ __( 'Navigation', 'smooth-restaurant' ) }</SheetTitle>
					</SheetHeader>
					<div className="sr-flex sr-flex-col sr-h-full">{ navContent }</div>
				</SheetContent>
			</Sheet>
		</>
	);
};
