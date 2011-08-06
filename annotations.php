<?php

/**
 * Addendum PHP Reflection Annotations
 * http://code.google.com/p/addendum/
 *
 * Copyright (C) 2006-2009 Jan "johno Suchal <johno@jsmf.net>

 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package AddendumPP
 */

namespace AddendumPP;

use \ReflectionClass,
    \ReflectionMethod,
    \ReflectionProperty,
    \Exception;

require_once(dirname(__FILE__) . '/annotations/annotation_parser.php');
require_once(dirname(__FILE__) . '/annotation_exceptions.php');

/**
 * Base class for all annotations
 * 
 * Provides constraint checking, and checks for circular references.
 */
class Annotation {
    /**
     * @var mixed Stores the un-named argument to the annotation
     */
    public $value;
    /**
     * The addendum object that this method belongs to
     * 
     * @var AddendumPP\AddendumPP
     */
    protected $addendum;

    /**
     * Constructs a new annotation
     * 
     * @param AddendumPP\AddendumPP $addendum The instance of addendum for this annotation
     * @param array $data Parameters as an associative array of keys to values
     * @param mixed $target The target to which the annotation is applied
     */
    public final function __construct($addendum, $data = array(), $target = false) {
        $this->addendum = $addendum;
        $reflection = new ReflectionClass($this);
        $class = $reflection->getName();
        if (isset($this->addendum->creationStack[$class])) {
            throw new CircularAnnotationReferenceException($class);
        }
        $this->addendum->creationStack[$class] = true;
        foreach ($data as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $this->$key = $value;
            } else {
                throw new InvalidPropertyException($class, $key);
            }
        }
        $this->checkTargetConstraints($target);
        $this->checkConstraints($target);
        unset($this->addendum->creationStack[$class]);
    }

    /**
     * Checks the constraint of the annotation's allowed targets
     * 
     * @param mixed $target The target that this annotation is applied to
     */
    private function checkTargetConstraints($target) {
        $reflection = new ReflectionAnnotatedClass($this->addendum, $this, 'AddendumPP\Annotation_Target');
        if ($reflection->hasAnnotation('Target')) {
            $value = $reflection->getAnnotation('Target')->value;
            $values = is_array($value) ? $value : array($value);
            foreach ($values as $value) {
                if ($value == 'meta' && $target instanceof ReflectionClass && $target->isSubclassOf("AddendumPP\Annotation"))
                    return;
                if ($value == 'class' && $target instanceof ReflectionClass)
                    return;
                if ($value == 'method' && $target instanceof ReflectionMethod)
                    return;
                if ($value == 'property' && $target instanceof ReflectionProperty)
                    return;
                if ($value == 'nested' && $target === false)
                    return;
            }
            if ($target === false) {
                throw new NoNestingAllowedException(get_class($this));
            } else {
                throw new NestingNotAllowedException(get_class($this), $this->createName($target));
            }
        }
    }

    /**
     * Creates a fully qualified name for the target, for caching purposes
     * 
     * @param mixed $target The target to be named
     * @return string The fully qualified name of the target
     */
    private function createName($target) {
        if ($target instanceof ReflectionMethod) {
            return $target->getUnannotatedDeclaringClass()->getName() . '::' . $target->getName();
        } elseif ($target instanceof ReflectionProperty) {
            return $target->getUnannotatedDeclaringClass()->getName() . '::$' . $target->getName();
        } else {
            return $target->getName();
        }
    }

    /**
     * Checks any constraints of an annotation
     * 
     * Override this function to add additional constraints to an annotation
     * 
     * @param mixed $target The target that this annotation is applied to
     */
    protected function checkConstraints($target) {
        
    }
}

/**
 * A collection of utility methods, for dealing with annotations on a reflection
 */
class AnnotationsCollection {
    /**
     * The annotations in this collection
     * 
     * @var AddendumPP\Annotation[]
     */
    private $annotations;
    /**
     * The addendum object that this method belongs to
     * 
     * @var AddendumPP\AddendumPP
     */
    private $addendum;

    public function __construct($addendum, $annotations) {
        $this->addendum = $addendum;
        $this->annotations = $annotations;
    }

    /**
     * Checks whether this collection holds a given annotation
     * 
     * @param type $class Tag name for the annotation
     * @return bool
     */
    public function hasAnnotation($class) {
        $class = $this->addendum->resolveClassName($class);
        return isset($this->annotations[$class]);
    }

