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
 * @copyright The MetaModels team.
 * @license   LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Tags;

use MetaModels\Attribute\BaseComplex;
use MetaModels\Render\Template;
use MetaModels\Filter\Rules\FilterRuleTags;
use \Contao\Database\Result;

/**
 * This is the MetaModelAttribute class for handling tag attributes.
 *
 * @package    AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 */
class Tags extends BaseComplex
{

    /**
     * The widget mode to use.
     *
     * @var int
     */
    protected $widgetMode;

    /**
     * Determine if we want to use tree selection.
     *
     * @return bool
     */
    protected function isTreePicker()
    {
        return $this->widgetMode == 2;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareTemplate(Template $objTemplate, $arrRowData, $objSettings = null)
    {
        parent::prepareTemplate($objTemplate, $arrRowData, $objSettings);
        $objTemplate->alias = $this->get('tag_alias');
        $objTemplate->value = $this->get('tag_column');
    }

    /**
     * Determine the column to be used for alias.
     *
     * This is either the configured alias column or the id, if an alias column is absent.
     *
     * @return string the name of the column.
     */
    public function getAliasCol()
    {
        $strColNameAlias = $this->get('tag_alias');
        if ($this->isTreePicker() || !$strColNameAlias) {
            $strColNameAlias = $this->get('tag_id');
        }
        return $strColNameAlias;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeSettingNames()
    {
        return array_merge(
            parent::getAttributeSettingNames(),
            array(
                'tag_table',
                'tag_column',
                'tag_id',
                'tag_alias',
                'tag_where',
                'tag_sorting',
                'tag_as_wizard',
                'mandatory',
                'filterable',
                'searchable',
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDefinition($arrOverrides = array())
    {
        $arrFieldDef      = parent::getFieldDefinition($arrOverrides);
        $this->widgetMode = $arrOverrides['tag_as_wizard'];
        if ($this->isTreePicker()) {
            $arrFieldDef['inputType']          = 'DcGeneralTreePicker';
            $arrFieldDef['eval']['sourceName'] = $this->get('tag_table');
            $arrFieldDef['eval']['sourceName'] = $this->get('tag_table');
            $arrFieldDef['eval']['fieldType']  = 'checkbox';
        } elseif ($this->widgetMode == 1) {
            // If tag as wizard is true, change the input type.
            $arrFieldDef['inputType'] = 'checkboxWizard';
        } else {
            $arrFieldDef['inputType'] = 'checkbox';
        }

        $arrFieldDef['options']                    = $this->getFilterOptions(null, false);
        $arrFieldDef['eval']['includeBlankOption'] = true;
        $arrFieldDef['eval']['multiple']           = true;
        return $arrFieldDef;
    }

    /**
     * {@inheritdoc}
     */
    public function valueToWidget($varValue)
    {
        $strColNameAlias = $this->getAliasCol();

        $arrResult = array();
        if ($varValue) {
            foreach ($varValue as $arrValue) {
                $arrResult[] = $arrValue[$strColNameAlias];
            }
        }
        return $arrResult;
    }

    /**
     * {@inheritdoc}
     */
    // @codingStandardsIgnoreStart - ignore unused parameter $intId.
    public function widgetToValue($varValue, $intId)
    {
        // If we are in tree mode, we got a comma separate list.
        if ($this->isTreePicker() && !empty($varValue) && !is_array($varValue)) {
            $varValue = explode(',', $varValue);
        }

        if ((!is_array($varValue)) || empty($varValue)) {
            return array();
        }

        $arrSearch = array();
        $arrParams = array();
        foreach ($varValue as $strValue) {
            $arrSearch[] = '?';
            $arrParams[] = $strValue;
        }
        $objDB    = \Database::getInstance();
        $objValue = $objDB
            ->prepare(
                sprintf('
                    SELECT %1$s.*
                    FROM %1$s
                    WHERE %2$s IN (%3$s)',
                    $this->get('tag_table'),
                    $this->getAliasCol(),
                    implode(',', $arrSearch)
                )
            )
            ->execute($arrParams);

        $strColNameId = $this->get('tag_id');
        $arrResult    = array();

        while ($objValue->next()) {
            // Adding the sorting from widget.
            $strAlias                                                 = $this->getAliasCol();
            $arrResult[$objValue->$strColNameId]                      = $objValue->row();
            $arrResult[$objValue->$strColNameId]['tag_value_sorting'] = array_search($objValue->$strAlias, $varValue);
        }

        return $arrResult;
    }
    // @codingStandardsIgnoreEnd

    /**
     * {@inheritdoc}
     *
     * Fetch filter options from foreign table.
     *
     */
    public function getFilterOptions($arrIds, $usedOnly, &$arrCount = null)
    {
        $strTableName    = $this->get('tag_table');
        $strColNameId    = $this->get('tag_id');
        $strSortColumn   = $this->get('tag_sorting') ?: $strColNameId;
        $strColNameWhere = ($this->get('tag_where') ? html_entity_decode($this->get('tag_where')) : false);
        $objDB           = \Database::getInstance();

        $arrReturn = array();

        if ($objDB->tableExists($strTableName) && $strTableName && $strColNameId && $strSortColumn) {
            $strColNameValue = $this->get('tag_column');
            $strColNameAlias = $this->getAliasCol();

            if ($arrIds) {
                if ($usedOnly) {
                    $strSQL = '
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
                    $strSQL = '
                        SELECT COUNT(rel.value_id) as mm_count, %1$s.*
                        FROM %1$s
                        LEFT JOIN tl_metamodel_tag_relation as rel ON (
                            (rel.att_id=?) AND (rel.value_id=%1$s.%2$s)
                        )
                        WHERE %1$s.%2$s IN (%3$s)%5$s
                        GROUP BY %1$s.%2$s
                        ORDER BY %1$s.%4$s';
                }

                $objValue = $objDB->prepare(
                    sprintf(
                        $strSQL,
                        // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                        $strTableName,                                              // 1
                        $strColNameId,                                              // 2
                        implode(',', $arrIds),                                      // 3
                        $strSortColumn,                                             // 4
                        ($strColNameWhere ? ' AND (' . $strColNameWhere . ')' : '') // 5
                    // @codingStandardsIgnoreEnd
                    )
                )->execute($this->get('id'));
            } else {
                if ($usedOnly) {
                    $strSQL = '
                        SELECT COUNT(%1$s.%3$s) as mm_count, %1$s.*
                        FROM %1$s
                        INNER JOIN tl_metamodel_tag_relation as rel
                        ON (
                            (rel.att_id="%4$s") AND (rel.value_id=%1$s.%3$s)
                        )
                        WHERE rel.att_id=%4$s'
                        . ($strColNameWhere ? ' AND %5$s' : '') . '
                        GROUP BY %1$s.%3$s
                        ORDER BY %1$s.%2$s';
                } else {
                    $strSQL = '
                        SELECT COUNT(rel.value_id) as mm_count, %1$s.*
                        FROM %1$s
                        LEFT JOIN tl_metamodel_tag_relation as rel
                        ON (
                            (rel.att_id="%4$s") AND (rel.value_id=%1$s.%3$s)
                        )'
                        . ($strColNameWhere ? ' WHERE %5$s' : '') . '
                        GROUP BY %1$s.%3$s
                        ORDER BY %1$s.%2$s';
                }

                $objValue = $objDB->prepare(
                    sprintf(
                        $strSQL,
                        // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                        $strTableName,    // 1
                        $strSortColumn,   // 2
                        $strColNameId,    // 3
                        $this->get('id'), // 4
                        $strColNameWhere  // 5
                    // @codingStandardsIgnoreEnd
                    )
                )->execute();
            }

            while ($objValue->next()) {
                if (is_array($arrCount)) {
                    $arrCount[$objValue->$strColNameAlias] = $objValue->mm_count;
                }

                $arrReturn[$objValue->$strColNameAlias] = $objValue->$strColNameValue;
            }
        }

        return $arrReturn;
    }

    /**
     * {@inheritdoc}
     */
    public function searchFor($strPattern)
    {
        $objFilterRule = new FilterRuleTags($this, $strPattern);
        return $objFilterRule->getMatchingIds();
    }

    /**
     * {@inheritdoc}
     */
    public function getDataFor($arrIds)
    {
        $strTableName = $this->get('tag_table');
        $strColNameId = $this->get('tag_id');
        $objDB        = \Database::getInstance();
        $arrReturn    = array();

        if ($objDB->tableExists($strTableName) && $strTableName && $strColNameId) {
            $strMetaModelTableName   = $this->getMetaModel()->getTableName();
            $strMetaModelTableNameId = $strMetaModelTableName . '_id';

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
                        $strMetaModelTableNameId, // 2
                        $strColNameId,            // 3
                        implode(',', $arrIds)     // 4
                    // @codingStandardsIgnoreEnd
                    )
                )
                ->execute($this->get('id'));

            while ($objValue->next()) {
                if (!$arrReturn[$objValue->$strMetaModelTableNameId]) {
                    $arrReturn[$objValue->$strMetaModelTableNameId] = array();
                }
                $arrData = $objValue->row();
                unset($arrData[$strMetaModelTableNameId]);
                $arrReturn[$objValue->$strMetaModelTableNameId][$objValue->$strColNameId] = $arrData;
            }
        }
        return $arrReturn;
    }

    /**
     * Update the tag ids for a given item.
     *
     * @param int    $itemId         The item for which data shall be set for.
     *
     * @param array  $tags           The tag ids that shall be set for the item.
     *
     * @param Result $existingTagIds The sql result containing the tag ids present in the database.
     *
     * @return array
     */
    protected function setDataForItem($itemId, $tags, $existingTagIds)
    {
        $objDB = \Database::getInstance();

        if ($tags === null) {
            $tagIds = array();
        } else {
            $tagIds = array_map('intval', array_keys($tags));
        }
        $thisExisting = array();

        // Determine existing tags for this item.
        if (($existingTagIds->item_id == $itemId)) {
            $thisExisting[] = $existingTagIds->value_id;
        }
        while ($existingTagIds->next() && ($existingTagIds->item_id == $itemId)) {
            $thisExisting[] = $existingTagIds->value_id;
        }

        // First pass, delete all not mentioned anymore.
        $valuesToRemove = array_diff($thisExisting, $tagIds);
        if ($valuesToRemove) {
            $objDB
                ->prepare(
                    sprintf(
                        'DELETE FROM tl_metamodel_tag_relation
                        WHERE
                        att_id=?
                        AND item_id=?
                        AND value_id IN (%s)',
                        implode(',', $valuesToRemove)
                    )
                )
                ->execute($this->get('id'), $itemId);
        }

        // Second pass, add all new values in a row.
        $valuesToAdd  = array_diff($tagIds, $thisExisting);
        $insertValues = array();
        if ($valuesToAdd) {
            foreach ($valuesToAdd as $valueId) {
                $insertValues[] = sprintf(
                    '(%s,%s,%s,%s)',
                    $this->get('id'),
                    $itemId,
                    (int)$tags[$valueId]['tag_value_sorting'],
                    $valueId
                );
            }
        }

        // Third pass, update all sorting values.
        $valuesToUpdate = array_diff($tagIds, $valuesToAdd);
        if ($valuesToUpdate) {
            foreach ($valuesToUpdate as $valueId) {
                if (!array_key_exists('tag_value_sorting', $tags[$valueId])) {
                    continue;
                }

                $objDB
                    ->prepare(
                        'UPDATE tl_metamodel_tag_relation
                        SET value_sorting = ' . (int)$tags[$valueId]['tag_value_sorting'] . '
                        WHERE
                        att_id=?
                        AND item_id=?
                        AND value_id=?'
                    )
                    ->execute($this->get('id'), $itemId, $valueId);
            }
        }

        return $insertValues;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataFor($arrValues)
    {
        $database = \Database::getInstance();
        $itemIds  = array_map('intval', array_keys($arrValues));
        sort($itemIds);
        // Load all existing tags for all items to be updated, keep the ordering to item Id
        // so we can benefit from the batch deletion and insert algorithm.
        $existingTagIds = $database
            ->prepare(
                sprintf(
                    'SELECT * FROM tl_metamodel_tag_relation
                    WHERE
                    att_id=?
                    AND item_id IN (%1$s)
                    ORDER BY item_id ASC',
                    implode(',', $itemIds)
                )
            )
            ->execute($this->get('id'));

        // Now loop over all items and update the values for them.
        // NOTE: we can not loop over the original array, as the item ids are not neccessarily
        // sorted ascending by item id.
        $insertValues = array();
        foreach ($itemIds as $itemId) {
            $insertValues = array_merge(
                $insertValues,
                $this->setDataForItem($itemId, $arrValues[$itemId], $existingTagIds)
            );
        }

        if ($insertValues) {
            $database->execute(
                'INSERT INTO tl_metamodel_tag_relation
                (att_id, item_id, value_sorting, value_id)
                VALUES ' . implode(',', $insertValues)
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException When an invalid id array has been passed.
     */
    public function unsetDataFor($arrIds)
    {
        if ($arrIds) {
            if (!is_array($arrIds)) {
                throw new \RuntimeException(
                    'MetaModelAttributeTags::unsetDataFor() invalid parameter given! Array of ids is needed.',
                    1
                );
            }
            $objDB = \Database::getInstance();
            $objDB->prepare(
                sprintf(
                    'DELETE FROM tl_metamodel_tag_relation
                    WHERE
                    att_id=?
                    AND item_id IN (%s)',
                    implode(',', $arrIds)
                )
            )->execute($this->get('id'));
        }
    }
}
