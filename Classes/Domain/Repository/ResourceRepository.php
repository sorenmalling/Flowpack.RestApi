<?php
namespace Flowpack\RestApi\Domain\Repository;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Reflection\ReflectionService;

/**
 * A generic REST resource entity repository, that allows for easily paginating through the entities with cursor-based pagination
 * See http://blog.novatec-gmbh.de/art-pagination-offset-vs-value-based-paging/ for more information.
 *
 * @Flow\Scope("singleton")
 */
class ResourceRepository extends Repository
{

	/**
	 * Array of property names that belong to the identity of the resource.
	 *
	 * Override this if you want to avoid reflection usage and have a custom identity on your entity.
	 *
	 * @var array
	 */
	protected $identityProperties;

	/**
	 * @param string $resourceEntityClassName
	 */
	public function __construct($resourceEntityClassName)
	{
		parent::__construct();
		$this->entityClassName = $resourceEntityClassName;
		if ($this->identityProperties === null) {
			if (property_exists($resourceEntityClassName, 'Persistence_Object_Identifier')) {
				$this->identityProperties = array('Persistence_Object_Identifier');
			} else {
				/* @var $reflectionService ReflectionService */
				$reflectionService = Bootstrap::$staticObjectManager->get(ReflectionService::class);
				$classSchema = $reflectionService->getClassSchema($this->entityClassName);
				$this->identityProperties = $classSchema->getIdentityProperties();
			}
		}
	}

	/**
	 * Build a constraint that will correctly paginate the next results starting from $lastCursor in the given direction.
	 * If $lastItem is given, then the cursorProperty is assumed to not be unique and the identity of the last item has to be taken into account.
	 * If the entity has a compound identity, $lastItemIdentity needs to be provided as associative array of all identity properties.
	 *
	 * @param QueryInterface $query
	 * @param string $cursorProperty
	 * @param string $lastCursor
	 * @param array|string|null $lastItemIdentity
	 * @param string $dir
	 * @return object
	 * @throws RepositoryException
	 */
	protected function buildConstraintForCursorPagination($query, $cursorProperty, $lastCursor, $lastItemIdentity, $dir)
	{
		$comparator = ($dir === 'ASC') ? 'greaterThan' : 'lessThan';
		if ($lastItemIdentity !== null) {
			$constraints[] = ($dir === 'ASC') ? $query->greaterThanOrEqual($cursorProperty, $lastCursor) : $query->lessThanOrEqual($cursorProperty, $lastCursor);
			if (count($this->identityProperties) === 1) {
				if (is_array($lastItemIdentity)) {
					$lastItemIdentity = reset($lastItemIdentity);
				}
				$constraints[] = $query->logicalOr($query->$comparator($cursorProperty, $lastCursor), $query->$comparator(reset($this->identityProperties), $lastItemIdentity));
			} else {
				// Compound identity case... ugh
				if (!is_array($lastItemIdentity) || count($lastItemIdentity) < count($this->identityProperties)) {
					throw new RepositoryException('Resource entity "' . $this->entityClassName . '" has a compound identity, but the given identity is a scalar (' . var_export($lastItemIdentity, true) . ')');
				}
				$subConstraints = array();
				foreach ($this->identityProperties as $identity) {
					$subConstraints[] = $query->$comparator($identity, $lastItemIdentity[$identity]);
				}
				$constraints[] = $query->logicalOr($query->$comparator($cursorProperty, $lastCursor), $query->logicalAnd($subConstraints));
			}

			return $query->logicalAnd($constraints);
		} else {
			// If the cursor is a unique property, everything is easy
			return $query->$comparator($cursorProperty, $lastCursor);
		}
	}

