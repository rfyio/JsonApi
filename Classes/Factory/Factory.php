<?php

namespace Flowpack\JsonApi\Factory;

use Flowpack\JsonApi\Encoder\Encoder;
use Flowpack\JsonApi\Schema\SchemaContainer;
use Neomerx\JsonApi\Contracts\Encoder\EncoderInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class Factory extends \Neomerx\JsonApi\Factories\Factory
{
    /**
     * @inheritdoc
     */
    public function createSchemaContainer(iterable $schemas): SchemaContainerInterface
    {
        return new SchemaContainer($this, $schemas);
    }

    /**
     * @inheritdoc
     */
    public function createEncoder(SchemaContainerInterface $container): EncoderInterface
    {
        return new Encoder($this, $container);
    }
}
