<?php

namespace Domain\Content;

use Domain\Content\Contracts\ContentBlockType;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ContentBlockTypeRegistry
{
    /** @var array<string, ContentBlockType>|null */
    private ?array $types = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<string, ContentBlockType>
     * @throws BindingResolutionException
     */
    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $this->types = [];

        foreach (config('content.types', []) as $class) {
            $type = $this->container->make($class);

            if (! $type instanceof ContentBlockType) {
                throw new InvalidArgumentException("Content type [$class] must implement ContentBlockType.");
            }

            if (isset($this->types[$type->key()])) {
                throw new InvalidArgumentException("Duplicate content type key [{$type->key()}].");
            }

            $this->types[$type->key()] = $type;
        }

        return $this->types;
    }

    public function get(?string $key): ?ContentBlockType
    {
        return $key === null ? null : ($this->all()[$key] ?? null);
    }

    public function has(?string $key): bool
    {
        return $this->get($key) !== null;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return array_map(static fn (ContentBlockType $type): string => $type->label(), $this->all());
    }
}
