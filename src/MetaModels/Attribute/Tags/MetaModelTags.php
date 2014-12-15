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

use MetaModels\Factory as MetaModelFactory;
use MetaModels\Filter\Rules\StaticIdList;
use MetaModels\Filter\Setting\Factory as FilterSettingFactory;
use MetaModels\Filter\IFilter;
use MetaModels\IItem;
use MetaModels\IItems;
use MetaModels\IMetaModel;
use \Contao\Database\Result;

/**
 * This is the MetaModelAttribute class for handling tag attributes.
 *
 * @package    AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 */
class MetaModelTags extends AbstractTags
{
    /**
     * The key in the result array where the RAW values shall be stored.
     */
    const TAGS_RAW = '__TAGS_RAW__';

    /**
     * The MetaModel we are referencing on.
     *
     * @var IMetaModel
     */
    protected $objSelectMetaModel;

    /**
     * Retrieve the linked MetaModel instance.
     *
     * @return IMetaModel
     */
    protected function getTagMetaModel()
    {
        if (empty($this->objSelectMetaModel)) {
            $this->objSelectMetaModel = MetaModelFactory::byTableName($this->getTagSource());
        }

        return $this->objSelectMetaModel;
    }

    /**
     * Retrieve the values with the given ids.
     *
     * @param int[] $valueIds The ids of the values to retrieve.
     *
     * @return array
     */
    protected function getValuesById($valueIds)
    {
        $metaModel = $this->getTagMetaModel();
        $filter    = $metaModel->getEmptyFilter();
        $filter->addFilterRule(new StaticIdList($valueIds));

        $items  = $metaModel->findByFilter($filter, 'id');
        $values = array();
        foreach ($items as $item) {
            $valueId    = $item->get('id');
            $parsedItem = $item->parseValue();

            $values[$valueId] = array_merge(
                array(self::TAGS_RAW => $parsedItem['raw']),
                $parsedItem['text']
            );
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function valueToWidget($varValue)
    {
        $aliasColumn = $this->getAliasColumn();
        $arrResult   = array();

        if ($varValue) {
            foreach ($varValue as $arrValue) {
                $aliasValue = ($aliasColumn == 'id') ? $arrValue[self::TAGS_RAW]['id'] : $arrValue[$aliasColumn];

                if (!empty($aliasValue)) {
                    $arrResult[] = $aliasValue;
                }
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
        if ($this->isTreePicker() && is_string($varValue)) {
            $varValue = trimsplit(',', $varValue);
        }

        if ($varValue == '') {
            $varValue = array();
        }

        if (!is_array($varValue)) {
            throw new \InvalidArgumentException('Incorrect values encountered ' . var_export($varValue, true));
        }

        $model     = $this->getTagMetaModel();
        $alias     = $this->getAliasColumn();
        $attribute = $model->getAttribute($alias);
        $valueId   = array();

        if ($attribute) {
            // It is an attribute, we may search for it.
            foreach($varValue as $value)
            {
                $ids = $attribute->searchFor($value);
                if ($ids) {
                    $valueId = array_merge($valueId, $ids);
                }
            }
        } else {
            // Must be a system column then.
            // Special case first, the id is our alias, easy way out.
            if ($alias === 'id') {
                $valueId = $varValue;
            } else {
                $result = $this->getDatabase()
                    ->prepare(
                        sprintf(
                            'SELECT v.id FROM %1$s AS v WHERE v.%2$s=?',
                            $model,
                            $alias
                        )
                    )
                    ->execute($varValue);

                if (!$result->numRows) {
                    throw new \RuntimeException('Could not translate value ' . var_export($varValue, true));
                }

                $valueId = $result->fetchEach('id');
            }
        }

        return $this->getValuesById($valueId);
    }
    // @codingStandardsIgnoreEnd

    /**
     * {@inheritdoc}
     *
     * Fetch filter options from foreign table.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function getFilterOptions($arrIds, $usedOnly, &$arrCount = null)
    {
        $strDisplayValue    = $this->getValueColumn();
        $strSortingValue    = $this->getSortingColumn();
        $strCurrentLanguage = null;

        if (!($this->getTagMetaModel() && $strDisplayValue)) {
            return array();
        }

        // Change language.
        if (TL_MODE == 'BE') {
            $strCurrentLanguage     = $GLOBALS['TL_LANGUAGE'];
            $GLOBALS['TL_LANGUAGE'] = $this->getMetaModel()->getActiveLanguage();
        }

        $filter = $this->getTagMetaModel()->getEmptyFilter();

        $this->buildFilterRulesForFilterSetting($filter);

        // Add some more filter rules.
        if ($usedOnly) {
            $this->buildFilterRulesForUsedOnly($filter, $arrIds);
        } elseif ($arrIds && is_array($arrIds)) {
            $filter->addFilterRule(new StaticIdList($arrIds));
        }

        $objItems = $this->getTagMetaModel()->findByFilter($filter, $strSortingValue);

        // Reset language.
        if (TL_MODE == 'BE') {
            $GLOBALS['TL_LANGUAGE'] = $strCurrentLanguage;
        }

        return $this->convertItemsToFilterOptions($objItems, $strDisplayValue, $this->getAliasColumn());
    }

    /**
     * Fetch filter options from foreign table taking the given flag into account.
     *
     * @param IFilter $filter The filter to which the rules shall be added to.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function buildFilterRulesForFilterSetting($filter)
    {
        // Set Filter and co.
        $filterSettings = FilterSettingFactory::byId($this->get('tag_filter'));
        if ($filterSettings) {
            $values       = $_GET;
            $presets      = (array) $this->get('tag_filterparams');
            $presetNames  = $filterSettings->getParameters();
            $filterParams = array_keys($filterSettings->getParameterFilterNames());
            $processed    = array();

            // We have to use all the preset values we want first.
            foreach ($presets as $presetName => $preset) {
                if (in_array($presetName, $presetNames)) {
                    $processed[$presetName] = $preset['value'];
                }
            }

            // Now we have to use all FrontEnd filter params, that are either:
            // * not contained within the presets
            // * or are overridable.
            foreach ($filterParams as $parameter) {
                // Unknown parameter? - next please.
                if (!array_key_exists($parameter, $values)) {
                    continue;
                }

                // Not a preset or allowed to override? - use value.
                if ((!array_key_exists($parameter, $presets)) || $presets[$parameter]['use_get']) {
                    $processed[$parameter] = $values[$parameter];
                }
            }

            $filterSettings->addRules($filter, $processed);
        }
    }

    /**
     * Fetch filter options from foreign table taking the given flag into account.
     *
     * @param IFilter $filter The filter to which the rules shall be added to.
     *
     * @param array   $idList The list of ids of items for which the rules shall be added.
     *
     * @return void
     */
    public function buildFilterRulesForUsedOnly($filter, $idList = array())
    {
        $params = array($this->get('id'));

        if (empty($idList)) {
            $query = sprintf(
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                'SELECT value_id
                     FROM tl_metamodel_tag_relation
                     WHERE att_id = ?
                     GROUP BY value_id'
            // @codingStandardsIgnoreEnd
            );

            $arrUsedValues = $this->getDatabase()
                ->prepare($query)
                ->execute($params)
                ->fetchEach('value_id');

        } else {
            $query = sprintf(
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                'SELECT value_id
                    FROM tl_metamodel_tag_relation
                    WHERE att_id = ?
                      AND item_id IN (%s)
                    GROUP BY value_id',
                implode(',', array_fill(0, count($idList), '?')) // 1
            // @codingStandardsIgnoreEnd
            );

            $arrUsedValues = $this->getDatabase()
                ->prepare($query)
                ->execute(array_merge($params, $idList))
                ->fetchEach('value_id');

        }

        $arrUsedValues = array_filter(
            $arrUsedValues,
            function ($value) {
                return !empty($value);
            }
        );

        $filter->addFilterRule(new StaticIdList($arrUsedValues));
    }

    /**
     * Convert a collection of items into a proper filter option list.
     *
     * @param IItems|IItem[] $items        The item collection to convert.
     * @param string         $displayValue The name of the attribute to use as value.
     * @param string         $aliasColumn  The name of the attribute to use as alias.
     *
     * @return array
     */
    protected function convertItemsToFilterOptions($items, $displayValue, $aliasColumn)
    {
        $result = array();
        foreach ($items as $item) {
            $parsed = $item->parseValue();

            $textValue  = ($displayValue == 'id') ? $item->get('id') : $parsed['text'][$displayValue];
            $aliasValue = ($aliasColumn == 'id') ? $item->get('id') :  $parsed['text'][$aliasColumn];

            $result[$aliasValue] = $textValue;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataFor($arrIds)
    {
        $result       = array();
        $displayValue = $this->getValueColumn();
        $metaModel    = $this->getTagMetaModel();

        if ($this->getTagSource() && $metaModel && $displayValue) {
            $rows =  $this->getDatabase()->prepare(
                    sprintf(
                        'SELECT item_id AS id, value_id AS value
                        FROM tl_metamodel_tag_relation
                        WHERE tl_metamodel_tag_relation.item_id IN (%1$s)
                        AND att_id = ?
                        GROUP BY value_id
                        ORDER BY tl_metamodel_tag_relation.value_sorting',
                        // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                        implode(',', array_fill(0, count($arrIds), '?')) // 1
                        // @codingStandardsIgnoreEnd
                    )
                )
                ->executeUncached(array_merge($arrIds, array($this->get('id'))));

            $valueIds     = array();
            $referenceIds = array();

            while ($rows->next()) {
                $valueIds[$rows->id][] = $rows->value;
                $referenceIds[]        = $rows->value;
            }

            $filter = $metaModel->getEmptyFilter();
            $filter->addFilterRule(new StaticIdList($referenceIds));

            $items  = $metaModel->findByFilter($filter, 'id');
            $values = array();
            foreach ($items as $item) {
                $valueId    = $item->get('id');
                $parsedItem = $item->parseValue();

                $values[$valueId] = array_merge(
                    array(self::TAGS_RAW  => $parsedItem['raw']),
                    $parsedItem['text']
                );
            }

            foreach ($valueIds as $itemId => $tagIds) {
                foreach ($tagIds as $tagId) {
                    $result[$itemId][$tagId] = $values[$tagId];
                }
            }
        }

        return $result;
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
        $database = $this->getDatabase();

        if ($tags === null) {
            $tagIds = array();
        } else {
            $tagIds = array_keys($tags);
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
            $database->prepare(
                    sprintf(
                        'DELETE FROM tl_metamodel_tag_relation
                        WHERE att_id=?
                        AND item_id=?
                        AND value_id IN (%s)',
                        implode(',', array_fill(0, count($valuesToRemove), '?'))
                    )
                )
                ->execute(array_merge(array($this->get('id'), $itemId), $valuesToRemove));
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

                $database->prepare(
                        'UPDATE tl_metamodel_tag_relation
                        SET value_sorting = ' . (int)$tags[$valueId]['tag_value_sorting'] . '
                        WHERE att_id=?
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
        if (!($this->getTagSource() && $this->getValueColumn())) {
            return;
        }

        $database = $this->getDatabase();
        $itemIds  = array_map('intval', array_keys($arrValues));
        sort($itemIds);

        // Load all existing tags for all items to be updated, keep the ordering to item Id
        // so we can benefit from the batch deletion and insert algorithm.
        $existingTagIds = $database
            ->prepare(
                sprintf(
                    'SELECT * FROM %1$s
                    WHERE att_id=?
                    AND item_id IN (%2$s)
                    ORDER BY item_id ASC',
                    $this->getReferenceTable(),
                    implode(',', array_fill(0, count($itemIds), '?')) // 1
                )
            )
            ->execute(array_merge(array($this->get('id')), $itemIds));

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
     * Convert the passed values to a list of value ids.
     *
     * @param string[] $values The values to convert.
     *
     * @return int[]
     */
    public function convertValuesToValueIds($values)
    {
        $strColNameAlias = $this->getAliasColumn();

        if ($strColNameAlias) {
            /** @var MetaModelTags $attribute */
            $metaModel       = $this->getTagMetaModel();
            $sanitizedValues = array();
            foreach ($values as $value) {
                $valueIds = $metaModel->getAttribute($strColNameAlias)->searchFor($value);
                if ($valueIds === null) {
                    return null;
                }

                $sanitizedValues = array_merge($valueIds, $sanitizedValues);
            }

            return $sanitizedValues;
        } else {
            $values = array_map('intval', $values);
        }

        return $values;
    }
}
