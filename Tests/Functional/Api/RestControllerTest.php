<?php
namespace Flowpack\RestApi\Tests\Functional\Api;

use Flowpack\RestApi\Utility\LinkHeader;
use Neos\Flow\Mvc\Routing\Route;
use Psr\Http\Message\ResponseInterface;

class RestControllerTest extends \Neos\Flow\Tests\FunctionalTestCase
{
	/**
	 * @var boolean
	 */
	protected static $testablePersistenceEnabled = true;

	/**
	 * @var string
	 */
	protected $baseRoute;

	/**
	 * Additional setup: Routes
	 */
	public function setUp(): void
	{
		parent::setUp();
		$routesFound = false;
		/* @var $route Route */
		foreach ($this->router->getRoutes() as $route) {
			if (strpos($route->getName(), 'Functional Test: API test Route :: REST API discovery entry point') !== false) {
				$this->baseRoute = $route->getUriPattern();
				$routesFound = true;
				break;
			}
		}

		if (!$routesFound) {
			throw new \Exception('No Routes for Flowpack.RestApi package defined. Please add a subRoute with name "Core" to your global Testing/Routes.yaml!');
		}
	}

	/**
	 * @param string $relUri
	 * @param bool $absolute
	 * @return string
	 */
	protected function uriFor($relUri, $absolute = true)
	{
		return ($absolute ? 'http://localhost/' : '') . $this->baseRoute . '/' . ltrim($relUri, '/');
	}

	/**
	 * @test
	 */
	public function routerCorrectlyResolvesIndexAction()
	{
		$uri = $this->router->resolve(array(
			'@package' => 'Flowpack.RestApi',
			'@subpackage' => 'Tests\Functional\Api\Fixtures',
			'@controller' => 'Aggregate',
			'@action' => 'index',
			'@format' => 'json'
		));
		$this->assertEquals($this->uriFor('aggregate', false), $uri, $uri);
	}

	/**
	 * @test
	 */
	public function routesWithFormatBasicallyWork()
	{
		$response = $this->createResource(array('title' => 'Foo'));
		$this->assertEquals(201, $response->getStatusCode());
		$this->assertNotEmpty($response->getHeader('Location'));

		$response = $this->browser->request($response->getHeader('Location') . '.json', 'GET');
		$this->assertNotEmpty($response->getBody()->getContents());
		$resource = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Foo', $resource['title']);
	}

	/**
	 * @param array $resourceProperties
	 * @return ResponseInterface
	 */
	protected function createResource(array $resourceProperties)
	{
		$arguments = array(
			'resource' => $resourceProperties
		);
		$response = $this->browser->request($this->uriFor('aggregate'), 'POST', $arguments);
		$this->persistenceManager->clearState();
		return $response;
	}

