<?php

namespace Domain\Content\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

final class PublicRouteOptions
{
    /** @return array<string, string> */
    public function get(): array
    {
        $adminPrefix = trim((string) config('moonshine.prefix', 'admin'), '/');
        $options = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null
                || ! in_array('GET', $route->methods(), true)
                || $this->hasRequiredParameters($route)
                || str_starts_with($route->uri(), $adminPrefix.'/')
                || $route->uri() === $adminPrefix
                || str_starts_with($name, 'moonshine.')) {
                continue;
            }

            $options[$name] = $name.' · /'.ltrim($route->uri(), '/');
        }

        ksort($options);

        return $options;
    }

    private function hasRequiredParameters(Route $route): bool
    {
        return preg_match('/\{[^}]+(?<!\?)\}/', $route->uri()) === 1;
    }
}