    /**
     * Returns one annotation, specified by the tag name
     * 
     * @param type $class Tag name for the annotation
     * @return AddendumPP\Annotation 
     */
    public function getAnnotation($class) {
        $class = $this->addendum->resolveClassName($class);
        return isset($this->annotations[$class]) ? end($this->annotations[$class]) : false;
    }

    /**
     * Returns all annotations, with a max of one per type
     * 
     * @return AddendumPP\Annotation[]
     */
    public function getAnnotations() {
        $result = array();
        foreach ($this->annotations as $instances) {
            $result[] = end($instances);
        }
        return $result;
    }

    /**
     * Returns all annotations, including multiple of the same type
     * 
     * @param string $restriction If specified, only annotations of this tag name will be returned
     * @return AddendumPP\Annotation[] 
     */
    public function getAllAnnotations($restriction = false) {
        if($restriction !== false)
            $restriction = $this->addendum->resolveClassName($restriction);
        $result = array();
        foreach ($this->annotations as $class => $instances) {
            if (!$restriction || $restriction == $class) {
                $result = array_merge($result, $instances);
            }
        }
        return $result;
    }
}

/**
 * A meta-annotation to restrict which targets it's subject can be applied to
 * 
 * Possible values:
 * "class"
 * "method"
 * "property"
 * "meta"
 * "nested"
 */
class Annotation_Target extends Annotation {
}

/**
 * Parses and instantiates annotation data
 */
class AnnotationsBuilder {
    private $cache = array();
    private $addendum;

    public function __construct($addendum) {
        $this->addendum = $addendum;
    }

    /**
     * Given a reflection of an object, collects and returns all the annotations that are applied to it
     * 
     * @param Reflector $targetReflection Reflection of the object to scan for annotations
     * @return AnnotationsCollection
     */
    public function build($targetReflection, $restriction = NULL) {
        $data = $this->parse($targetReflection);
        $annotations = array();
        
        foreach ($data as $class => $parameters) {
            $className = $this->addendum->resolveClassName($class);
            if($restriction != NULL && !(is_subclass_of($className, $restriction) || $restriction == $className))
                continue;
            
            foreach ($parameters as $params) {
                $annotation = $this->instantiateAnnotation($class, $params, $targetReflection);
                if ($annotation !== false) {
                    $annotations[get_class($annotation)][] = $annotation;
                }
            }
        }
        return new AnnotationsCollection($this->addendum, $annotations);
    }

    /**
     * Instantiates an annotation
     * 
     * @param string $class The name of the annotation to instantiate
     * @param array $parameters Array of the parameters for the annotation's constructor
     * @param Reflector $targetReflection The object that the annotation targets
     * @return AddendumPP\Annotation
     */
    public function instantiateAnnotation($class, $parameters, $targetReflection = false) {
        $class = $this->addendum->resolveClassName($class);
        if (is_subclass_of($class, 'AddendumPP\\Annotation') && !$this->addendum->ignores($class) || $class == 'AddendumPP\\Annotation') {
            $annotationReflection = new ReflectionClass($class);
            return $annotationReflection->newInstance($this->addendum, $parameters, $targetReflection);
        }
        return false;
    }

    private function parse($reflection) {
        $key = $this->createName($reflection);
        if (!isset($this->cache[$key])) {
            $parser = new AnnotationsMatcher;
            $parser->matches($this->addendum, $this->getDocComment($reflection), $data);
            $this->cache[$key] = $data;
        }
        return $this->cache[$key];
    }

    private function createName($target) {
        if ($target instanceof ReflectionMethod) {
            return $target->getDeclaringClass()->getName() . '::' . $target->getName();
        } elseif ($target instanceof ReflectionProperty) {
            return $target->getDeclaringClass()->getName() . '::$' . $target->getName();
        } else {
            return $target->getName();
        }
    }
    
    protected function getDocComment($reflection) {
        return $this->addendum->getDocComment($reflection);
    }

    /**
     * Clears the cache of annotations-per-reflection
     */
    public function clearCache() {
        $this->cache = array();
    }
}

/**
 * Reflection of a class, with associated annotations
 * 
 * @see ReflectionClass
 */
class ReflectionAnnotatedClass extends ReflectionClass {
    /**
     * Stores the annotations which target this class
     * 
     * @var AnnotationsCollection 
     */
    private $annotations;
    
    /**
     * The AddendumPP object
     * 
     * @var AddendumPP\AddendumPP
     */
    private $addendum;

    /**
     * @see ReflectionClass::__construct
     * 
     * @param AddendumPP\AddendumPP $addendum
     * @param mixed $class 
     */
    public function __construct($addendum, $class, $restriction = NULL) {
        parent::__construct($class);

        $this->addendum = $addendum;
        $this->annotations = $this->createAnnotationBuilder()->build($this, $restriction);
    }

