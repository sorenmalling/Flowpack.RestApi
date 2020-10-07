<?php
namespace Flowpack\RestApi\Tests\Unit\Api\Utility;

use Flowpack\RestApi\Utility\ViewConfigurationHelper;
use Neos\Flow\Reflection\ClassSchema;
use Neos\Flow\Reflection\ReflectionService;

/**
 * Test case for the ViewConfigurationHelper
 */
class ViewConfigurationHelperTest extends \Neos\Flow\Tests\UnitTestCase
{

	/**
	 * @var ViewConfigurationHelper
	 */
	protected $helper;

	public function setUp(): void
	{
		$this->helper = $this->getAccessibleMock(ViewConfigurationHelper::class, array('dummy'));
	}

	/**
	 * Data provider with property paths input and expected view configuration
	 * @return array
	 */
	public function propertyPathsInput()
	{
		return array(
			array('some.property,some.other', array( 'some' => array( '_descend' => array(
				'property' => array( '_descend' => array() ),
				'other' => array( '_descend' => array() )
			) ) )),
			array('some.deep.property.path', array( 'some' => array( '_descend' => array( 'deep' => array( '_descend' => array( 'property' => array( '_descend' => array( 'path' => array( '_descend' => array() ))))))))),
		);
	}

	/**
	 * @test
	 * @dataProvider propertyPathsInput
	 * @param string $input
	 * @param array $expected
	 */
	public function convertPropertyPathsToViewConfigurationWorksAsExpected($input, $expected)
	{
		$output = $this->helper->convertPropertyPathsToViewConfiguration($input);
		$this->assertThat($output, $this->equalTo($expected));
	}

	/**
	 * Data provider with Aggregate Schemas input and expected view configuration
	 * @return array
	 */
	public function aggregateSchemasInput()
	{
		$simpleEntitySchema = array(
			'stringProperty' => array( 'type' => 'string', 'identity' => false, 'multiValued' => false ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'string', 'identity' => false, 'multiValued' => true ),
			'aggregateProperty' => array( 'type' => 'AggregateRoot', 'identity' => false, 'multiValued' => false, 'schema' => 'AggregateRoot' ),
		);
		$simpleAggregateSchema = array(
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
				'schema' => $simpleEntitySchema,
			),
			'entityProperty' => array(
				'type' => 'Entity2',
				'identity' => false,
				'multiValued' => false,
				'schema' => $simpleEntitySchema,
			),
		);

		$cyclicEntitySchema = array(
			'stringProperty' => array( 'type' => 'string', 'identity' => false, 'multiValued' => false ),
			'arrayProperty' => array( 'type' => 'array', 'elementType' => 'Entity', 'identity' => false, 'multiValued' => true, 'schema' => 'Entity' ),
			'entityProperty' => array( 'type' => 'Entity', 'identity' => false, 'multiValued' => false, 'schema' => 'Entity' ),
		);
		$cyclicAggregateSchema = array(
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
				'schema' => $cyclicEntitySchema,
			),
			/*'entityProperty' => array(
				'type' => 'Entity',
				'identity' => false,
				'multiValued' => false,
				'schema' => $cyclicEntitySchema,
			),*/
		);
		return array(
			array($simpleAggregateSchema,
				array(
					'arrayProperty' => array( '_descendAll' => array(
						'_exposeObjectIdentifier'     => true,
						'_exposedObjectIdentifierKey' => 'uuid',
						'_descend' => array(
							'arrayProperty' => array( '_descendAll' => array() ),
							'aggregateProperty' => array(
								'_only' => array(),
								'_exposeObjectIdentifier'     => true,
								'_exposedObjectIdentifierKey' => 'uuid'
							),
						),
					)),
					'entityProperty' => array(
						'_exposeObjectIdentifier'     => true,
						'_exposedObjectIdentifierKey' => 'uuid',
						'_descend' => array(
							'arrayProperty' => array( '_descendAll' => array() ),
							'aggregateProperty' => array(
								'_only' => array(),
								'_exposeObjectIdentifier'     => true,
								'_exposedObjectIdentifierKey' => 'uuid'
							),
						)
					),
				)
			),
			array($cyclicAggregateSchema,
				array(
				'arrayProperty' => array( '_descendAll' => array(
					'_exposeObjectIdentifier'     => true,
					'_exposedObjectIdentifierKey' => 'uuid',
					'_descend' => array(
						'arrayProperty' => array( '_descendAll' => array(
							'_only' => array(),
							'_exposeObjectIdentifier'     => true,
							'_exposedObjectIdentifierKey' => 'uuid',
						) ),
						'entityProperty' => array(
							'_only' => array(),
							'_exposeObjectIdentifier'     => true,
							'_exposedObjectIdentifierKey' => 'uuid'
						),
					),
				)),
			)
		),
		);
	}

	/**
	 * @test
	 * @dataProvider aggregateSchemasInput
	 * @param array $input
	 * @param array $expected
	 */
	public function convertAggregateSchemaToViewConfigurationWorksAsExpected($input, $expected)
	{
		$output = $this->helper->convertAggregateSchemaToViewConfiguration($input);
		$this->assertThat($output, $this->equalTo($expected));
	}
}