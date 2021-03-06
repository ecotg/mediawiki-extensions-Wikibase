<?php

namespace Wikibase\Api;

use ApiResult;
use InvalidArgumentException;
use Revision;
use Status;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Reference;
use Wikibase\EntityRevision;
use Wikibase\Lib\Serializers\EntitySerializer;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\Lib\Store\EntityTitleLookup;

/**
 * Builder for Api Results
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 * @author Daniel Kinzler
 */
class ResultBuilder {

	/**
	 * @var ApiResult
	 */
	protected $result;

	/**
	 * @var int
	 */
	protected $missingEntityCounter;

	/**
	 * @var SerializerFactory
	 */
	protected $serializerFactory;

	/**
	 * @var EntityTitleLookup
	 */
	protected $entityTitleLookup;

	/**
	 * @var SerializationOptions
	 */
	protected $options;

	/**
	 * @param ApiResult $result
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param SerializerFactory $serializerFactory
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		$result,
		EntityTitleLookup $entityTitleLookup,
		SerializerFactory $serializerFactory
	) {
		if( !$result instanceof ApiResult ){
			throw new InvalidArgumentException( 'Result builder must be constructed with an ApiWikibase' );
		}

		$this->result = $result;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->serializerFactory = $serializerFactory;
		$this->missingEntityCounter = -1;
	}

	/**
	 * Returns the serialization options used by this ResultBuilder.
	 * This can be used to modify the options.
	 *
	 * @return SerializationOptions
	 */
	public function getOptions() {
		if ( !$this->options ) {
			$this->options = new SerializationOptions();
			$this->options->setIndexTags( $this->result->getIsRawMode() );
			$this->options->setOption( EntitySerializer::OPT_SORT_ORDER, EntitySerializer::SORT_NONE );
		}

		return $this->options;
	}

	/**
	 * @since 0.5
	 *
	 * @param $success bool|int|null
	 *
	 * @throws InvalidArgumentException
	 */
	public function markSuccess( $success = true ) {
		$value = intval( $success );
		if( $value !== 1 && $value !== 0 ){
			throw new InvalidArgumentException(
				'$wasSuccess must evaluate to either 1 or 0 when using intval()'
			);
		}
		$this->result->addValue( null, 'success', $value );
	}

	/**
	 * Adds a list of values for the given path and name.
	 * This automatically sets the indexed tag name, if appropriate.
	 *
	 * To set atomic values or records, use setValue() or appendValue().
	 *
	 * @see ApiResult::addValue
	 * @see ApiResult::setIndexedTagName
	 * @see ResultBuilder::setValue()
	 * @see ResultBuilder::appendValue()
	 *
	 * @since 0.5
	 *
	 * @param $path array|string|null
	 * @param $name string
	 * @param $values array
	 * @param string $tag tag name to use for elements of $values
	 *
	 * @throws InvalidArgumentException
	 */
	public function setList( $path, $name, array $values, $tag ) {
		$this->checkPathType( $path );
		$this->checkNameIsString( $name );
		$this->checkTagIsString( $tag );

		if ( $this->result->getIsRawMode() ) {
			// Unset first, so we don't make the tag name an actual value.
			// We'll be setting this to $tag by calling setIndexedTagName().
			unset( $values['_element'] );

			$values = array_values( $values );
			$this->result->setIndexedTagName( $values, $tag );
		}

		$this->result->addValue( $path, $name, $values );
	}

	/**
	 * Set an atomic value (or record) for the given path and name.
	 * If the value is an array, it should be a record (associative), not a list.
	 * For adding lists, use setList().
	 *
	 * @see ResultBuilder::setList()
	 * @see ResultBuilder::appendValue()
	 * @see ApiResult::addValue
	 *
	 * @since 0.5
	 *
	 * @param $path array|string|null
	 * @param $name string
	 * @param $value mixed
	 *
	 * @throws InvalidArgumentException
	 */
	public function setValue( $path, $name, $value ) {
		$this->checkPathType( $path );
		$this->checkNameIsString( $name );
		$this->checkValueIsNotList( $value );

		$this->result->addValue( $path, $name, $value );
	}

