<?php
namespace Flowpack\RestApi\Utility;

use Neos\Flow\Reflection\ClassSchema;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\TypeHandling;

use Neos\Flow\Annotations as Flow;

/**
 * A class for reflecting aggregates up to their boundaries
 *
 * @Flow\Scope("singleton")
 */
class AggregateReflectionHelper
{

	/**
	 * @var ReflectionService
	 * @Flow\Inject
	 */
	protected $reflectionService;

	/**
	 * @var string
	 */
	protected $identifierName = 'uuid';

	/**
	 * Injection setter necessary for compile time usage of this class.
	 * @param ReflectionService $reflectionService
	 */
	public function injectReflectionService(ReflectionService $reflectionService)
	{
		$this->reflectionService = $reflectionService;
	}

	/**
	 * @param string $identifierName
	 * @return $this
	 */
	public function withIdentifierName($identifierName)
	{
		$this->identifierName = $identifierName;
		return $this;
	}

	/**
	 * @param ClassSchema $classSchema Class schema of the current entity that is visited
	 * @param int $recursionDepth A counter to stop at a probably cyclic recursion
	 * @param array $visitedTypes Array given by reference that will hold all visited class types in order to prevent cyclic schemas
	 * @param array $propertyDescriptions Array given by reference that will hold the property reflection information for this class schema and it's children
	 */
	protected function iterateAggregateBoundaryPropertiesRecursively(ClassSchema $classSchema, $recursionDepth, array &$visitedTypes, array &$propertyDescriptions)
	{
		if (++$recursionDepth >= 100) {
			throw new \Exception(sprintf('Cyclic references detected in schema for class "%s".', $classSchema->getClassName()));
		}

		$identityProperties = array_keys($classSchema->getIdentityProperties());
		foreach ($classSchema->getProperties() as $propertyName => $property) {
			$property['identity'] = in_array($propertyName, $identityProperties);
			$property['multiValued'] = $classSchema->isMultiValuedProperty($propertyName);

			$propertyType = $property['type'];
			if ($classSchema->isMultiValuedProperty($propertyName)) {
				$propertyType = $property['elementType'] ? : $propertyType;
			}

			unset($property['lazy']);	// Irrelevant for structural schema
			if ($propertyName === 'Persistence_Object_Identifier') {
				$propertyName = $this->identifierName;
				$property['identity'] = true;
			}
			$propertyDescriptions[$propertyName] = $property;

			if (TypeHandling::isSimpleType($propertyType)) {
				continue;
			}

			$propertyClassSchema = $this->reflectionService->getClassSchema($propertyType);
			if ($propertyClassSchema === null) {
				continue;
			}
			if ($propertyClassSchema->getModelType() === ClassSchema::MODELTYPE_ENTITY &&
				(isset($visitedTypes[$propertyType]) || $propertyClassSchema->isAggregateRoot())) {
				$propertyDescriptions[$propertyName]['schema'] = $propertyType;
				continue;
			}
			$propertyDescriptions[$propertyName]['schema'] = array();
			$visitedTypes[$propertyType] = true;
			$this->iterateAggregateBoundaryPropertiesRecursively($propertyClassSchema, $recursionDepth, $visitedTypes, $propertyDescriptions[$propertyName]['schema']);
			unset($visitedTypes[$propertyType]);
		}
	}

	/**
	 * Compile a class schema up to the Aggregate boundaries.
	 * This will traverse objects deeply and create a cyclic schema for non-aggregates.
	 *
	 * @param string $className The name of the class to get the configuration for from the class schema
	 * @return array The class schema for the whole Aggregate
	 */
	public function reflectAggregate($className)
	{
		$classSchema = $this->reflectionService->getClassSchema($className);
		if ($classSchema === null) {
			return array();
		}

		$propertyDescriptions = array();
		$visitedTypes = array($className => true);
		$this->iterateAggregateBoundaryPropertiesRecursively($classSchema, 0, $visitedTypes, $propertyDescriptions);
		return $propertyDescriptions;
	}
} 