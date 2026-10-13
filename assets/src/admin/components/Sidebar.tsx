import { Card, CardBody, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	Icon,
	check,
	commentContent,
	page,
	shield,
	starFilled,
} from '@wordpress/icons';
import { useInstall } from '../store/install';

/**
 * A page on relatedpostsforwp.com, tagged so the site can tell visits from the plugin apart.
 *
 * @param path     The path, for example `upgrade-premium/`.
 * @param campaign What on this screen links to it.
 * @return The URL.
 */
function siteUrl( path: string, campaign: string ): string {
	return `https://www.relatedpostsforwp.com/${ path }?utm_source=plugin&utm_medium=link&utm_campaign=${ campaign }`;
}

/**
 * A page of the documentation on docs.relatedpostsforwp.com, tagged like siteUrl().
 *
 * @param path     The path, for example `styling/adding-custom-css-theme/`; empty for the home page.
 * @param campaign What on this screen links to it.
 * @return The URL.
 */
function docsUrl( path: string, campaign: string ): string {
	return `https://docs.relatedpostsforwp.com/${ path }?utm_source=plugin&utm_medium=link&utm_campaign=${ campaign }`;
}

const REVIEW_URL =
	'https://wordpress.org/support/plugin/related-posts-for-wp/reviews/#new-post';

const FORUM_URL = 'https://wordpress.org/support/plugin/related-posts-for-wp/';

/**
 * Next to the settings: what Premium adds (in the free edition), help, and once posts are linked, a request for a
 * review. Below the settings when the screen is narrow.
 */
export function Sidebar() {
	const premium = 'premium' === window.rp4wpAdminData?.edition;
	const installed = !! useInstall().state?.installed;

	return (
		<aside
			className="rp4wp-sidebar"
			aria-label={ __(
				'More about Related Posts',
				'related-posts-for-wp'
			) }
		>
			{ ! premium && <PremiumCard /> }
			<HelpCard premium={ premium } />
			{ installed && <ReviewCard /> }
		</aside>
	);
}

/**
 * What Premium adds, and where to get it.
 */
function PremiumCard() {
	const features = [
		__(
			'Design the layout in the visual configurator',
			'related-posts-for-wp'
		),
		__(
			'Relate pages, products and custom post types',
			'related-posts-for-wp'
		),
		__(
			'Choose what counts with adjustable weights',
			'related-posts-for-wp'
		),
		__( 'Exclude posts and whole categories', 'related-posts-for-wp' ),
		__( 'Older posts get your new posts too', 'related-posts-for-wp' ),
		__( 'See why posts are related', 'related-posts-for-wp' ),
		__( 'Priority email support', 'related-posts-for-wp' ),
	];

	return (
		<Card className="rp4wp-card rp4wp-promo">
			<CardBody className="rp4wp-promo__body">
				<span className="rp4wp-promo__eyebrow">
					<Icon icon={ starFilled } size={ 16 } />
					{ __( 'Premium', 'related-posts-for-wp' ) }
				</span>
				<h2 className="rp4wp-promo__title">
					{ __(
						'Take your related posts further',
						'related-posts-for-wp'
					) }
				</h2>
				<p className="rp4wp-promo__text">
					{ __(
						'Premium gives you control over what gets related and how it looks. It is an add-on for this plugin, so your settings and links stay as they are.',
						'related-posts-for-wp'
					) }
				</p>
				<ul className="rp4wp-promo__features">
					{ features.map( ( feature ) => (
						<li key={ feature }>
							<span
								className="rp4wp-promo__check"
								aria-hidden="true"
							>
								<Icon icon={ check } size={ 16 } />
							</span>
							{ feature }
						</li>
					) ) }
				</ul>
				<ExternalLink
					className="rp4wp-promo__cta"
					href={ siteUrl( 'upgrade-premium/', 'upgrade-box' ) }
				>
					{ __( 'Upgrade to Premium', 'related-posts-for-wp' ) }
				</ExternalLink>
				<ExternalLink
					className="rp4wp-promo__more"
					href={ siteUrl( 'features/', 'upgrade-box-features' ) }
				>
					{ __( 'See all features', 'related-posts-for-wp' ) }
				</ExternalLink>
				<p className="rp4wp-promo__guarantee">
					<Icon icon={ shield } size={ 16 } />
					{ __(
						'14 day money back guarantee',
						'related-posts-for-wp'
					) }
				</p>
			</CardBody>
		</Card>
	);
}

