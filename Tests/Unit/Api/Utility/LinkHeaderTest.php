<?php
namespace Flowpack\RestApi\Tests\Unit\Api\Utility;

use Flowpack\RestApi\Utility\LinkHeader;

class LinkHeaderTest extends \Neos\Flow\Tests\UnitTestCase
{

	/**
	 * @test
	 */
	public function linkHeaderWorksWithSingleLinkHeaderString()
	{
		$links = new LinkHeader('<https://api.github.com/user/9287/repos?page=3&per_page=100>; rel="next"');
		$this->assertThat($links->getNext(), $this->equalTo('https://api.github.com/user/9287/repos?page=3&per_page=100'));
	}

	/**
	 * @test
	 */
	public function linkHeaderWorksWithMultipleLinkHeaderString()
	{
		$links = new LinkHeader('<https://api.github.com/user/9287/repos?page=3&per_page=100>; rel="next",<https://api.github.com/user/9287/repos?page=1&per_page=100>; rel="prev"; pet="cat"');
		$this->assertThat($links->getNext(), $this->equalTo('https://api.github.com/user/9287/repos?page=3&per_page=100'));
		$this->assertThat($links->getPrev(), $this->equalTo('https://api.github.com/user/9287/repos?page=1&per_page=100'));
	}

	/**
	 * @test
	 */
	public function linkHeaderWorksWithMultipleLinkHeaderStringContaingCommas()
	{
		$links = new LinkHeader('<https://api.github.com/user/9287/repos?page=3&per_page=100,5>; rel="next",<https://api.github.com/user/9287/repos?page=1&per_page=100,5>; rel="prev"; pet="cat,dog"');
		$this->assertThat($links->getNext(), $this->equalTo('https://api.github.com/user/9287/repos?page=3&per_page=100,5'));
		$this->assertThat($links->getPrev(), $this->equalTo('https://api.github.com/user/9287/repos?page=1&per_page=100,5'));
	}

	/**
	 * @test
	 */
	public function linkHeaderWorksWithArray()
	{
		$links = new LinkHeader(array(
			'<https://api.github.com/user/9287/repos?page=3&per_page=100>; rel="next"',
			'<https://api.github.com/user/9287/repos?page=1&per_page=100>; rel="prev"; pet="cat"'));
		$this->assertThat($links->getNext(), $this->equalTo('https://api.github.com/user/9287/repos?page=3&per_page=100'));
		$this->assertThat($links->getPrev(), $this->equalTo('https://api.github.com/user/9287/repos?page=1&per_page=100'));
	}
}