	/**
	 * Appends a value to the list at the given path.
	 * This automatically sets the indexed tag name, if appropriate.
	 *
	 * If the value is an array, it should be associative, not a list.
	 * For adding lists, use setList().
	 *
	 * @see ResultBuilder::setList()
	 * @see ResultBuilder::setValue()
	 * @see ApiResult::addValue
	 * @see ApiResult::setIndexedTagName_internal
	 *
	 * @since 0.5
	 *
	 * @param $path array|string|null
	 * @param $key int|string|null the key to use when appending, or null for automatic.
	 * May be ignored even if given, based on $this->result->getIsRawMode().
	 * @param $value mixed
	 * @param string $tag tag name to use for $value in indexed mode
	 *
	 * @throws InvalidArgumentException
	 */
	public function appendValue( $path, $key, $value, $tag ) {
		$this->checkPathType( $path );
		$this->checkKeyType( $key );
		$this->checkTagIsString( $tag );

		$this->checkValueIsNotList( $value );

		if ( $this->result->getIsRawMode() ) {
			$key = null;
		}

		$this->result->addValue( $path, $key, $value );

		if ( $this->result->getIsRawMode() && !is_string( $key ) ) {
			$this->result->setIndexedTagName_internal( $path, $tag );
		}
	}

	/**
	 * @param array|string|null $path
	 *
	 * @throws InvalidArgumentException
	 */
	private function checkPathType( $path ) {
		if ( is_string( $path ) ) {
			$path = array( $path );
		}

		if ( !is_array( $path ) && $path !== null ) {
			throw new InvalidArgumentException( '$path must be an array (or null)' );
		}
	}

	/**
	 * @param string $name
	 *
	 * @throws InvalidArgumentException
	 */
	private function checkNameIsString( $name ) {
		if ( !is_string( $name ) ) {
			throw new InvalidArgumentException( '$name must be a string' );
		}
	}

	/**
	 * @param $key int|string|null the key to use when appending, or null for automatic.
	 *
	 * @throws InvalidArgumentException
	 */
	private function checkKeyType( $key ) {
		if ( $key !== null && !is_string( $key ) && !is_int( $key ) ) {
			throw new InvalidArgumentException( '$key must be a string, int, or null' );
		}
	}

	/**
	 * @param string $tag tag name to use for elements of $values
	 *
	 * @throws InvalidArgumentException
	 */
	private function checkTagIsString( $tag ) {
		if ( !is_string( $tag ) ) {
			throw new InvalidArgumentException( '$tag must be a string' );
		}
	}

	/**
	 * @param mixed $value
	 *
	 * @throws InvalidArgumentException
	 */
	private function checkValueIsNotList( $value ) {
		if ( is_array( $value ) && isset( $value[0] ) ) {
			throw new InvalidArgumentException( '$value must not be a list' );
		}
	}

	/**
	 * Get serialized entity for the EntityRevision and add it to the result
	 *
	 * @param string|null $key The key for the entity in the 'entities' structure.
	 *        Will default to the entity's serialized ID if null.
	 * @param EntityRevision $entityRevision
	 * @param SerializationOptions|null $options
	 * @param array|string $props a list of fields to include, or "all"
	 * @param array $siteIds A list of site IDs to filter by
	 *
	 * @since 0.5
	 */
	public function addEntityRevision(
		$key,
		EntityRevision $entityRevision,
		SerializationOptions
		$options = null,
		$props = 'all',
		$siteIds = array()
	) {
		$entity = $entityRevision->getEntity();
		$entityId = $entity->getId();

		if ( $key === null ) {
			$key = $entityId->getSerialization();
		}

		$record = array();

		$serializerOptions = $this->getOptions();
		if ( $options ) {
			$serializerOptions->merge( $options );
		}

		//if there are no props defined only return type and id..
		if ( $props === array() ) {
			$record['id'] = $entityId->getSerialization();
			$record['type'] = $entityId->getEntityType();
		} else {
			if ( $props == 'all' || in_array( 'info', $props ) ) {
				$title = $this->entityTitleLookup->getTitleForId( $entityId );
				$record['pageid'] = $title->getArticleID();
				$record['ns'] = intval( $title->getNamespace() );
				$record['title'] = $title->getPrefixedText();
				$record['lastrevid'] = intval( $entityRevision->getRevision() );
				$record['modified'] = wfTimestamp( TS_ISO_8601, $entityRevision->getTimestamp() );
			}

			//FIXME: $props should be used to filter $entitySerialization!
			// as in, $entitySerialization = array_intersect_key( $entitySerialization, array_flip( $props ) )
			$entitySerializer = $this->serializerFactory->newSerializerForObject( $entity, $serializerOptions );
			$entitySerialization = $entitySerializer->getSerialized( $entity );

			if ( !empty( $siteIds ) && array_key_exists( 'sitelinks', $entitySerialization ) ) {
				foreach ( $entitySerialization['sitelinks'] as $siteId => $sitelink ) {
					if ( is_array( $sitelink ) && !in_array( $sitelink['site'], $siteIds ) ) {
						unset( $entitySerialization['sitelinks'][$siteId] );
					}
				}
			}

			$record = array_merge( $record, $entitySerialization );
		}

		$this->appendValue( array( 'entities' ), $key, $record, 'entity' );
	}

