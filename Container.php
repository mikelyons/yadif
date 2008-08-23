<?php
/**
 * Yadif
 * 
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to tsmckelvey@gmail.com so I can send you a copy immediately.
 */

require_once 'Exception.php';

/**
 * Yadif_Container
 *
 * @category   Yadif
 * @copyright  Copyright (c) 2008 Thomas McKelvey
 */
class Yadif_Container
{
	/**
	 * Class index key of component $config
	 */
	const CONFIG_CLASS = 'class';

	/**
	 * Arguments index key of component $config
	 */
	const CONFIG_ARGUMENTS = 'arguments';

	// container of component configurations
	protected $_container = array();

	// parameters which have been set, expected to be bound
	protected $_parameters = array();

	public function __construct(array $config = null)
	{
		if (isset($config)) {
			if (!is_array($config)) throw new Yadif_Exception('$config not array, is ' . gettype($config));

			foreach ($config as $componentName => $componentConfig) {
				$this->addComponent( $componentName, $componentConfig );
			}
		}
	}

	/**
	 * Getter method for internal array of component configurations
	 *
	 * @return array
	 */
	public function getContainer()
	{
		return $this->_container;
	}

	/**
	 * Getter method for internal array of parameters
	 * 
	 * @return array
	 */
	public function getParameters()
	{
		return $this->_parameters;
	}
	
	/**
	 * Add a component to the container
	 *
	 * If $config is omitted, $name is assumed to be the classname.
	 *
	 * @param string|Yadif_Container $name Class-tag, class, or Yadif_Container
	 * @param array $config Array of configuration values
	 * @return Yadif_Container
	 */
	public function addComponent($name = null, array $config = null)
	{
		if (!is_string($name) && !($name instanceof Yadif_Container)) 
			throw new Yadif_Exception('$string not string|Yadif_Container, is ' . gettype($string));

		if ($name instanceof Yadif_Container) { // if Yadif_Container
			$foreignContainer = $name->getContainer();

			// merge containers, @TODO: duplicates
			$this->_container = array_merge($this->_container, $foreignContainer);

			return $this;
		}

		if (!is_array($config) || !isset($config[self::CONFIG_CLASS])) { // assume name is the class name
			$config[self::CONFIG_CLASS] = $name;
		}

		if (!is_array($config[ self::CONFIG_ARGUMENTS ]))
			$config[ self::CONFIG_ARGUMENTS ] = array();

		// if class is set and doesn't exist
		if (!class_exists( $config[self::CONFIG_CLASS] ))
			throw new Yadif_Exception( 'Class ' . $config[self::CONFIG_CLASS] . ' not found' );

		$this->_container[ $name ] = $config;

		return $this;
	}

	/**
	 * Bind a parameter
	 *
	 * @param string $param The parameter name, to be given with a leading colon ":param"
	 * @param mixed $value The value to bind to the parameter
	 * @return Yadif_Container
	 */
	public function bindParam($param = null, $value)
	{
		if (!is_string($param)) throw new Yadif_Exception('$param not string, is ' . gettype($param));

		if ($param{0} != ':')
			throw new Yadif_Exception($param . ' must start with a colon (:)');

		$this->_parameters[$param] = $value;

		return $this;
	}

	/**
	 * Retrieve a parameter by name
	 *
	 * @return mixed
	 */
	public function getParam($param)
	{
		if (isset($this->_parameters[$param])) {
			return $this->_parameters[$param];
		}

		return false;
	}

	/**
	 * Get back a fully assembled component based on the configuration provided beforehand
	 *
	 * @param string $name The name of the component
	 * @return mixed 
	 */
	public function getComponent($name = null)
	{
		if (!is_string($name)) throw new Yadif_Exception('$name not string, is ' . gettype($name));

		// if we're trying to "getParameter" (see the loop below)
		if (array_key_exists($name, $this->_parameters)) {
			return $this->getParam($name);
		}

		if (!array_key_exists($name, $this->_container))
			throw new Yadif_Exception("'$name' not in " . '$this->_container or $this->_parameters');

		$component = $this->_container[ $name ];

		$componentReflection = new ReflectionClass($component[ self::CONFIG_CLASS ]);

		$componentArgs = $component[ self::CONFIG_ARGUMENTS ];

		if (empty($componentArgs)) { // if no instructions
			return $componentReflection->newInstance();
		}

		$currentIndex = 0;

		foreach ($componentArgs as $method => $args) {
			$injection = array();

			foreach ($args as $arg) {
				$injection[] = $this->getComponent($arg);
			}

			if ($componentReflection->getMethod($method)->isConstructor()) {
				if (empty($injection)) {
					$component = $componentReflection->newInstance();
				} else {
					$component = $componentReflection->newInstanceArgs($injection);
				}
			} else { // if not constructor
				if (!is_object($component)) 
					$component = $componentReflection->newInstance();

				if (empty($injection)) {
					$componentReflection->getMethod($method)
										->invoke($component);
				} else {
					$componentReflection->getMethod($method)
										->invokeArgs( $component, $injection );
				}
			}

			++$currentIndex;
		}

		return $component;
	}

	/**
	 * Replace a component with another, hopefully implementing the same interface
	 *
	 * @param string $name The component name in our container
	 * @param string|array $replacement The class name of our replacement or a configuration array
	 * @return Yadif_Container
	 */
	public function replace($name = null, $replacement = null)
	{
		if (!is_string($name))
			throw new Yadif_Exception('$name not string, is ' . gettype($name));
		
		if (!is_string($replacement) && !is_array($replacement))
			throw new Yadif_Exception('$replacement not string|array, is ' . gettype($name));

		if (!array_key_exists($name, $this->_container))
			throw new Yadif_Exception('$name ' . $name . ' not in $this->_container');

		$replacementClass = is_array($replacement) ? $replacement[self::CONFIG_CLASS] : $replacement;

		assert(is_string($replacementClass));

		if (!class_exists($replacementClass))
			throw new Yadif_Exception('Class ' . $replacement . ' not found');

		if (is_string($replacement)) {
			$this->_container[$name][self::CONFIG_CLASS] = $replacement;
		} elseif (is_array($replacement)) {
			$this->addComponent($name, $replacement);
		}

		return $this;
	}
}