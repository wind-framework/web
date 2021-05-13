<?php

namespace Wind\Web;

use FastRoute\RouteCollector;

/**
 * Wind Framework Router
 *
 * @package Wind\Web
 */
class Router
{

    private $dispatcher;

    /**
     * @var RouteCollector
     */
    private $collector;

    public function __construct()
    {
        $this->dispatcher = \FastRoute\simpleDispatcher(function(RouteCollector $r) {
            $this->collector = $r;
            $groups = \config('routes', []);
            $this->addGroups($groups);
            $this->collector = null;
        });
    }

    public function dispatch($httpMethod, $uri)
    {
        return $this->dispatcher->dispatch($httpMethod, $uri);
    }

    private function addGroups($groups, $options=[])
    {
        foreach ($groups as $key => $group) {
            $this->addGroup($key, $group, $options);
        }
    }

    /**
     * @param int|string $key
     * @param array $group
     * @param array $options
     */
    private function addGroup($key, $group, $options)
    {
        //Namespace
        if (!empty($group['namespace'])) {
            if (!empty($options['namespace'])) {
                $options['namespace'] .= '\\'.$group['namespace'];
            } else {
                $options['namespace'] = $group['namespace'];
            }
        }

        //Prefix
        if (!empty($group['prefix'])) {
            if (!empty($options['prefix'])) {
                $options['prefix'] = $this->joinPath($options['prefix'], $group['prefix']);
            } else {
                $options['prefix'] = $group['prefix'];
            }
        }

        //Middlewares
        if (!empty($group['middlewares'])) {
            $options['middlewares'] = isset($options['middlewares']) ?
                array_merge($options['middlewares'], $group['middlewares']) : $group['middlewares'];
        }

        if (isset($group['routes'])) {
            $this->addRoutes($group['routes'], $options);
        }

        if (isset($group['groups'])) {
            $this->addGroups($group['groups'], $options);
        }
    }

    private function addRoutes($routes, $options)
    {
        foreach ($routes as $req => $target) {
            list($methods, $path) = explode(' ', $req, 2);
            $methods = \strtr($methods, 'acdeghilnoprstu', 'ACDEGHILNOPRSTU');
            if (str_contains($methods, '|')) {
                $methods = explode('|', $methods);
            }

            if (isset($options['prefix'])) {
                $path = $this->joinPath($options['prefix'], $path);
            }

            if (is_string($target) || $target instanceof \Closure) {
                $target = [
                    'handler' => $target
                ];
            }

            if (isset($options['middlewares'])) {
                $target['middlewares'] = isset($target['middlewares']) ?
                    array_merge($options['middlewares'], $target['middlewares']) : $options['middlewares'];
            }

            if (isset($options['namespace']) && is_string($target['handler']) && str_contains($target['handler'], '::')) {
                $target['handler'] = $options['namespace'].'\\'.$target['handler'];
            }

            $this->collector->addRoute($methods, $path, $target);
        }
    }

    private function joinPath($prefix, $path)
    {
        return rtrim($prefix, '/').'/'.ltrim($path, '/');
    }

}

