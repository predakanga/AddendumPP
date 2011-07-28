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
 */

namespace AddendumPP;

use \ReflectionClass,
    \ReflectionMethod,
    \ReflectionProperty,
    \Exception;

require_once(dirname(__FILE__) . '/annotations/annotation_parser.php');

class Annotation {
    public $value;
    private static $creationStack = array();
    private $addendum;

    public final function __construct($addendum, $data = array(), $target = false) {
        $this->addendum = $addendum;
        $reflection = new ReflectionClass($this);
        $class = $reflection->getName();
        if (isset(self::$creationStack[$class])) {
            trigger_error("Circular annotation reference on '$class'", E_USER_ERROR);
            return;
        }
        self::$creationStack[$class] = true;
        foreach ($data as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $this->$key = $value;
            } else {
                trigger_error("Property '$key' not defined for annotation '$class'");
            }
        }
        $this->checkTargetConstraints($target);
        $this->checkConstraints($target);
        unset(self::$creationStack[$class]);
    }

    private function checkTargetConstraints($target) {
        $reflection = new ReflectionAnnotatedClass($this->addendum, $this);
        if ($reflection->hasAnnotation('Target')) {
            $value = $reflection->getAnnotation('Target')->value;
            $values = is_array($value) ? $value : array($value);
            foreach ($values as $value) {
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
                trigger_error("Annotation '" . get_class($this) . "' nesting not allowed", E_USER_ERROR);
            } else {
                trigger_error("Annotation '" . get_class($this) . "' not allowed on " . $this->createName($target), E_USER_ERROR);
            }
        }
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

    protected function checkConstraints($target) {
        
    }
}

class AnnotationsCollection {
    private $annotations;
    private $addendum;

    public function __construct($addendum, $annotations) {
        $this->addendum = $addendum;
        $this->annotations = $annotations;
    }

    public function hasAnnotation($class) {
        $class = $this->addendum->resolveClassName($class);
        return isset($this->annotations[$class]);
    }

    public function getAnnotation($class) {
        $class = $this->addendum->resolveClassName($class);
        return isset($this->annotations[$class]) ? end($this->annotations[$class]) : false;
    }

    public function getAnnotations() {
        $result = array();
        foreach ($this->annotations as $instances) {
            $result[] = end($instances);
        }
        return $result;
    }

