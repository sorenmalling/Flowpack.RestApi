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

	public function setUp()
	{
		$this->helper = $this->getAccessibleMock(AggregateReflectionHelper::class, array('dummy'));
	}

	protected function createMockSchema($className, array $classSchema, $aggregateRoot = false)
	{
		$mockClassSchema = $this->getMockBuilder(ClassSchema::class)->setConstructorArgs(array($className))->getMock();
		$mockClassSchema->expects($this->any())->method('getIdentityProperties')->will($this->returnValue(['uuid' => []]));
		$mockClassSchema->expects($this->any())->method('getProperties')->will($this->returnValue($classSchema));
		$mockClassSchema->expects($this->any())->method('isAggregateRoot')->will($this->returnValue($aggregateRoot));
		$mockClassSchema->expects($this->any())->method('getModelType')->will($this->returnValue(ClassSchema::MODELTYPE_ENTITY));
		$mockClassSchema->expects($this->any())->method('isMultiValuedProperty')->will($this->returnCallback(function($propertyName) use ($classSchema) {
			return isset($classSchema[$propertyName]) && ($classSchema[$propertyName]['type'] === 'array' || !empty($classSchema[$propertyName]['elementType']));
		}));
		$mockClassSchema->expects($this->any())->method('isPropertyTransient')->will($this->returnValue(false));
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
		$mockAggregateClassSchema = $this->createAggregateMockSchema('AggregateRoot', array(
			'stringProperty' => array( 'type' => 'string' ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'Entity' ),
			'entityProperty' => array( 'type' => 'Entity2' )
		));

		$mockEntityClassSchema = $this->createMockSchema('Entity', array(
			'stringProperty' => array( 'type' => 'string' ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'string' ),
			'aggregateProperty' => array( 'type' => 'AggregateRoot' )
		));

		$mockReflectionService = $this->createMock(ReflectionService::class);
		$mockReflectionService->expects($this->any())->method('getClassSchema')->will($this->returnValueMap(array(
			array('Entity', $mockEntityClassSchema),
			array('Entity2', $mockEntityClassSchema),
			array('AggregateRoot', $mockAggregateClassSchema),
		)));
		$this->helper->injectReflectionService($mockReflectionService);

		$output = $this->helper->reflectAggregate('AggregateRoot');

		$entitySchema = array(
			'stringProperty' => array( 'type' => 'string', 'identity' => false, 'multiValued' => false ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'string', 'identity' => false, 'multiValued' => true ),
			'aggregateProperty' => array( 'type' => 'AggregateRoot', 'identity' => false, 'multiValued' => false, 'schema' => 'AggregateRoot' ),
		);
		$expected = array(
			'stringProperty' => array(
				'type' => 'string',
				'identity' => false,
				'multiValued' => false,
			),
			'arrayProperty' => array(
				'type' => 'array',
				'elementType' => 'Entity',
				'identity' => false,
				'multiValued' => true,
				'schema' => $entitySchema,
			),
			'entityProperty' => array(
				'type' => 'Entity2',
				'identity' => false,
				'multiValued' => false,
				'schema' => $entitySchema,
			),
		);
		$this->assertThat($output, $this->equalTo($expected));
	}

	/**
	 * @test
	 */
	public function iterateAggregateBoundaryRecursivelyWorksForCyclicRelations()
	{
		$mockAggregateClassSchema = $this->createAggregateMockSchema('AggregateRoot', array(
			'stringProperty' => array( 'type' => 'string' ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'Entity' ),
			'entityProperty' => array( 'type' => 'Entity' ),
		));

		$mockEntityClassSchema = $this->createMockSchema('Entity', array(
			'stringProperty' => array( 'type' => 'string' ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'Entity' ),
			'entityProperty' => array( 'type' => 'Entity' ),
		));

		$mockReflectionService = $this->createMock(ReflectionService::class);
		$mockReflectionService->expects($this->any())->method('getClassSchema')->will($this->returnValueMap(array(
			array('Entity', $mockEntityClassSchema),
			array('AggregateRoot', $mockAggregateClassSchema),
		)));
		$this->helper->injectReflectionService($mockReflectionService);

		$output = $this->helper->reflectAggregate('AggregateRoot');

		$entitySchema = array(
			'stringProperty' => array( 'type' => 'string', 'identity' => false, 'multiValued' => false ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'Entity', 'identity' => false, 'multiValued' => true, 'schema' => 'Entity' ),
			'entityProperty' => array( 'type' => 'Entity', 'identity' => false, 'multiValued' => false, 'schema' => 'Entity' ),
		);
		$expected = array(
			'stringProperty' => array(
				'type' => 'string',
				'identity' => false,
				'multiValued' => false,
			),
			'arrayProperty' => array(
				'type' => 'array',
				'elementType' => 'Entity',
				'identity' => false,
				'multiValued' => true,
				'schema' => $entitySchema,
			),
			'entityProperty' => array(
				'type' => 'Entity',
				'identity' => false,
				'multiValued' => false,
				'schema' => $entitySchema,
			),
		);
		$this->assertThat($output, $this->equalTo($expected));
	}
}