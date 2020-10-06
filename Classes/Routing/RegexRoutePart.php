<?php
namespace Flowpack\RestApi\Routing;

use Neos\Flow\Annotations as Flow;

/**
 * Regex Route Part handler that matches a route part against a regular expression.
 *
 * This class can be removed from core once it is part of Flow Framework.
 */
class RegexRoutePart extends \Neos\Flow\Mvc\Routing\DynamicRoutePart
{

	/**
	 * Checks whether the current URI section matches the configured RegEx pattern.
	 *
	 * @param string $requestPath value to match, the string to be checked
	 * @return boolean TRUE if value could be matched successfully, otherwise FALSE.
	 */
	protected function matchValue($requestPath) {
		if (!preg_match($this->options['pattern'], $requestPath, $matches)) {
			return FALSE;
		}
		$this->value = rawurldecode(array_shift($matches));
		return TRUE;
	}

	/**
	 * Checks whether the route part matches the configured RegEx pattern.
	 *
	 * @param string $value The route part (must be a string)
	 * @return boolean TRUE if value could be resolved successfully, otherwise FALSE.
	 */
	protected function resolveValue($value) {
		if (!is_string($value) || !preg_match($this->options['pattern'], $value, $matches)) {
			return FALSE;
		}
		$this->value = rawurlencode(array_shift($matches));
		if ($this->lowerCase) {
			$this->value = strtolower($this->value);
		}
		return TRUE;
	}

}
