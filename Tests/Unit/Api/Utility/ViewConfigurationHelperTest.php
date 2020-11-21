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
		$this->helper = $this->getAccessibleMock(ViewConfigurationHelper::class, ['dummy']);
	}

	/**
	 * Data provider with property paths input and expected view configuration
	 * @return array
	 */
	public function propertyPathsInput()
	{
		return [
			['some.property,some.other', [
				'some' => [
					'_descend' => [
						'property' => [ '_descend' => [] ],
						'other' => [ '_descend' => [] ]
					]
				]
			]],
			['some.deep.property.path', [
				'some' => [
					'_descend' => [
						'deep' => [
							'_descend' => [
								'property' => [
									'_descend' => [
										'path' => [ '_descend' => [] ]
									]
								]
							]
						]
					]
				]
			]],
		];
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
		self::assertThat($output, self::identicalTo($expected));
	}

	/**
	 * Data provider with Aggregate Schemas input and expected view configuration
	 * @return array
	 */
	public function aggregateSchemasInput()
	{
		$simpleEntitySchema = [
			'stringProperty' => [ 'type' => 'string', 'identity' => false, 'multiValued' => false ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'string', 'identity' => false, 'multiValued' => true ],
			'aggregateProperty' => [ 'type' => 'AggregateRoot', 'identity' => false, 'multiValued' => false, 'schema' => 'AggregateRoot' ],
		];
		$simpleAggregateSchema = [
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
				'schema' => $simpleEntitySchema,
			],
			'entityProperty' => [
				'type' => 'Entity2',
				'identity' => false,
				'multiValued' => false,
				'schema' => $simpleEntitySchema,
			],
		];

		$cyclicEntitySchema = [
			'stringProperty' => [ 'type' => 'string', 'identity' => false, 'multiValued' => false ],
			'arrayProperty' => [ 'type' => 'array', 'elementType' => 'Entity', 'identity' => false, 'multiValued' => true, 'schema' => 'Entity' ],
			'entityProperty' => [ 'type' => 'Entity', 'identity' => false, 'multiValued' => false, 'schema' => 'Entity' ],
		];
		$cyclicAggregateSchema = [
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
				'schema' => $cyclicEntitySchema,
			],
			/*'entityProperty' => [
				'type' => 'Entity',
				'identity' => false,
				'multiValued' => false,
				'schema' => $cyclicEntitySchema,
			],*/
		];
		return [
			[$simpleAggregateSchema,
				[
					'arrayProperty' => [ '_descendAll' => [
						'_exposeObjectIdentifier'	 => true,
						'_exposedObjectIdentifierKey' => 'uuid',
						'_descend' => [
							'arrayProperty' => [ '_descendAll' => [] ],
							'aggregateProperty' => [
								'_only' => [],
								'_exposeObjectIdentifier'	 => true,
								'_exposedObjectIdentifierKey' => 'uuid'
							],
						],
					]],
					'entityProperty' => [
						'_exposeObjectIdentifier'	 => true,
						'_exposedObjectIdentifierKey' => 'uuid',
						'_descend' => [
							'arrayProperty' => [ '_descendAll' => [] ],
							'aggregateProperty' => [
								'_only' => [],
								'_exposeObjectIdentifier'	 => true,
								'_exposedObjectIdentifierKey' => 'uuid'
							],
						]
					],
				]
			],
			[$cyclicAggregateSchema,
				[
					'arrayProperty' => [ '_descendAll' => [
						'_exposeObjectIdentifier'	 => true,
						'_exposedObjectIdentifierKey' => 'uuid',
						'_descend' => [
							'arrayProperty' => [ '_descendAll' => [
								'_only' => [],
								'_exposeObjectIdentifier'	 => true,
								'_exposedObjectIdentifierKey' => 'uuid',
							] ],
							'entityProperty' => [
								'_only' => [],
								'_exposeObjectIdentifier'	 => true,
								'_exposedObjectIdentifierKey' => 'uuid'
							],
						],
					]],
				]
			],
		];
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
		self::assertThat($output, self::assertEqualsCanonicalizing($expected));
	}
}
