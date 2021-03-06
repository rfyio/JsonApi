<?php

namespace Flowpack\JsonApi\Object;

use Flowpack\JsonApi\Contract\Object\RelationshipsInterface;
use Flowpack\JsonApi\Exception\RuntimeException;

/**
 * Class Relationships
 *
 * @package Flowpack\JsonApi
 */
class Relationships extends StandardObject implements RelationshipsInterface
{

    /**
     * @inheritdoc
     */
    public function getAll()
    {
        foreach ($this->keys() as $key) {
            yield $key => $this->getRelationship($key);
        }
    }

    /**
     * @inheritdoc
     */
    public function getRelationship($key)
    {
        if (!$this->has($key)) {
            throw new RuntimeException("Relationship member '$key' is not present.");
        }

        $value = $this->{$key};

        if (!is_object($value)) {
            throw new RuntimeException("Relationship member '$key' is not an object.'");
        }

        return new Relationship($value);
    }
}
