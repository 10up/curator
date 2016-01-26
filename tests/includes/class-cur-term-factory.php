<?php

/**
 * Class to create terms for all taxonomy kinds.
 *
 * Class Cur_Term_Factory
 */
 
class Cur_Term_Factory extends WP_UnitTest_Factory_For_Term {
	
	public function __construct ( $taxonomy, $factory = null ) {

		parent::__construct( $factory );

		$this->taxonomy = $taxonomy;

		$this->default_generation_definitions = array(
			'name' => new WP_UnitTest_Generator_Sequence( 'Test Term %s' ),
			'slug' => new WP_UnitTest_Generator_Sequence( 'test_term %s' ),
			'taxonomy' => $this->taxonomy,
			'description' => new WP_UnitTest_Generator_Sequence( 'Term description %s' ),
		);

	}

}