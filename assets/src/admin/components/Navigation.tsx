import { __ } from '@wordpress/i18n';
import type { Route } from '../registry/registry';

interface Props {
	routes: Route[];
	current?: Route;
}

/**
 * The tabs: one per route, and one per group of routes, which opens the first route of the group.
 *
 * @param props         The props.
 * @param props.routes  The routes, in order.
 * @param props.current The route that is open.
 */
export function Navigation( { routes, current }: Props ) {
	const tabs = routes.filter(
		( route, index ) =>
			! route.group ||
			routes.findIndex(
				( other ) => other.group?.id === route.group?.id
			) === index
	);

	if ( tabs.length < 2 ) {
		return null;
	}

	return (
		<nav
			className="rp4wp-admin__nav"
			aria-label={ __(
				'Related Posts settings',
				'related-posts-for-wp'
			) }
		>
			{ tabs.map( ( route ) => {
				const active = route.group
					? route.group.id === current?.group?.id
					: route.path === current?.path;

				return (
					<a
						key={ route.path }
						href={ `#/${ route.path }` }
						className="rp4wp-admin__tab"
						aria-current={ active ? 'page' : undefined }
					>
						{ route.group ? route.group.title : route.title }
					</a>
				);
			} ) }
		</nav>
	);
}

/**
 * The switcher between the routes of a group, for example the general settings of each post type.
 *
 * @param props         The props.
 * @param props.routes  The routes, in order.
 * @param props.current The route that is open.
 */
export function GroupSwitcher( { routes, current }: Props ) {
	const group = current?.group;
	const members = group
		? routes.filter( ( route ) => route.group?.id === group.id )
		: [];

	if ( members.length < 2 ) {
		return null;
	}

	return (
		<div
			className="rp4wp-switcher"
			role="group"
			aria-label={ group?.title }
		>
			{ members.map( ( route ) => (
				<a
					key={ route.path }
					href={ `#/${ route.path }` }
					className="rp4wp-switcher__item"
					aria-current={
						route.path === current?.path ? 'page' : undefined
					}
				>
					{ route.group?.label }
				</a>
			) ) }
		</div>
	);
}
