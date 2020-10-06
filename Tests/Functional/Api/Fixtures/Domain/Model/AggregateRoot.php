<?php
namespace Flowpack\RestApi\Tests\Functional\Api\Fixtures\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class AggregateRoot
 *
 * @Flow\Entity
 */
class AggregateRoot
{
	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var string
	 * @Flow\Validate(type="EmailAddress")
	 * @ORM\Column(nullable=true)
	 */
	protected $email;

	/**
	 * @var Collection<Entity>
	 * @ORM\ManyToMany
	 * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn(unique=true)})
	 */
	protected $entities;

	/**
	 * @var AggregateRoot
	 * @ORM\OneToOne
	 */
	protected $otherAggregate;

	public function __construct()
	{
		$this->entities = new ArrayCollection();
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 */
	public function setTitle($title)
	{
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getEmail()
	{
		return $this->email;
	}

	/**
	 * @param string $email
	 */
	public function setEmail($email)
	{
		$this->email = $email;
	}

	/**
	 * @return Collection<Entity>
	 */
	public function getEntities()
	{
		return $this->entities;
	}

	/**
	 * @param Collection<Entity> $entities
	 */
	public function setEntities($entities)
	{
		$this->entities = $entities;
	}

	/**
	 * @param Entity $entity
	 */
	public function addEntity($entity)
	{
		$this->entities->add($entity);
	}

	/**
	 * @return AggregateRoot
	 */
	public function getOtherAggregate()
	{
		return $this->otherAggregate;
	}

	/**
	 * @param AggregateRoot $otherAggregate
	 */
	public function setOtherAggregate($otherAggregate)
	{
		$this->otherAggregate = $otherAggregate;
	}
}