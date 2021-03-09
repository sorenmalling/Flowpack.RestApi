<?php
namespace Flowpack\RestApi\Error;

use Flowpack\RestApi\Controller\AbstractRestController;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Exception as FlowException;
use Neos\Flow\Mvc\ActionResponse;
use Psr\Log\LoggerInterface;

/**
 * A flow AOP aspect that registers the JsonExceptionHandler if an RestController is targeted.
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class ErrorHandlerAspect
{

	/**
	 * @Flow\Inject
	 * @var LoggerInterface
	 */
	protected $systemLogger;

	/**
	 * @Flow\Inject
	 * @var Bootstrap
	 */
	protected $bootstrap;

	/**
	 * Around advice on the Dispatcher, that registers the JsonExceptionHandler if the targeted controller of the request
	 * is an instance of an AbstractRestController.
	 * Also, to properly support functional tests and the internal request engine, exceptions in Testing context will
	 * directly be translated to json responses.
	 *
	 * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint The current join point
	 * @return void
	 * @Flow\Around("method(Neos\Flow\Mvc\Dispatcher->dispatch())")
	 */
	public function registerJsonExceptionHandler(JoinPointInterface $joinPoint)
	{
		$actionRequest = $joinPoint->getMethodArgument('request');
		if (!($actionRequest instanceof ActionRequest)) {
			$joinPoint->getAdviceChain()->proceed($joinPoint);
			return;
		}

		if (is_subclass_of($actionRequest->getControllerObjectName(), AbstractRestController::class)) {
			$exceptionHandler = new JsonExceptionHandler();

			if ($this->bootstrap->getContext()->isTesting()) {
				// In Testing context we need to manually catch exceptions and prepare a proper JSON response, because otherwise the
				// InternalRequestEngine will catch the exception and translate it into a non-JSON response.
				try {
					$joinPoint->getAdviceChain()->proceed($joinPoint);
				} catch (\Exception $e) {
					$this->systemLogger->info('Caught exception in Testing context while calling a RestController. Manually preparing JSON response.');
					/* @var $response ActionResponse */
					$response = $joinPoint->getMethodArgument('response');
					$statusCode = ($e instanceof FlowException) ? $e->getStatusCode() : 500;
					$referenceCode = ($e instanceof FlowException) ? $e->getReferenceCode() : null;
					$response->setStatusCode($statusCode);
					$response->setContentType('application/json');
					$response->setContent(json_encode(array('code' => $e->getCode(), 'message' => $e->getMessage(), 'reference' => $referenceCode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
				}
				return;
			}
		}

		$joinPoint->getAdviceChain()->proceed($joinPoint);
	}
}