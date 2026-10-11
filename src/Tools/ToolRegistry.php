<?php

namespace Myori\AiClient\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The tools listed in config('myori-ai.tools').
 */
class ToolRegistry
{
    /** @var array<string, Tool>|null */
    private ?array $tools = null;

    /**
     * @param  list<class-string<Tool>>  $classes
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $classes,
    ) {}

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $this->tools = [];

        foreach ($this->classes as $class) {
            $tool = $this->container->make($class);

            if (! $tool instanceof Tool) {
                throw new InvalidArgumentException("{$class} must extend ".Tool::class.'.');
            }

            if (isset($this->tools[$tool->name()])) {
                throw new InvalidArgumentException("Two tools are named \"{$tool->name()}\".");
            }

            $this->tools[$tool->name()] = $tool;
        }

        return $this->tools;
    }

    public function find(string $name): ?Tool
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return list<Tool>
     */
    public function availableTo(Authenticatable $user): array
    {
        return array_values(array_filter($this->all(), fn (Tool $tool) => $tool->authorize($user)));
    }

    /**
     * Tool definitions in the shape the gateway passes to the model.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function definitionsFor(Authenticatable $user): array
    {
        return array_map(fn (Tool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->parameters(),
        ], $this->availableTo($user));
    }
}
