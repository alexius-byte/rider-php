<?php

declare(strict_types=1);

namespace Rider\System\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Rider\System\Container\Exception\ContainerException;
use Rider\System\Container\Exception\NotFoundException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $reflectionCache = [];
    private array $resolving = [];

    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'singleton' => false];
    }

    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'singleton' => true];
    }

    public function instance(string $abstract, mixed $value): void
    {
        $this->instances[$abstract] = $value;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            return $this->resolve($id);
        }

        if (class_exists($id)) {
            return $this->build($id);
        }

        throw new NotFoundException("No binding found for [{$id}].");
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }

    private function resolve(string $abstract): mixed
    {
        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];

        $object = $concrete instanceof Closure ? $concrete($this) : $this->build($concrete);

        if ($binding['singleton']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    private function build(string $class): object
    {
        if (isset($this->resolving[$class])) {
            throw new ContainerException("Circular dependency detected for [{$class}].");
        }

        $this->resolving[$class] = true;

        try {
            $reflection = $this->reflect($class);

            if (!$reflection->isInstantiable()) {
                throw new ContainerException("[{$class}] is not instantiable.");
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstanceWithoutConstructor();
            }

            $dependencies = [];

            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $dependencies[] = $this->get($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot resolve parameter [{$param->getName()}] in [{$class}]."
                    );
                }
            }

            return $reflection->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to reflect [{$class}]: {$e->getMessage()}");
        } finally {
            unset($this->resolving[$class]);
        }
    }

    private function reflect(string $class): ReflectionClass
    {
        if (!isset($this->reflectionCache[$class])) {
            try {
                $this->reflectionCache[$class] = new ReflectionClass($class);
            } catch (ReflectionException) {
                throw new ContainerException("Class [{$class}] does not exist.");
            }
        }

        return $this->reflectionCache[$class];
    }
}
