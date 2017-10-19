<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Type value object (immutable).
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final since version 3.3
 */
class Type
{
    const BUILTIN_TYPE_INT = 'int';
    const BUILTIN_TYPE_FLOAT = 'float';
    const BUILTIN_TYPE_STRING = 'string';
    const BUILTIN_TYPE_BOOL = 'bool';
    const BUILTIN_TYPE_RESOURCE = 'resource';
    const BUILTIN_TYPE_OBJECT = 'object';
    const BUILTIN_TYPE_ARRAY = 'array';
    const BUILTIN_TYPE_NULL = 'null';
    const BUILTIN_TYPE_CALLABLE = 'callable';

    /**
     * List of PHP builtin types.
     *
     * @var string[]
     */
    public static $builtinTypes = array(
        self::BUILTIN_TYPE_INT,
        self::BUILTIN_TYPE_FLOAT,
        self::BUILTIN_TYPE_STRING,
        self::BUILTIN_TYPE_BOOL,
        self::BUILTIN_TYPE_RESOURCE,
        self::BUILTIN_TYPE_OBJECT,
        self::BUILTIN_TYPE_ARRAY,
        self::BUILTIN_TYPE_CALLABLE,
        self::BUILTIN_TYPE_NULL,
    );

    /**
     * @var string
     */
    private $builtinType;

    /**
     * @var bool
     */
    private $nullable;

    /**
     * @var string|null
     */
    private $class;

    /**
     * @var bool
     */
    private $collection;

    /**
     * @var Type|null
     */
    private $collectionKeyType;

    /**
     * @var Type|null
     */
    private $collectionValueType;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $builtinType, bool $nullable = false, string $class = null, bool $collection = false, Type $collectionKeyType = null, Type $collectionValueType = null)
    {
        if (!in_array($builtinType, self::$builtinTypes)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid PHP type.', $builtinType));
        }

        $this->builtinType = $builtinType;
        $this->nullable = $nullable;
        $this->class = $class;
        $this->collection = $collection;
        $this->collectionKeyType = $collectionKeyType;
        $this->collectionValueType = $collectionValueType;
    }

    public function getBuiltinType(): string
    {
        return $this->builtinType;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getClassName(): ?string
    {
        return $this->class;
    }

    public function isCollection(): bool
    {
        return $this->collection;
    }

    public function getCollectionKeyType(): ?self
    {
        return $this->collectionKeyType;
    }

    public function getCollectionValueType(): ?self
    {
        return $this->collectionValueType;
    }
}