    /**
     * Checks whether this class is targetted by a type of annotation
     * 
     * @param string $class Tag name of the annotation
     * @return bool
     */
    public function hasAnnotation($class) {
        return $this->annotations->hasAnnotation($class);
    }

    /**
     * Returns one annotation, specified by the tag name
     * 
     * @param string $annotation Tag name of the annotation
     * @return AddendumPP\Annotation
     */
    public function getAnnotation($annotation) {
        return $this->annotations->getAnnotation($annotation);
    }

    /**
     * Returns all annotations, with a max of one per type
     * 
     * @return AddendumPP\Annotation[]
     */
    public function getAnnotations() {
        return $this->annotations->getAnnotations();
    }

    /**
     * Returns all annotations, including multiple of the same type
     * 
     * @param string $restriction If specified, only annotations of this tag name will be returned
     * @return AddendumPP\Annotation[]
     */
    public function getAllAnnotations($restriction = false) {
        return $this->annotations->getAllAnnotations($restriction);
    }

    /**
     * @see ReflectionClass::getConstructor
     * 
     * @return AddendumPP\ReflectionAnnotatedMethod
     */
    public function getConstructor() {
        return $this->createReflectionAnnotatedMethod(parent::getConstructor());
    }

    /**
     * @see ReflectionClass::getMethod
     * 
     * @return AddendumPP\ReflectionAnnotatedMethod
     */
    public function getMethod($name) {
        return $this->createReflectionAnnotatedMethod(parent::getMethod($name));
    }

    /**
     * @see ReflectionClass::getMethods
     * @param string $filter Flags with which to filter
     * @return AddendumPP\ReflectionAnnotatedMethod[] 
     */
    public function getMethods($filter = -1) {
        $result = array();
        foreach (parent::getMethods($filter) as $method) {
            $result[] = $this->createReflectionAnnotatedMethod($method);
        }
        return $result;
    }

    /**
     * @see ReflectionClass::getProperty
     * @param string $name Property name
     * @return AddendumPP\ReflectionAnnotatedProperty 
     */
    public function getProperty($name) {
        return $this->createReflectionAnnotatedProperty(parent::getProperty($name));
    }

    /**
     * @see ReflectionClass::getProperties
     * @param string $filter Flags with which to filter
     * @return AddendumPP\ReflectionAnnotatedProperty[]
     */
    public function getProperties($filter = -1) {
        $result = array();
        foreach (parent::getProperties($filter) as $property) {
            $result[] = $this->createReflectionAnnotatedProperty($property);
        }
        return $result;
    }

    /**
     * @see ReflectionClass::getInterfaces
     * @return ReflectionInterface
     */
    public function getInterfaces() {
        $result = array();
        foreach (parent::getInterfaces() as $interface) {
            $result[] = $this->createReflectionAnnotatedClass($interface);
        }
        return $result;
    }

    /**
     * @see ReflectionClass::getParentClass
     * @return AddendumPP\ReflectionAnnotatedClass
     */
    public function getParentClass() {
        $class = parent::getParentClass();
        return $this->createReflectionAnnotatedClass($class);
    }

    protected function createAnnotationBuilder() {
        return $this->addendum->getAnnotationBuilder();
    }

    private function createReflectionAnnotatedClass($class) {
        return ($class !== false) ? new ReflectionAnnotatedClass($this->addendum, $class->getName()) : false;
    }

    private function createReflectionAnnotatedMethod($method) {
        return ($method !== null) ? new ReflectionAnnotatedMethod($this->addendum, $this->getName(), $method->getName()) : null;
    }

    private function createReflectionAnnotatedProperty($property) {
        return ($property !== null) ? new ReflectionAnnotatedProperty($this->addendum, $this->getName(), $property->getName()) : null;
    }

}

/**
 * Reflection of a method, with associated annotations
 * 
 * @see ReflectionMethod
 */
class ReflectionAnnotatedMethod extends ReflectionMethod {
    /**
     * Stores the annotations which target this class
     * 
     * @var AnnotationsCollection 
     */
    private $annotations;
    
    /**
     * The AddendumPP object
     * 
     * @var AddendumPP\AddendumPP
     */
    private $addendum;

    /**
     * @see ReflectionMethod::__construct
     * 
     * @param AddendumPP\AddendumPP $addendum
     * @param mixed $class
     * @param string $name 
     */
    public function __construct($addendum, $class, $name) {
        parent::__construct($class, $name);

        $this->addendum = $addendum;
        $this->annotations = $this->createAnnotationBuilder()->build($this);
    }

