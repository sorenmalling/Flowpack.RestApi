<?php
namespace Flowpack\RestApi\Controller;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Flowpack\RestApi\Domain\Repository\ResourceRepository;

use Flowpack\RestApi\Utility\AggregateReflectionHelper;
use Flowpack\RestApi\Utility\ResourceTypeHelper;
use Flowpack\RestApi\Utility\ViewConfigurationHelper;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Flow\Property\TypeConverter\PersistentObjectConverter;
use Neos\Flow\Reflection\MethodReflection;
use Neos\Utility\ObjectAccess;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Flow\Validation\Error;

/**
 * Base class for a RESTful API controller
 *
 * To use this, just extend this class and override $RESOURCE_ENTITY_CLASS and optionally
 * $resourceEntityCursorProperty, $resourceEntityRenderConfiguration and, if necessary because your resource
 * entity has properties with conflicting name, any of
 * $EMBED_ARGUMENT_NAME, $RENDER_FIELDS_ARGUMENT_NAME, $SORTING_ARGUMENT_NAME, $LIMIT_ARGUMENT_NAME and/or $OFFSET_ARGUMENT_NAME
 *
 * This implementation is highly inspired by http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api
 *
 * @package Flowpack\RestApi\Controller
 */
abstract class AbstractRestController extends \Neos\Flow\Mvc\Controller\ActionController
{

	/**
	 * Argument name for a comma separated list of subentities to be embedded in the output.
	 * This should only be changed if your entity contains a property with this name.
	 * See http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#autoloading
	 *
	 * @var string
	 */
	protected static $EMBED_ARGUMENT_NAME = 'embed';

	/**
	 * Argument name for a comma separated list of fields to be output. This can only reduce the number of fields that
	 * will be returned, but can not force the API to return fields that are not configured via $resourceEntityRenderConfiguration.
	 * This should only be changed if your entity contains a property with this name.
	 * See http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#limiting-fields
	 *
	 * @var string
	 */
	protected static $RENDER_FIELDS_ARGUMENT_NAME = 'fields';

	/**
	 * Argument name for a comma separated list of fields to be searched within in the search action.
	 * This should only be changed if your entity contains a property with this name.
	 * See http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#limiting-fields
	 *
	 * @var string
	 */
	protected static $SEARCH_FIELDS_ARGUMENT_NAME = 'search';

	/**
	 * Argument name for a comma separated list of fields to sort by, optionally prefixed with a '-' sign for descending order.
	 * This is only used in the filterAction, and should only be changed if your entity contains a property with this name.
	 * See http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#advanced-queries - Sorting
	 *
	 * @var string
	 */
	protected static $SORTING_ARGUMENT_NAME = 'sort';

	/**
	 * Argument name for the maximum number of entities to return.
	 * This is only used in the filterAction, and should only be changed if your entity contains a property with this name.
	 *
	 * @var string
	 */
	protected static $LIMIT_ARGUMENT_NAME = 'limit';

	/**
	 * Argument name for the numeric offset to start returning entities from.
	 * This is only used in the filterAction, and should only be changed if your entity contains a property with this name.
	 *
	 * @var string
	 */
	protected static $OFFSET_ARGUMENT_NAME = 'offset';

	/**
	 * @var string
	 */
	protected $defaultViewObjectName = JsonView::class;

	/**
	 * @var array
	 */
	protected $supportedMediaTypes = ['application/json'];

	protected static $RESOURCE_ARGUMENT_NAME = 'resource';

	// This will be automatically inflected from $RESOURCE_ARGUMENT_NAME
	protected static $RESOURCES_ARGUMENT_NAME = 'resources';

	/**
	 * Override this in specific resource controllers
	 *
	 * @var string
	 */
	protected static $RESOURCE_ENTITY_CLASS;

	/**
	 * Override this in specific resource controllers to change the alias for the identifier when rendering and querying.
	 *
	 * @var string
	 */
	protected static $RESOURCE_ENTITY_IDENTIFIER = 'uuid';

	/**
	 * The name of the property that is used for cursor pagination.
	 *
	 * Override this in specific resource controllers to set the property name of the pagination cursor.
	 * This should be a property of a unique steadily increasing value, like an autoincrement or a timestamp.
	 * Also, the table of the entity should contain an index for this property.
	 *
	 * @var string
	 */
	protected $resourceEntityCursorProperty = '__identity';

	/**
	 * Array of property names to render in output by default.
	 *
	 * Override this in specific resource controllers to define the properties that are rendered.
	 * See JsonView::$configuration for more information. If not set, all gettable properties will be output by default.
	 *
	 * @var array
	 */
	protected $resourceEntityRenderConfiguration;

	/**
	 * Array of filter property names and values that should be applied to queries.
	 *
	 * @var array
	 */
	protected $resourceEntityDefaultFilter = [];

	/**
	 * @var ResourceRepository
	 */
	protected $repository;

	/**
	 * @var bool
	 * @Flow\InjectConfiguration(package="Flowpack.RestApi", path="useAbsoluteUris")
	 */
	protected $useAbsoluteUris = true;

	/**
	 * @var bool
	 * @Flow\InjectConfiguration(package="Flowpack.RestApi",path="normalizeResourceTypes")
	 */
	protected $normalizeResourceTypes = false;

	/**
	 * Readonly configuration for the resource entity for this request.
	 * @var array
	 */
	protected $resourceEntityConfiguration;

	/**
	 * @return string
	 */
	public static function resourceType()
	{
		return static::$RESOURCE_ENTITY_CLASS;
	}

