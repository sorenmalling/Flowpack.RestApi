<?php
namespace Flowpack\RestApi\Error;

use Neos\Flow\Error\ProductionExceptionHandler;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception as FlowException;
use Neos\Flow\Http\Response;

/**
 * An exception handler that outputs exceptions as JSON objects
 *
 * @Flow\Scope("singleton")
 */
class JsonExceptionHandler extends ProductionExceptionHandler
{

	/**
	 * Echoes an exception for the web.
	 *
	 * @param object $exception The exception
	 * @return void
	 */
	protected function echoExceptionWeb($exception)
	{
		$statusCode = ($exception instanceof FlowException) ? $exception->getStatusCode() : 500;
		$statusMessage = Response::getStatusMessageByCode($statusCode);
		$referenceCode = ($exception instanceof FlowException) ? $exception->getReferenceCode() : null;
		if (!headers_sent()) {
			header(sprintf('HTTP/1.1 %s %s', $statusCode, $statusMessage));
		}
		$this->systemLogger->logException($exception);
		header('Content-Type: application/json');
		header('Access-Control-Allow-Origin', '*');
		echo json_encode(array('code' => $exception->getCode(), 'message' => $exception->getMessage(), 'reference' => $referenceCode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

}
