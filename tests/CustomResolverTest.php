<?php

require_once("PHPUnit/Autoload.php");
require_once(dirname(__FILE__)."/../annotations.php");
require_once(dirname(__FILE__)."/fixtures/CustomResolverFixture.php");

/**
 * Tests the functionality of a simple custom resolver
 *
 * @author predakanga
 * @since 0.1
 */
class CustomResolverTest extends PHPUnit_Framework_TestCase {
    /**
     * @var CustomAddendum
     */
    protected $fixture;

    protected function setUp() {
        $this->fixture = new CustomAddendum();
    }

    protected function tearDown() {
        
    }

    public function testNormalResolving() {
        $reflClass = $this->fixture->reflect("TestTarget");
        $reflMethod = $reflClass->getMethod("blah");
        
        $this->assertTrue($reflClass->hasAnnotation("Test"));
        $this->assertTrue($reflMethod->hasAnnotation("Test2"));
    }
    
    public function testNamespaceAndAlias() {
        $reflClass = $this->fixture->reflect("TestTarget");
        
        $this->assertTrue($reflClass->hasAnnotation("Test2"));
        $this->assertTrue($reflClass->hasAnnotation("T:Other"));
    }

    public function testAliasOnly() {
        $reflClass = $this->fixture->reflect("TestTarget");
        $reflMethod = $reflClass->getMethod("blah");
        
        $this->assertTrue($reflMethod->hasAnnotation("Test"));
        $this->assertTrue($reflMethod->hasAnnotation("Nonsense"));
    }
}

?>