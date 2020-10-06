<?php
namespace Flowpack\RestApi\Tests\Functional\Api\Fixtures\Controller;

class ApplicationException extends \Neos\Flow\Exception
{
	public function __construct($message, $code)
	{
		$this->statusCode = $code;
		parent::__construct($message, $code);
	}
}