<?php

/**
 * @AddendumPP\Annotation_Target("meta")
 */
class MetaAnnotation extends AddendumPP\Annotation
{
}

/**
 * @AddendumPP\Annotation_Target("class")
 */
class ClassAnnotation extends AddendumPP\Annotation
{
}

/**
 * @AddendumPP\Annotation_Target("method")
 */
class MethodAnnotation extends AddendumPP\Annotation
{
}

/**
 * @AddendumPP\Annotation_Target("property")
 */
class PropertyAnnotation extends AddendumPP\Annotation
{
}

/**
 * @AddendumPP\Annotation_Target("nested")
 */
class NestedAnnotation extends AddendumPP\Annotation
{
}

/**
 * @MetaAnnotation()
 * @ClassAnnotation()
 */
class ProperlyAnnotatedClass extends AddendumPP\Annotation
{
    /**
     * @PropertyAnnotation()
     */
    public $goodProperty;
    
    /**
     * @MethodAnnotation()
     */
    public function goodMethod()
    {
    }
    
    /**
     * @MethodAnnotation(@NestedAnnotation())
     */
    public function goodNested()
    {
    }
    
    /**
     * @MethodAnnotation()
     */
    public $badMethod;
    /**
     * @ClassAnnotation()
     */
    public $badClass;
    
    /**
     * @PropertyAnnotation()
     */
    public function badProperty()
    {
    }
    
    /**
     * @NestedAnnotation()
     */
    public function badNested()
    {
    }
}

/**
 * @MetaAnnotation()
 */
class ImproperlyAnnotatedClass
{
    
}

?>
