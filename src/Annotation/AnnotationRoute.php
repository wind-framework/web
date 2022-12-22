<?php

namespace Wind\Web\Annotation;

use Wind\Annotation\Collectable;
use Wind\Web\Router;

/**
 * Wind Web Annotation Route
 */
abstract class AnnotationRoute implements Collectable
{

    /**
     * @param string $path Route path
     * @param string[] $middlewares Route middlewares
     */
    public function __construct(public $path, public $middlewares=[])
    {
    }

    public function collectClass(\ReflectionClass $reference)
    {
    }

    public function collectMethod(\ReflectionMethod $reference)
    {
        $className = get_class($this);
        $method = strtoupper(substr($className, strrpos($className, '\\')+1));

        $handler = $reference->getDeclaringClass()->getName().'::'.$reference->getName();
        $target = [
            'handler' => $handler,
            'middlewares' => $this->middlewares
        ];

        Router::addAnnotationRoute($method, $this->path, $target);
    }

}

