<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package    MetaModels
 * @subpackage AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Andreas Isaak <info@andreas-isaak.de>
 * @author     Christian de la Haye <service@delahaye.de>
 * @author     David Maack <david.maack@arcor.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\Filter\Rules;

use MetaModels\Attribute\Tags\AbstractTags;
use MetaModels\Filter\FilterRule;

/**
 * This is the MetaModelFilterRule class for handling select fields.
 *
 * @package AttributeTags
 * @author  Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class FilterRuleTags extends FilterRule
{
    /**
     * The attribute to filter.
     *
     * @var AbstractTags
     */
    protected $objAttribute;

    /**
     * The filter value.
     *
     * @var string
     */
    protected $value;

    /**
     * {@inheritDoc}
     */
    public function __construct(AbstractTags $objAttribute, $strValue)
    {
        parent::__construct();

        $this->objAttribute = $objAttribute;
        $this->value        = $strValue;
    }

    /**
     * Ensure the value is either a proper id or array od ids - converts aliases to ids.
     *
     * @return array
     */
    public function sanitizeValue()
    {
        $strTableNameId  = $this->objAttribute->get('tag_table');
        // The tag_id field is empty if the source of the attribute is from type metamodel
        $strColNameId    = $this->objAttribute->get('tag_id') ?:'id';
        $strColNameAlias = $this->objAttribute->get('tag_alias');

        $arrValues = is_array($this->value) ? $this->value : explode(',', $this->value);

        $objDB = $this->objAttribute->getMetaModel()->getServiceContainer()->getDatabase();

        if ($strColNameAlias) {
            $objSelectIds = $objDB
                ->prepare(
                    sprintf(
                        'SELECT %1$s FROM %2$s WHERE %3$s IN (%4$s)',
                        $strColNameId,
                        $strTableNameId,
                        $strColNameAlias,
                        implode(',', array_fill(0, count($arrValues), '?'))
                    )
                )
                ->execute($arrValues);

            $arrValues = $objSelectIds->fetchEach($strColNameId);
        } else {
            $arrValues = array_map('intval', $arrValues);
        }

        return $arrValues;
    }

    /**
     * {@inheritdoc}
     */
    public function getMatchingIds()
    {
        $arrValues = $this->sanitizeValue();

        // Get out when no values are available.
        if (!$arrValues) {
            return array();
        }

        $objMatches = $this
            ->objAttribute
            ->getMetaModel()
            ->getServiceContainer()
            ->getDatabase()
            ->prepare(
                'SELECT item_id as id
                FROM tl_metamodel_tag_relation
                WHERE value_id IN (' . implode(',', $arrValues) . ')
                AND att_id = ?'
            )
            ->execute($this->objAttribute->get('id'));

        return $objMatches->fetchEach('id');
    }
}
