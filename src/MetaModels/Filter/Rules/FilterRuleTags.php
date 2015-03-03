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
 * @author     Martin Treml <github@r2pi.net>
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
     * The MetaModel we are referencing on.
     *
     * @var IMetaModel
     */
    protected $objSelectMetaModel;

    /**
     * Checking for the reference is a MetaModel.
     *
     * @param string $table
     *
     * @return bool
     */
    protected function isMetaModel($table) {
        if ((substr($table, 0, 3) == 'mm_')) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve the linked MetaModel instance.
     *
     * @return IMetaModel
     */
    protected function getTagMetaModel()
    {
        if (empty($this->objSelectMetaModel)) {
            $this->objSelectMetaModel = $this->objAttribute->getMetaModel()
                                             ->getServiceContainer()
                                             ->getFactory()
                                             ->getMetaModel($this->objAttribute->get('tag_table'));
        }

        return $this->objSelectMetaModel;
    }

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
        $strColNameId    = $this->objAttribute->get('tag_id') ?: 'id';
        $strColNameAlias = $this->objAttribute->get('tag_alias');

        $arrValues = is_array($this->value) ? $this->value : explode(',', $this->value);

        $objDB = $this->objAttribute->getMetaModel()->getServiceContainer()->getDatabase();

        if(!$this->isMetaModel($strTableNameId)) {
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
        } else {
            $values = array();
            foreach($arrValues as $value) {
                $values[] = array_values($this->getTagMetaModel()->getAttribute($strColNameAlias)->searchFor($value));
            }

            $arrValues = $this->flatten($values);
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

    /**
     * Flatten the value id array.
     *
     * @param array $array
     *
     * @return array
     */
    public function flatten(array $array)
    {
        $return = array();
        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }
}
