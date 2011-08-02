<?php

namespace AddendumPP;

use \Exception;

class CircularAnnotationReferenceException extends Exception {
    public function __construct($annotationName) {
        parent::__construct("Circular annotation reference encountered on '" . $annotationName . "'");
    }
}

class InvalidPropertyException extends Exception {
    public function __construct($annotationName, $propertyName) {
        parent::__construct("Property '" . $propertyName . "' not defined for annotation '" . $annotationName . "'");
    }
}

class NestingNotAllowedException extends Exception {
    public function __construct($annotationName, $target) {
        parent::__construct("Annotation '" . $annotationName . "' not allowed on " . $target);
    }
}

class NoNestingAllowedException extends Exception {
    public function __construct($annotationName) {
        parent::__construct("Annotation '" . $annotationName . "' nesting not allowed");
    }
}

class UnresolvedAnnotationException extends Exception {
    public function __construct($annotationName) {
        parent::__construct("Unresolved annotation encountered: @" . $annotationName);
    }
}
?>
