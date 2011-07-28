<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once("annotations.php");

class Test extends AddendumPP\Annotation
{
    
}

class Test2 extends AddendumPP\Annotation
{
    public $message;
}

/**
 * @Test()
 * @Ts:et()
 */
class TestTarget
{
    /**
     * @Test2(message = "Success!")
     */
    function blah()
    {
        return 42;
    }
}

$add = new AddendumPP\AddendumPP();

$reflClass = $add->reflect('TestTarget');
foreach($reflClass->getAnnotations() as $annotation) {
    echo "Annotation:\n";
    var_dump($annotation);
    echo "\n\n";
}

foreach($reflClass->getMethods() as $reflMethod) {
    foreach($reflMethod->getAnnotations() as $annotation) {
        echo "Method annotation:\n";
        var_dump($annotation);
        echo "\n\n";
    }
}

?>