    /**
     * Checks whether this method is targetted by a type of annotation
     * 
     * @param string $class Tag name of the annotation
     * @return bool
     */
    public function hasAnnotation($class) {
        return $this->annotations->hasAnnotation($class);
    }

    /**
     * Returns all annotations, with a max of one per type
     * 
     * @return AddendumPP\Annotation[]
     */
    public function getAnnotation($annotation) {
        return $this->annotations->getAnnotation($annotation);
    }

    /**
     * Returns all annotations, with a max of one per type
     * 
     * @return AddendumPP\Annotation[]
     */
    public function getAnnotations() {
        return $this->annotations->getAnnotations();
    }

    /**
     * Returns all annotations, including multiple of the same type
     * 
     * @param string $restriction If specified, only annotations of this tag name will be returned
     * @return AddendumPP\Annotation[]
     */
    public function getAllAnnotations($restriction = false) {
        return $this->annotations->getAllAnnotations($restriction);
    }
    
    /**
     * Returns a reflection of the declaring class
     * 
     * @see ReflectionMethod::getDeclaringClass
     * 
     * @return ReflectionClass
     */
    public function getUnannotatedDeclaringClass() {
        return parent::getDeclaringClass();
    }

    /**
     * Returns a reflection of the declaring class, with annotations
     * 
     * @see ReflectionMethod::getDeclaringClass
     * 
     * @return AddendumPP\ReflectionAnnotatedClass
     */
    public function getDeclaringClass() {
        $class = parent::getDeclaringClass();
        return new ReflectionAnnotatedClass($this->addendum, $class->getName());
    }

    protected function createAnnotationBuilder() {
        return $this->addendum->getAnnotationBuilder();
    }

}

/**
 * Reflection of a property, with associated annotations
 * 
 * @see ReflectionProperty
 */
class ReflectionAnnotatedProperty extends ReflectionProperty {
    /**
     * Stores the annotations which target this class
     * 
     * @var AnnotationsCollection 
     */
    private $annotations;
    
    /**
     * The AddendumPP object
     * 
     * @var AddendumPP\AddendumPP
     */
    private $addendum;

    /**
     * @see ReflectionProperty::__construct
     * 
     * @param AddendumPP\AddendumPP $addendum
     * @param mixed $class
     * @param string $name 
     */
    public function __construct($addendum, $class, $name) {
        parent::__construct($class, $name);

        $this->addendum = $addendum;
        $this->annotations = $this->createAnnotationBuilder()->build($this);
    }

    /**
     * Checks whether this property is targetted by a type of annotation
     * 
     * @param string $class Tag name of the annotation
     * @return bool
     */
    public function hasAnnotation($class) {
        return $this->annotations->hasAnnotation($class);
    }

    /**
     * Returns all annotations, with a max of one per type
     * 
     * @return AddendumPP\Annotation[]
     */
    public function getAnnotation($annotation) {
        return $this->annotations->getAnnotation($annotation);
    }

    /**
     * Returns all annotations, with a max of one per type
     * 
     * @return AddendumPP\Annotation[]
     */
    public function getAnnotations() {
        return $this->annotations->getAnnotations();
    }

    /**
     * Returns all annotations, including multiple of the same type
     * 
     * @param string $restriction If specified, only annotations of this tag name will be returned
     * @return AddendumPP\Annotation[]
     */
    public function getAllAnnotations($restriction = false) {
        return $this->annotations->getAllAnnotations($restriction);
    }
    
    /**
     * Returns a reflection of the declaring class
     * 
     * @see ReflectionMethod::getDeclaringClass
     * 
     * @return ReflectionClass
     */
    public function getUnannotatedDeclaringClass() {
        return parent::getDeclaringClass();
    }

    /**
     * Returns a reflection of the declaring class, with annotations
     * 
     * @see ReflectionMethod::getDeclaringClass
     * 
     * @return AddendumPP\ReflectionAnnotatedClass
     */
    public function getDeclaringClass() {
        $class = parent::getDeclaringClass();
        return new ReflectionAnnotatedClass($this->addendum, $class->getName());
    }

    protected function createAnnotationBuilder() {
        return $this->addendum->getAnnotationBuilder();
    }
}

/**
 * Central class for AddendumPP
 * 
 * Provides the entry point for reflecting annotated objects, as well as
 * caching for various related objects
 */
