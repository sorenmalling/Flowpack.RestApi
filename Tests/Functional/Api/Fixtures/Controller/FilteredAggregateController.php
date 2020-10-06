<?php
namespace Flowpack\RestApi\Tests\Functional\Api\Fixtures\Controller;

class FilteredAggregateController extends AggregateController
{
	protected static $EMBED_ARGUMENT_NAME = 'embed';

	protected static $RENDER_FIELDS_ARGUMENT_NAME = 'fields';

	protected static $SORTING_ARGUMENT_NAME = 'sort';

	protected static $LIMIT_ARGUMENT_NAME = 'limit';

	protected static $OFFSET_ARGUMENT_NAME = 'offset';

	protected $resourceEntityDefaultFilter = array( 'email' => '%@trackmyrace.com' );

	protected $resourceEntityRenderConfiguration = array( 'title', 'email' );
}
