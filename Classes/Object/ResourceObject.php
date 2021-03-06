<?php

namespace Flowpack\JsonApi\Object;

use Flowpack\JsonApi\Contract\Object\ResourceObjectInterface;
use Flowpack\JsonApi\Contract\Object\StandardObjectInterface;
use Flowpack\JsonApi\Exception\UnprocessableEntityException;

/**
 * Class Resource
 */
class ResourceObject extends StandardObject implements ResourceObjectInterface
{
    use IdentifiableTrait,
        MetaMemberTrait;

    /**
     * @inheritdoc
     */
    public function getIdentifier()
    {
        return ResourceIdentifier::create($this->getType(), $this->getId());
    }

    /**
     * @inheritdoc
     */
    public function getAttributes()
    {
        $attributes = $this->hasAttributes() ? $this->get(self::ATTRIBUTES) : new StandardObject();

        if (!$attributes instanceof StandardObjectInterface) {
            throw new UnprocessableEntityException('Attributes member is not an object. Perhaps you are passing an empty attribute?');
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function hasAttributes()
    {
        return $this->has(self::ATTRIBUTES);
    }

    /**
     * @inheritdoc
     */
    public function getRelationships()
    {
        $relationships = $this->hasRelationships() ? $this->{self::RELATIONSHIPS} : null;

        if (!is_null($relationships) && !is_object($relationships)) {
            throw new UnprocessableEntityException('Relationships member is not an object. Perhaps you are passing empty relations?');
        }

        return new Relationships($relationships);
    }

    /**
     * @inheritdoc
     */
    public function hasRelationships()
    {
        return $this->has(self::RELATIONSHIPS);
    }

    /**
     * @inheritDoc
     */
    public function getRelationship($key)
    {
        $relationships = $this->getRelationships();

        return $relationships->has($key) ? $relationships->getRelationship($key) : null;
    }
}
