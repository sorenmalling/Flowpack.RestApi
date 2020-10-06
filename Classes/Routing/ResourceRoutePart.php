<?php
namespace Flowpack\RestApi\Routing;

use Flowpack\RestApi\Controller\AbstractRestController;
use Neos\Flow\Mvc\Routing\DynamicRoutePart;
use Neos\Flow\ObjectManagement\ObjectManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Reflection\ReflectionService;

class ResourceRoutePart extends DynamicRoutePart
{
	/**
	 * @Flow\Inject
	 * @var PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var ObjectManager
	 */
	protected $objectManager;

	protected $splitString = '.';

	/**
	 * @param ObjectManager $objectManager
	 * @Flow\CompileStatic
	 */
	public static function apiActionNames(ObjectManager $objectManager)
	{
		/* @var $reflectionService ReflectionService */
		$reflectionService = $objectManager->get(ReflectionService::class);
		$apiControllers = $reflectionService->getAllSubClassNamesForClass(AbstractRestController::class);
		$apiActionNames = array();
		foreach ($apiControllers as $controllerName) {
			$methodNames = get_class_methods($controllerName);
			foreach ($methodNames as $methodName) {
				if (strlen($methodName) > 6 && strpos($methodName, 'Action', strlen($methodName) - 6) !== false) {
					if ($reflectionService->isMethodPublic($controllerName, $methodName)) {
						$actionName = strtolower(substr($methodName, 0, -6));
						$apiActionNames[$actionName] = true;
					}
				}
			}
		}
		return $apiActionNames;
	}

	/**
	 * Split off possible format postfixes.
	 *
	 * @param string $routePath
	 * @return string
	 */
	protected function findValueToMatch($routePath)
	{
		$valueToMatch = parent::findValueToMatch($routePath);
		$splitStringPosition = strpos($valueToMatch, '.');
		if ($splitStringPosition !== false) {
			$valueToMatch = substr($valueToMatch, 0, $splitStringPosition);
		}

		return $valueToMatch;
	}

	/**
	 * Checks, whether given value can be matched.
	 * If the value is empty, FALSE is returned.
	 * Otherwise the ObjectPathMappingRepository is asked for a matching ObjectPathMapping.
	 * If that is found the identifier is stored in $this->value, otherwise this route part does not match.
	 *
	 * @param string $value value to match, usually the current query path segment(s)
	 * @return boolean TRUE if value could be matched successfully, otherwise FALSE
	 * @api
	 */
	protected function matchValue($value)
	{
		if ($value === null || $value === '') {
			return false;
		}
		$apiActionNames = self::apiActionNames($this->objectManager);
		if (isset($apiActionNames[strtolower($value)])) {
			return false;
		}
		$identifier = $this->getObjectIdentifierFromPathSegment($value);
		if ($identifier === null) {
			return false;
		}
		$this->value = array('__identity' => $identifier);
		return true;
	}

	/**
	 * Resolves the given entity and sets the value to a URI representation (path segment) that matches $this->uriPattern and is unique for the given object.
	 *
	 * @param mixed $value
	 * @return boolean TRUE if the object could be resolved and stored in $this->value, otherwise FALSE.
	 */
	protected function resolveValue($value)
	{
		$identifier = null;
		if (is_array($value) && isset($value['__identity'])) {
			$identifier = $value['__identity'];
		} elseif (is_object($value)) {
			$identifier = $this->persistenceManager->getIdentifierByObject($value);
		} elseif (is_string($value) || is_integer($value)) {
			$apiActionNames = self::apiActionNames($this->objectManager);
			if (!isset($apiActionNames[strtolower($value)])) {
				$identifier = $value;
			}
		}
		if ($identifier === null || (!is_string($identifier) && !is_integer($identifier))) {
			return false;
		}
		$pathSegment = $this->getPathSegmentByIdentifier($identifier);
		if ($pathSegment === null) {
			return false;
		}
		$this->value = $pathSegment;
		return true;
	}

	/**
	 * Retrieves the object identifier from the given $pathSegment.
	 *
	 * @param string $pathSegment the query path segment to convert
	 * @return string|integer the technical identifier of the object or NULL if it couldn't be found
	 */
	protected function getObjectIdentifierFromPathSegment($pathSegment)
	{
		return rawurldecode($pathSegment);
	}

	/**
	 * Generates a unique string for the given identifier according to $this->uriPattern.
	 *
	 * @param string $identifier the technical identifier of the object
	 * @return string|integer the resolved path segment(s)
	 */
	protected function getPathSegmentByIdentifier($identifier)
	{
		return rawurlencode($identifier);
	}

}