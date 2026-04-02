<?php

declare(strict_types=1);

namespace Arcos\Core\Routing;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Routing\Attributes\Routable;
use LogicException;
use ReflectionClass;
use ReflectionMethod;

class AutoResolver
{
    public function __construct(
        private readonly Router $router,
        private readonly string $namespace,
        private readonly string $basePath,
        private readonly string $subdomain,
    ) {}

    public function resolve(): void
    {
        $classes = $this->discoverClasses();

        foreach ($classes as $className) {
            $this->processController($className);
        }
    }

    private function discoverClasses(): array
    {
        $directory = $this->namespaceToPath();

        if (!is_dir($directory)) {
            throw new LogicException(
                "Auto-resolution failed: directory [{$directory}] for namespace [{$this->namespace}] does not exist."
            );
        }

        $classes = [];

        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isDot() || $file->getExtension() !== 'php') {
                continue;
            }

            $classes[] = $this->namespace . '\\' . $file->getBasename('.php');
        }

        return $classes;
    }

    private function processController(string $className): void
    {
        if (!class_exists($className)) {
            throw new LogicException(
                "Auto-resolution failed: class [{$className}] could not be loaded. " .
                "Ensure the file exists and the namespace matches."
            );
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        $this->validateController($reflection);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Routable::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var Routable $routable */
            $routable = $attributes[0]->newInstance();

            $this->validateRoutableMethod($method, $className);

            $uri = $this->buildUri($reflection, $method);

            foreach ($routable->methods as $httpMethod) {
                $this->router->add(
                    strtoupper($httpMethod),
                    $uri,
                    $className,
                    $method->getName(),
                );
            }
        }
    }

    private function validateController(ReflectionClass $reflection): void
    {
        // Must not be a built-in or anonymous class
        if ($reflection->isAnonymous()) {
            throw new LogicException(
                "Auto-resolution failed: anonymous classes cannot be used as controllers."
            );
        }

        // Constructor parameters must all be type-hinted (container needs to resolve them)
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $param) {
            if ($param->getType() === null) {
                throw new LogicException(
                    "Auto-resolution failed: constructor parameter [{$param->getName()}] " .
                    "in [{$reflection->getName()}] has no type hint. " .
                    "All constructor parameters must be type-hinted for container resolution."
                );
            }
        }
    }

    private function validateRoutableMethod(ReflectionMethod $method, string $className): void
    {
        $params = $method->getParameters();

        // Must accept exactly one parameter: Request
        if (count($params) !== 1) {
            throw new LogicException(
                "Auto-resolution failed: [{$className}::{$method->getName()}()] " .
                "must accept exactly one parameter of type Request."
            );
        }

        $paramType = $params[0]->getType()?->getName();

        if ($paramType !== Request::class) {
            throw new LogicException(
                "Auto-resolution failed: [{$className}::{$method->getName()}()] " .
                "parameter must be typed as [" . Request::class . "], [{$paramType}] given."
            );
        }

        // Must return Response
        $returnType = $method->getReturnType()?->getName();

        if ($returnType !== Response::class) {
            throw new LogicException(
                "Auto-resolution failed: [{$className}::{$method->getName()}()] " .
                "must declare a return type of [" . Response::class . "], [{$returnType}] given."
            );
        }
    }

    private function buildUri(ReflectionClass $reflection, ReflectionMethod $method): string
    {
        $controllerName = $reflection->getShortName();
        $segment        = lcfirst(str_replace('Controller', '', $controllerName));

        return '/' . $segment . '/' . $method->getName();
    }

    private function namespaceToPath(): string
    {
        $relative = str_replace('\\', DIRECTORY_SEPARATOR,
            str_replace('App\\', 'app' . DIRECTORY_SEPARATOR, $this->namespace)
        );

        return rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
    }
}