	/**
	 * @param array $resourceProperties
	 * @return ResponseInterface
	 */
	protected function createResources(array $resourceProperties)
	{
		$arguments = array(
			'resources' => $resourceProperties
		);
		$response = $this->browser->request($this->uriFor('aggregate'), 'POST', $arguments);
		$this->persistenceManager->clearState();
		return $response;
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedViaRestCall()
	{
		$response = $this->createResource(array(
			'title' => 'Foo'
		));
		$this->assertEquals(201, $response->getStatusCode());
		$this->assertNotEmpty($response->getHeader('Location'));

		$response = $this->browser->request($response->getHeader('Location'), 'GET');
		$this->assertNotEmpty($response->getBody()->getContents());
		$resource = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Foo', $resource['title']);
	}

	/**
	 * @test
	 */
	public function multipleResourcesCanBeCreatedViaSingleRestCall()
	{
		$response = $this->createResources(array(
			array('title' => 'Foo'),
			array('title' => 'Bar'),
			array('title' => 'Baz')
		));
		$this->assertEquals(201, $response->getStatusCode(), $response->getBody()->getContents());
		$this->assertNotEmpty($response->getHeader('Location'));

		$response = $this->browser->request($response->getHeader('Location') . '?cursor=title', 'GET');
		$this->assertNotEmpty($response->getBody()->getContents());
		$resources = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Bar', $resources[0]['title']);
		$this->assertEquals('Baz', $resources[1]['title']);
		$this->assertEquals('Foo', $resources[2]['title']);
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedWithSubEntitiesViaRestCall()
	{
		$response = $this->createResource(array(
			'title' => 'Foo',
			'entities' => array(
				array( 'title' => 'Bar' )
			)
		));
		$this->assertEquals(201, $response->getStatusCode());
		$this->assertNotEmpty($response->getHeader('Location'));

		$response = $this->browser->request($response->getHeader('Location'), 'GET');
		$this->assertNotEmpty($response->getBody()->getContents());
		$resource = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Bar', $resource['entities'][0]['title']);
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedWithPredefinedIdentifierViaRestCall()
	{
		$arguments = array(
			'resource' => array(
				'title' => 'Foo',
			)
		);
		$response = $this->browser->request($this->uriFor('aggregate/e413ed09-bd63-4a4e-9e0a-026f9179a2c1'), 'POST', $arguments);
		$this->assertEquals(201, $response->getStatusCode());
		$this->assertStringEndsWith('e413ed09-bd63-4a4e-9e0a-026f9179a2c1', $response->getHeader('Location'));

		$response = $this->browser->request($response->getHeader('Location'), 'GET');
		$this->assertNotEmpty($response->getBody()->getContents());
		$resource = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Foo', $resource['title']);
		$this->assertEquals('e413ed09-bd63-4a4e-9e0a-026f9179a2c1', $resource['uuid']);
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedViaJsonRestCall()
	{
		$jsonBody = json_encode(array(
			'resource' => array(
				'title' => 'Foo',
			)
		));
		$response = $this->browser->request($this->uriFor('aggregate'), 'POST', array(), array(), array('HTTP_CONTENT_TYPE' => 'application/json'), $jsonBody);

		$this->assertEquals(201, $response->getStatusCode());
		$this->assertNotEmpty($response->getHeader('Location'));

		$response = $this->browser->request($response->getHeader('Location'), 'GET');
		$this->assertNotEmpty($response->getBody()->getContents());
		$resource = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Foo', $resource['title']);
	}

	/**
	 * @test
	 */
	public function resourceCanBeUpdatedViaRestCall()
	{
		$response = $this->createResource(array(
			'title' => 'Foo'
		));
		$resourceUri = $response->getHeader('Location');

		$arguments = array(
			'resource' => array(
				'title' => 'Bar'
			)
		);
		$response = $this->browser->request($resourceUri, 'PUT', $arguments);
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEmpty($response->getBody()->getContents());

		$response = $this->browser->request($resourceUri, 'GET');
		$this->assertNotEmpty($response->getBody()->getContents());
		$resource = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Bar', $resource['title']);
	}

	/**
	 * @test
	 */
	public function resourceCanBeDeletedViaRestCall()
	{
		$response = $this->createResource(array(
			'title' => 'Foo'
		));
		$resourceUri = $response->getHeader('Location');

		$response = $this->browser->request($resourceUri, 'DELETE');
		$this->assertEquals(204, $response->getStatusCode());
		$this->assertEmpty($response->getBody()->getContents());
		$this->persistenceManager->clearState();

		$response = $this->browser->request($resourceUri, 'GET');
		$this->assertEquals(404, $response->getStatusCode());
	}

	/**
	 * @test
	 */
	public function resourceReturnedWillRespectAggregateBoundariesByDefault()
	{
		$arguments = array(
			'resource' => array(
				'title' => 'Foo',
			)
		);
		$this->browser->request($this->uriFor('aggregate/e413ed09-bd63-4a4e-9e0a-026f9179a2c1'), 'POST', $arguments);

		$response = $this->createResource(array(
			'title' => 'Bar',
			'otherAggregate' => 'e413ed09-bd63-4a4e-9e0a-026f9179a2c1'
		));

		$response = $this->browser->request($response->getHeader('Location'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertNotEmpty($response->getBody()->getContents());
		$resource = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse(isset($resource['otherAggregate']['title']));
	}

	/**
	 * @test
	 */
	public function resourceCanBeDescribedViaRestCall()
	{
		$expected = array(
			'title' => array(
				'type' => 'string',
				'elementType' => NULL,
				'transient' => false,
				'identity' => false,
				'multiValued' => false,
			),
			'email' => array(
				'type' => 'string',
				'elementType' => NULL,
				'transient' => false,
				'identity' => false,
				'multiValued' => false,
			),
			'entities' => array(
				'type' => 'Collection',
				'elementType' => 'Entity',
				'transient' => false,
				'identity' => false,
				'multiValued' => true,
				'schema' => array(
					'title' => array(
							'type' => 'string',
							'elementType' => NULL,
							'transient' => false,
							'identity' => false,
							'multiValued' => false,
					),
					'entities' => array(
							'type' => 'Collection',
							'elementType' => 'Entity',
							'transient' => false,
							'identity' => false,
							'multiValued' => true,
							'schema' => 'Entity',
					),
					'uuid' => array(
							'type' => 'string',
							'elementType' => NULL,
							'transient' => false,
							'identity' => true,
							'multiValued' => false,
					),
				),
			),
			'otherAggregate' => array(
				'type' => 'AggregateRoot',
				'elementType' => NULL,
				'transient' => false,
				'identity' => false,
				'multiValued' => false,
				'schema' => 'AggregateRoot',
			),
			'uuid' => array(
				'type' => 'string',
				'elementType' => NULL,
				'transient' => false,
				'identity' => true,
				'multiValued' => false,
			),
		);

		$response = $this->browser->request($this->uriFor('aggregate/describe'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$description = json_decode($response->getBody()->getContents(), true);
		$this->assertThat($expected, $this->equalTo($description), 'The received entity description was: ' . var_export($description, true));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeListedWithPagination()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate?limit=2&cursor=title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(2));
		$this->assertThat($results[0]['title'], $this->equalTo('Bar'));
		$this->assertThat($results[1]['title'], $this->equalTo('Baz'));

		$response = $this->browser->request($this->uriFor('aggregate?limit=2&cursor=title&last=Baz'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(1));
		$this->assertThat($results[0]['title'], $this->equalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeListedWithCursorPaginationByDefault()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz'
		);
		$identity = 1;
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'__identity' => 'e413ed09-bd63-4a4e-9e0a-026f9179a2c' . $identity++,
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate?limit=2'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(2));
		$this->assertThat($results[0]['title'], $this->equalTo('Foo'));
		$this->assertThat($results[1]['title'], $this->equalTo('Bar'));

		$links = new LinkHeader($response->getHeader('Link'));
		$next = $links->getNext();
		$this->assertNotNull($next);

		$response = $this->browser->request($next, 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(1));
		$this->assertThat($results[0]['title'], $this->equalTo('Baz'));
	}

	/**
	 * @test
	 */
	public function resourcesListReturnsPaginationLinkInHeader()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate?limit=2&cursor=title'), 'GET');

		$links = new LinkHeader($response->getHeader('Link'));
		$this->assertNotNull($links->getPrev());
		$next = $links->getNext();
		$this->assertNotNull($next);

		$response = $this->browser->request($next, 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(1));
		$this->assertThat($results[0]['title'], $this->equalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeFiltered()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/filter?title=F%'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(1));
		$this->assertThat($results[0]['title'], $this->equalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBePaginatedWithOffsetAndLimitInFilter()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/filter?limit=1&offset=1'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(1));
		$this->assertThat($results[0]['title'], $this->equalTo('Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSortedInFilter()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/filter?sort=title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(3));
		$this->assertThat($results[0]['title'], $this->equalTo('Bar'));
		$this->assertThat($results[1]['title'], $this->equalTo('Baz'));
		$this->assertThat($results[2]['title'], $this->equalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSortedBackwardsInFilter()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/filter?sort=-title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(3));
		$this->assertThat($results[0]['title'], $this->equalTo('Foo'));
		$this->assertThat($results[1]['title'], $this->equalTo('Baz'));
		$this->assertThat($results[2]['title'], $this->equalTo('Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQuery()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz', 'Bux'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/search?query=Ba&sort=title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode(), $response->getBody()->getContents());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($results['results']));
		$this->assertThat(count($results['results']), $this->equalTo(2));
		$this->assertThat($results['results'][0]['title'], $this->equalTo('Bar'));
		$this->assertThat($results['results'][1]['title'], $this->equalTo('Baz'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQueryWithQuotedString()
	{
		$resourceTitles = array(
			'Foo Bar', 'Bar Bar', 'Baz Bar', 'Bux Bar'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/search?query='.urlencode('"r Bar"')), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($results['results']));
		$this->assertThat(count($results['results']), $this->equalTo(1));
		$this->assertThat($results['results'][0]['title'], $this->equalTo('Bar Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQueryWithinSubEntities()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz', 'Bux'
		);
		$count = 0;
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => 'Aggregate ' . ++$count,
				'entities' => array(
					array( 'title' => $title )
				)
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/search?query=Ba&sort=title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode(), $response->getBody()->getContents());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($results['results']));
		$this->assertThat(count($results['results']), $this->equalTo(2));
		$this->assertThat($results['results'][0]['title'], $this->equalTo('Aggregate 2'));
		$this->assertThat($results['results'][1]['title'], $this->equalTo('Aggregate 3'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQueryWithSimpleBooleanLogic()
	{
		$resourceTitles = array(
			'Foo Bar', 'Bar Bar', 'Baz Bar', 'Bux Bar'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title,
				'email' => '',
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/search?query='.urlencode('Bar +Foo')), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($results['results']));
		$this->assertThat(count($results['results']), $this->equalTo(1));
		$this->assertThat($results['results'][0]['title'], $this->equalTo('Foo Bar'));

		$response = $this->browser->request($this->uriFor('aggregate/search?query='.urlencode('Bar -Foo').'&sort=title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($results['results']));
		$this->assertThat(count($results['results']), $this->equalTo(3));
		$this->assertThat($results['results'][0]['title'], $this->equalTo('Bar Bar'));
		$this->assertThat($results['results'][1]['title'], $this->equalTo('Baz Bar'));
		$this->assertThat($results['results'][2]['title'], $this->equalTo('Bux Bar'));
	}

	/**
	 * see ResourceRepository::findBySearch(...) and https://jira.neos.io/browse/FLOW-462
	 *
	 * @test
	 */
	public function logicalNotQueryWorksCorrectlyOnNullFields()
	{
		$resourceTitles = array(
			'Foo Bar', 'Bar Bar', 'Baz Bar'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title,
				'email' => null
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/search?query='.urlencode('Bar -Foo').'&sort=title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($results['results']));
		$this->assertThat(count($results['results']), $this->equalTo(2));
		$this->assertThat($results['results'][0]['title'], $this->equalTo('Bar Bar'));
		$this->assertThat($results['results'][1]['title'], $this->equalTo('Baz Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedWithinSpecificPropertiesOnly()
	{
		$resourceTitles = array(
			'Foo', 'Bar', 'Baz', 'Bux'
		);
		foreach ($resourceTitles as $title) {
			$this->createResource(array(
				'title' => $title,
				'email' => 'bar@trackmyrace.com'
			));
		}

		$response = $this->browser->request($this->uriFor('aggregate/search?query=Ba&search=title&sort=title'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($results['results']));
		$this->assertThat(count($results['results']), $this->equalTo(2));
		$this->assertThat($results['results'][0]['title'], $this->equalTo('Bar'));
		$this->assertThat($results['results'][1]['title'], $this->equalTo('Baz'));
	}

	/**
	 * @test
	 */
	public function resourcesDefaultFilterIsAppliedInAllQueryActions()
	{
		$resourceEmails = array(
			'a@trackmyrace.com', 'b@trackmyrace.com', 'c@mandigo.de'
		);
		foreach ($resourceEmails as $email) {
			$this->createResource(array(
				'title' => 'Foo',
				'email' => $email
			));
		}

		$response = $this->browser->request($this->uriFor('filteredaggregate?cursor=email'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(2));
		$this->assertThat($results[0]['email'], $this->equalTo('a@trackmyrace.com'));
		$this->assertThat($results[1]['email'], $this->equalTo('b@trackmyrace.com'));

		$response = $this->browser->request($this->uriFor('filteredaggregate/filter?cursor=email'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertThat(count($results), $this->equalTo(2));
		$this->assertThat($results[0]['email'], $this->equalTo('a@trackmyrace.com'));
		$this->assertThat($results[1]['email'], $this->equalTo('b@trackmyrace.com'));
	}

	/**
	 * @test
	 */
	public function resourceRenderingCanBeConfiguredViaRenderConfigurationProperty()
	{
		$this->createResource(array(
			'title' => 'Foo',
			'email' => 'test@trackmyrace.com',
			'entities' => array(
				array('title' => 'Bar')
			)
		));

		$response = $this->browser->request($this->uriFor('filteredaggregate'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse(isset($results[0]['entities']), 'Subentities are included');
	}

	/**
	 * @test
	 */
	public function resourceRenderingConfiguredViaFieldsArgumentIsRestrictedByRenderConfigurationProperty()
	{
		$this->createResource(array(
			'title' => 'Foo',
			'email' => 'test@trackmyrace.com',
			'entities' => array(
				array('title' => 'Bar')
			)
		));

		$response = $this->browser->request($this->uriFor('filteredaggregate?fields=title,email,entities'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse(isset($results[0]['entities']), 'Subentities are included');
	}

	/**
	 * @test
	 */
	public function resourceRenderingCanBeConfiguredViaFieldsArgument()
	{
		$this->createResource(array(
			'title' => 'Foo',
			'email' => 'test@trackmyrace.com',
			'entities' => array(
				array('title' => 'Bar')
			)
		));

		$response = $this->browser->request($this->uriFor('aggregate?fields=title,email'), 'GET');
		$this->assertEquals(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse(isset($results[0]['entities']), 'Subentities are included');
	}

	/**
	 * @test
	 */
	public function nonExistingResourcesResultIn404JsonError()
	{
		$response = $this->browser->request($this->uriFor('aggregate/12345678'), 'GET');
		$this->assertEquals(404, $response->getStatusCode());
		$this->assertEquals('application/json', $response->getHeader('Content-Type'));
		$this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));

		$error = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($error['code']), 'Error code is not set');
		$this->assertTrue(isset($error['message']), 'Error message is not set');
		$this->assertTrue(isset($error['reference']), 'Error reference is not set');
	}

	/**
	 * @test
	 */
	public function propertyMappingErrorsResultIn500JsonError()
	{
		$response = $this->createResource(array('nonExistingProperty' => 'Foo Bar!'));
		$this->assertEquals(500, $response->getStatusCode());
		$this->assertEquals('application/json', $response->getHeader('Content-Type'));
		$this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));

		$error = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($error['code']), 'Error code is not set');
		$this->assertTrue(isset($error['message']), 'Error message is not set');
		$this->assertTrue(isset($error['reference']), 'Error reference is not set');
	}

	/**
	 * @test
	 */
	public function validationErrorsResultIn422JsonError()
	{
		$response = $this->createResource(array('email' => 'Foo Bar!'));
		$this->assertEquals(422, $response->getStatusCode());
		$this->assertEquals('application/json', $response->getHeader('Content-Type'));
		$this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));

		$error = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($error['message']), 'Error message is not set');
		$this->assertTrue(isset($error['errors']), 'Error property sub-errors are not set');
	}

	/**
	 * @test
	 */
	public function exceptionsInTheApplicationCanReturnCustomStatusCodeJsonError()
	{
		$response = $this->browser->request($this->uriFor('aggregate/exceptional'), 'GET');
		$this->assertEquals(9001, $response->getStatusCode());
		$this->assertEquals('application/json', $response->getHeader('Content-Type'));
		$this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));

		$error = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue(isset($error['code']), 'Error code is not set');
		$this->assertTrue(isset($error['message']), 'Error message is not set');
		$this->assertTrue(isset($error['reference']), 'Error reference is not set');
	}
}