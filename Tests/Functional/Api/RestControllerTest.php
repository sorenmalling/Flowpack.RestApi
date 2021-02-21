<?php
namespace Flowpack\RestApi\Tests\Functional\Api;

use Flowpack\RestApi\Utility\LinkHeader;
use Neos\Flow\Mvc\Routing\Route;
use Neos\Flow\Mvc\Routing\Dto\ResolveContext;
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
	 * @param string $uri
	 * @return array
	 */
	protected function get(string $uri): array
	{
		$response = $this->browser->request($uri, 'GET');
		self::assertSame(200, $response->getStatusCode(), $uri . ' expected to return 200 OK');
		$body = $response->getBody()->getContents();
		self::assertNotEmpty($body);
		$parsedBody = json_decode($body, true);
		if ($parsedBody === null) {
			throw new \Exception('Invalid JSON body returned. Got "' . $body . '".');
		}
		return $parsedBody;
	}

	/**
	 * @test
	 */
	public function routerCorrectlyResolvesIndexAction()
	{
		$uri = $this->router->resolve(new ResolveContext(new \GuzzleHttp\Psr7\Uri('http://localhost'), [
			'@package' => 'Flowpack.RestApi',
			'@subpackage' => 'Tests\Functional\Api\Fixtures',
			'@controller' => 'Aggregate',
			'@action' => 'index',
			'@format' => 'json'
		], true));
		self::assertSame($this->uriFor('aggregate', false), $uri, $uri);
	}

	/**
	 * @test
	 */
	public function routesWithFormatBasicallyWork()
	{
		$response = $this->createResource(['title' => 'Foo']);
		self::assertSame(201, $response->getStatusCode());
		self::assertNotEmpty($response->getHeaderLine('Location'));

		$resource = $this->get($response->getHeaderLine('Location') . '.json');
		self::assertSame('Foo', $resource['title']);
	}

	/**
	 * @param array $resourceProperties
	 * @return ResponseInterface
	 */
	protected function createResource(array $resourceProperties)
	{
		$arguments = [
			'resource' => $resourceProperties
		];
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
		$arguments = [
			'resources' => $resourceProperties
		];
		$response = $this->browser->request($this->uriFor('aggregate'), 'POST', $arguments);
		$this->persistenceManager->clearState();
		return $response;
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedViaRestCall()
	{
		$response = $this->createResource([
			'title' => 'Foo'
		]);
		self::assertSame(201, $response->getStatusCode());
		self::assertNotEmpty($response->getHeaderLine('Location'));

		$resource = $this->get($response->getHeaderLine('Location'));
		self::assertSame('Foo', $resource['title']);
	}

	/**
	 * @test
	 */
	public function multipleResourcesCanBeCreatedViaSingleRestCall()
	{
		$response = $this->createResources([
			['title' => 'Foo'],
			['title' => 'Bar'],
			['title' => 'Baz']
		]);
		self::assertSame(201, $response->getStatusCode(), $response->getBody()->getContents());
		self::assertNotEmpty($response->getHeaderLine('Location'));

		$resources = $this->get($response->getHeaderLine('Location') . '?cursor=title');
		self::assertSame('Bar', $resources[0]['title']);
		self::assertSame('Baz', $resources[1]['title']);
		self::assertSame('Foo', $resources[2]['title']);
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedWithSubEntitiesViaRestCall()
	{
		$response = $this->createResource([
			'title' => 'Foo',
			'entities' => [
				[ 'title' => 'Bar' ]
			]
		]);
		self::assertSame(201, $response->getStatusCode());
		self::assertNotEmpty($response->getHeaderLine('Location'));

		$resource = $this->get($response->getHeaderLine('Location'));
		self::assertSame('Bar', $resource['entities'][0]['title']);
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedWithPredefinedIdentifierViaRestCall()
	{
		$arguments = [
			'resource' => [
				'title' => 'Foo',
			]
		];
		$response = $this->browser->request($this->uriFor('aggregate/e413ed09-bd63-4a4e-9e0a-026f9179a2c1'), 'POST', $arguments);
		self::assertSame(201, $response->getStatusCode());
		self::assertStringEndsWith('e413ed09-bd63-4a4e-9e0a-026f9179a2c1', $response->getHeaderLine('Location'));

		$resource = $this->get($response->getHeaderLine('Location'));
		self::assertSame('Foo', $resource['title']);
		self::assertSame('e413ed09-bd63-4a4e-9e0a-026f9179a2c1', $resource['uuid']);
	}

	/**
	 * @test
	 */
	public function resourcesCanBeCreatedViaJsonRestCall()
	{
		$jsonBody = json_encode([
			'resource' => [
				'title' => 'Foo',
			]
		]);
		$response = $this->browser->request($this->uriFor('aggregate'), 'POST', [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], $jsonBody);

		self::assertSame(201, $response->getStatusCode());
		self::assertNotEmpty($response->getHeaderLine('Location'));

		$resource = $this->get($response->getHeaderLine('Location'));
		self::assertSame('Foo', $resource['title']);
	}

	/**
	 * @test
	 */
	public function resourceCanBeUpdatedViaRestCall()
	{
		$response = $this->createResource([
			'title' => 'Foo'
		]);
		$resourceUri = $response->getHeaderLine('Location');

		$arguments = [
			'resource' => [
				'title' => 'Bar'
			]
		];
		$response = $this->browser->request($resourceUri, 'PUT', $arguments);
		self::assertSame(200, $response->getStatusCode(), $resourceUri . ' expected to return 200 OK');
		self::assertEmpty($response->getBody()->getContents());

		$resource = $this->get($resourceUri);
		self::assertSame('Bar', $resource['title']);
	}

	/**
	 * @test
	 */
	public function resourceCanBeDeletedViaRestCall()
	{
		$response = $this->createResource([
			'title' => 'Foo'
		]);
		$resourceUri = $response->getHeaderLine('Location');

		$response = $this->browser->request($resourceUri, 'DELETE');
		self::assertSame(204, $response->getStatusCode());
		self::assertEmpty($response->getBody()->getContents());
		$this->persistenceManager->clearState();

		$response = $this->browser->request($resourceUri, 'GET');
		self::assertSame(404, $response->getStatusCode());
	}

	/**
	 * @test
	 */
	public function resourceReturnedWillRespectAggregateBoundariesByDefault()
	{
		$arguments = [
			'resource' => [
				'title' => 'Foo',
			]
		];
		$this->browser->request($this->uriFor('aggregate/e413ed09-bd63-4a4e-9e0a-026f9179a2c1'), 'POST', $arguments);

		$response = $this->createResource([
			'title' => 'Bar',
			'otherAggregate' => 'e413ed09-bd63-4a4e-9e0a-026f9179a2c1'
		]);

		$resource = $this->get($response->getHeaderLine('Location'));
		self::assertFalse(isset($resource['otherAggregate']['title']));
	}

	/**
	 * @test
	 */
	public function resourceCanBeDescribedViaRestCall()
	{
		$expected = [
			'title' => [
				'type' => 'string',
				'elementType' => NULL,
				'transient' => false,
				'identity' => false,
				'multiValued' => false,
			],
			'email' => [
				'type' => 'string',
				'elementType' => NULL,
				'transient' => false,
				'identity' => false,
				'multiValued' => false,
			],
			'entities' => [
				'type' => 'Collection',
				'elementType' => 'Entity',
				'transient' => false,
				'identity' => false,
				'multiValued' => true,
				'schema' => [
					'title' => [
						'type' => 'string',
						'elementType' => NULL,
						'transient' => false,
						'identity' => false,
						'multiValued' => false,
					],
					'entities' => [
						'type' => 'Collection',
						'elementType' => 'Entity',
						'transient' => false,
						'identity' => false,
						'multiValued' => true,
						'schema' => 'Entity',
					],
					'uuid' => [
						'type' => 'string',
						'elementType' => NULL,
						'transient' => false,
						'identity' => true,
						'multiValued' => false,
					],
				],
			],
			'otherAggregate' => [
				'type' => 'AggregateRoot',
				'elementType' => NULL,
				'transient' => false,
				'identity' => false,
				'multiValued' => false,
				'schema' => 'AggregateRoot',
			],
			'uuid' => [
				'type' => 'string',
				'elementType' => NULL,
				'transient' => false,
				'identity' => true,
				'multiValued' => false,
			],
		];

		$description = $this->get($this->uriFor('aggregate/describe'));
		self::assertThat($expected, self::identicalTo($description), 'The received entity description was: ' . var_export($description, true));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeListedWithPagination()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$results = $this->get($this->uriFor('aggregate?limit=2&cursor=title'));
		self::assertThat(count($results), self::identicalTo(2));
		self::assertThat($results[0]['title'], self::identicalTo('Bar'));
		self::assertThat($results[1]['title'], self::identicalTo('Baz'));

		$results = $this->get($this->uriFor('aggregate?limit=2&cursor=title&last=Baz'));
		self::assertThat(count($results), self::identicalTo(1));
		self::assertThat($results[0]['title'], self::identicalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeListedWithCursorPaginationByDefault()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz'
		];
		$identity = 1;
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'__identity' => 'e413ed09-bd63-4a4e-9e0a-026f9179a2c' . $identity++,
				'title' => $title
			]);
		}

		$response = $this->browser->request($this->uriFor('aggregate?limit=2'), 'GET');
		self::assertSame(200, $response->getStatusCode());
		$results = json_decode($response->getBody()->getContents(), true);
		self::assertThat(count($results), self::identicalTo(2));
		self::assertThat($results[0]['title'], self::identicalTo('Foo'));
		self::assertThat($results[1]['title'], self::identicalTo('Bar'));

		self::assertNotEmpty($response->getHeaderLine('Link'), json_encode($response->getHeaders()));
		$links = new LinkHeader($response->getHeaderLine('Link'));
		$next = $links->getNext();
		self::assertNotNull($next, 'Link for next expected to be set in "' . $response->getHeaderLine('Link') . '"');

		$results = $this->get($next);
		self::assertThat(count($results), self::identicalTo(1));
		self::assertThat($results[0]['title'], self::identicalTo('Baz'));
	}

	/**
	 * @test
	 */
	public function resourcesListReturnsPaginationLinkInHeader()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$response = $this->browser->request($this->uriFor('aggregate?limit=2&cursor=title'), 'GET');

		$links = new LinkHeader($response->getHeaderLine('Link'));
		self::assertNotNull($links->getPrev());
		$next = $links->getNext();
		self::assertNotNull($next, 'Link for next expected to be set in "' . $response->getHeaderLine('Link') . '"');

		$results = $this->get($next);
		self::assertThat(count($results), self::identicalTo(1));
		self::assertThat($results[0]['title'], self::identicalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeFiltered()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$results = $this->get($this->uriFor('aggregate/filter?title=F%'));
		self::assertThat(count($results), self::identicalTo(1));
		self::assertThat($results[0]['title'], self::identicalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBePaginatedWithOffsetAndLimitInFilter()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$results = $this->get($this->uriFor('aggregate/filter?limit=1&offset=1'));
		self::assertThat(count($results), self::identicalTo(1));
		self::assertThat($results[0]['title'], self::identicalTo('Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSortedInFilter()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$results = $this->get($this->uriFor('aggregate/filter?sort=title'));
		self::assertThat(count($results), self::identicalTo(3));
		self::assertThat($results[0]['title'], self::identicalTo('Bar'));
		self::assertThat($results[1]['title'], self::identicalTo('Baz'));
		self::assertThat($results[2]['title'], self::identicalTo('Foo'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSortedBackwardsInFilter()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$results = $this->get($this->uriFor('aggregate/filter?sort=-title'));
		self::assertThat(count($results), self::identicalTo(3));
		self::assertThat($results[0]['title'], self::identicalTo('Foo'));
		self::assertThat($results[1]['title'], self::identicalTo('Baz'));
		self::assertThat($results[2]['title'], self::identicalTo('Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQuery()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz', 'Bux'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$results = $this->get($this->uriFor('aggregate/search?query=Ba&sort=title'));
		self::assertTrue(isset($results['results']));
		self::assertThat(count($results['results']), self::identicalTo(2));
		self::assertThat($results['results'][0]['title'], self::identicalTo('Bar'));
		self::assertThat($results['results'][1]['title'], self::identicalTo('Baz'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQueryWithQuotedString()
	{
		$resourceTitles = [
			'Foo Bar', 'Bar Bar', 'Baz Bar', 'Bux Bar'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title
			]);
		}

		$results = $this->get($this->uriFor('aggregate/search?query='.urlencode('"r Bar"')));
		self::assertTrue(isset($results['results']));
		self::assertThat(count($results['results']), self::identicalTo(1));
		self::assertThat($results['results'][0]['title'], self::identicalTo('Bar Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQueryWithinSubEntities()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz', 'Bux'
		];
		$count = 0;
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => 'Aggregate ' . ++$count,
				'entities' => [
					[ 'title' => $title ]
				]
			]);
		}

		$results = $this->get($this->uriFor('aggregate/search?query=Ba&sort=title'));
		self::assertTrue(isset($results['results']));
		self::assertThat(count($results['results']), self::identicalTo(2));
		self::assertThat($results['results'][0]['title'], self::identicalTo('Aggregate 2'));
		self::assertThat($results['results'][1]['title'], self::identicalTo('Aggregate 3'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedByQueryWithSimpleBooleanLogic()
	{
		$resourceTitles = [
			'Foo Bar', 'Bar Bar', 'Baz Bar', 'Bux Bar'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title,
				'email' => '',
			]);
		}

		$results = $this->get($this->uriFor('aggregate/search?query='.urlencode('Bar +Foo')));
		self::assertTrue(isset($results['results']));
		self::assertThat(count($results['results']), self::identicalTo(1));
		self::assertSame('Foo Bar', $results['results'][0]['title']);

		$results = $this->get($this->uriFor('aggregate/search?query='.urlencode('Bar -Foo').'&sort=title'));
		self::assertTrue(isset($results['results']));
		self::assertThat(count($results['results']), self::identicalTo(3));
		self::assertThat($results['results'][0]['title'], self::identicalTo('Bar Bar'));
		self::assertThat($results['results'][1]['title'], self::identicalTo('Baz Bar'));
		self::assertThat($results['results'][2]['title'], self::identicalTo('Bux Bar'));
	}

	/**
	 * see ResourceRepository::findBySearch(...) and https://jira.neos.io/browse/FLOW-462
	 *
	 * @test
	 */
	public function logicalNotQueryWorksCorrectlyOnNullFields()
	{
		$resourceTitles = [
			'Foo Bar', 'Bar Bar', 'Baz Bar'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title,
				'email' => null
			]);
		}

		$results = $this->get($this->uriFor('aggregate/search?query='.urlencode('Bar -Foo').'&sort=title'));
		self::assertTrue(isset($results['results']));
		self::assertThat(count($results['results']), self::identicalTo(2));
		self::assertThat($results['results'][0]['title'], self::identicalTo('Bar Bar'));
		self::assertThat($results['results'][1]['title'], self::identicalTo('Baz Bar'));
	}

	/**
	 * @test
	 */
	public function resourcesCanBeSearchedWithinSpecificPropertiesOnly()
	{
		$resourceTitles = [
			'Foo', 'Bar', 'Baz', 'Bux'
		];
		foreach ($resourceTitles as $title) {
			$this->createResource([
				'title' => $title,
				'email' => 'bar@trackmyrace.com'
			]);
		}

		$results = $this->get($this->uriFor('aggregate/search?query=Ba&search=title&sort=title'));
		self::assertTrue(isset($results['results']));
		self::assertThat(count($results['results']), self::identicalTo(2));
		self::assertThat($results['results'][0]['title'], self::identicalTo('Bar'));
		self::assertThat($results['results'][1]['title'], self::identicalTo('Baz'));
	}

	/**
	 * @test
	 */
	public function resourcesDefaultFilterIsAppliedInAllQueryActions()
	{
		$resourceEmails = [
			'a@trackmyrace.com', 'b@trackmyrace.com', 'c@mandigo.de'
		];
		foreach ($resourceEmails as $email) {
			$this->createResource([
				'title' => 'Foo',
				'email' => $email
			]);
		}

		$results = $this->get($this->uriFor('filteredaggregate?cursor=email'));
		self::assertThat(count($results), self::identicalTo(2));
		self::assertThat($results[0]['email'], self::identicalTo('a@trackmyrace.com'));
		self::assertThat($results[1]['email'], self::identicalTo('b@trackmyrace.com'));

		$results = $this->get($this->uriFor('filteredaggregate/filter?cursor=email'));
		self::assertThat(count($results), self::identicalTo(2));
		self::assertThat($results[0]['email'], self::identicalTo('a@trackmyrace.com'));
		self::assertThat($results[1]['email'], self::identicalTo('b@trackmyrace.com'));
	}

	/**
	 * @test
	 */
	public function resourceRenderingCanBeConfiguredViaRenderConfigurationProperty()
	{
		$this->createResource([
			'title' => 'Foo',
			'email' => 'test@trackmyrace.com',
			'entities' => [
				['title' => 'Bar']
			]
		]);

		$results = $this->get($this->uriFor('filteredaggregate'));
		self::assertFalse(isset($results[0]['entities']), 'Subentities are included');
	}

	/**
	 * @test
	 */
	public function resourceRenderingConfiguredViaFieldsArgumentIsRestrictedByRenderConfigurationProperty()
	{
		$this->createResource([
			'title' => 'Foo',
			'email' => 'test@trackmyrace.com',
			'entities' => [
				['title' => 'Bar']
			]
		]);

		$results = $this->get($this->uriFor('filteredaggregate?fields=title,email,entities'));
		self::assertFalse(isset($results[0]['entities']), 'Subentities are included');
	}

	/**
	 * @test
	 */
	public function resourceRenderingCanBeConfiguredViaFieldsArgument()
	{
		$this->createResource([
			'title' => 'Foo',
			'email' => 'test@trackmyrace.com',
			'entities' => [
				['title' => 'Bar']
			]
		]);

		$results = $this->get($this->uriFor('aggregate?fields=title,email'));
		self::assertFalse(isset($results[0]['entities']), 'Subentities are included');
	}

	/**
	 * @test
	 */
	public function nonExistingResourcesResultIn404JsonError()
	{
		$response = $this->browser->request($this->uriFor('aggregate/12345678'), 'GET');
		self::assertSame(404, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

		$error = json_decode($response->getBody()->getContents(), true);
		self::assertTrue(isset($error['code']), 'Error code is not set');
		self::assertTrue(isset($error['message']), 'Error message is not set');
		self::assertTrue(isset($error['reference']), 'Error reference is not set');
	}

	/**
	 * @test
	 */
	public function propertyMappingErrorsResultIn500JsonError()
	{
		$response = $this->createResource(['nonExistingProperty' => 'Foo Bar!']);
		self::assertSame(500, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

		$error = json_decode($response->getBody()->getContents(), true);
		self::assertTrue(isset($error['code']), 'Error code is not set');
		self::assertTrue(isset($error['message']), 'Error message is not set');
		self::assertTrue(isset($error['reference']), 'Error reference is not set');
	}

	/**
	 * @test
	 */
	public function validationErrorsResultIn422JsonError()
	{
		$response = $this->createResource(['email' => 'Foo Bar!']);
		self::assertSame(422, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

		$error = json_decode($response->getBody()->getContents(), true);
		self::assertTrue(isset($error['message']), 'Error message is not set');
		self::assertTrue(isset($error['errors']), 'Error property sub-errors are not set');
	}

	/**
	 * @test
	 */
	public function exceptionsInTheApplicationCanReturnCustomStatusCodeJsonError()
	{
		$this->markTestSkipped('Flow 6+ currently doesnt propagate the exception code as status...');
		$response = $this->browser->request($this->uriFor('aggregate/exceptional'), 'GET');
		self::assertSame(9001, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

		$error = json_decode($response->getBody()->getContents(), true);
		self::assertTrue(isset($error['code']), 'Error code is not set');
		self::assertTrue(isset($error['message']), 'Error message is not set');
		self::assertTrue(isset($error['reference']), 'Error reference is not set');
	}
}
