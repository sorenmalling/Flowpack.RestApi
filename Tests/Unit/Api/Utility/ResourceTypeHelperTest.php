<?php
namespace Flowpack\RestApi\Tests\Unit\Api\Utility;

use Flowpack\RestApi\Utility\ResourceTypeHelper;

class ResourceTypeHelperTest extends \Neos\Flow\Tests\UnitTestCase
{

	/**
	 * @test
	 */
	public function normalizeCorrectlyNormalizesModelTypes()
	{
		$input = 'Acme\\Foo\\Domain\\Model\\Bar';
		$this->assertThat(ResourceTypeHelper::normalize($input), $this->equalTo('Bar'));

		$input = 'Acme\\Domain\\Model\\Foo\\Bar';
		$this->assertThat(ResourceTypeHelper::normalize($input), $this->equalTo('Foo\\Bar'));
	}

	/**
	 * @test
	 */
	public function normalizeDoesNotNormalizeNonModelTypes()
	{
		$input = 'Acme\\Foo\\Domain\\Repository\\Bar';
		$this->assertThat(ResourceTypeHelper::normalize($input), $this->equalTo($input));

		$input = 'string';
		$this->assertThat(ResourceTypeHelper::normalize($input), $this->equalTo($input));
	}

	/**
	 * @test
	 */
	public function normalizeCorrectlyNormalizesCollectionAndArrayTypes()
	{
		$input = 'array<Acme\\Foo\\Domain\\Model\\Bar>';
		$this->assertThat(ResourceTypeHelper::normalize($input), $this->equalTo('array<Bar>'));

		$input = 'array<Acme\\Domain\\Model\\Foo\\Bar>';
		$this->assertThat(ResourceTypeHelper::normalize($input), $this->equalTo('array<Foo\\Bar>'));

		$input = 'Acme\\Foo\\Collection<Acme\\Foo\\Domain\\Model\\Bar>';
		$this->assertThat(ResourceTypeHelper::normalize($input), $this->equalTo('Acme\\Foo\\Collection<Bar>'));
	}
}