	/**
	 * @param QueryInterface $query
	 * @param string $property The property name to query for
	 * @param mixed $value The property value(s) to check against
	 * @return object
	 * @throws RepositoryException If the entity identifier is incompatible with the provided identity
	 */
	protected function buildIdentityMatchingConstraint(QueryInterface $query, $property, $value) {
		if (substr($property, -10) !== '__identity') {
			return null;
		}

		// TODO: Check if we can just replace with "IDENTITY(${substr($property, 0, -10)})", ie. if "IDENTITY()" works
		if (strlen($property) > 10) {
			// If we match against a subentity we can just directly compare and strip the '.__identity'
			return $query->equals(str_replace('.__identity', '', $property), $value);
		}

		// If we try to match the main entity, we need to add constraints for all identity properties specifically
		// because otherwise we would end up with a query "'' EQUALS {$someIdentity}"
		if (count($this->identityProperties) === 1) {
			if (is_array($value)) {
				$value = reset($value);
			}
			return $query->equals(reset($this->identityProperties), $value);
		}

		if (!is_array($value) || count($value) < count($this->identityProperties)) {
			throw new RepositoryException('Resource entity "' . $this->entityClassName . '" has a compound identity, but the given identity is a scalar (' . var_export($value, true) . ')');
		}
		$subConstraints = array();
		foreach ($this->identityProperties as $identity) {
			if (!isset($value[$identity])) {
				throw new RepositoryException('Resource entity "' . $this->entityClassName . '" has a compound identity, but the given identity misses the identity property "' . $identity . '"."');
			}
			$subConstraints[] = $query->equals($identity, $value[$identity]);
		}
		return $query->logicalAnd($subConstraints);
	}

	/**
	 * Paginate results by the given cursor property. For optimal performance, an index ($cursorProperty, $identity) should exist on the target entity table.
	 * Note that on InnoDB tables, the primary key (identity) will automatically be appended to any secondary index.
	 *
	 * @param string $cursorProperty The property that should be sorted and paginated by. If this is a unique property, $lastItem is not needed and will degrade performance.
	 * @param integer|null $limit The max number of elements to return.
	 * @param string $dir The direction to paginate. ASC will return the next items following $lastCursor, DESC will return the previous items before $lastCursor.
	 * @param string $lastCursor The last cursor value that was visible.
	 * @param string|array $lastItem If cursorProperty is not unique, the last items identity property should be given too to avoid duplicate results.
	 * @param array $filters Array of properties and values to filter by.
	 * @param array $orderings
	 * @return array An array of resource entities
	 */
	public function findByCursorPagination($cursorProperty, $limit = null, $dir = 'ASC', $lastCursor = null, $lastItem = null, array $filters = array(), array $orderings = array())
	{
		if ($cursorProperty === null || $cursorProperty === '__identity') {
			$cursorProperty = reset($this->identityProperties);
		}
		$query = $this->createQuery();

		$constraints = array();
		foreach ($filters as $filterProperty => $filterValue) {
			if (substr($filterProperty, -10) === '__identity') {
				$constraints[] = $this->buildIdentityMatchingConstraint($query, $filterProperty, $filterValue);
			} elseif (is_numeric($filterValue) || is_bool($filterValue)) {
				$constraints[] = $query->equals($filterProperty, $filterValue);
			} else {
				$constraints[] = $query->like($filterProperty, $filterValue, false);
			}
		}

		if ($lastCursor !== null) {
			$constraints[] = $this->buildConstraintForCursorPagination($query, $cursorProperty, $lastCursor, $lastItem, $dir);

			$orderings = array($cursorProperty => $dir);
			foreach ($this->identityProperties as $identity) {
				$orderings[$identity] = $dir;
			}
		} else {
			$orderings = array($cursorProperty => $dir);
			foreach ($this->identityProperties as $identity) {
				$orderings[$identity] = $dir;
			}
		}

		if ($constraints !== array()) {
			$query->matching($query->logicalAnd($constraints));
		}
		$query->setOrderings($orderings);

		if ($limit > 0) {
			$query->setLimit($limit);
		}

		$result = $query->execute()->toArray();
		if ($lastItem !== null && $dir !== 'ASC') {
			return array_reverse($result);
		}

		return $result;
	}

