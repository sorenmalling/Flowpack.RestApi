<?php
namespace Flowpack\RestApi\Controller;

use Flowpack\RestApi\Utility\ResourceTypeHelper;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Reflection\ClassReflection;
use Neos\Flow\Reflection\ReflectionService;

abstract class AbstractEntryController extends \Neos\Flow\Mvc\Controller\ActionController
{

	/**
	 * Override this property in
	 * @var string
	 */
	public static $REST_API_VERSION = '1.0';

	/**
	 * @var string
	 */
	protected $defaultViewObjectName = JsonView::class;

	/**
	 * @var array
	 */
	protected $supportedMediaTypes = array('application/json');

	/**
	 * @Flow\Inject
	 * @var ReflectionService
	 */
	protected $reflectionService;

	/**
	 * @var bool
	 * @Flow\InjectConfiguration(package="Flowpack.RestApi",path="useAbsoluteUris")
	 */
	protected $useAbsoluteUris = true;

	/**
	 * @var bool
	 * @Flow\InjectConfiguration(package="Flowpack.RestApi",path="normalizeResourceTypes")
	 */
	protected $normalizeResourceTypes = false;

	protected function initializeAction()
	{
		$this->response->setHeader('Access-Control-Allow-Origin', '*');
		$this->response->setHeader('X-API-Version', static::$REST_API_VERSION);
	}

	/**
	 * @param \Neos\Flow\Mvc\View\ViewInterface $view
	 */
	protected function initializeView(\Neos\Flow\Mvc\View\ViewInterface $view)
	{
		if ($view instanceof JsonView) {
			$view->setOption('jsonEncodingOptions', JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		}
	}

	public function discoverAction()
	{
		$this->forward('index');
	}

	/**
	 * Show a list of all resources (controllers) that the API provides access to
	 * @return void
	 */
	public function indexAction()
	{
		$apiControllers = $this->reflectionService->getAllSubClassNamesForClass(AbstractRestController::class);
		$apiEntryPoints = array();
		$currentClassReflection = new ClassReflection($this);
		$currentNamespace = $currentClassReflection->getNamespaceName();

		foreach ($apiControllers as $controllerName) {
			$controllerReflection = new ClassReflection($controllerName);
			if (substr($controllerReflection->getNamespaceName(), 0, strlen($currentNamespace)) !== $currentNamespace) {
				// Only discover controllers in the same namespace (and sub-namespaces)
				continue;
			}
			$controllerDescription = $controllerReflection->getDescription();
			$simpleControllerName = substr($controllerName, strrpos($controllerName, '\\') + 1);
			$resourceName = strtolower(str_replace('Controller', '', $simpleControllerName));
			$resourceUri = $this->uriBuilder->setCreateAbsoluteUri($this->useAbsoluteUris)->setFormat($this->request->getFormat())->uriFor('discover', array(), $resourceName);
			$resourceType = call_user_func($controllerName . '::resourceType');
			$apiEntryPoints[$resourceName] = array(
				'uri' => $resourceUri,
				'resourceType' => $this->normalizeResourceTypes ? ResourceTypeHelper::normalize($resourceType) : $resourceType,
				'description' => $controllerDescription
			);
		}

		$response = array(
			'apiVersion' => static::$REST_API_VERSION,
			'entrypoints' => $apiEntryPoints
		);
		$this->view->assign('value', $response);
	}
}