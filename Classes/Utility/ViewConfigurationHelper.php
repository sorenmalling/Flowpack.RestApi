<?php
namespace Flowpack\RestApi\Utility;

use Neos\Flow\Reflection\ClassSchema;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\TypeHandling;

use Neos\Flow\Annotations as Flow;

/**
 * A class for basic utility methods to create JSON view configurations
 *
 * @Flow\Scope("singleton")
 */
class ViewConfigurationHelper
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
	 * Converts a property path list into a JSON view configuration.
	 * Example:
	 *   "some.property,some.other" => array( 'some' => array( '_descend' => array( 'property' => array( '_descend' => array() ), 'other' => array( '_descend' => array() ) ) )
	 *
	 * @param string $pathsString A list of dot-notation property paths, separated by ","
	 * @return array The view configuration matching the given property paths to visit
	 */
	public function convertPropertyPathsToViewConfiguration($pathsString)
	{
		if ($pathsString[0] === '*') {
			throw new \Exception('Invalid path. Path may not start with wildcard.');
		}
		$descendConfiguration = array();
		$propertyPaths = explode(',', $pathsString);
		foreach ($propertyPaths as $descendPath) {
			$pathParts = explode('.', $descendPath);
			$currentPathConfiguration = &$descendConfiguration;
			foreach ($pathParts as $pathPart) {
				$descend = '_descend';
				if (isset($currentPathConfiguration['_descendAll']) || $pathPart === '*') {
					$descend = '_descendAll';
				}
				if (!isset($currentPathConfiguration[$descend])) {
					$currentPathConfiguration[$descend] = array();
				}
				if ($pathPart === '*') {
					$currentPathConfiguration = &$currentPathConfiguration[$descend];
					continue;
				}

				if (!isset($currentPathConfiguration[$descend][$pathPart])) {
					$currentPathConfiguration[$descend][$pathPart] = array();
				}
				$currentPathConfiguration = &$currentPathConfiguration[$descend][$pathPart];
			}
			$currentPathConfiguration['_descend'] = array();
		}

		return $descendConfiguration['_descend'];
	}

	/**
	 * @param array $classSchema Class schema of the current entity that is visited
	 * @return array $configuration Array that will hold the created view configuration
	 */
	protected function iterateAggregateSchemaRecursively(array $classSchema)
	{
		$configuration = array();
		foreach ($classSchema as $propertyName => $property) {
			$propertyConfiguration = &$configuration;
			$propertyType = $property['type'];

			if ($property['multiValued']) {
				$propertyType = $property['elementType'] ?: $propertyType;
				$propertyConfiguration[$propertyName] = array('_descendAll' => array());
				$propertyConfiguration = &$propertyConfiguration[$propertyName];
				$propertyName = '_descendAll';
			}

			if (TypeHandling::isSimpleType($propertyType)) {
				continue;
			}

			if (!isset($property['schema'])) {
				$propertyConfiguration[$propertyName]['_descend'] = array();
				continue;
			}

			$propertyConfiguration[$propertyName] = array(
				'_exposeObjectIdentifier'     => true,
				'_exposedObjectIdentifierKey' => $this->identifierName
			);

			if (!is_array($property['schema'])) {
				$propertyConfiguration[$propertyName]['_only'] = array();
				continue;
			}
			$propertyConfiguration[$propertyName]['_descend'] = $this->iterateAggregateSchemaRecursively($property['schema']);
		}
		return $configuration;
	}

	/**
	 * Convert class schema up to the aggregate boundaries to a JSON view configuration.
	 * This will traverse non-entity objects deeply and non aggregate roots once per class name.
	 * Aggregate roots and revisited entity classes are only referenced by identifier.
	 *
	 * @param array $aggregateSchema An Aggregate schema as it is provided by the AggregateReflectionHelper
	 * @return array The view configuration matching the given Aggregate schema
	 */
	public function convertAggregateSchemaToViewConfiguration(array $aggregateSchema)
	{
		return $this->iterateAggregateSchemaRecursively($aggregateSchema);
	}
}