	/**
	 * Get serialized information for the EntityId and add them to result
	 *
	 * @param EntityId $entityId
	 * @param string|array|null $path
	 *
	 * @since 0.5
	 */
	public function addBasicEntityInformation( EntityId $entityId, $path ) {
		$this->setValue( $path, 'id', $entityId->getSerialization() );
		$this->setValue( $path, 'type', $entityId->getEntityType() );
	}

	/**
	 * Get serialized labels and add them to result
	 *
	 * @since 0.5
	 *
	 * @param array $labels the labels to set in the result
	 * @param array|string $path where the data is located
	 */
	public function addLabels( array $labels, $path ) {
		$labelSerializer = $this->serializerFactory->newLabelSerializer( $this->getOptions() );

		$values = $labelSerializer->getSerialized( $labels );
		$this->setList( $path, 'labels', $values, 'label' );
	}

	/**
	 * Get serialized descriptions and add them to result
	 *
	 * @since 0.5
	 *
	 * @param array $descriptions the descriptions to insert in the result
	 * @param array|string $path where the data is located
	 */
	public function addDescriptions( array $descriptions, $path ) {
		$descriptionSerializer = $this->serializerFactory->newDescriptionSerializer( $this->getOptions() );

		$values = $descriptionSerializer->getSerialized( $descriptions );
		$this->setList( $path, 'descriptions', $values, 'description' );
	}

	/**
	 * Get serialized aliases and add them to result
	 *
	 * @since 0.5
	 *
	 * @param array $aliases the aliases to set in the result
	 * @param array|string $path where the data is located
	 */
	public function addAliases( array $aliases, $path ) {
		$aliasSerializer = $this->serializerFactory->newAliasSerializer( $this->getOptions() );
		$values = $aliasSerializer->getSerialized( $aliases );
		$this->setList( $path, 'aliases', $values, 'alias' );
	}

	/**
	 * Get serialized sitelinks and add them to result
	 *
	 * @since 0.5
	 *
	 * @param array $siteLinks the site links to insert in the result, as SiteLink objects
	 * @param array|string $path where the data is located
	 * @param array|null $options
	 */
	public function addSiteLinks( array $siteLinks, $path, $options = null ) {
		$serializerOptions = $this->getOptions();

		if ( is_array( $options ) ) {
			if ( in_array( EntitySerializer::SORT_ASC, $options ) ) {
				$serializerOptions->setOption( EntitySerializer::OPT_SORT_ORDER, EntitySerializer::SORT_ASC );
			} elseif ( in_array( EntitySerializer::SORT_DESC, $options ) ) {
				$serializerOptions->setOption( EntitySerializer::OPT_SORT_ORDER, EntitySerializer::SORT_DESC );
			}

			if ( in_array( 'url', $options ) ) {
				$serializerOptions->addToOption( EntitySerializer::OPT_PARTS, "sitelinks/urls" );
			}

			if ( in_array( 'removed', $options ) ) {
				$serializerOptions->addToOption( EntitySerializer::OPT_PARTS, "sitelinks/removed" );
			}
		}

		$siteLinkSerializer = $this->serializerFactory->newSiteLinkSerializer( $serializerOptions );
		$values = $siteLinkSerializer->getSerialized( $siteLinks );

		if ( $values !== array() ) {
			$this->setList( $path, 'sitelinks', $values, 'sitelink' );
		}
	}

