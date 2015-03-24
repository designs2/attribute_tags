<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package   AttributeTags
 * @author    Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author    Christian de la Haye <service@delahaye.de>
 * @author    Andreas Isaak <info@andreas-isaak.de>
 * @author    Andreas Nölke <zero@brothers-project.de>
 * @author    David Maack <david.maack@arcor.de>
 * @author    Patrick Kahl <kahl.patrick@googlemail.com>
 * @author    Stefan Heimes <stefan_heimes@hotmail.com>
 * @copyright The MetaModels team.
 * @license   LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Tags;

use Contao\Database\Result;

/**
 * This is the MetaModelAttribute class for handling tag attributes.
 *
 * @package    AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 */
class Tags extends AbstractTags
{
    /**
     * {@inheritdoc}
     */
    public function valueToWidget($varValue)
    {
        $strColNameAlias = $this->getAliasColumn();

        $arrResult = array();
        if ($varValue) {
            foreach ($varValue as $arrValue) {
                $arrResult[] = $arrValue[$strColNameAlias];
            }
        }

        // If we have a tree picker, the value must be a comma separated string.
        if ($this->isTreePicker() && !empty($arrResult)) {
            $arrResult = implode(',', $arrResult);
        }

        // We must use string keys.
        return array_map('strval', $arrResult);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException When values could not be translated.
     */
    protected function getValuesFromWidget($varValue)
    {
        $arrParams = array();
        foreach ($varValue as $strValue) {
            $arrParams[] = $strValue;
        }

        $objValue = $this
            ->getDatabase()
            ->prepare(
                sprintf(
                    'SELECT %1$s.*
                    FROM %1$s
                    WHERE %2$s IN (%3$s)
                    ORDER BY %4$s',
                    $this->getTagSource(),
                    $this->getAliasColumn(),
                    implode(',', array_fill(0, count($arrParams), '?')),
                    $this->getSortingColumn()
                )
            )
            ->execute($arrParams);

        $strColNameId = $this->get('tag_id');
        $arrResult    = array();

        while ($objValue->next()) {
            // Adding the sorting from widget.
            $strAlias                                                 = $this->getAliasColumn();
            $arrResult[$objValue->$strColNameId]                      = $objValue->row();
            $arrResult[$objValue->$strColNameId]['tag_value_sorting'] = array_search($objValue->$strAlias, $varValue);
        }

        return $arrResult;
    }

    /**
     * Retrieve the filter options for items with the given ids.
     *
     * @param array $arrIds   The ids for which the options shall be retrieved.
     *
     * @param bool  $usedOnly Flag if only used options shall be retrieved.
     *
     * @return Result
     */
    protected function retrieveFilterOptionsForIds($arrIds, $usedOnly)
    {
        if ($usedOnly) {
            $sqlQuery = '
                    SELECT COUNT(%1$s.%2$s) as mm_count, %1$s.*
                    FROM %1$s
                    LEFT JOIN tl_metamodel_tag_relation ON (
                        (tl_metamodel_tag_relation.att_id=?)
                        AND (tl_metamodel_tag_relation.value_id=%1$s.%2$s)
                    )
                    WHERE (tl_metamodel_tag_relation.item_id IN (%3$s)%5$s)
                    GROUP BY %1$s.%2$s
                    ORDER BY %1$s.%4$s
                ';
        } else {
            $sqlQuery = '
                    SELECT COUNT(rel.value_id) as mm_count, %1$s.*
                    FROM %1$s
                    LEFT JOIN tl_metamodel_tag_relation as rel ON (
                        (rel.att_id=?) AND (rel.value_id=%1$s.%2$s)
                    )
                    WHERE %1$s.%2$s IN (%3$s)%5$s
                    GROUP BY %1$s.%2$s
                    ORDER BY %1$s.%4$s';
        }

        return $this
            ->getDatabase()
            ->prepare(
                sprintf(
                    $sqlQuery,
                    // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                    $this->getTagSource(),                                                    // 1
                    $this->getIdColumn(),                                                     // 2
                    implode(',', $arrIds),                                                    // 3
                    $this->getSortingColumn(),                                                // 4
                    ($this->getWhereColumn() ? ' AND (' . $this->getWhereColumn() . ')' : '') // 5
                // @codingStandardsIgnoreEnd
                )
            )
            ->execute($this->get('id'));
    }

    /**
     * Retrieve the filter options for items with the given ids.
     *
     * @param bool $usedOnly Flag if only used options shall be retrieved.
     *
     * @return Result
     */
    protected function retrieveFilterOptionsWithoutIds($usedOnly)
    {
        if ($usedOnly) {
            $sqlQuery = '
                    SELECT COUNT(%1$s.%3$s) as mm_count, %1$s.*
                    FROM %1$s
                    INNER JOIN tl_metamodel_tag_relation as rel
                    ON (
                        (rel.att_id="%4$s") AND (rel.value_id=%1$s.%3$s)
                    )
                    WHERE rel.att_id=%4$s'
                . ($this->getWhereColumn() ? ' AND %5$s' : '') . '
                    GROUP BY %1$s.%3$s
                    ORDER BY %1$s.%2$s';
        } else {
            $sqlQuery = '
                    SELECT COUNT(rel.value_id) as mm_count, %1$s.*
                    FROM %1$s
                    LEFT JOIN tl_metamodel_tag_relation as rel
                    ON (
                        (rel.att_id="%4$s") AND (rel.value_id=%1$s.%3$s)
                    )'
                . ($this->getWhereColumn() ? ' WHERE %5$s' : '') . '
                    GROUP BY %1$s.%3$s
                    ORDER BY %1$s.%2$s';
        }

        return $this
            ->getDatabase()
            ->prepare(
                sprintf(
                    $sqlQuery,
                    // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                    $this->getTagSource(),       // 1
                    $this->getSortingColumn(),   // 2
                    $this->getIdColumn(),        // 3
                    $this->get('id'),            // 4
                    $this->getWhereColumn()      // 5
                // @codingStandardsIgnoreEnd
                )
            )
            ->execute();
    }

    /**
     * {@inheritdoc}
     *
     * Fetch filter options from foreign table.
     *
     */
    public function getFilterOptions($idList, $usedOnly, &$arrCount = null)
    {
        if (!(
            $this->getTagSource()
            && $this->getDatabase()->tableExists($this->getTagSource())
            && $this->getIdColumn()
            && $this->getSortingColumn())
        ) {
            return array();
        }

        if ($idList) {
            $objValue = $this->retrieveFilterOptionsForIds($idList, $usedOnly);
        } else {
            $objValue = $this->retrieveFilterOptionsWithoutIds($usedOnly);
        }

        $result      = array();
        $valueColumn = $this->getValueColumn();
        $aliasColumn = $this->getAliasColumn();
        while ($objValue->next()) {
            if ($arrCount !== null) {
                /** @noinspection PhpUndefinedFieldInspection */
                $arrCount[$objValue->$aliasColumn] = $objValue->mm_count;
            }

            $result[$objValue->$aliasColumn] = $objValue->$valueColumn;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataFor($arrIds)
    {
        $strTableName = $this->getTagSource();
        $strColNameId = $this->getIdColumn();
        $objDB        = $this->getDatabase();
        $arrReturn    = array();

        if ($objDB->tableExists($strTableName) && $strTableName && $strColNameId) {
            $metaModelTableName   = $this->getMetaModel()->getTableName();
            $metaModelTableNameId = $metaModelTableName . '_id';

            $objValue = $objDB
                ->prepare(
                    sprintf(
                        'SELECT %1$s.*, tl_metamodel_tag_relation.item_id AS %2$s
                        FROM %1$s
                        LEFT JOIN tl_metamodel_tag_relation ON (
                            (tl_metamodel_tag_relation.att_id=?)
                            AND (tl_metamodel_tag_relation.value_id=%1$s.%3$s)
                        )
                        WHERE tl_metamodel_tag_relation.item_id IN (%4$s)
                        ORDER BY tl_metamodel_tag_relation.value_sorting',
                        // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                        $strTableName,            // 1
                        $metaModelTableNameId, // 2
                        $strColNameId,            // 3
                        implode(',', $arrIds)     // 4
                    // @codingStandardsIgnoreEnd
                    )
                )
                ->execute($this->get('id'));

            while ($objValue->next()) {
                if (!isset($arrReturn[$objValue->$metaModelTableNameId])) {
                    $arrReturn[$objValue->$metaModelTableNameId] = array();
                }
                $arrData = $objValue->row();
                unset($arrData[$metaModelTableNameId]);
                $arrReturn[$objValue->$metaModelTableNameId][$objValue->$strColNameId] = $arrData;
            }
        }
        return $arrReturn;
    }

    /**
     * Convert the passed values to a list of value ids.
     *
     * @param string[] $values The values to convert.
     *
     * @return int[]
     */
    public function convertValuesToValueIds($values)
    {
        $strTableNameId  = $this->getTagSource();
        $strColNameId    = $this->getIdColumn();
        $strColNameAlias = $this->getAliasColumn();

        if ($strColNameAlias) {
            $objSelectIds = $this
                ->getDatabase()
                ->prepare(sprintf(
                    'SELECT %s FROM %s WHERE %s IN (%s)',
                    $strColNameId,
                    $strTableNameId,
                    $strColNameAlias,
                    implode(',', array_fill(0, count($values), '?'))
                ))
                ->execute($values);

            $values = $objSelectIds->fetchEach($strColNameId);
        } else {
            $values = array_map('intval', $values);
        }

        return $values;
    }
}