	/**
	 * We don't want any flash messages or redirects to referrer for the REST Api.
	 *
	 * @return string
	 */
	protected function errorAction()
	{
		$this->handleTargetNotFoundError();

		return $this->getFlattenedValidationErrorMessage();
	}

	/**
	 * Override this method in order to transform an error object (e.g. translate messages) before returning as JSON.
	 *
	 * @param array $errorObject
	 * @return array
	 */
	protected function transformErrorObject($errorObject)
	{
		return $errorObject;
	}

	/**
	 * Returns a string containing all validation errors separated by PHP_EOL.
	 *
	 * @return string
	 */
	protected function getFlattenedValidationErrorMessage()
	{
		$outputMessage = 'Validation failed while trying to call ' . get_class($this) . '->' . $this->actionMethodName . '().' . PHP_EOL;
		$errorObject = [
			'message' => $outputMessage,
		];
		$logMessage = $outputMessage;

		foreach ($this->arguments->getValidationResults()->getFlattenedErrors() as $propertyPath => $errors) {
			/* @var $error Error */
			foreach ($errors as $error) {
				$logMessage .= 'Error for ' . $propertyPath . ':  ' . $error->render() . PHP_EOL;
				$errorObject['errors'][] = ['code' => $error->getCode(), 'field' => $propertyPath, 'message' => $error->render()];
			}
		}
		$this->logger->error($logMessage, $errorObject);

		$errorObject = $this->transformErrorObject($errorObject);

		$this->response->setStatusCode(422);
		$this->response->setContentType('application/json');

		return json_encode($errorObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Returns a map of action method names and their parameters.
	 *
	 * TODO: This is highly hacky code to make action argument validation work with the dynamic resource argument name.
	 *
	 * @param ObjectManagerInterface $objectManager
	 * @return array Array of method parameters by action name
	 * @Flow\CompileStatic
	 */
	public static function getActionMethodParameters($objectManager)
	{
		$result = parent::getActionMethodParameters($objectManager);
		$result['showAction'][static::$RESOURCE_ARGUMENT_NAME] =
		$result['createAction'][static::$RESOURCE_ARGUMENT_NAME] =
		$result['updateAction'][static::$RESOURCE_ARGUMENT_NAME] =
		$result['removeAction'][static::$RESOURCE_ARGUMENT_NAME] = [
			'position' => 0,
			'optional' => false,
			'type' => static::$RESOURCE_ENTITY_CLASS,
			'class' => static::$RESOURCE_ENTITY_CLASS,
			'array' => false,
			'byReference' => false,
			'allowsNull' => false,
			'defaultValue' => null
		];
		$result['createAction'][static::$RESOURCE_ARGUMENT_NAME]['optional'] = true;

		$inflector = InflectorFactory::create()->build();
		static::$RESOURCES_ARGUMENT_NAME = $inflector->pluralize(static::$RESOURCE_ARGUMENT_NAME);
		$result['createAction'][static::$RESOURCES_ARGUMENT_NAME] = [
			'position' => 1,
			'optional' => true,
			'type' => 'array<' . static::$RESOURCE_ENTITY_CLASS . '>',
			'class' => null,
			'array' => true,
			'byReference' => false,
			'allowsNull' => false,
			'defaultValue' => []
		];

		$result['searchAction'][static::$SORTING_ARGUMENT_NAME] =
		$result['filterAction'][static::$SORTING_ARGUMENT_NAME] = [
			'position' => 0,
			'optional' => true,
			'type' => 'string',
			'class' => null,
			'array' => false,
			'byReference' => false,
			'allowsNull' => true,
			'defaultValue' => null
		];
		$result['searchAction'][static::$LIMIT_ARGUMENT_NAME] =
		$result['searchAction'][static::$OFFSET_ARGUMENT_NAME] =
		$result['filterAction'][static::$LIMIT_ARGUMENT_NAME] =
		$result['filterAction'][static::$OFFSET_ARGUMENT_NAME] = [
			'position' => 0,
			'optional' => true,
			'type' => 'integer',
			'class' => null,
			'array' => false,
			'byReference' => false,
			'allowsNull' => true,
			'defaultValue' => null
		];

		$result['showAction'][static::$RENDER_FIELDS_ARGUMENT_NAME] =
		$result['listAction'][static::$RENDER_FIELDS_ARGUMENT_NAME] =
		$result['searchAction'][static::$RENDER_FIELDS_ARGUMENT_NAME] =
		$result['filterAction'][static::$RENDER_FIELDS_ARGUMENT_NAME] = [
			'position' => 0,
			'optional' => true,
			'type' => 'string',
			'class' => null,
			'array' => false,
			'byReference' => false,
			'allowsNull' => true,
			'defaultValue' => null
		];

		$result['showAction'][static::$EMBED_ARGUMENT_NAME] =
		$result['listAction'][static::$EMBED_ARGUMENT_NAME] =
		$result['searchAction'][static::$EMBED_ARGUMENT_NAME] =
		$result['filterAction'][static::$EMBED_ARGUMENT_NAME] = [
			'position' => 0,
			'optional' => true,
			'type' => 'string',
			'class' => null,
			'array' => false,
			'byReference' => false,
			'allowsNull' => true,
			'defaultValue' => null
		];
		return $result;
	}

	protected function initializeAction()
	{
		$this->repository = new ResourceRepository(static::$RESOURCE_ENTITY_CLASS);
		$this->response->setHttpHeader('Access-Control-Allow-Origin', '*');
		$this->response->setHttpHeader('Content-Security-Policy', 'default-src \'none\'; frame-ancestors \'none\'');
	}

	/**
	 * @return string
	 * @deprecated Use the static member $RESOURCES_ARGUMENT_NAME instead
	 */
	public static function getResourcesArgumentName()
	{
		$inflector = InflectorFactory::create()->build();
		return $inflector->pluralize(static::$RESOURCE_ARGUMENT_NAME);
	}

	/**
	 * Return the resource entity that was submitted as argument to the current request.
	 * @return object The current requests resource entity if specified
	 */
	protected function getResourceEntity()
	{
		return $this->arguments->getArgument(static::$RESOURCE_ARGUMENT_NAME)->getValue();
	}

	/**
	 * Return the array of resource entities that was submitted as argument to the current request.
	 * @return array The current requests array of resource entities if specified
	 */
	protected function getResources()
	{
		return $this->arguments->getArgument(static::$RESOURCES_ARGUMENT_NAME)->getValue();
	}

	/**
	 * @param \Neos\Flow\Mvc\View\ViewInterface $view
	 */
	protected function initializeView(\Neos\Flow\Mvc\View\ViewInterface $view)
	{
		if (!($view instanceof JsonView)) {
			return;
		}

		$view->setOption('jsonEncodingOptions', JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		// Build default descend configuration based on aggregate boundaries
		$descendConfiguration = static::resourceEntityDescendConfiguration($this->objectManager);
		if ($this->request->hasArgument(static::$EMBED_ARGUMENT_NAME)) {
			/* @var $configurationHelper ViewConfigurationHelper */
			$configurationHelper = $this->objectManager->get(ViewConfigurationHelper::class);
			$embedPaths = $this->request->getArgument(static::$EMBED_ARGUMENT_NAME);
			// Build descend configuration based on submitted embed argument
			$embedDescendConfiguration = $configurationHelper->convertPropertyPathsToViewConfiguration($embedPaths);
			// And merge it into the default descend configuration
			$descendConfiguration = array_merge_recursive($descendConfiguration, $embedDescendConfiguration);
		}
		$configuration = [
			'_descend'                    => $descendConfiguration,
			'_exposeObjectIdentifier'     => true,
			'_exposedObjectIdentifierKey' => static::$RESOURCE_ENTITY_IDENTIFIER,
		];
		if (is_array($this->resourceEntityRenderConfiguration)) {
			$configuration['_only'] = $this->resourceEntityRenderConfiguration;
		}

		if ($this->request->hasArgument(static::$RENDER_FIELDS_ARGUMENT_NAME)) {
			$fields = explode(',', $this->request->getArgument(static::$RENDER_FIELDS_ARGUMENT_NAME));
			$only = array_filter($fields, static function($field) {
				return $field[0] !== '!';
			});
			if ($only !== []) {
				if (isset($configuration['_only'])) {
					$configuration['_only'] = array_intersect($configuration['_only'], $only);
				} else {
					$configuration['_only'] = $only;
				}
			}

			$exclude = array_filter($fields, static function($field) {
				return $field[0] === '!';
			});
			if ($exclude !== []) {
				$configuration['_exclude'] = array_map(static function($field) {
					return substr($field, 1);
				}, $exclude);
			}
		}

		$this->resourceEntityConfiguration = $configuration;
		$view->setConfiguration([
			'value'  => $configuration,
			'values' => ['_descendAll' => $configuration]
		]);
	}

	/**
	 * Call this method if you want to return a collection of entities in your action.
	 * In that case, you should assign the collection to the view variable 'values' instead of 'value'.
	 * @param string $variableName Optionally override the variable name that should be used.
	 */
	protected function setCollectionReturnValue($variableName = 'values')
	{
		if ($this->view instanceof JsonView) {
			$this->view->setConfiguration([$variableName => ['_descendAll' => $this->resourceEntityConfiguration]]);
			$this->view->setVariablesToRender([$variableName]);
		}
	}

	/**
	 * @param ObjectManagerInterface $objectManager
	 * @return array
	 * @Flow\CompileStatic
	 */
	public static function resourceEntityProperties(ObjectManagerInterface $objectManager)
	{
		/* @var $reflectionService ReflectionService */
		$reflectionService = $objectManager->get(ReflectionService::class);

		return $reflectionService->getClassPropertyNames(static::$RESOURCE_ENTITY_CLASS);
	}

	/**
	 * @param ObjectManagerInterface $objectManager
	 * @return array
	 * @Flow\CompileStatic
	 */
	public static function resourceEntityPropertiesDescription(ObjectManagerInterface $objectManager)
	{
		/* @var $aggregateReflectionHelper AggregateReflectionHelper */
		$aggregateReflectionHelper = $objectManager->get(AggregateReflectionHelper::class);

		return $aggregateReflectionHelper->withIdentifierName(static::$RESOURCE_ENTITY_IDENTIFIER)
						->reflectAggregate(static::$RESOURCE_ENTITY_CLASS);
	}

	/**
	 * @param ObjectManagerInterface $objectManager
	 * @return array
	 * @Flow\CompileStatic
	 */
	public static function resourceEntityDescendConfiguration(ObjectManagerInterface $objectManager)
	{
		/* @var $configurationHelper ViewConfigurationHelper */
		$configurationHelper = $objectManager->get(ViewConfigurationHelper::class);

		return $configurationHelper->withIdentifierName(static::$RESOURCE_ENTITY_IDENTIFIER)
						->convertAggregateSchemaToViewConfiguration(static::resourceEntityPropertiesDescription($objectManager));
	}

	/**
	 * This action is called for CORS preflight requests (OPTIONS request method).
	 *
	 * @return string An empty body
	 */
	public function optionsAction()
	{
		return '';
	}

	protected function initializeOptionsAction()
	{
		$this->response->setHttpHeader('Access-Control-Allow-Methods', 'HEAD, GET, POST, PUT, PATCH, DELETE, OPTIONS');
		$this->response->setHttpHeader('Access-Control-Allow-Headers', $this->request->getHttpRequest()->getHeader('Access-Control-Request-Headers'));
		$this->response->setHttpHeader('Access-Control-Max-Age', 3600);
	}

	/**
	 * Get a description of the resource entity and it's properties.
	 * @return void An array of property names accessible for this resource
	 */
	public function describeAction()
	{
		$resourceProperties = static::resourceEntityPropertiesDescription($this->objectManager);
		if ($this->normalizeResourceTypes) {
			$schemas[] = &$resourceProperties;
			while (count($schemas) > 0) {
				foreach ($schemas[0] as &$property) {
					$property['type'] = ResourceTypeHelper::normalize($property['type']);
					if (isset($property['elementType']) && $property['elementType'] !== null) {
						$property['elementType'] = ResourceTypeHelper::normalize($property['elementType']);
					}
					if (isset($property['schema'])) {
						if (is_array($property['schema'])) {
							$schemas[] = &$property['schema'];
						} else {
							$property['schema'] = ResourceTypeHelper::normalize($property['schema']);
						}
					}
				}
				array_shift($schemas);
			}
		}
		//$resourceProperties = array_diff($resourceProperties, array('Persistence_Object_Identifier'));
		$this->view->assign('description', $resourceProperties);
		if ($this->view instanceof JsonView) {
			$this->view->setVariablesToRender(['description']);
		}
	}

	/**
	 * Get a description of all action entrypoints to this resource.
	 * @return void An array of resource action URIs and their description
	 */
	public function discoverAction()
	{
		$resourceEntryPoints = [];
		$actionMethodNames = static::getPublicActionMethods($this->objectManager);
		$actionMethodParameters = static::getActionMethodParameters($this->objectManager);
		foreach ($actionMethodNames as $actionMethodName => $isPublic) {
			if (in_array($actionMethodName, ['discoverAction', 'optionsAction'])) {
				continue;
			}
			$actionName = str_replace('Action', '', $actionMethodName);

			$arguments = [];
			if (isset($actionMethodParameters[$actionMethodName][static::$RESOURCE_ARGUMENT_NAME])) {
				if ($actionMethodParameters[$actionMethodName][static::$RESOURCE_ARGUMENT_NAME]['optional'] === false) {
					$arguments = [static::$RESOURCE_ARGUMENT_NAME => ['__identity' => '{identifier}']];
				} else {
					$arguments = [static::$RESOURCE_ARGUMENT_NAME => ['__identity' => '({identifier})']];
				}
			}
			// Map CRUD actions back to generic URI action
			$uriActionName = in_array($actionName, ['show', 'list', 'create', 'update', 'remove']) ? 'index' : $actionName;
			$actionUri = $this->uriBuilder->setCreateAbsoluteUri($this->useAbsoluteUris)->setFormat($this->request->getFormat())->uriFor($uriActionName, $arguments);
			$actionReflection = new MethodReflection($this, $actionMethodName);

			$parameterDescriptions = [];
			if ($actionReflection->isTaggedWith('param')) {
				foreach ($actionReflection->getTagValues('param') as $parameterDescription) {
					$descriptionParts = preg_split('/\s/', $parameterDescription, 3);
					if (isset($descriptionParts[2])) {
						$parameterName = ltrim($descriptionParts[1], '$');
						$parameterDescriptions[$parameterName] = $descriptionParts[2];
					}
				}
			}

			$parameters = array_map(static function($parameterInfo) {
				return [
					'required' => !$parameterInfo['optional'],
					'type' => $this->normalizeResourceTypes ? ResourceTypeHelper::normalize($parameterInfo['type']) : $parameterInfo['type'],
					'default' => $parameterInfo['defaultValue']
				];
			}, $actionMethodParameters[$actionMethodName]);
			// PHPSadness.com: array_walk operates in place
			array_walk($parameters, static function(&$parameterInfo, $parameterName) use ($parameterDescriptions) {
				$parameterInfo['description'] = $parameterDescriptions[$parameterName] ?? '';
			});

			$return = '';
			if ($actionReflection->isTaggedWith('return')) {
				$returnTags = $actionReflection->getTagValues('return');
				$returnParts = preg_split('/\s/', reset($returnTags), 2);
				$return = $returnParts[1] ?? '';
			}
			$resourceEntryPoints[$actionName] = [
				'uri' => rawurldecode($actionUri),
				'parameters' => $parameters,
				'description' => $actionReflection->getDescription(),
				'return' => $return,
			];
		}
		$this->view->assign('description', $resourceEntryPoints);
		if ($this->view instanceof JsonView) {
			$this->view->setVariablesToRender(['description']);
		}
	}

	/**
	 * Get one single resource entity
	 *
	 * Examples:
	 *   GET /api/{resource}/{identifier}/
	 *
	 * @param string $fields A comma separated list of resource properties to include in the results
	 * @param string $embed A comma separated list of related resource properties to embed into the results
	 * @return void The resource entity
	 * @Flow\IgnoreValidation("$resource")
	 */
	public function showAction()
	{
		if (!$this->request->hasArgument(static::$RESOURCE_ARGUMENT_NAME)) {
			$this->throwStatus(400, 'No resource specified', '');
		}
		$this->view->assign('value', $this->getResourceEntity());
	}

	/**
	 * Check if the given propertyPath is part of the persistence resource schema.
	 *
	 * @param string $propertyPath The dot-notation property path to check
	 * @param array $resourceSchema The resource schema to to check the property path against
	 * @param bool $onlySearchable Set to true if only searchable (string) leafs should be returned
	 * @return bool
	 */
	protected function isInPersistenceSchema($propertyPath, array $resourceSchema, $onlySearchable = false)
	{
		if ($propertyPath === '') return false;

		$propertyPathParts = explode('.', $propertyPath);
		foreach ($propertyPathParts as $pathPart) {
			if ($pathPart === '__identity') {
				return true;
			}
			if (!isset($resourceSchema[$pathPart]) || $resourceSchema[$pathPart]['transient'] === true) {
				return false;
			}
			if (isset($resourceSchema[$pathPart]['schema'])) {
				$resourceSchema = &$resourceSchema[$pathPart]['schema'];
			}
		}
		$lastPart = end($propertyPathParts);
		if (!isset($resourceSchema[$lastPart])) {
			return false;
		}
		if ($onlySearchable && $resourceSchema[$lastPart]['type'] !== 'string') {
			return false;
		}
		return ($resourceSchema[$lastPart]['multiValued'] === false);
	}

	/**
	 * @param array $resourceSchema The resource aggregate schema
	 * @param bool $onlySearchable Set to true if only searchable (string) leafs should be returned
	 * @return array An array of property paths inside this resources persistence schema
	 */
	protected function getPersistencePropertyPaths(array $resourceSchema, $onlySearchable = false)
	{
		$propertyPaths = [];
		foreach ($resourceSchema as $propertyName => $property) {
			if ($property['transient']) continue;
			/*if ($property['multiValued']) {
				$propertyName .= '.*';
			}*/
			if (isset($property['schema']) && is_array($property['schema'])) {
				$subPropertyPaths = $this->getPersistencePropertyPaths($property['schema'], $onlySearchable);
				foreach ($subPropertyPaths as $subPropertyPath) {
					$propertyPaths[] = $propertyName . '.' . $subPropertyPath;
				}
			} elseif ($property['multiValued'] === false) {
				if ($onlySearchable && ($property['type'] !== 'string' || $property['identity'] === true)) {
					continue;
				}
				$propertyPaths[] = $propertyName;
			}
		}
		return $propertyPaths;
	}

	/**
	 * Get a list of properties and the values to filter the results by, depending on the given action arguments.
	 * @param array $resourceProperties The allowed filter properties to use
	 * @return array
	 */
	protected function getPropertyFilters(array $resourceProperties)
	{
		$filters = array_merge([], $this->resourceEntityDefaultFilter);
		// Note: This work-around is necessary, because PHP converts all dots in query parameters to underscores.
		// Since we want to use dot-notation for filtering by subproperties, we need to parse the query string ourself.
		// See http://ca.php.net/variables.external#example-123
		$query = $this->request->getHttpRequest()->getUri()->getQuery();
		if ($query == '') return $filters;

		$arguments = explode('&', $query);
		foreach ($arguments as $argumentString) {
			list($argumentName, $argumentValue) = explode('=', urldecode($argumentString), 2);

			if ($argumentName === static::$RESOURCE_ENTITY_IDENTIFIER) {
				$argumentName = '__identity';
			} elseif ($argumentName !== '__identity') {
				$argumentName = str_replace('_', '.', $argumentName);		// TODO: Check if this is really necessary (see bedf1b210814e1e6815dca4602f05cd23830069a)
				$argumentName = str_replace(['.' . static::$RESOURCE_ENTITY_IDENTIFIER], '.__identity', $argumentName);
			}

			if ($this->isInPersistenceSchema($argumentName, $resourceProperties)) {
				$filters[$argumentName] = $argumentValue;
			}
		}
		return $filters;
	}

	/**
	 * Get a list of properties and the values to filter the results by, depending on the given action arguments.
	 * @param array $resourceProperties The allowed search properties to use
	 * @return array
	 */
	protected function getPropertySearchFields(array $resourceProperties)
	{
		$filters = [];
		// Note: This work-around is necessary, because PHP converts all dots in query parameters to underscores.
		// Since we want to use dot-notation for filtering by subproperties, we need to parse the query string ourself.
		// See http://ca.php.net/variables.external#example-123
		$query = $this->request->getHttpRequest()->getUri()->getQuery();
		if ($query == '') return $filters;

		if (preg_match('/' . static::$SEARCH_FIELDS_ARGUMENT_NAME . '=([^&]*)/', $query, $matches) > 0) {
			$search = explode(',', str_replace('.' . static::$RESOURCE_ENTITY_IDENTIFIER, '.__identity', urldecode($matches[1])));
			$searchProperties = [];
			foreach ($search as $searchProperty) {
				if ($this->isInPersistenceSchema($searchProperty, $resourceProperties, true)) {
					$searchProperties[] = $searchProperty;
				}
			}
		} else {
			$searchProperties = $this->getPersistencePropertyPaths($resourceProperties, true);
		}

		return $searchProperties;
	}

	/**
	 * Get a list of properties and the directions to sort the results by, depending on the given action arguments.
	 * @param array $resourceProperties The allowed sorting properties to use
	 * @return array
	 */
	protected function getPropertyOrderings(array $resourceProperties)
	{
		$orderings = [];
		if ($this->request->hasArgument(static::$SORTING_ARGUMENT_NAME)) {
			$sortColumns = explode(',', $this->request->getArgument(static::$SORTING_ARGUMENT_NAME));
			foreach ($sortColumns as $columnName) {
				$order = QueryInterface::ORDER_ASCENDING;
				if ($columnName[0] === '-') {
					$order = QueryInterface::ORDER_DESCENDING;
					$columnName = substr($columnName, 1);
				}
				if ($this->isInPersistenceSchema($columnName, $resourceProperties)) {
					$orderings[$columnName] = $order;
				}
			}
		}
		return $orderings;
	}

	/**
	 * Get a possibly paginated list of resource entities.
	 *
	 * Examples:
	 *   GET /api/{resource}/?limit=50&last={lastSeenCursor}
	 *   	-> returns up to 50 resources starting from the entity with the cursor property {lastSeenCursor} ordered by the cursor property
	 *   GET /api/{resource}/?limit=20&last={lastSeenCursor}&lastId[]={identifier}&dir=DESC
	 *   	-> returns up to 20 resources before the entity with identity {identifier} ordered by the cursor property
	 *
	 * This way of pagination is especially useful for showing an indefinite and probably large amount of resources,
	 * that can for example be scrolled through like comments or pictures in a gallery. It will also provide a stable
	 * pagination (no duplications/jumps), even when items are inserted or removed in between pagination requests.
	 *
	 * This method has linear performance characteristics on the number of shown items only, rather than the offset to
	 * start returning items from as with offset pagination (@see filterAction).
	 *
	 * @TODO: See Facebooks Graph-API for a good implementation example https://developers.facebook.com/docs/graph-api/using-graph-api#paging
	 *
	 * @param string $cursor The property to use as pagination cursor. Defaults to $resourceEntityCursorProperty. The results will be ordered by this property.
	 * @param string $last The cursor value of the last visible item.
	 * @param array $lastId The identity of the last visible item. Only needed if the cursor property is not unique.
	 * @param string $dir The direction to paginate, ASC for forward, DESC for backward from $last
	 * @param integer $limit The number of items to load.
	 * @param string $fields A comma separated list of resource properties to include in the results
	 * @param string $embed A comma separated list of related resource properties to embed into the results
	 * @return void An array of resource entities matching the given cursor pagination
	 */
	public function listAction($cursor = null, $last = null, $lastId = null, $dir = 'ASC', $limit = null)
	{
		$resourceProperties = static::resourceEntityPropertiesDescription($this->objectManager);
		$filters = $this->getPropertyFilters($resourceProperties);
		$linkHeaders = [];

		$cursorProperty = $cursor !== null ? $cursor : $this->resourceEntityCursorProperty;
		if ($cursorProperty === static::$RESOURCE_ENTITY_IDENTIFIER) {
			$cursorProperty = '__identity';
		}
		$values = $this->repository->findByCursorPagination($cursorProperty, $limit, $dir, $last, $lastId, $filters);

		// Build pagination links and return them as "Link" Header
		$lastResource = end($values);
		$firstResource = reset($values);
		if ($lastResource && ($limit === null || count($values) >= $limit)) {
			$lastIdentity = $this->persistenceManager->getIdentifierByObject($lastResource);
			if ($cursorProperty === '__identity') {
				$lastCursor = is_array($lastIdentity) ? reset($lastIdentity) : $lastIdentity;
			} else {
				$lastCursor = ObjectAccess::getProperty($lastResource, $cursorProperty);
				if ($lastCursor instanceof \DateTime) {
					$lastCursor = $lastCursor->format(\DateTime::ATOM);
				}
			}
			$nextPageUri = $this->uriBuilder->setCreateAbsoluteUri(true)->uriFor('index', ['cursor' => $cursor, 'limit' => $limit, 'dir' => $dir === 'ASC' ? null : $dir, 'last' => $lastCursor, 'lastId' => $lastId !== null ? $lastIdentity : null]);
			$linkHeaders[] = sprintf('<%s>; rel="next"', $nextPageUri);
		}
		if ($firstResource) {
			$lastIdentity = $this->persistenceManager->getIdentifierByObject($firstResource);
			if ($cursorProperty === '__identity') {
				$lastCursor = is_array($lastIdentity) ? reset($lastIdentity) : $lastIdentity;
			} else {
				$lastCursor = ObjectAccess::getProperty($lastResource, $cursorProperty);
				if ($lastCursor instanceof \DateTime) {
					$lastCursor = $lastCursor->format(\DateTime::ATOM);
				}
			}
			$prevPageUri = $this->uriBuilder->setCreateAbsoluteUri(true)->uriFor('index', ['cursor' => $cursor, 'limit' => $limit, 'dir' => $dir === 'ASC' ? 'DESC' : null, 'last' => $lastCursor, 'lastId' => $lastId !== null ? $lastIdentity : null]);
			$linkHeaders[] = sprintf('<%s>; rel="prev"', $prevPageUri);
		}

		$this->response->setHttpHeader('Link', implode(',', $linkHeaders));

		$this->view->assign('values', $values);
		$this->setCollectionReturnValue();
	}

	/**
	 * Build a type converter configuration to allow creation of subentities given a descend view configuration for the aggregate.
	 *
	 * @param PropertyMappingConfiguration $configuration
	 * @param array $descendConfiguration
	 */
	protected function configureTypeConverterByAggregateBoundary(PropertyMappingConfiguration $configuration, array $descendConfiguration)
	{
		foreach ($descendConfiguration as $propertyName => $subConfiguration)
		{
			if ($propertyName === '_descendAll') {
				$propertyName = PropertyMappingConfiguration::PROPERTY_PATH_PLACEHOLDER;
			}
			if (isset($subConfiguration['_descend'])) {
				$configuration->forProperty($propertyName)->allowAllProperties();
				$configuration->forProperty($propertyName)->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
				if (isset($subConfiguration['_exposeObjectIdentifier'])) {
					$configuration->forProperty($propertyName)->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_IDENTITY_CREATION_ALLOWED, true);
				}
				$subConfiguration = $subConfiguration['_descend'];
			}
			if (is_array($subConfiguration)) {
				$this->configureTypeConverterByAggregateBoundary($configuration->forProperty($propertyName), $subConfiguration);
			}
		}
	}

	protected function initializeCreateAction()
	{
		if (!$this->request->hasArgument(static::$RESOURCE_ARGUMENT_NAME) && !$this->request->hasArgument(static::$RESOURCES_ARGUMENT_NAME)) {
			$this->throwStatus(400, 'No resource specified', '');
		}

		/* @var $configuration PropertyMappingConfiguration */
		if ($this->request->hasArgument(static::$RESOURCE_ARGUMENT_NAME)) {
			$configuration = $this->arguments->getArgument(static::$RESOURCE_ARGUMENT_NAME)->getPropertyMappingConfiguration();
			$configuration->allowAllProperties();
			$configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
			$configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_IDENTITY_CREATION_ALLOWED, true);

			$descendConfiguration = static::resourceEntityDescendConfiguration($this->objectManager);
			$this->configureTypeConverterByAggregateBoundary($configuration, $descendConfiguration);
		} else {
			// Configuration for resources array
			$configuration = $this->arguments->getArgument(static::$RESOURCES_ARGUMENT_NAME)->getPropertyMappingConfiguration();
			$configuration->allowAllProperties();
			$configuration = $configuration->forProperty('*')
				->allowAllProperties()
				->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_TARGET_TYPE, static::$RESOURCE_ENTITY_CLASS)
				->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true)
				->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_IDENTITY_CREATION_ALLOWED, true);