    public function getAllAnnotations($restriction = false) {
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

class Annotation_Target extends Annotation {
}

class AnnotationsBuilder {
    private $cache = array();
    private $addendum;

    public function __construct($addendum) {
        $this->addendum = $addendum;
    }

    public function build($targetReflection) {
        $data = $this->parse($targetReflection);
        $annotations = array();
        foreach ($data as $class => $parameters) {
            foreach ($parameters as $params) {
                $annotation = $this->instantiateAnnotation($class, $params, $targetReflection);
                if ($annotation !== false) {
                    $annotations[get_class($annotation)][] = $annotation;
                }
            }
        }
        return new AnnotationsCollection($this->addendum, $annotations);
    }

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
            $parser->matches($this->getDocComment($reflection), $data);
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

    public function clearCache() {
        $this->cache = array();
    }
}

class ReflectionAnnotatedClass extends ReflectionClass {

    private $annotations;

    public function __construct($addendum, $class) {
        parent::__construct($class);

        $this->addendum = $addendum;
        $this->annotations = $this->createAnnotationBuilder()->build($this);
    }

    public function hasAnnotation($class) {
        return $this->annotations->hasAnnotation($class);
    }

    public function getAnnotation($annotation) {
        return $this->annotations->getAnnotation($annotation);
    }

    public function getAnnotations() {
        return $this->annotations->getAnnotations();
    }

    public function getAllAnnotations($restriction = false) {
        return $this->annotations->getAllAnnotations($restriction);
    }

    public function getConstructor() {
        return $this->createReflectionAnnotatedMethod(parent::getConstructor());
    }

    public function getMethod($name) {
        return $this->createReflectionAnnotatedMethod(parent::getMethod($name));
    }

    public function getMethods($filter = -1) {
        $result = array();
        foreach (parent::getMethods($filter) as $method) {
            $result[] = $this->createReflectionAnnotatedMethod($method);
        }
        return $result;
    }

    public function getProperty($name) {
        return $this->createReflectionAnnotatedProperty(parent::getProperty($name));
    }

    public function getProperties($filter = -1) {
        $result = array();
        foreach (parent::getProperties($filter) as $property) {
            $result[] = $this->createReflectionAnnotatedProperty($property);
        }
        return $result;
    }

    public function getInterfaces() {
        $result = array();
        foreach (parent::getInterfaces() as $interface) {
            $result[] = $this->createReflectionAnnotatedClass($interface);
        }
        return $result;
    }

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

class ReflectionAnnotatedMethod extends ReflectionMethod {

    private $annotations;
    private $addendum;

    public function __construct($addendum, $class, $name) {
        parent::__construct($class, $name);

        $this->addendum = $addendum;
        $this->annotations = $this->createAnnotationBuilder()->build($this);
    }

    public function hasAnnotation($class) {
        return $this->annotations->hasAnnotation($class);
    }

    public function getAnnotation($annotation) {
        return $this->annotations->getAnnotation($annotation);
    }

    public function getAnnotations() {
        return $this->annotations->getAnnotations();
    }

    public function getAllAnnotations($restriction = false) {
        return $this->annotations->getAllAnnotations($restriction);
    }

    public function getDeclaringClass() {
        $class = parent::getDeclaringClass();
        return new ReflectionAnnotatedClass($this->addendum, $class->getName());
    }

    protected function createAnnotationBuilder() {
        return $this->addendum->getAnnotationBuilder();
    }

}

class ReflectionAnnotatedProperty extends ReflectionProperty {
    private $annotations;
    private $addendum;

    public function __construct($addendum, $class, $name) {
        parent::__construct($class, $name);

        $this->addendum = $addendum;
        $this->annotations = $this->createAnnotationBuilder()->build($this);
    }

    public function hasAnnotation($class) {
        return $this->annotations->hasAnnotation($class);
    }

    public function getAnnotation($annotation) {
        return $this->annotations->getAnnotation($annotation);
    }

    public function getAnnotations() {
        return $this->annotations->getAnnotations();
    }

    public function getAllAnnotations($restriction = false) {
        return $this->annotations->getAllAnnotations($restriction);
    }

    public function getDeclaringClass() {
        $class = parent::getDeclaringClass();
        return new ReflectionAnnotatedClass($this->addendum, $class->getName());
    }

    protected function createAnnotationBuilder() {
        return $this->addendum->getAnnotationBuilder();
    }
}

class UnresolvedAnnotationException extends Exception {
    public function __construct($annotationName) {
        parent::__construct("Unresolved annotation encountered: @" . $annotationName);
    }
}

class AnnotationResolver {
    protected $cachedMappings = array();
    protected $addendum;
    
    public function __construct($addendum) {
        $this->addendum = $addendum;
    }
    
    public function match($className) {
        if (isset($this->cachedMappings[$className]))
            return $this->cachedMappings[$className];
        $matching = array();
        foreach ($this->addendum->getDeclaredAnnotations() as $declared) {
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
                throw new UnmatchedAnnotationException($className);
        }
        $this->classnames[$className] = $result;
        return $result;
    }
}

abstract class AddendumPPBase {
    /**
     * @var bool Denotes whether the parser will use manual doc-comment parsing
     */
    protected $rawMode;
    /**
     * @var AnnotationResolver The resolver which will be used by default
     */
    protected $resolver;
    /**
     * @var array List of class names that this addendum instance ignores
     */
    protected $ignore;
    /**
     * @var array List of cached declared annotations
     */
    protected $annotations = false;
    
    public function __construct() {
        $this->checkRawDocCommentParsingNeeded();
        $this->builder = new AnnotationsBuilder($this);
    }
    
    // <editor-fold desc="Raw mode detection">
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

    public function setRawMode($enabled = true) {
        if ($enabled) {
            require_once(dirname(__FILE__) . '/annotations/doc_comment.php');
        }
        $this->rawMode = $enabled;
    }
    // </editor-fold>
    
    public function ignore() {
        foreach (func_get_args() as $class) {
            $this->ignore[$class] = true;
        }
    }

    public function ignores($class) {
        return isset($this->ignore[$class]);
    }
    
    public function resetIgnoredAnnotations() {
        $this->ignore = array();
    }

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

    public function getAnnotationBuilder() {
        return $this->builder;
    }
    
    public function resolveClassName($className) {
        return $this->resolver->match($className);
    }
    
    /**
     *
     * @param type $class
     * @return ReflectionAnnotatedClass Reflection of the class
     */
    public function reflect($class) {
        return new ReflectionAnnotatedClass($this, $class);
    }
}

class AddendumPP extends AddendumPPBase {
    public function __construct() {
        parent::__construct();
        
        $this->resolver = new AnnotationResolver($this);
    }
}

?>
