/**
 * The screenshots on WordPress.org, in the order of the Screenshots section of readme.txt. Run with
 * `npm run screenshots`; this resets the wp-env test site.
 *
 * The site is a small gardening blog. Its featured images are photos that ship with the Twenty Twenty-Five theme.
 */
import { test, expect } from '../fixtures';
import type { Page, Route } from '@playwright/test';
import path from 'node:path';

const OUT = path.join( __dirname, '../../../.wordpress-org' );

type Post = { slug: string; title: string; image: string; content: string[] };

const POSTS: Post[] = [
	{
		slug: 'wildflower-meadow',
		title: 'Planting a wildflower meadow',
		image: 'flower-meadow-square.webp',
		content: [
			'A wildflower meadow turns a tired lawn into a home for bees and butterflies. Sow the seed in autumn on poor soil, so the grass does not crowd out the flowers.',
			'Mow the meadow once a year, after the wildflowers have dropped their seed, and take the cuttings away.',
			'Leave a few thistles at the edge: their flowers feed the bees and butterflies, and goldfinches eat the seed in autumn.',
		],
	},
	{
		slug: 'buttercups',
		title: 'Buttercups: weed or wildflower?',
		image: 'northern-buttercups-flowers.webp',
		content: [
			'Buttercups spread fast in a lawn, but in a meadow they are a bright wildflower that bees love.',
			'Let them flower and seed in the wild corners of the garden, and mow the paths.',
		],
	},
	{
		slug: 'thistles-for-bees',
		title: 'Thistles for bees and butterflies',
		image: 'star-thristle-flower.webp',
		content: [
			'Few flowers feed as many bees and butterflies as a thistle. Goldfinches eat the seed in autumn.',
			'Grow them at the back of a meadow or a wild border, where their spines are out of the way.',
		],
	},
	{
		slug: 'sunflowers-from-seed',
		title: 'Growing sunflowers from seed',
		image: 'category-sunflowers.webp',
		content: [
			'Sow sunflower seed outside in late spring, when the soil is warm. Bees visit the flowers all summer.',
			'Leave the seed heads standing in autumn for the birds.',
		],
	},
	{
		slug: 'delphiniums',
		title: 'Delphiniums for a tall border',
		image: 'delphinium-flowers.webp',
		content: [
			'Delphiniums give a border height and a deep blue few other flowers have. Stake the stems early.',
			'Cut the first flowers back, and a second, smaller flush follows in late summer.',
		],
	},
	{
		slug: 'campanula',
		title: 'Bellflowers in the shade',
		image: 'campanula-alliariifolia-flower.webp',
		content: [
			'Campanula, the bellflower, is one of the few border flowers that does well in light shade.',
			'Divide the clumps every few years in spring to keep them flowering.',
		],
	},
	{
		slug: 'hibiscus',
		title: 'Hibiscus in a cool climate',
		image: 'red-hibiscus-closeup.webp',
		content: [
			'A tropical hibiscus spends the summer outside and the winter indoors as a houseplant, in a bright room.',
			'Water it well while it flowers, and less in winter.',
		],
	},
	{
		slug: 'watering-cactus',
		title: 'Watering a cactus the right way',
		image: 'category-cactus.webp',
		content: [
			'More houseplants die of too much water than too little, and a cactus most of all.',
			'Water a cactus only when the soil is dry, and keep it in the brightest window of the house.',
		],
	},
	{
		slug: 'anthuriums',
		title: 'Anthuriums as houseplants',
		image: 'category-anthuriums.webp',
		content: [
			'Anthuriums are easy houseplants with glossy leaves and flowers that last for weeks.',
			'They like a bright window out of the midday sun, and water when the top of the soil is dry.',
		],
	},
	{
		slug: 'light-for-houseplants',
		title: 'How much light do houseplants need?',
		image: 'malibu-plantlife.webp',
		content: [
			'Most houseplants want bright light without the full sun of a south window. Turn the pots now and then.',
			'In winter, move them closer to the window and water them less.',
		],
	},
];

/**
 * Save a screenshot of the window.
 *
 * @param page   The page.
 * @param number The number of the screenshot in readme.txt.
 */
