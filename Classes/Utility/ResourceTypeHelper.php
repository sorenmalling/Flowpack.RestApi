<?php
namespace Flowpack\RestApi\Utility;

use Neos\Flow\Annotations as Flow;

/**
 * Class ResourceTypeHelper
 *
 * @Flow\Scope("singleton")
 */
class ResourceTypeHelper
{

	const MODEL_NAMESPACE = '\\Domain\\Model\\';

	/**
	 * Normalize a resource type by stripping off the namespace up to "\Domain\Model\".
	 *
	 * @param string $type
	 * @return string
	 */
	public static function normalize($type)
	{
		$type = str_replace('Doctrine\\Common\\Collections\\', '', $type);
		$modelNamespace = strpos($type, self::MODEL_NAMESPACE);
		if ($modelNamespace === false) return $type;

		$arrayItem = strpos($type, '<');
		return ($arrayItem !== false ? substr($type, 0, $arrayItem + 1) : '') . substr($type, $modelNamespace + strlen(self::MODEL_NAMESPACE));
	}

}
