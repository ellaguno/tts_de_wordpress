<?php

namespace WP_TTS\Core;

/**
 * Service Container for Dependency Injection
 *
 * A simple dependency injection container that manages service instances
 * and their dependencies throughout the plugin lifecycle.
 */
class ServiceContainer {

	/**
	 * Service definitions
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Service instances
	 *
	 * @var array
	 */
	private $instances = array();

	/**
	 * Singleton services (cached instances)
	 *
	 * @var array
	 */
	private $singletons = array();

	/**
	 * Set a service definition
	 *
	 * @param string $name Service name
	 * @param mixed  $definition Service definition (callable or instance)
	 * @param bool   $singleton Whether to cache the instance
	 */
	public function set( string $name, $definition, bool $singleton = true ): void {
		$this->services[ $name ] = $definition;

		if ( $singleton ) {
			$this->singletons[ $name ] = true;
		}

		// Clear cached instance if it exists
		unset( $this->instances[ $name ] );
	}

	/**
	 * Get a service instance
	 *
	 * @param string $name Service name
	 * @return mixed Service instance
	 * @throws \InvalidArgumentException If service is not found
	 */
	public function get( string $name ) {
		// Return cached instance if it's a singleton
		if ( isset( $this->instances[ $name ] ) && isset( $this->singletons[ $name ] ) ) {
			return $this->instances[ $name ];
		}

		// Check if service is defined
		if ( ! isset( $this->services[ $name ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \InvalidArgumentException( "Service '{$name}' is not defined" );
		}

		$definition = $this->services[ $name ];

		// Create instance
		if ( is_callable( $definition ) ) {
			$instance = $definition( $this );
		} elseif ( is_object( $definition ) ) {
			$instance = $definition;
		} elseif ( is_string( $definition ) && class_exists( $definition ) ) {
			$instance = new $definition();
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \InvalidArgumentException( "Invalid service definition for '{$name}'" );
		}

		// Cache instance if it's a singleton
		if ( isset( $this->singletons[ $name ] ) ) {
			$this->instances[ $name ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if a service is defined
	 *
	 * @param string $name Service name
	 * @return bool
	 */
	public function has( string $name ): bool {
		return isset( $this->services[ $name ] );
	}

	/**
	 * Remove a service definition
	 *
	 * @param string $name Service name
	 */
	public function remove( string $name ): void {
		unset( $this->services[ $name ], $this->instances[ $name ], $this->singletons[ $name ] );
	}

	/**
	 * Get all defined service names
	 *
	 * @return array
	 */
	public function getServiceNames(): array {
		return array_keys( $this->services );
	}

	/**
	 * Clear all cached instances
	 */
	public function clearInstances(): void {
		$this->instances = array();
	}

	/**
	 * Register a factory for creating instances
	 *
	 * @param string   $name Service name
	 * @param callable $factory Factory function
	 */
	public function factory( string $name, callable $factory ): void {
		$this->set( $name, $factory, false );
	}

	/**
	 * Register a shared service (singleton)
	 *
	 * @param string $name Service name
	 * @param mixed  $definition Service definition
	 */
	public function singleton( string $name, $definition ): void {
		$this->set( $name, $definition, true );
	}

	/**
	 * Bind an interface to an implementation
	 *
	 * @param string $interface Interface name
	 * @param string $implementation Implementation class name
	 * @param bool   $singleton Whether to cache the instance
	 */
	public function bind( string $interface, string $implementation, bool $singleton = true ): void {
		$this->set(
			$interface,
			function() use ( $implementation ) {
				return new $implementation();
			},
			$singleton
		);
	}

	/**
	 * Auto-wire a class by resolving its dependencies
	 *
	 * @param string $className Class name to instantiate
	 * @return object Instance of the class
	 * @throws \ReflectionException If class doesn't exist
	 * @throws \InvalidArgumentException If dependencies can't be resolved
	 */
	public function make( string $className ) {
		$reflection = new \ReflectionClass( $className );

		if ( ! $reflection->isInstantiable() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \InvalidArgumentException( "Class '{$className}' is not instantiable" );
		}

		$constructor = $reflection->getConstructor();

		if ( $constructor === null ) {
			return new $className();
		}

		$parameters   = $constructor->getParameters();
		$dependencies = array();

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();

			if ( $type === null ) {
				if ( $parameter->isDefaultValueAvailable() ) {
					$dependencies[] = $parameter->getDefaultValue();
				} else {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers only
					throw new \InvalidArgumentException(
						"Cannot resolve parameter '" . esc_html( $parameter->getName() ) . "' for class '" . esc_html( $className ) . "'"
					);
				}
				continue;
			}

			$typeName = $type->getName();

			// Try to resolve from container
			if ( $this->has( $typeName ) ) {
				$dependencies[] = $this->get( $typeName );
			} elseif ( class_exists( $typeName ) ) {
				// Recursively auto-wire dependencies
				$dependencies[] = $this->make( $typeName );
			} elseif ( $parameter->isDefaultValueAvailable() ) {
				$dependencies[] = $parameter->getDefaultValue();
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers only
				throw new \InvalidArgumentException(
					"Cannot resolve dependency '" . esc_html( $typeName ) . "' for class '" . esc_html( $className ) . "'"
				);
			}
		}

		return $reflection->newInstanceArgs( $dependencies );
	}

	/**
	 * Call a method with dependency injection
	 *
	 * @param object|string $target Object instance or class name
	 * @param string        $method Method name
	 * @param array         $parameters Additional parameters
	 * @return mixed Method return value
	 */
	public function call( $target, string $method, array $parameters = array() ) {
		if ( is_string( $target ) ) {
			$target = $this->make( $target );
		}

		$reflection       = new \ReflectionMethod( $target, $method );
		$methodParameters = $reflection->getParameters();
		$dependencies     = array();

		foreach ( $methodParameters as $parameter ) {
			$name = $parameter->getName();

			// Use provided parameter if available
			if ( array_key_exists( $name, $parameters ) ) {
				$dependencies[] = $parameters[ $name ];
				continue;
			}

			$type = $parameter->getType();

			if ( $type === null ) {
				if ( $parameter->isDefaultValueAvailable() ) {
					$dependencies[] = $parameter->getDefaultValue();
				} else {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers only
					throw new \InvalidArgumentException(
						"Cannot resolve parameter '" . esc_html( $name ) . "' for method '" . esc_html( $method ) . "'"
					);
				}
				continue;
			}

			$typeName = $type->getName();

			if ( $this->has( $typeName ) ) {
				$dependencies[] = $this->get( $typeName );
			} elseif ( class_exists( $typeName ) ) {
				$dependencies[] = $this->make( $typeName );
			} elseif ( $parameter->isDefaultValueAvailable() ) {
				$dependencies[] = $parameter->getDefaultValue();
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers only
				throw new \InvalidArgumentException(
					"Cannot resolve dependency '" . esc_html( $typeName ) . "' for method '" . esc_html( $method ) . "'"
				);
			}
		}

		return $reflection->invokeArgs( $target, $dependencies );
	}

	/**
	 * Register multiple services at once
	 *
	 * @param array $services Array of service definitions
	 */
	public function registerServices( array $services ): void {
		foreach ( $services as $name => $definition ) {
			if ( is_array( $definition ) && isset( $definition['class'] ) ) {
				$singleton = $definition['singleton'] ?? true;
				$this->set( $name, $definition['class'], $singleton );
			} else {
				$this->set( $name, $definition );
			}
		}
	}

	/**
	 * Get container statistics
	 *
	 * @return array
	 */
	public function getStats(): array {
		return array(
			'total_services'   => count( $this->services ),
			'cached_instances' => count( $this->instances ),
			'singletons'       => count( $this->singletons ),
			'memory_usage'     => memory_get_usage( true ),
		);
	}
}
