<?php

namespace Wind\Web\Annotation;

use Wind\Annotation\Collectable;
use Wind\Web\Router;

abstract class AnnotationRoute implements Collectable
{

    public function __construct(public $path, public $options=[])
    {
    }

    public function collect($reference) {
        $className = get_class($this);
        $method = strtoupper(substr($className, strrpos($className, '\\')+1));

        $handler = $reference->getDeclaringClass()->getName().'::'.$reference->getName();
        $target = array_merge($this->options, [
            'handler' => $handler
        ]);

        Router::addAnnotationRoute($method, $this->path, $target);
    }

}

