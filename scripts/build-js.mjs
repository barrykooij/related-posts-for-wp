/**
 * Minifies every assets/js/*.js file (except *.min.js) to its .min.js sibling, like the old Grunt uglify task.
 * The script handles keep their paths, so nothing changes for WordPress.
 */
import { readdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { minify } from 'terser';

const root = path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) );
const directory = path.join( root, 'assets', 'js' );

const files = ( await readdir( directory ) ).filter(
	( file ) => file.endsWith( '.js' ) && ! file.endsWith( '.min.js' )
);

for ( const file of files ) {
	const source = await readFile( path.join( directory, file ), 'utf8' );
	const result = await minify( source, {
		compress: true,
		mangle: true,
		// Keep license and /*! */ comments, like uglify's preserveComments: 'some'.
		format: { comments: /^!|@preserve|@license|@cc_on/i },
	} );

	const target = file.replace( /\.js$/, '.min.js' );
	await writeFile( path.join( directory, target ), result.code );
	process.stdout.write( `assets/js/${ file } -> assets/js/${ target }\n` );
}