async function shoot( page: Page, number: number ): Promise< void > {
	// No hover state in the picture: the mouse rests in the empty bottom right corner.
	await page.mouse.move( 1279, 727 );
	await page.screenshot( {
		path: path.join( OUT, `screenshot-${ number }.jpg` ),
		type: 'jpeg',
		quality: 90,
		animations: 'disabled',
		caret: 'hide',
	} );
}

test( 'WordPress.org screenshots', async ( {
	page,
	admin,
	request,
	requestUtils,
	rp4wp,
} ) => {
	await rp4wp.reset();
	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/settings',
		data: { title: 'The Garden Journal' },
	} );

	// The blog: posts with a featured image, a day apart.
	const ids: Record< string, number > = {};
	let day = 1;
	for ( const post of POSTS ) {
		const image = await request.get(
			`/wp-content/themes/twentytwentyfive/assets/images/${ post.image }`
		);
		expect( image.ok() ).toBe( true );
		const media = ( await requestUtils.uploadMedia( {
			name: post.image,
			mimeType: 'image/webp',
			buffer: await image.body(),
		} ) ) as { id: number };
		const created = ( await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				title: post.title,
				slug: post.slug,
				status: 'publish',
				date: `2026-09-${ String( day++ ).padStart( 2, '0' ) }T09:00:00`,
				featured_media: media.id,
				content: post.content
					.map(
						( text ) =>
							`<!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph -->`
					)
					.join( '\n' ),
			},
		} ) ) as { id: number };
		ids[ post.slug ] = created.id;
	}

	await rp4wp.install();
	await requestUtils.rest( {
		method: 'PATCH',
		path: '/rp4wp/v1/settings/styling',
		data: { values: { display_image: true } },
	} );

	// 1. The first-run card.
	await page.goto( '/wp-admin/options-general.php?page=rp4wp' );
	const start = page.getByRole( 'button', { name: 'Start' } );
	await expect( start ).toBeVisible();
	await shoot( page, 1 );

	// 2. The installer at work. Once it is half way through linking, hold the status requests so the card stands still.
	await rp4wp.installerMode( { slow: true, pause: 1500 } );
	await start.click();
	const linked = page
		.locator( '.rp4wp-step', { hasText: 'Linking posts' } )
		.locator( '.rp4wp-step__count' );
	let held: Route[] = [];
	await expect
		.poll(
			async () => {
				const count = ( await linked.count() )
					? await linked.textContent()
					: '';
				if ( /^[4-6] of /.test( count ?? '' ) ) {
					await page.route(
						/rp4wp(%2F|\/)v1(%2F|\/)install/,
						( route ) => {
							held.push( route );
						}
					);
					return true;
				}
				return false;
			},
			{ intervals: [ 50 ], timeout: 60_000 }
		)
		.toBe( true );
	await shoot( page, 2 );
	await page.unrouteAll( { behavior: 'ignoreErrors' } );
	await Promise.all( held.map( ( route ) => route.continue() ) );
	held = [];

	// 3. Done, with the settings on the same screen.
	await expect(
		page.getByRole( 'heading', { name: 'All set! Your posts are linked.' } )
	).toBeVisible( { timeout: 120_000 } );
	await shoot( page, 3 );

	// 4. The meta box in the post editor.
	await admin.editPost( ids[ 'wildflower-meadow' ] );
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes' } );
	if (
		( await toggle.count() ) > 0 &&
		( await toggle.getAttribute( 'aria-expanded' ) ) === 'false'
	) {
		await toggle.click( { position: { x: 30, y: 10 } } );
	}
	const metaBox = page.locator( '#rp4wp_metabox_related_posts' );
	await metaBox.scrollIntoViewIfNeeded();
	await expect( metaBox.locator( '.row-title' ).first() ).toBeVisible();
	await shoot( page, 4 );

	// 5. The related posts below the post.
	await page.goto( `/?p=${ ids[ 'wildflower-meadow' ] }` );
	const related = page.locator( '.rp4wp-related-posts' );
	await expect( related.locator( 'img' ).first() ).toBeVisible();
	// The end of the post, and all three related posts below it.
	await related.evaluate( ( element ) =>
		window.scrollTo(
			0,
			element.getBoundingClientRect().top + window.scrollY - 180
		)
	);
	await shoot( page, 5 );
} );
