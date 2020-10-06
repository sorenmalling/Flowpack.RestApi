<?php
namespace Flowpack\RestApi\Utility;

/**
 * Utility class for parsing "Link" headers by their "rel" tags.
 */
class LinkHeader
{

	/**
	 * @var array|string
	 */
	protected $header;

	/**
	 * @var array
	 */
	protected $parsedHeaders;

	/**
	 * @param string|array $header An array of Link header strings or a single string of comma-separated Link headers
	 */
	public function __construct($header)
	{
		$this->header = $header;
	}

	/**
	 * This method parses the header lazily into a structure of array('$rel' => '$uri')
	 */
	protected function parse()
	{
		if ($this->parsedHeaders !== NULL) return;
		$this->parsedHeaders = array();

		if (is_string($this->header)) {
			$this->header = preg_split('/(<[^>]+>;(?:\s*[^=]+="[^"]*";?)*),/', $this->header, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		}
		if (!is_array($this->header)) {
			return;
		}

		foreach ($this->header as $header) {
			if (preg_match('/<(?P<uri>[^>]+)>;.*rel="(?P<rel>[^"]+)"/', $header, $matches) > 0) {
				$this->parsedHeaders[$matches['rel']] = $matches['uri'];
			}
		}
	}

	/**
	 * @param string $rel A "rel" tag to get the URI for
	 * @return null|string The URI for this "rel" link or null if no such "rel" exists
	 */
	public function getRel($rel)
	{
		$this->parse();
		if (isset($this->parsedHeaders[$rel])) {
			return $this->parsedHeaders[$rel];
		}
		return NULL;
	}

	/**
	 * Get the URI for the "next" rel tag.
	 *
	 * @return null|string
	 */
	public function getNext()
	{
		return $this->getRel('next');
	}

	/**
	 * Get the URI for the "prev" rel tag.
	 *
	 * @return null|string
	 */
	public function getPrev()
	{
		return $this->getRel('prev');
	}

}
