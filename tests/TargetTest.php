<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once("PHPUnit/Autoload.php");
require_once(dirname(__FILE__)."/../annotations.php");
require_once(dirname(__FILE__)."/fixtures/TargetFixture.php");

/**
 * Description of TargetTest
 *
 * @author predakanga
 * @since x.y
 */
class TargetTest extends PHPUnit_Framework_TestCase {
    /**
     * @var AddendumPP\AddendumPP
     */
    protected $fixture;

    public function setUp() {
        $this->fixture = new AddendumPP\AddendumPP();
    }

    public function tearDown() {
        
    }

    public function testProperTargets() {
        $reflClass = $this->fixture->reflect("ProperlyAnnotatedClass");
        $reflMethod = $reflClass->getMethod("goodMethod");
        $reflNested = $reflClass->getMethod("goodNested");
        $reflProperty = $reflClass->getProperty("goodProperty");
    }
    
    public function testImproperTargets() {
        $exceptionCount = 0;
        try
        {
            $reflClass = $this->fixture->reflect("ImproperlyAnnotatedClass");
        }
        catch(AddendumPP\NestingNotAllowedException $e)
        {
            $exceptionCount++;
            // When we have a failed reflection, reset the creation stack
            $this->fixture->creationStack = array();
        }
        $reflParent = $this->fixture->reflect("ProperlyAnnotatedClass");
        try
        {
            $reflMethod = $reflParent->getProperty("badMethod");
        }
        catch(AddendumPP\NestingNotAllowedException $e)
        {
            $exceptionCount++;
            // When we have a failed reflection, reset the creation stack
            $this->fixture->creationStack = array();
        }
        try
        {
            $reflClass = $reflParent->getProperty("badClass");
        }
        catch(AddendumPP\NestingNotAllowedException $e)
        {
            $exceptionCount++;
            // When we have a failed reflection, reset the creation stack
            $this->fixture->creationStack = array();
        }
        try
        {
            $reflProperty = $reflParent->getMethod("badProperty");
        }
        catch(AddendumPP\NestingNotAllowedException $e)
        {
            $exceptionCount++;
            // When we have a failed reflection, reset the creation stack
            $this->fixture->creationStack = array();
        }
        try
        {
            $reflNested = $reflParent->getMethod("badNested");
        }
        catch(AddendumPP\NestingNotAllowedException $e)
        {
            $exceptionCount++;
            // When we have a failed reflection, reset the creation stack
            $this->fixture->creationStack = array();
        }
        $this->assertEquals(5, $exceptionCount);
    }
}
?>