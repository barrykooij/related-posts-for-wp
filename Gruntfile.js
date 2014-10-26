/* jshint node:true */
module.exports = function (grunt) {
    'use strict';

    grunt.initConfig({
        // setting folder templates
        dirs: {
            css: 'assets/css',
            fonts: 'assets/fonts',
            images: 'assets/images',
            js: 'assets/js',
            lang: 'languages'
        },

        // Compile all .less files.
        less: {
            compile: {
                options: {
                    // These paths are searched for @imports
                    paths: ['<%= dirs.css %>/']
                },
                files: [{
                    expand: true,
                    cwd: '<%= dirs.css %>/',
                    src: [
                        '*.less',
                        '!mixins.less'
                    ],
                    dest: '<%= dirs.css %>/',
                    ext: '.css'
                }]
            }
        },

        // Minify all .css files.
        cssmin: {
            minify: {
                expand: true,
                cwd: '<%= dirs.css %>/',
                src: ['*.css'],
                dest: '<%= dirs.css %>/',
                ext: '.css'
            }
        },

        // Minify .js files.
        uglify: {
            options: {
                preserveComments: 'some'
            },
            jsfiles: {
                files: [{
                    expand: true,
                    cwd: '<%= dirs.js %>/',
                    src: [
                        '*.js',
                        '!*.min.js',
                        '!Gruntfile.js',
                    ],
                    dest: '<%= dirs.js %>/',
                    ext: '.min.js'
                }]
            }
        },

        // Watch changes for assets
        watch: {
            less: {
                files: ['<%= dirs.css %>/*.less'],
                tasks: ['less', 'cssmin'],
            },
            js: {
                files: [
                    '<%= dirs.js %>/*js',
                    '!<%= dirs.js %>/*.min.js'
                ],
                tasks: ['uglify']
            }
        },

        // Generate POT files.
        makepot: {
            options: {
                type: 'wp-plugin',
                domainPath: 'languages',
                potHeaders: {
                    'report-msgid-bugs-to': 'https://github.com/barrykooij/related-posts-for-wp/issues',
                    'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
                }
            },
            frontend: {
                options: {
                    potFilename: 'related-posts-for-wp.pot',
                    exclude: [
                        'node_modules/.*'
                    ],
                    processPot: function (pot) {
                        return pot;
                    }
                }
            }
        },

		po2mo: {
			files: {
				src: '<%= dirs.lang %>/*.po',
				expand: true
			}
		}

    });

    // Load NPM tasks to be used here
    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-checktextdomain');
    grunt.loadNpmTasks('grunt-po2mo');

    // Register tasks
    grunt.registerTask('default', [
        'less',
        'cssmin',
        'uglify'
    ]);

    // Just an alias for pot file generation
    grunt.registerTask('pot', [
        'makepot'
    ]);

};