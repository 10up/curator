<?php

/**
 * Reusable Singleton Class
 *
 * This class is meant to be extended
 * Example:
 *
 * class CUR_MyClass extends CUR_Singleton {
 * }
 */
abstract class CUR_Singleton {

	/**
	 * Internally-stored instance of the class
	 *
	 * DO NOT CALL THIS PROPERTY DIRECTLY,
	 * use the MyClass::factory() method below instead
	 *
	 * By using an array, we can store singltetons for many classes
	 * This makes it possible to be a base class, meant to be extended
	 * @var array
	 */
	protected static $_instance = array();

	/**
	 * All extended child classes
	 */
	protected function  __construct() { }

	/**
	 * Returns new or existing Singleton instance
	 *
	 * Call this method instead of using 'new MyClass ;'
	 *
	 * Example:
	 *
	 * class CUR_MyClass extends CUR_Singleton {
	 * 		public function __construct {
	 * 			$this->doMethod();
	 * 		}
	 * 		public function doMethod {
	 * 			// this runs on instantiation
	 * 		}
	 * 		public function doAnotherMethod {
	 * 		}
	 * }
	 *
	 * $myClassInstance = CUR_MyClass::factory;
	 * $myClassInstance->doAnotherMethod;
	 *
	 * @return Singleton
	 */
	final static function factory() {
		// Reference the class being instantiated
		$class = get_called_class();

		// Is there already an instance of the class?
		if ( ! isset( static::$_instance[ $class ] ) ) {

			// If not, instantiate a new instance
			self::$_instance[$class] = new $class();
		}

		// Return the single instance of the class
		return self::$_instance[$class];
	}
}