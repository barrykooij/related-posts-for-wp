<?php
/**
 * The API snapshot class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

/**
 * Records the public API of the legacy classes and functions with reflection, and checks that a later version is
 * still compatible with it.
 *
 * Compatible means code written against the snapshot keeps working:
 * - every class, public or protected method, property and constant still exists;
 * - visibility is the same or wider, static stays static, nothing becomes abstract;
 * - parameters keep their position, name (named arguments exist since PHP 8), by-reference flag and default value;
 * - parameters added at the end are optional;
 * - a class still extends the parent it extended.
 *
 * Members inherited from WordPress core classes (WP_List_Table, WP_Widget) are left out: they belong to WordPress and
 * change between WordPress versions. Only members declared by the plugin's own classes are part of the contract.
 */
final class ApiSnapshot {

	/**
	 * Constants whose value is expected to change, such as the version.
	 */
	private const VOLATILE_CONSTANTS = [ 'RP4WP::VERSION' ];

	/**
	 * Prefix of the plugin's own legacy classes.
	 */
	private const OWN_PREFIX = 'RP4WP';

	/**
	 * Build a snapshot.
	 *
	 * @param string[] $classes   Class names.
	 * @param string[] $functions Function names.
	 *
	 * @return array{classes: array<string, mixed>, functions: array<string, mixed>}
	 */
	public static function build( array $classes, array $functions ): array {
		$snapshot = [
			'classes'   => [],
			'functions' => [],
		];

		sort( $classes );
		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$snapshot['classes'][ $class ] = self::describe_class( new \ReflectionClass( $class ) );
			}
		}

		sort( $functions );
		foreach ( $functions as $function ) {
			if ( function_exists( $function ) ) {
				$snapshot['functions'][ $function ] = [ 'params' => self::describe_params( new \ReflectionFunction( $function ) ) ];
			}
		}

		return $snapshot;
	}

	/**
	 * List everything in $actual that breaks code written against $expected.
	 *
	 * @param array<string, mixed> $expected The recorded snapshot.
	 * @param array<string, mixed> $actual   A snapshot of the current code.
	 *
	 * @return string[] Human-readable problems; empty when compatible.
	 */
	public static function compare( array $expected, array $actual ): array {
		$problems = [];

		foreach ( $expected['classes'] as $class => $old ) {
			$new = $actual['classes'][ $class ] ?? null;

			if ( null === $new ) {
				$problems[] = "Class {$class} is missing.";
				continue;
			}

			$problems = array_merge( $problems, self::compare_class( $class, $old, $new ) );
		}

		foreach ( $expected['functions'] as $function => $old ) {
			$new = $actual['functions'][ $function ] ?? null;

			if ( null === $new ) {
				$problems[] = "Function {$function}() is missing.";
				continue;
			}

			$problems = array_merge( $problems, self::compare_params( "{$function}()", $old['params'], $new['params'] ) );
		}

		return $problems;
	}

	/**
	 * Describe a class.
	 *
	 * @param \ReflectionClass $class The class.
	 *
	 * @return array<string, mixed>
	 */
	private static function describe_class( \ReflectionClass $class ): array {
		$parents = [];
		for ( $parent = $class->getParentClass(); $parent; $parent = $parent->getParentClass() ) {
			$parents[] = $parent->getName();
		}

		$constants = [];
		foreach ( $class->getReflectionConstants() as $constant ) {
			if ( ! $constant->isPrivate() && self::is_own( $constant->getDeclaringClass() ) ) {
				$constants[ $constant->getName() ] = $constant->getValue();
			}
		}
		ksort( $constants );

		$properties = [];
		foreach ( $class->getProperties() as $property ) {
			if ( ! $property->isPrivate() && self::is_own( $property->getDeclaringClass() ) ) {
				$properties[ $property->getName() ] = [
					'visibility' => $property->isPublic() ? 'public' : 'protected',
					'static'     => $property->isStatic(),
				];
			}
		}
		ksort( $properties );

		$methods = [];
		foreach ( $class->getMethods() as $method ) {
			if ( $method->isPrivate() || ! self::is_own( $method->getDeclaringClass() ) ) {
				continue;
			}

			$methods[ $method->getName() ] = [
				'visibility' => $method->isPublic() ? 'public' : 'protected',
				'static'     => $method->isStatic(),
				'abstract'   => $method->isAbstract(),
				'params'     => self::describe_params( $method ),
			];
		}
		ksort( $methods );

		return [
			'abstract'   => $class->isAbstract(),
			'parents'    => $parents,
			'magic_get'  => $class->hasMethod( '__get' ),
			'constants'  => $constants,
			'properties' => $properties,
			'methods'    => $methods,
		];
	}

	/**
	 * Whether a class is one of the plugin's own, rather than a WordPress core class it extends.
	 *
	 * @param \ReflectionClass $class The class.
	 *
	 * @return bool
	 */
	private static function is_own( \ReflectionClass $class ): bool {
		return 0 === strpos( $class->getName(), self::OWN_PREFIX );
	}

	/**
	 * Describe the parameters of a function or method.
	 *
	 * @param \ReflectionFunctionAbstract $function The function or method.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function describe_params( \ReflectionFunctionAbstract $function ): array {
		$params = [];

		foreach ( $function->getParameters() as $param ) {
			$default = null;
			if ( $param->isDefaultValueAvailable() ) {
				$default = $param->isDefaultValueConstant() ? $param->getDefaultValueConstantName() : var_export( $param->getDefaultValue(), true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Serialises default values for comparison.
			}

			$params[] = [
				'name'     => $param->getName(),
				'optional' => $param->isOptional(),
				'default'  => $default,
				'by_ref'   => $param->isPassedByReference(),
				'variadic' => $param->isVariadic(),
			];
		}

		return $params;
	}

	/**
	 * Compare one class.
	 *
	 * @param string               $class The class name.
	 * @param array<string, mixed> $old   The recorded description.
	 * @param array<string, mixed> $new   The current description.
	 *
	 * @return string[]
	 */
	private static function compare_class( string $class, array $old, array $new ): array {
		$problems = [];

		if ( ! $old['abstract'] && $new['abstract'] ) {
			$problems[] = "Class {$class} became abstract.";
		}

		foreach ( $old['parents'] as $parent ) {
			if ( ! in_array( $parent, $new['parents'], true ) ) {
				$problems[] = "Class {$class} no longer extends {$parent}.";
			}
		}

		foreach ( $old['constants'] as $constant => $value ) {
			if ( ! array_key_exists( $constant, $new['constants'] ) ) {
				$problems[] = "Constant {$class}::{$constant} is missing.";
			} elseif ( $new['constants'][ $constant ] !== $value && ! in_array( "{$class}::{$constant}", self::VOLATILE_CONSTANTS, true ) ) {
				$problems[] = "Constant {$class}::{$constant} changed value.";
			}
		}

		foreach ( $old['properties'] as $property => $description ) {
			$current = $new['properties'][ $property ] ?? null;

			if ( null === $current ) {
				// A public property can live on as a magic getter; protected ones must stay real for subclasses.
				if ( ! ( 'public' === $description['visibility'] && ! $description['static'] && $new['magic_get'] ) ) {
					$problems[] = "Property {$class}::\${$property} is missing.";
				}
				continue;
			}

			if ( 'public' === $description['visibility'] && 'public' !== $current['visibility'] ) {
				$problems[] = "Property {$class}::\${$property} is no longer public.";
			}
			if ( $description['static'] !== $current['static'] ) {
				$problems[] = "Property {$class}::\${$property} changed static-ness.";
			}
		}

		foreach ( $old['methods'] as $method => $description ) {
			$current = $new['methods'][ $method ] ?? null;
			$label   = "{$class}::{$method}()";

			if ( null === $current ) {
				$problems[] = "Method {$label} is missing.";
				continue;
			}

			if ( 'public' === $description['visibility'] && 'public' !== $current['visibility'] ) {
				$problems[] = "Method {$label} is no longer public.";
			}
			if ( $description['static'] !== $current['static'] ) {
				$problems[] = "Method {$label} changed static-ness.";
			}
			if ( ! $description['abstract'] && $current['abstract'] ) {
				$problems[] = "Method {$label} became abstract.";
			}

			$problems = array_merge( $problems, self::compare_params( $label, $description['params'], $current['params'] ) );
		}

		return $problems;
	}

	/**
	 * Compare parameter lists.
	 *
	 * @param string                           $label The function label for messages.
	 * @param array<int, array<string, mixed>> $old   The recorded parameters.
	 * @param array<int, array<string, mixed>> $new   The current parameters.
	 *
	 * @return string[]
	 */
	private static function compare_params( string $label, array $old, array $new ): array {
		$problems = [];

		foreach ( $old as $index => $param ) {
			$current  = $new[ $index ] ?? null;
			$position = $index + 1;

			if ( null === $current ) {
				// A trailing variadic can absorb removed parameters without breaking callers.
				$last = end( $new );
				if ( false === $last || ! $last['variadic'] ) {
					$problems[] = "{$label}: parameter {$position} (\${$param['name']}) was removed.";
				}
				continue;
			}

			if ( $param['name'] !== $current['name'] ) {
				$problems[] = "{$label}: parameter {$position} was renamed from \${$param['name']} to \${$current['name']}.";
			}
			if ( $param['by_ref'] !== $current['by_ref'] ) {
				$problems[] = "{$label}: parameter \${$param['name']} changed by-reference passing.";
			}
			if ( $param['optional'] && ! $current['optional'] ) {
				$problems[] = "{$label}: parameter \${$param['name']} became required.";
			}
			if ( $param['optional'] && $current['optional'] && $param['default'] !== $current['default'] && ! $param['variadic'] ) {
				$problems[] = "{$label}: parameter \${$param['name']} changed its default from {$param['default']} to {$current['default']}.";
			}
		}

		foreach ( array_slice( $new, count( $old ) ) as $index => $param ) {
			if ( ! $param['optional'] ) {
				$position   = count( $old ) + $index + 1;
				$problems[] = "{$label}: new parameter {$position} (\${$param['name']}) is required.";
			}
		}

		return $problems;
	}
}
