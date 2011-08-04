<?php

require_once("PHPUnit/Autoload.php");
require_once(dirname(__FILE__)."/../annotations.php");
require_once(dirname(__FILE__)."/fixtures/DefaultResolverFixture.php");

/**
 * Tests the functionality of the built-in default resolver
 *
 * @author predakanga
 * @since 0.1
 */
class DefaultResolverTest extends PHPUnit_Framework_TestCase {
    /**
     * @var AddendumPP\AddendumPP
     */
    protected $fixture;

    protected function setUp() {
        $this->fixture = new AddendumPP\AddendumPP();
    }

    protected function tearDown() {
        
    }

    public function testOneMatch() {
        $this->assertEquals("OneMatch_Works", $this->fixture->resolveClassName("Works"));
        $this->assertEquals("Doesnt", $this->fixture->resolveClassName("Doesnt"));
    }
    
    public function testExactMatch() {
        $this->assertEquals("ExactMatch", $this->fixture->resolveClassName("ExactMatch"));
    }

    public function testNoMatch() {
        $this->assertEquals("NoMatch", $this->fixture->resolveClassName("NoMatch"));
    }
    
    public function testTooManyMatches() {
        
        try
        {
            $this->fixture->resolveClassName("Failure");
        }
        catch(AddendumPP\UnresolvedAnnotationException $e)
        {
            // Shouldn't have an exception here
            $this->fail("Didn't expect an UnresolvedAnnotationException");
        }
        
        try
        {
            $this->fixture->resolveClassName("Success");
        }
        catch(AddendumPP\UnresolvedAnnotationException $e)
        {
            // Expected exception, so just return
            return;
        }
        $this->fail("Expected an UnresolvedAnnotationException");
    }
}

?>