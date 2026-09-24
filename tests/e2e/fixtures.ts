/**
 * Shared fixtures: the WordPress test utilities plus helpers for this plugin.
 *
 * Every spec gets an `rp4wp` helper that talks to the E2E helpers mu-plugin, and every test fails when plugin code
 * raised a PHP notice, warning, deprecation or "doing it wrong" during the test.
 */
import { test as base, expect } from '@wordpress/e2e-test-utils-playwright';
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

type RecordedProblem = {
	type: string;
	message: string;
	file: string;
	line: number;
	url: string;
};

type CorpusPost = { slug: string; title: string; content: string };

export type CreatedPost = { id: number; link: string };

/**
 * A small corpus in two clear topics, so related posts are predictable.
 */
export const CORPUS: CorpusPost[] = [
	{
		slug: 'sourdough-bread',
		title: 'Baking sourdough bread',
		content:
			'A sourdough starter needs flour, water and patience. Bake the bread in a hot oven with steam.',
	},
	{
		slug: 'banana-bread',
		title: 'Moist banana bread',
		content:
			'Ripe bananas make the best banana bread. Bake the loaf low and slow.',
	},
	{
		slug: 'focaccia-bread',
		title: 'Focaccia bread with rosemary',
		content:
			'Focaccia is an Italian flat bread with olive oil, rosemary and sea salt.',
	},
	{
		slug: 'rye-bread',
		title: 'Dark rye bread',
		content: 'Rye bread is dense and sour. Bake the rye loaf in a tin.',
	},
	{
		slug: 'pruning-roses',
		title: 'Pruning roses in spring',
		content:
			'Prune roses before new growth. Cut dead wood and shape the rose bush.',
	},
	{
		slug: 'climbing-roses',
		title: 'Training climbing roses',
		content:
			'Climbing roses need support. Tie the rose canes horizontally.',
	},
	{
		slug: 'rose-diseases',
		title: 'Common rose diseases',
		content:
			'Black spot and mildew hurt roses. Water the roses at the base.',
	},
	{
		slug: 'rose-feeding',
		title: 'Feeding roses in summer',
		content: 'Feed roses every month in summer for more rose flowers.',
	},
];

export class RP4WPUtils {
	constructor( private readonly requestUtils: RequestUtils ) {}

	/**
	 * Back to a site where the plugin was never installed: inactive, no data, no posts.
	 */
	async reset(): Promise< void > {
		await this.requestUtils.rest( {
			method: 'POST',
			path: '/rp4wp-e2e/v1/reset',
		} );
	}

	/**
	 * Activate the plugin and create its table, like activating on the Plugins screen, but skip the wizard redirect.
	 */
	async install(): Promise< void > {
		await this.requestUtils.rest( {
			method: 'POST',
			path: '/rp4wp-e2e/v1/install',
		} );
	}

	/**
	 * Cache words and link all posts, like finishing the wizard.
	 */
	async link(): Promise< void > {
		await this.requestUtils.rest( {
			method: 'POST',
			path: '/rp4wp-e2e/v1/link',
		} );
	}

	/**
	 * Create the corpus posts.
	 *
	 * @return The created posts by slug.
	 */
	async createCorpus(): Promise< Record< string, CreatedPost > > {
		const posts: Record< string, CreatedPost > = {};
		let day = 1;

		for ( const post of CORPUS ) {
			const created = await this.requestUtils.createPost( {
				title: post.title,
				content: post.content,
				slug: post.slug,
				status: 'publish',
				date: `2024-01-${ String( day++ ).padStart( 2, '0' ) }T09:00:00`,
			} );
			posts[ post.slug ] = { id: created.id, link: created.link };
		}

		return posts;
	}

	/**
	 * Put the related posts widget in a sidebar of the active theme.
	 *
	 * @param sidebar The sidebar ID.
	 */
	async addWidget( sidebar: string ): Promise< void > {
		await this.requestUtils.rest( {
			method: 'POST',
			path: '/rp4wp-e2e/v1/widget',
			data: { sidebar },
		} );
	}

	/**
	 * The stylesheet of the active theme.
	 */
	async activeTheme(): Promise< string > {
		const themes = ( await this.requestUtils.rest( {
			path: '/wp/v2/themes',
			params: { status: 'active' },
		} ) ) as Array< { stylesheet: string } >;

		return themes[ 0 ].stylesheet;
	}

	async clearProblems(): Promise< void > {
		await this.requestUtils.rest( {
			method: 'DELETE',
			path: '/rp4wp-e2e/v1/errors',
		} );
	}

	async problems(): Promise< RecordedProblem[] > {
		return ( await this.requestUtils.rest( {
			method: 'GET',
			path: '/rp4wp-e2e/v1/errors',
		} ) ) as RecordedProblem[];
	}
}

export const test = base.extend< { rp4wp: RP4WPUtils } >( {
	rp4wp: async ( { requestUtils }, provide ) => {
		const rp4wp = new RP4WPUtils( requestUtils );
		await rp4wp.clearProblems();

		await provide( rp4wp );

		// Fail the test when plugin code raised a PHP notice, warning, deprecation or "doing it wrong".
		expect(
			await rp4wp.problems(),
			'PHP problems raised by plugin code'
		).toEqual( [] );
	},
} );

export { expect };
