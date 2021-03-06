<?php

namespace Flowpack\JsonApi\Tests\Functional\Controller;

use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\Tests\Functional\Persistence\Fixtures\TestEntity;
use Neos\Flow\Tests\Functional\Persistence\Fixtures\SubEntity;
use Neos\Flow\Tests\Functional\Persistence\Fixtures\TestEntityRepository;
use Neos\Flow\Tests\FunctionalTestCase;

/**
 * Testcase for the JSONAPI Endpoint Controller
 */
class BasicEndpointControllerTest extends FunctionalTestCase
{
    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * @var TestEntityRepository
     */
    protected $testEntityRepository;


    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->persistenceManager instanceof PersistenceManager) {
            $this->markTestSkipped('Doctrine persistence is not enabled');
        }

        $this->testEntityRepository = $this->objectManager->get(TestEntityRepository::class);
    }

    /**
     * @test
     */
    public function assertResponseHeaders()
    {
        $response = $this->browser->request('http://localhost/testing/v1/entities', 'GET');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/vnd.api+json', $response->getHeader('Content-Type'));
        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
    }

    /**
     * @test
     */
    public function assertNotFound()
    {
        $response = $this->browser->request('http://localhost/testing/v1/no-entities', 'GET');
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function fetchingResourceList()
    {
        $entity1 = new TestEntity();
        $entity1->setName('Some Name');
        $this->testEntityRepository->add($entity1);
        $entity2 = new TestEntity();
        $entity2->setName('Some Name');
        $this->testEntityRepository->add($entity2);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $response = $this->browser->request('http://localhost/testing/v1/entities', 'GET');
        $jsonResponse = \json_decode($response->getBody()->getContents());

        $entityIdentifier1 = $this->persistenceManager->getIdentifierByObject($entity1);
        $this->isJson($response->getBody()->getContents());
        $this->assertSame('entities', $jsonResponse->data[0]->type);
        $this->assertSame($entityIdentifier1, $jsonResponse->data[0]->id);
        $this->assertSame('Some Name', $jsonResponse->data[0]->attributes->name);
        $this->assertSame('http://localhost/testing/v1/entities/' . $entityIdentifier1, $jsonResponse->data[0]->links->self);

        $entityIdentifier2 = $this->persistenceManager->getIdentifierByObject($entity2);
        $this->assertSame('entities', $jsonResponse->data[1]->type);
        $this->assertSame($entityIdentifier2, $jsonResponse->data[1]->id);
        $this->assertSame('Some Name', $jsonResponse->data[1]->attributes->name);
        $this->assertSame('http://localhost/testing/v1/entities/' . $entityIdentifier2, $jsonResponse->data[1]->links->self);
    }

    /**
     * @test
     */
    public function fetchingResourceListForbidden()
    {
        $request['data'] = [
            'type' => 'entities',
            'attributes' => [
                'name' => 'Name #1',
                'description' => 'A description'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/restricted-entities', 'GET');
        $this->isJson($response->getBody());
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function fetchResource()
    {
        $entity = new TestEntity();
        $entity->setName('Some Name');
        $this->testEntityRepository->add($entity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);
        $response = $this->browser->request('http://localhost/testing/v1/entities/' . $entityIdentifier, 'GET');
        $jsonResponse = \json_decode($response->getBody());

        $this->isJson($response->getBody());
        $this->assertSame('entities', $jsonResponse->data->type);
        $this->assertSame($entityIdentifier, $jsonResponse->data->id);
        $this->assertSame('Some Name', $jsonResponse->data->attributes->name);
        $this->assertSame('http://localhost/testing/v1/entities/' . $entityIdentifier, $jsonResponse->data->links->self);
    }

    /**
     * @test
     */
    public function fetchResourceSorting()
    {
        $entity1 = new TestEntity();
        $entity1->setName('At the end');
        $this->testEntityRepository->add($entity1);
        $entity2 = new TestEntity();
        $entity2->setName('Z at the start');
        $this->testEntityRepository->add($entity2);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $response = $this->browser->request('http://localhost/testing/v1/entities?sort=-name', 'GET');
        $jsonResponse = \json_decode($response->getBody());

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity2);
        $this->isJson($response->getBody());
        $this->assertSame('entities', $jsonResponse->data[0]->type);
        $this->assertSame($entityIdentifier, $jsonResponse->data[0]->id);
        $this->assertSame('Z at the start', $jsonResponse->data[0]->attributes->name);
        $this->assertSame('http://localhost/testing/v1/entities/' . $entityIdentifier, $jsonResponse->data[0]->links->self);

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity1);
        $this->assertSame('entities', $jsonResponse->data[1]->type);
        $this->assertSame($entityIdentifier, $jsonResponse->data[1]->id);
        $this->assertSame('At the end', $jsonResponse->data[1]->attributes->name);
        $this->assertSame('http://localhost/testing/v1/entities/' . $entityIdentifier, $jsonResponse->data[1]->links->self);
    }

    /**
     * @test
     */
    public function fetchResourceListWithPagination()
    {
        for ($i = 0; $i < 100; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity #' . $i);
            $this->testEntityRepository->add($entity);
        }
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        // Page / limit / page_limit
        $response = $this->browser->request('http://localhost/testing/v1/entities?page[number]=1&page[size]=10', 'GET');
        $jsonResponse = \json_decode($response->getBody());

        $this->isJson($response->getBody());
        $this->assertSame(100, $jsonResponse->meta->total);
        $this->assertSame(10, $jsonResponse->meta->size);

        $this->assertSame('http://localhost/testing/v1/entities?page%5Bnumber%5D=1&page%5Bsize%5D=10', $jsonResponse->links->self);
        $this->assertSame('http://localhost/testing/v1/entities?page%5Bnumber%5D=1&page%5Bsize%5D=10', $jsonResponse->links->first);
        $this->assertSame('http://localhost/testing/v1/entities?page%5Bnumber%5D=10&page%5Bsize%5D=10', $jsonResponse->links->last);
        $this->assertSame(10, \count($jsonResponse->data));

        for ($i = 1; $i < 10; $i++) {
            $response = $this->browser->request($jsonResponse->links->next, 'GET');
            $jsonResponse = \json_decode($response->getBody());
            $this->isJson($response->getBody());
            $this->assertSame(10, \count($jsonResponse->data));
            $this->assertSame(100, $jsonResponse->meta->total);
            $this->assertSame(10, $jsonResponse->meta->size);
            $this->assertSame(10 * $i, $jsonResponse->meta->offset);
        }
    }

    /**
     * @test
     */
    public function fetchResourceFiltering()
    {
        for ($i = 0; $i < 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Bar' . $i);
            $this->testEntityRepository->add($entity);
        }

        $entity = new TestEntity();
        $entity->setName('Foo');
        $this->testEntityRepository->add($entity);

        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $response = $this->browser->request('http://localhost/testing/v1/entities?filter[name]=Foo', 'GET');
        $jsonResponse = \json_decode($response->getBody());
        $this->isJson($response->getBody());
        $this->assertSame(6, $jsonResponse->meta->total);
        $this->assertSame(1, \count($jsonResponse->data));

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);
        $this->assertSame('entities', $jsonResponse->data[0]->type);
        $this->assertSame($entityIdentifier, $jsonResponse->data[0]->id);
        $this->assertSame('Foo', $jsonResponse->data[0]->attributes->name);
        $this->assertSame('http://localhost/testing/v1/entities/' . $entityIdentifier, $jsonResponse->data[0]->links->self);
    }

    /**
     * @test
     */
    public function createResource()
    {
        $request['data'] = [
            'type' => 'entities',
            'attributes' => [
                'name' => 'Name #1',
                'description' => 'A description'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/entities', 'POST', [], [], [], \json_encode($request));
        $jsonResponse = \json_decode($response->getBody());

        $this->isJson($response->getBody());
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame('entities', $jsonResponse->data->type);
        $this->assertNotNull($jsonResponse->data->id);
        $this->assertSame('Name #1', $jsonResponse->data->attributes->name);
        $this->assertSame('A description', $jsonResponse->data->attributes->description);
        $this->assertStringStartsWith('http://localhost/testing/v1/entities/', $jsonResponse->data->links->self);
    }

    /**
     * @test
     */
    public function createResourceNoContent()
    {
        $this->markTestSkipped('Create resource validation returns errors');
        $request = [];
        $response = $this->browser->request('http://localhost/testing/v1/entities', 'POST', [], [], [], \json_encode($request));
        $this->assertEquals(406, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function createResourceForbidden()
    {
        $request['data'] = [
            'type' => 'entities',
            'attributes' => [
                'name' => 'Name #1',
                'description' => 'A description'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/restricted-entities', 'POST', [], [], [], \json_encode($request));
        $this->isJson($response->getBody());
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function createResourceNotFound()
    {
        $request['data'] = [
            'type' => 'entities',
            'attributes' => [
                'name' => 'Name #1',
                'description' => 'A description'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/no-entities', 'POST', [], [], [], \json_encode($request));
        $this->isJson($response->getBody());
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function updateResource()
    {
        $entity = new TestEntity();
        $entity->setName('Name #1');
        $entity->setDescription('Test Description');
        $this->testEntityRepository->add($entity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);

        $request['data'] = [
            'type' => 'entities',
            'id' => $entityIdentifier,
            'attributes' => [
                'description' => 'Patched object'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/entities/' . $entityIdentifier, 'PATCH', [], [], [], \json_encode($request));
        $jsonResponse = \json_decode($response->getBody());

        $this->isJson($response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('entities', $jsonResponse->data->type);
        $this->assertEquals($entityIdentifier, $jsonResponse->data->id);
        $this->assertSame('Name #1', $jsonResponse->data->attributes->name);
        $this->assertSame('Patched object', $jsonResponse->data->attributes->description);
        $this->assertStringStartsWith('http://localhost/testing/v1/entities/', $jsonResponse->data->links->self);
    }

    /**
     * @test
     */
    public function updateResourceNoContent()
    {
        $entity = new TestEntity();
        $entity->setName('No content');
        $this->testEntityRepository->add($entity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);

        $request = [];
        $response = $this->browser->request('http://localhost/testing/v1/entities/' . $entityIdentifier, 'PATCH', [], [], [], \json_encode($request));

        $this->markTestSkipped('BUGFIX: Currently not handling the case of a empty body with a actual resource Id');
        $this->assertEquals(406, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function updateResourceForbidden()
    {
        $entity = new TestEntity();
        $entity->setName('Z at the start');
        $this->testEntityRepository->add($entity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);
        $request['data'] = [
            'type' => 'entities',
            'id' => $entityIdentifier,
            'attributes' => [
                'name' => 'Name #1',
                'description' => 'A description'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/restricted-entities/' . $entityIdentifier, 'PATCH', [], [], [], \json_encode($request));
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function updateResourceNotFound()
    {
        $request['data'] = [
            'type' => 'entities',
            'id' => 'no-id',
            'attributes' => [
                'description' => 'Patched object'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/entities/no-id', 'UPDATE', [], [], [], \json_encode($request));

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function deleteResource()
    {
        $response = $this->browser->request('http://localhost/testing/v1/entities/no-id', 'DELETE');
        $this->assertEquals(404, $response->getStatusCode());

        $entity = new TestEntity();
        $entity->setName('No content');
        $this->testEntityRepository->add($entity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);

        $response = $this->browser->request('http://localhost/testing/v1/entities/' . $entityIdentifier, 'DELETE');

        $this->assertEquals(204, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function deleteResourceForbidden()
    {
        $entity = new TestEntity();
        $entity->setName('Z at the start');
        $this->testEntityRepository->add($entity);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $entityIdentifier = $this->persistenceManager->getIdentifierByObject($entity);
        $request['data'] = [
            'type' => 'entities',
            'id' => $entityIdentifier,
            'attributes' => [
                'name' => 'Name #1',
                'description' => 'A description'
            ]
        ];

        $response = $this->browser->request('http://localhost/testing/v1/restricted-entities/' . $entityIdentifier, 'DELETE');
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function validateResource()
    {
        $this->markTestSkipped('validate resource');
    }
}
