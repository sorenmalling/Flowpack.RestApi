<?php
namespace Flowpack\RestApi\Tests\Unit\Api\Utility;
use Flowpack\RestApi\Utility\AggregateReflectionHelper;
use Neos\Flow\Reflection\ClassSchema;
use Neos\Flow\Reflection\ReflectionService;

/**
 * Test case for the AggregateReflectionHelper
 */
class AggregateReflectionHelperTest extends \Neos\Flow\Tests\UnitTestCase
{

	/**
	 * @var AggregateReflectionHelper
	 */
	protected $helper;

	public function setUp(): void
	{
		$this->helper = $this->getAccessibleMock(AggregateReflectionHelper::class, ['dummy']);
	}

	protected function createMockSchema($className, array $classSchema, $aggregateRoot = false)
	{
		$mockClassSchema = $this->getMockBuilder(ClassSchema::class)->setConstructorArgs([$className])->getMock();
		$mockClassSchema->method('getIdentityProperties')->willReturn(['uuid' => []]);
		$mockClassSchema->method('getProperties')->willReturn($classSchema);
		$mockClassSchema->method('isAggregateRoot')->willReturn($aggregateRoot);
		$mockClassSchema->method('getModelType')->willReturn(ClassSchema::MODELTYPE_ENTITY);
		$mockClassSchema->method('isMultiValuedProperty')->will($this->returnCallback(static function($propertyName) use ($classSchema) {
			return isset($classSchema[$propertyName]) && ($classSchema[$propertyName]['type'] === 'array' || !empty($classSchema[$propertyName]['elementType']));
		}));
		$mockClassSchema->method('isPropertyTransient')->willReturn(false);
		return $mockClassSchema;
	}

	protected function createAggregateMockSchema($className, array $classSchema)
	{
		return $this->createMockSchema($className, $classSchema, true);
	}

	/**
	 * @test
	 */
	public function iterateAggregateBoundaryRecursivelyWorksAsExpected()
	{
		$mockAggregateClassSchema = $this->createAggregateMockSchema('AggregateRoot', [
			'stringProperty' => [ 'type' => 'string' ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'Entity' ],
			'entityProperty' => [ 'type' => 'Entity2' ]
		]);

		$mockEntityClassSchema = $this->createMockSchema('Entity', [
			'stringProperty' => [ 'type' => 'string' ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'string' ],
			'aggregateProperty' => [ 'type' => 'AggregateRoot' ]
		]);

		$mockReflectionService = $this->createMock(ReflectionService::class);
		$mockReflectionService->method('getClassSchema')->will($this->returnValueMap([
			['Entity', $mockEntityClassSchema],
			['Entity2', $mockEntityClassSchema],
			['AggregateRoot', $mockAggregateClassSchema],
		]));
		$this->helper->injectReflectionService($mockReflectionService);

		$output = $this->helper->reflectAggregate('AggregateRoot');

		$entitySchema = [
			'stringProperty' => [ 'type' => 'string', 'identity' => false, 'multiValued' => false ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'string', 'identity' => false, 'multiValued' => true ],
			'aggregateProperty' => [ 'type' => 'AggregateRoot', 'identity' => false, 'multiValued' => false, 'schema' => 'AggregateRoot' ],
		];
		$expected = [
			'stringProperty' => [
				'type' => 'string',
				'identity' => false,
				'multiValued' => false,
			],
			'arrayProperty' => [
				'type' => 'array',
				'elementType' => 'Entity',
				'identity' => false,
				'multiValued' => true,
				'schema' => $entitySchema,
			],
			'entityProperty' => [
				'type' => 'Entity2',
				'identity' => false,
				'multiValued' => false,
				'schema' => $entitySchema,
			],
		];
		self::assertThat($output, self::identicalTo($expected));
	}

	/**
	 * @test
	 */
	public function iterateAggregateBoundaryRecursivelyWorksForCyclicRelations()
	{
		$mockAggregateClassSchema = $this->createAggregateMockSchema('AggregateRoot', [
			'stringProperty' => [ 'type' => 'string' ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'Entity' ],
			'entityProperty' => [ 'type' => 'Entity' ],
		]);

		$mockEntityClassSchema = $this->createMockSchema('Entity', [
			'stringProperty' => [ 'type' => 'string' ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'Entity' ],
			'entityProperty' => [ 'type' => 'Entity' ],
		]);

		$mockReflectionService = $this->createMock(ReflectionService::class);
		$mockReflectionService->method('getClassSchema')->will($this->returnValueMap([
			['Entity', $mockEntityClassSchema],
			['AggregateRoot', $mockAggregateClassSchema],
		]));
		$this->helper->injectReflectionService($mockReflectionService);

		$output = $this->helper->reflectAggregate('AggregateRoot');

		$entitySchema = [
			'stringProperty' => [ 'type' => 'string', 'identity' => false, 'multiValued' => false ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'Entity', 'identity' => false, 'multiValued' => true, 'schema' => 'Entity' ],
			'entityProperty' => [ 'type' => 'Entity', 'identity' => false, 'multiValued' => false, 'schema' => 'Entity' ],
		];
		$expected = [
			'stringProperty' => [
				'type' => 'string',
				'identity' => false,
				'multiValued' => false,
			],
			'arrayProperty' => [
				'type' => 'array',
				'elementType' => 'Entity',
				'identity' => false,
				'multiValued' => true,
				'schema' => $entitySchema,
			],
			'entityProperty' => [
				'type' => 'Entity',
				'identity' => false,
				'multiValued' => false,
				'schema' => $entitySchema,
			],
		];
		self::assertThat($output, self::identicalTo($expected));
	}
}
