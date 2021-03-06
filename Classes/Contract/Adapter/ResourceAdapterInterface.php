<?php

namespace Flowpack\JsonApi\Contract\Adapter;

use Flowpack\JsonApi\Contract\Object\ResourceObjectInterface;
use Flowpack\JsonApi\Domain\Model\PaginationParameters;
use Flowpack\JsonApi\Mvc\Controller\EncodingParametersParser;

/**
 * Interface ResourceAdapterInterface
 *
 * Adapters are responsible for converting JSON API queries or resource identifiers into domain
 * record(s). Adapters are attached to a repository via the adapter container. This allows a JSON API
 * repository to query different types of domain records regardless of how these are actually stored
 * and retrieved within an application.
 *
 * @package Flowpack\JsonApi
 */
interface ResourceAdapterInterface
{

    /**
     * Query many domain records.
     *
     * @param EncodingParametersParser $parameters
     * @param PaginationParameters $paginationParameters
     * @return mixed
     */
    public function query(EncodingParametersParser $parameters, PaginationParameters $paginationParameters);

    /**
     * Create a domain record using data from the supplied resource object.
     *
     * @param $propertyMappedResource
     * @param ResourceObjectInterface $resourceObject
     * @param EncodingParametersParser $parameters
     * @return object
     */
    public function create($propertyMappedResource, ResourceObjectInterface $resourceObject, EncodingParametersParser $parameters);

    /**
     * Query a single domain record.
     *
     * @param string $resourceId
     * @param EncodingParametersParser $parameters
     * @return object|null
     */
    public function read($resourceId, EncodingParametersParser $parameters);

    /**
     * Update a domain record with data from the supplied resource object.
     *
     * @param object $record
     *      the domain record to update.
     * @param ResourceObjectInterface $resource
     * @param EncodingParametersParser $params
     * @return object the updated domain record.
     */
    public function update($record, ResourceObjectInterface $resource, EncodingParametersParser $params);

    /**
     * Delete a domain record.
     *
     * @param $record
     * @param EncodingParametersParser $params
     * @return void whether the record was successfully destroyed.
     */
    public function delete($record, EncodingParametersParser $params);

    /**
     * Does a domain record of the specified JSON API resource id exist?
     *
     * @param string $resourceId
     * @return bool
     */
    public function exists($resourceId);

    /**
     * Get the domain record that relates to the specified JSON API resource id, if it exists.
     *
     * @param string $resourceId
     * @return object|null
     */
    public function find($resourceId);

    /**
     * Find many domain records for the specified JSON API resource ids.
     *
     * The returned collection MUST NOT contain any duplicate domain records, and MUST only contain
     * domain records that match the supplied resource ids. A collection MUST be returned even if some
     * or all of the resource IDs cannot be converted into domain records - i.e. the returned collection
     * may contain less domain records than the supplied number of ids.
     *
     * @param array $resourceIds
     * @return array
     */
    public function findMany(array $resourceIds);

    /**
     * Get the relationship adapter for the specified relationship.
     *
     * @param $field
     * @return RelationshipAdapterInterface
     */
    public function related($field);
}