			$descendConfiguration = static::resourceEntityDescendConfiguration($this->objectManager);
			$this->configureTypeConverterByAggregateBoundary($configuration, $descendConfiguration);
		}
	}

	/**
	 * Create a new resource entity. This will return the created entity including the identity.
	 *
	 * The identifier can be supplied with the request:
	 *   POST /api/{resource}/{identifier}/ resource[property1]=value1&resource[property2]=value2
	 *
	 * This allows for fully idempotent create requests and fits well with CQRS designs, where the client is responsible for creating aggregate identifiers
	 *
	 * @return null|string The created entity together with a Location header pointing to the new resource location
	 */
	public function createAction()
	{
		$resource = $this->getResourceEntity();
		if ($resource === null) {
			$resources = $this->getResources();
			foreach ($resources as $resource) {
				if (!$this->persistenceManager->isNewObject($resource)) {
					$this->response->setStatusCode(409);
					$this->response->setHttpHeader('X-Resource-Identifier', $this->persistenceManager->getIdentifierByObject($resource));
					return '';
				}
				$this->repository->add($resource);
			}
			$this->response->setStatusCode(201);
			$resourceUri = $this->uriBuilder->reset()
				->setFormat($this->request->getFormat())
				->setCreateAbsoluteUri(true)
				->uriFor('index');
			$this->response->setHttpHeader('Location', $resourceUri);
			$this->view->assign('values', $resources);
			$this->setCollectionReturnValue();
		} else {
			if (!$this->persistenceManager->isNewObject($resource)) {
				$this->response->setStatusCode(409);

				return '';
			}
			$this->repository->add($resource);
			$this->response->setHttpHeader('X-Resource-Identifier', $this->persistenceManager->getIdentifierByObject($resource));
			$this->response->setStatusCode(201);
			$resourceUri = $this->uriBuilder->reset()
				->setFormat($this->request->getFormat())
				->setCreateAbsoluteUri(true)
				->uriFor('index', ['resource' => $resource]);
			$this->response->setHttpHeader('Location', $resourceUri);
			$this->view->assign('value', $resource);
		}
		return null;
	}

	protected function initializeUpdateAction()
	{
		if (!$this->request->hasArgument(static::$RESOURCE_ARGUMENT_NAME)) {
			$this->throwStatus(400, 'No resource specified', '');
		}
		/* @var $configuration PropertyMappingConfiguration */
		$configuration = $this->arguments->getArgument(static::$RESOURCE_ARGUMENT_NAME)->getPropertyMappingConfiguration();
		$configuration->allowAllProperties();
		$configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_MODIFICATION_ALLOWED, true);
	}

	/**
	 * Update an existing resource entity.
	 *
	 * Examples:
	 *   PUT|PATCH /api/{resource}/{identifier}/ resource[property1]=value1&resource[property2]=value2
	 *
	 * @return string An empty body
	 */
	public function updateAction()
	{
		$resource = $this->getResourceEntity();
		$this->repository->update($resource);
		return '';
	}

	/**
	 * Delete an existing resource entity.
	 *
	 * Examples:
	 *   DELETE /api/{resource}/{identifier}/
	 *
	 * @return string An empty body with status 204
	 */
	public function removeAction()
	{
		if (!$this->request->hasArgument(static::$RESOURCE_ARGUMENT_NAME)) {
			$this->throwStatus(400, 'No resource specified', '');
		}
		$resource = $this->getResourceEntity();
		$this->repository->remove($resource);
		$this->response->setStatusCode(204);
		return '';
	}

	/**
	 * Search entities by arbitrary search query with simplified boolean logic.
	 * TODO: Implement functionality using elastic search
	 *
	 * @param string $query A search query that will be tokenized and searched for. May prefix terms with "+" to denote logical AND or "-" to denote logical NOT
	 * @param string $sort A comma separated list of resource properties to sort by, possibly prefixed with "-" to denote descending order
	 * @param int $limit A number limiting the amount of resources returned at once
	 * @param int $offset A number describing how many resources to skip at the start from the results
	 * @param string $fields A comma separated list of resource properties to include in the results
	 * @param string $embed A comma separated list of related resource properties to embed into the results
	 * @return void An object containing the list of terms that got tokenized, the fields that were searched in and an array of resource entities matching the given search query
	 */
	public function searchAction($query)
	{
		$resourceProperties = static::resourceEntityPropertiesDescription($this->objectManager);
		$searchProperties = $this->getPropertySearchFields($resourceProperties);

		preg_match_all('/([-+]?"[^"]+"|[^\s]+)\s*/', $query, $matches);
		$searchTerms = [];
		foreach ($matches[1] as $queryToken) {
			$type = $queryToken[0] === '+' ? '+' : ($queryToken[0] === '-' ? '-' : '*');
			$queryToken = ltrim($queryToken, '+-');
			$searchTerms[$type][] = trim($queryToken, '"');
		}
		// strip duplicate terms
		$searchTerms = array_map('array_unique', $searchTerms);

		$orderings = $this->getPropertyOrderings($resourceProperties);

		$limit = null;
		$offset = null;
		if ($this->request->hasArgument(static::$LIMIT_ARGUMENT_NAME)) {
			$limit = $this->request->getArgument(static::$LIMIT_ARGUMENT_NAME);
		}
		if ($this->request->hasArgument(static::$OFFSET_ARGUMENT_NAME)) {
			$offset = $this->request->getArgument(static::$OFFSET_ARGUMENT_NAME);
		}

		$resources = $this->repository->findBySearch($searchTerms, $searchProperties, $orderings, $limit, $offset);

		$result = [
			'terms' => $searchTerms,
			'fields' => $searchProperties,
			'results' => $resources
		];
		$this->view->assign('result', $result);

		if ($this->view instanceof JsonView) {
			$this->view->setConfiguration(['result' => ['results' => ['_descendAll' => $this->resourceEntityConfiguration]]]);
			$this->view->setVariablesToRender(['result']);
		}
	}

	/**
	 * Find entities by property filters, possibly ordered by one or more properties.
	 * Results can optionally be paginated with limit and offset.
	 *
	 * Examples:
	 *   GET /api/{resource}/filter/?property1=value%&sort=-property2&limit=40&offset=120
	 *   	-> get 40 items that have property1 match "value%", sorted descending by property2 and starting at position 120
	 *
	 * Attention: This is the most flexible querying method, but also the worst regarding performance,
	 * especially if arguments are chosen poorly. Also, offset pagination has linear performance to the offset at which
	 * it starts returning items. See for example http://blog.novatec-gmbh.de/art-pagination-offset-vs-value-based-paging/
	 *
	 * @param string $sort A comma separated list of resource properties to sort by, possibly prefixed with "-" to denote descending order
	 * @param int $limit A number limiting the amount of resources returned at once
	 * @param int $offset A number describing how many resources to skip at the start from the results
	 * @param string $fields A comma separated list of resource properties to include in the results
	 * @param string $embed A comma separated list of related resource properties to embed into the results
	 * @return void An array of resource entities matching the given property filters and sorting
	 */
	public function filterAction()
	{
		$resourceProperties = static::resourceEntityPropertiesDescription($this->objectManager);
		$filters = $this->getPropertyFilters($resourceProperties);

		$orderings = $this->getPropertyOrderings($resourceProperties);

		$limit = null;
		$offset = null;
		if ($this->request->hasArgument(static::$LIMIT_ARGUMENT_NAME)) {
			$limit = $this->request->getArgument(static::$LIMIT_ARGUMENT_NAME);
		}
		if ($this->request->hasArgument(static::$OFFSET_ARGUMENT_NAME)) {
			$offset = $this->request->getArgument(static::$OFFSET_ARGUMENT_NAME);
		}

		$this->view->assign('values', $this->repository->findByFilter($filters, $orderings, $limit, $offset));
		$this->setCollectionReturnValue();
	}

	/**
	 * Maps requests to the actual CRUD actions depending on request method.
	 *
	 * @return string The action method name
	 * @throws \Neos\Flow\Mvc\Exception\NoSuchActionException if the action specified in the request object does not exist (and if there's no default action either).
	 */
	protected function resolveActionMethodName()
	{
		if ($this->request->getHttpRequest()->getMethod() === 'OPTIONS') {
			$this->request->setControllerActionName('options');
		}
		if ($this->request->getControllerActionName() === 'index') {
			$actionName = 'index';
			switch ($this->request->getHttpRequest()->getMethod()) {
				case 'HEAD':
				case 'GET':
					$actionName = ($this->request->hasArgument(static::$RESOURCE_ARGUMENT_NAME)) ? 'show' : 'list';
					break;
				case 'POST':
					$actionName = 'create';
					break;
				case 'PUT':
				case 'PATCH':
					$actionName = 'update';
					break;
				case 'DELETE':
					$actionName = 'remove';
					break;
			}
			$this->request->setControllerActionName($actionName);
		}

		return parent::resolveActionMethodName();
	}

}