class AddendumPP {
    /**
     * @var bool Denotes whether the parser will use manual doc-comment parsing
     */
    protected $rawMode;
    /**
     * @var AddendumPP\AnnotationsBuilder The builder which will be used by default
     */
    protected $builder;
    /**
     * @var array List of class names that this addendum instance ignores
     */
    protected $ignore;
    /**
     * @var array List of cached declared annotations
     */
    protected $annotations = false;
    /**
     * @var array List of cached mappings from annotation name to class
     */
    protected $cachedMappings = array();
    /**
     * Stores a list of classes currently being instantiated, to
     * be used to detect and error upon circular references
     * 
     * @var string[] Stack of class names being created
     */
    public $creationStack = array();
    
    public function __construct() {
        $this->checkRawDocCommentParsingNeeded();
        $this->builder = new AnnotationsBuilder($this);
    }
    
    // <editor-fold desc="Raw mode detection">
    /**
     * Retrieves the doc comment associated with a given reflection.
     * Works regardless of whether PHP supports it or not.
     * 
     * @param Reflector $reflection The reflection on which to operate
     * @return string The doc comment if it exists, otherwise FALSE
     */
    public function getDocComment($reflection) {
        if ($this->checkRawDocCommentParsingNeeded()) {
            $docComment = new DocComment();
            return $docComment->get($reflection);
        } else {
            return $reflection->getDocComment();
        }
    }

    /** Raw mode test */
    private function checkRawDocCommentParsingNeeded() {
        if ($this->rawMode === null) {
            $reflection = new ReflectionClass(__CLASS__);
            $method = $reflection->getMethod('checkRawDocCommentParsingNeeded');
            $this->setRawMode($method->getDocComment() === false);
        }
        return $this->rawMode;
    }

    /**
     * Enables or disables raw mode parsing of doc comments
     * 
     * @param bool $enabled
     */
    public function setRawMode($enabled = true) {
        if ($enabled) {
            require_once(dirname(__FILE__) . '/annotations/doc_comment.php');
        }
        $this->rawMode = $enabled;
    }
    // </editor-fold>
    
    /**
     * Forces AddendumPP to ignore annotations of a given class.
     * Accepts a variable amount of parameters, one per class
     */
    public function ignore() {
        foreach (func_get_args() as $class) {
            $this->ignore[$class] = true;
        }
    }

    /**
     * Checks whether AddendumPP ignores annotations of a given class
     * 
     * @return bool
     */
    public function ignores($class) {
        return isset($this->ignore[$class]);
    }
    
    /**
     * Empties the list of classes for AddendumPP to ignore
     */
    public function resetIgnoredAnnotations() {
        $this->ignore = array();
    }

    /**
     * Returns a list of annotations known to this instance of AddendumPP
     * Note: This list is cached on it's first retrieval
     * 
     * @return array
     */
    public function getDeclaredAnnotations() {
        if (!$this->annotations) {
            $this->annotations = array();
            foreach (get_declared_classes() as $class) {
                if (is_subclass_of($class, 'AddendumPP\\Annotation') || $class == 'AddendumPP\\Annotation') {
                    $this->annotations[] = $class;
                }
            }
        }
        return $this->annotations;
    }

    /**
     * Returns the annotation builder used by this instance of AddendumPP
     * 
     * @return AddendumPP\AnnotationsBuilder
     */
    public function getAnnotationBuilder() {
        return $this->builder;
    }
    
    /**
     * Resolves a tag name to a class.
     * Override this function to provide your own annotation naming logic
     * 
     * @return string The class name of the annotation
     */
    public function resolveClassName($className) {
        if (isset($this->cachedMappings[$className]))
            return $this->cachedMappings[$className];
        $matching = array();
        foreach ($this->getDeclaredAnnotations() as $declared) {
            if ($declared == $className) {
                $matching[] = $declared;
            } else {
                $pos = strrpos($declared, "_$className");
                if ($pos !== false && ($pos + strlen($className) == strlen($declared) - 1)) {
                    $matching[] = $declared;
                }
            }
        }
        $result = null;
        switch (count($matching)) {
            case 0:
                $result = $className;
                break;
            case 1:
                $result = $matching[0];
                break;
            default:
                throw new UnresolvedAnnotationException($className);
        }
        $this->cachedMappings[$className] = $result;
        return $result;
    }
    
    /**
     * Reflects a class, including annotations
     * 
     * @param string $class Class to reflect
     * @return ReflectionAnnotatedClass Annotated reflection of the class
     */
    public function reflect($class) {
        return new ReflectionAnnotatedClass($this, $class);
    }
}

?>
