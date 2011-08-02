<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once("PHPUnit/Autoload.php");
require_once(dirname(__FILE__)."/../annotations.php");
require_once(dirname(__FILE__)."/fixtures/AddendumFixture.php");

/**
 * Description of AddendumTest.php
 *
 * @author predakanga
 * @since x.y
 */
class AddendumTest extends PHPUnit_Framework_TestCase {
    /**
     * @var AddendumPP\AddendumPP
     */
    protected $fixture;

    protected function setUp() {
        $this->fixture = new AddendumPP\AddendumPP();
    }

    protected function tearDown() {

    }
    
    public function testReflectionMode() {
        // If DocComment exists already, then the fixture decided to use it by default
        // That means that we have to skip this test
        if(array_search("AddendumPP\\DocComment", get_declared_classes()) !== FALSE)
                $this->markTestSkipped("Reflection mode not available");
        
        $this->fixture->setRawMode(false);
        
        $mockRefl = $this->getMock('ReflectionClass', array('getDocComment'), array('AnnotatedClass'));
        $mockRefl->expects($this->once())->method('getDocComment');
        
        $this->fixture->getDocComment($mockRefl);
        
        $actualRefl = $this->fixture->reflect('AnnotatedClass');
        $this->assertTrue($actualRefl->hasAnnotation('IgnoredAnnotation'));
    }
    
    public function testRawMode() {
        $this->fixture->setRawMode(true);
        
        $mockRefl = $this->getMock('ReflectionClass', array('getDocComment'), array('AnnotatedClass'));
        $mockRefl->expects($this->never())->method('getDocComment');
        
        $this->fixture->getDocComment($mockRefl);
        
        $actualRefl = $this->fixture->reflect('AnnotatedClass');
        $this->assertTrue($actualRefl->hasAnnotation('IgnoredAnnotation'));
    }
    
    public function testIgnore() {
        $reflClass = $this->fixture->reflect('AnnotatedClass');
        $this->assertEquals(1, count($reflClass->getAllAnnotations()));
        $this->assertFalse($this->fixture->ignores('IgnoredAnnotation'));
        
        $this->fixture->ignore('IgnoredAnnotation');
        
        $reflClass = $this->fixture->reflect('AnnotatedClass');
        $this->assertEquals(0, count($reflClass->getAllAnnotations()));
        $this->assertTrue($this->fixture->ignores('IgnoredAnnotation'));
        
        $this->fixture->resetIgnoredAnnotations();
        
        $reflClass = $this->fixture->reflect('AnnotatedClass');
        $this->assertFalse($this->fixture->ignores('IgnoredAnnotation'));
        $this->assertEquals(1, count($reflClass->getAllAnnotations()));
    }
}
?>