<?php

class AliasAnnotation extends AddendumPP\Annotation {}

class NamespaceAnnotation extends AddendumPP\Annotation {}

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
 * @T:Other("Alias and namespace")
 */
class TestTarget
{
    /**
     * @Nonsense("Alias only")
     * @Test2("Basic resolution")
     */
    function blah()
    {
        return 42;
    }
}

class CustomAddendum extends AddendumPP\AddendumPP
{
    private $namespaces = array("" => array("Target" => "AddendumPP\Annotation_Target"));
    
    public function resolveClassName($className) {
        $namespace = "";
        $key = $className;
        if(strpos($className, ":") !== FALSE)
        {
            $parts = explode(":", $className, 2);
            $namespace = $parts[0];
            $key = $parts[1];
        }
        
        // Check whether we have a cached result
        if(isset($this->namespaces[$namespace])) {
            if(isset($this->namespaces[$namespace][$key]))
                return $this->namespaces[$namespace][$key];
        }
        
        // If not, and there's no namespace, scan the declared annotation list
        if($namespace == "")
        {
            foreach($this->getDeclaredAnnotations() as $annotation) {
                if($annotation == $className) {
                    if(!isset($this->namespaces[""]))
                        $this->namespaces[""] = array();
                    $this->namespaces[""][$className] = $annotation;
                    return $annotation;
                }
            }
        }
        
        // If we didn't find one, check for an aliased one
        // If there is a namespace, go through the list of annotations
        // and stack them into the cached array
        foreach($this->getDeclaredAnnotations() as $annotation) {
            // To avoid a circular reference exception, skip over any annotations already on the creation stack
            if(isset($this->creationStack[$annotation]) && $this->creationStack[$annotation])
                continue;
            
            $reflClass = $this->reflect($annotation);
            if($reflClass->hasAnnotation("AliasAnnotation")) {
                $targetNamespace = "";
                $targetAlias = $reflClass->getAnnotation("AliasAnnotation")->value;
                if($reflClass->hasAnnotation("NamespaceAnnotation")) {
                    $targetNamespace = $reflClass->getAnnotation("NamespaceAnnotation")->value;
                }
                if(!isset($this->namespaces[$targetNamespace]))
                    $this->namespaces[$targetNamespace] = array();
                $this->namespaces[$targetNamespace][$targetAlias] = $annotation;
                if($namespace == $targetNamespace && $targetAlias == $key)
                    return $annotation;
            }
        }
        throw new AddendumPP\UnresolvedAnnotationException($className);
    }
}

?>