	/**
	 * Filter and sort results by custom values.
	 *
	 * @param array $filters Array of properties and values to filter by. All filters must match.
	 * @param array $orderings Array of properties and direction to sort by.
	 * @param integer|null $limit The max number of elements to return.
	 * @param integer|null $offset The element number to start from.
	 * @return array
	 */
	public function findByFilter(array $filters, array $orderings = array(), $limit = null, $offset = null)
	{
		$query = $this->createQuery();

		$constraints = array();
		foreach ($filters as $filterProperty => $filterValue) {
			if (substr($filterProperty, -10) === '__identity') {
				$constraints[] = $this->buildIdentityMatchingConstraint($query, $filterProperty, $filterValue);
			} elseif (is_numeric($filterValue) || is_bool($filterValue)) {
				$constraints[] = $query->equals($filterProperty, $filterValue);
			} else {
				$constraints[] = $query->like($filterProperty, $filterValue, false);
			}
		}

		if ($constraints !== array()) {
			$query->matching($query->logicalAnd($constraints));
		}

		if ($orderings !== array()) {
			$query->setOrderings($orderings);
		}

		if ($limit > 0) {
			$query->setLimit($limit);
		}

		if ($offset !== null && $offset >= 0) {
			$query->setOffset($offset);
		}

		$result = $query->execute()->toArray();

		return $result;
	}

	/**
	 * Search and sort results by a list of search terms and search properties.
	 *
	 * @param array $searchTerms Array of terms to search for. Any filter must match.
	 * @param array $searchProperties Array of properties to search in.
	 * @param array $orderings Array of properties and direction to sort by.
	 * @param integer|null $limit The max number of elements to return.
	 * @param integer|null $offset The element number to start from.
	 * @return array
	 */
	public function findBySearch(array $searchTerms, array $searchProperties, array $orderings = array(), $limit = null, $offset = null)
	{
		$query = $this->createQuery();

		// Search for identity should be done with a filter instead of search
		$searchProperties = array_filter($searchProperties, function($searchProperty) {
			return substr($searchProperty, -11) !== '.__identity';
		});
		$requiredConstraints = array();
		$optionalConstraints = array();
		if (isset($searchTerms['*'])) {
			foreach ($searchTerms['*'] as $searchTerm) {
				$constraints = array();
				foreach ($searchProperties as $searchProperty) {
					$constraints[] = $query->like($searchProperty, '%' . $searchTerm . '%', false);
				}
				// Search term may occur in any property
				$optionalConstraints[] = $query->logicalOr($constraints);
			}
		}
		if (isset($searchTerms['+'])) {
			foreach ($searchTerms['+'] as $searchTerm) {
				$constraints = array();
				foreach ($searchProperties as $searchProperty) {
					$constraints[] = $query->like($searchProperty, '%' . $searchTerm . '%', false);
				}
				// Search term must occur in any property
				$requiredConstraints[] = $query->logicalOr($constraints);
			}
		}
		if (isset($searchTerms['-'])) {
			foreach ($searchTerms['-'] as $searchTerm) {
				$constraints = array();
				foreach ($searchProperties as $searchProperty) {
					// We'd need to coalesce the like statement, because "NOT(NULL LIKE '%')" will be NULL and hence never match.
					// Unfortunately, Doctrine won't parse the resulting DQL correctly and error out and Flow currently doesn't provide
					// a method to coalesce properties (https://github.com/neos/flow-development-collection/issues/616).
					//$like = new \Doctrine\ORM\Query\Expr\Func('COALESCE', array($query->like($searchProperty, '%' . $searchTerm . '%', false), "''"));
					$like = $query->like($searchProperty, '%' . $searchTerm . '%', false);
					// The workaround for now is to add a ORed NULL check
					$constraints[] = $query->logicalOr($query->equals($searchProperty, null), $query->logicalNot($like));
				}
				// Search term may not occur in any properties
				$requiredConstraints[] = $query->logicalAnd($constraints);
			}
		}

		if ($optionalConstraints !== array()) {
			// Any search constraint must match
			$requiredConstraints[] = $query->logicalOr($optionalConstraints);
		}

		if ($requiredConstraints !== array()) {
			// All search constraints must match
			$query->matching($query->logicalAnd($requiredConstraints));
		}

		if ($orderings !== array()) {
			$query->setOrderings($orderings);
		}

		if ($limit > 0) {
			$query->setLimit($limit);
		}

		if ($offset !== null && $offset >= 0) {
			$query->setOffset($offset);
		}

		$result = $query->execute()->toArray();

		return $result;
	}

}