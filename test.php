<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
require_once("annotations.php");

class AliasAnnotation extends AddendumPP\Annotation {}

class NamespaceAnnotation extends AddendumPP\Annotation {}

// Quick hack
class Target extends AddendumPP\Annotation_Target {}

/**
 * @AliasAnnotation("Nonsense")
 */
class Test extends AddendumPP\Annotation
{
    
}

/**
 * @NamespaceAnnotation("T")
 * @AliasAnnotation("Other")
 */
class Test2 extends AddendumPP\Annotation
{
}

/**
 * @Test()
 * @T:Other("Other message")
 */
class TestTarget
{
    /**
     * @Nonsense("Meh")
     * @Test2("Success!")
     */
    function blah()
    {
        return 42;
    }
}

class FancyResolver extends AddendumPP\AnnotationResolver
{
    private $namespaces = array();
    public function match($className) {
        $namespace = "";
        $key = $className;
        if(strpos($className, ":") !== FALSE)
        {
            $parts = explode(":", $className, 2);
            $namespace = $parts[0];
            $key = $parts[1];
        }
        
        // Check whether we have a cached result
        if(isset($namespaces[$namespace])) {
            if(isset($namespaces[$namespace][$key]))
                return $namespaces[$namespace][$key];
        }
        
        // If not, and there's no namespace, scan the declared annotation list
        if($namespace == "")
        {
            foreach($this->addendum->getDeclaredAnnotations() as $annotation) {
                if($annotation == $className) {
                    if(!isset($namespaces[""]))
                        $namespaces[""] = array();
                    $namespaces[""][$className] = $annotation;
                    return $annotation;
                }
            }
        }
        
        // If we didn't find one, check for an aliased one
        // If there is a namespace, go through the list of annotations
        // and stack them into the cached array
        foreach($this->addendum->getDeclaredAnnotations() as $annotation) {
            $reflClass = $this->addendum->reflect($annotation);
            if($reflClass->hasAnnotation("AliasAnnotation")) {
                $targetNamespace = "";
                $targetAlias = $reflClass->getAnnotation("AliasAnnotation")->value;
                if($reflClass->hasAnnotation("NamespaceAnnotation")) {
                    $targetNamespace = $reflClass->getAnnotation("NamespaceAnnotation")->value;
                }
                if(!isset($namespaces[$targetNamespace]))
                    $namespaces[$targetNamespace] = array();
                $namespaces[$targetNamespace][$targetAlias] = $annotation;
                if($namespace == $targetNamespace && $targetAlias == $key)
                    return $annotation;
            }
        }
        throw new AddendumPP\UnresolvedAnnotationException($className);
    }
}

class FancyAddendum extends AddendumPP\AddendumPP
{
    public function __construct()
    {
        parent::__construct();
        $this->resolver = new FancyResolver($this);
    }
}

$add = new FancyAddendum();

$reflClass = $add->reflect('TestTarget');
echo "Class stuff:\n";
foreach($reflClass->getAnnotations() as $annotation) {
    echo get_class($annotation) . " => " . $annotation->value . "\n";
}
echo "\n";

echo "Method stuff:\n";
foreach($reflClass->getMethods() as $reflMethod) {
    echo $reflMethod->getName() . ":\n";
    foreach($reflMethod->getAnnotations() as $annotation) {
        echo get_class($annotation) . " => " . $annotation->value . "\n";
    }
}

?>