interface HelpCardProps {
	premium: boolean;
}

/**
 * The documentation, and where to ask when it has no answer: the forum, or email for Premium.
 *
 * @param props         The props.
 * @param props.premium Whether Premium is active.
 */
function HelpCard( { premium }: HelpCardProps ) {
	const docs = [
		{
			path: 'getting-started/installation-guide/',
			label: __( 'Getting started', 'related-posts-for-wp' ),
		},
		{
			path: 'showing-related-posts/theme-functions-to-display-related-posts/',
			label: __(
				'Show related posts in your theme',
				'related-posts-for-wp'
			),
		},
		{
			path: 'styling/adding-custom-css-theme/',
			label: __(
				'Style the related posts with CSS',
				'related-posts-for-wp'
			),
		},
		{
			path: '',
			label: __( 'All documentation', 'related-posts-for-wp' ),
		},
	];

	return (
		<Card className="rp4wp-card rp4wp-help">
			<CardBody className="rp4wp-sidebar__body">
				<h2 className="rp4wp-card__title">
					{ __( 'Need help?', 'related-posts-for-wp' ) }
				</h2>
				<p className="rp4wp-card__description">
					{ __(
						'The documentation answers most questions.',
						'related-posts-for-wp'
					) }
				</p>
				<ul className="rp4wp-help__links">
					{ docs.map( ( doc ) => (
						<li key={ doc.label }>
							<ExternalLink
								className="rp4wp-help__link"
								href={ docsUrl( doc.path, 'help' ) }
							>
								<Icon icon={ page } size={ 20 } />
								{ doc.label }
							</ExternalLink>
						</li>
					) ) }
					<li className="rp4wp-help__ask">
						{ premium ? (
							<ExternalLink
								className="rp4wp-help__link"
								href={ siteUrl( 'contact/', 'support' ) }
							>
								<Icon icon={ commentContent } size={ 20 } />
								{ __(
									'Priority email support',
									'related-posts-for-wp'
								) }
							</ExternalLink>
						) : (
							<ExternalLink
								className="rp4wp-help__link"
								href={ FORUM_URL }
							>
								<Icon icon={ commentContent } size={ 20 } />
								{ __(
									'Ask on the support forum',
									'related-posts-for-wp'
								) }
							</ExternalLink>
						) }
					</li>
				</ul>
			</CardBody>
		</Card>
	);
}

/**
 * A request for a review on WordPress.org, once the plugin did its work.
 */
function ReviewCard() {
	return (
		<Card className="rp4wp-card rp4wp-review">
			<CardBody className="rp4wp-sidebar__body">
				<span className="rp4wp-review__stars" aria-hidden="true">
					{ [ 1, 2, 3, 4, 5 ].map( ( star ) => (
						<Icon key={ star } icon={ starFilled } size={ 20 } />
					) ) }
				</span>
				<h2 className="rp4wp-card__title">
					{ __( 'Enjoying Related Posts?', 'related-posts-for-wp' ) }
				</h2>
				<p className="rp4wp-card__description">
					{ __(
						'A review on WordPress.org helps other people find the plugin, and keeps it going.',
						'related-posts-for-wp'
					) }
				</p>
				<ExternalLink
					className="rp4wp-review__link"
					href={ REVIEW_URL }
				>
					{ __( 'Leave a review', 'related-posts-for-wp' ) }
				</ExternalLink>
			</CardBody>
		</Card>
	);
}