	/**
	 * Get serialized claims and add them to result
	 *
	 * @since 0.5
	 *
	 * @param Claim[] $claims the labels to set in the result
	 * @param array|string $path where the data is located
	 */
	public function addClaims( array $claims, $path ) {
		$claimsSerializer = $this->serializerFactory->newClaimsSerializer( $this->getOptions() );

		$values = $claimsSerializer->getSerialized( new Claims( $claims ) );

		// HACK: comply with ApiResult::setIndexedTagName
		$tag = isset( $values['_element'] ) ? $values['_element'] : 'claim';
		$this->setList( $path, 'claims', $values, $tag );
	}

	/**
	 * Get serialized claim and add it to result
	 *
	 * @param Claim $claim
	 *
	 * @since 0.5
	 */
	public function addClaim( Claim $claim ) {
		$serializer = $this->serializerFactory->newClaimSerializer( $this->getOptions() );

		//TODO: this is currently only used to add a Claim as the top level structure,
		//      with a null path and a fixed name. Would be nice to also allow claims
		//      to be added to a list, using a path and a id key or index.

		$value = $serializer->getSerialized( $claim );
		$this->setValue( null, 'claim', $value );
	}

	/**
	 * Get serialized reference and add it to result
	 *
	 * @param Reference $reference
	 *
	 * @since 0.5
	 */
	public function addReference( Reference $reference ) {
		$serializer = $this->serializerFactory->newReferenceSerializer( $this->getOptions() );

		//TODO: this is currently only used to add a Reference as the top level structure,
		//      with a null path and a fixed name. Would be nice to also allow references
		//      to be added to a list, using a path and a id key or index.

		$value = $serializer->getSerialized( $reference );
		$this->setValue( null, 'reference', $value );
	}

	/**
	 * Add an entry for a missing entity...
	 *
	 * @param string|null $key The key under which to place the missing entity in the 'entities'
	 *        structure. If null, defaults to the 'id' field in $missingDetails if that is set;
	 *        otherwise, it defaults to using a unique negative number.
	 * @param array $missingDetails array containing key value pair missing details
	 *
	 * @since 0.5
	 */
	public function addMissingEntity( $key, $missingDetails ) {
		if ( $key === null && isset( $missingDetails['id'] ) ) {
			$key = $missingDetails['id'];
		}

		if ( $key === null ) {
			$key = $this->missingEntityCounter;
		}

		$this->appendValue(
			'entities',
			$key,
			array_merge( $missingDetails, array( 'missing' => "" ) ),
			'entity'
		);

		$this->missingEntityCounter--;
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @param string $name
	 *
	 * @since 0.5
	 */
	public function addNormalizedTitle( $from, $to, $name = 'n' ) {
		$this->setValue(
			'normalized',
			$name,
			array( 'from' => $from, 'to' => $to )
		);
	}

	/**
	 * Adds the ID of the new revision from the Status object to the API result structure.
	 * The status value is expected to be structured in the way that EditEntity::attemptSave()
	 * resp WikiPage::doEditContent() do it: as an array, with an EntityRevision or Revision
	 *  object in the 'revision' field.
	 *
	 * If no revision is found the the Status object, this method does nothing.
	 *
	 * @see ApiResult::addValue()
	 *
	 * @since 0.5
	 *
	 * @param Status $status The status to get the revision ID from.
	 * @param string|null|array $path Where in the result to put the revision id
	 */
	public function addRevisionIdFromStatusToResult( Status $status, $path ) {
		$statusValue = $status->getValue();

		/* @var Revision $revision */
		$revision = isset( $statusValue['revision'] )
			? $statusValue['revision'] : null;

		if ( $revision ) {
			//HACK: $revision may be a Revision or EntityRevision
			$revId = ( $revision instanceof Revision ) ? $revision->getId() : $revision->getRevision();

			$this->setValue(
				$path,
				'lastrevid',
				intval( $revId )
			);
		}
	}

}
