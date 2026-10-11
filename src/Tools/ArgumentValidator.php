<?php

namespace Myori\AiClient\Tools;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Checks a tool's arguments against its JSON Schema, using Laravel validation.
 * Arguments the schema doesn't declare are dropped, never passed to the tool.
 */
class ArgumentValidator
{
    public function __construct(private readonly ValidationFactory $validator) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Tool $tool, array $arguments): array
    {
        $schema = $tool->parameters();
        $properties = (array) ($schema['properties'] ?? []);
        $required = $schema['required'] ?? [];
        $rules = [];

        foreach ($properties as $name => $property) {
            $property = (array) $property;
            $rules[$name] = array_values(array_filter([
                in_array($name, $required, true) ? 'required' : 'nullable',
                match ($property['type'] ?? null) {
                    'string' => 'string',
                    'integer' => 'integer',
                    'number' => 'numeric',
                    'boolean' => 'boolean',
                    default => null,
                },
                isset($property['enum']) ? Rule::in($property['enum']) : null,
                isset($property['minimum']) ? 'min:'.$property['minimum'] : null,
                isset($property['maximum']) ? 'max:'.$property['maximum'] : null,
                isset($property['maxLength']) ? 'max:'.$property['maxLength'] : null,
                ($property['format'] ?? null) === 'date' ? 'date_format:Y-m-d' : null,
            ]));
        }

        $validated = $this->validator->make($arguments, $rules)->validate();

        return array_intersect_key($validated, $properties);
    }
}
