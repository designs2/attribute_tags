<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package     AttributeTags
 * @author      Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author      Christian de la Haye <service@delahaye.de>
 * @author      Andreas Isaak <info@andreas-isaak.de>
 * @author      Andreas Nölke <zero@brothers-project.de>
 * @author      David Maack <david.maack@arcor.de>
 * @author      Patrick Kahl <kahl.patrick@googlemail.com>
 * @author      Stefan Heimes <stefan_heimes@hotmail.com>
 * @author      Christopher Boelter <christopher@boelter.eu>
 * @copyright   The MetaModels team.
 * @license     LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Tags;

use MetaModels\Filter\Rules\StaticIdList;
use MetaModels\Filter\IFilter;
use MetaModels\IItem;
use MetaModels\IItems;
use MetaModels\IMetaModel;

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
            $this->objSelectMetaModel =
                $this->getMetaModel()->getServiceContainer()->getFactory()->getMetaModel($this->getTagSource());
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
                $aliasValue = isset($arrValue[$aliasColumn]) && !empty($arrValue[$aliasColumn])
                    ? $arrValue[$aliasColumn]
                    : $arrValue[self::TAGS_RAW][$aliasColumn];

                if (!empty($aliasValue)) {
                    $arrResult[] = $aliasValue;
                }
            }
        }

        return $arrResult;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException When values could not be translated.
     */
    protected function getValuesFromWidget($varValue)
    {
        $model     = $this->getTagMetaModel();
        $alias     = $this->getAliasColumn();
        $attribute = $model->getAttribute($alias);
        $valueIds  = array();

        if ($attribute) {
            // It is an attribute, we may search for it.
            foreach ($varValue as $value) {
                $ids = $attribute->searchFor($value);
                if ($ids) {
                    $valueIds = array_merge($valueIds, $ids);
                }
            }
        } else {
            // Must be a system column then.
            // Special case first, the id is our alias, easy way out.
            if ($alias === 'id') {
                $valueIds = $varValue;
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

                /** @noinspection PhpUndefinedFieldInspection */
                if (!$result->numRows) {
                    throw new \RuntimeException('Could not translate value ' . var_export($varValue, true));
                }

                $valueIds = $result->fetchEach('id');
            }
        }

        return $this->getValuesById($valueIds);
    }

    /**
     * Calculate the amount how often each value has been assigned.
     *
     * @param IItems $items       The item list containing the values.
     *
     * @param array  $amountArray The target array to where the counters shall be stored to.
     *
     * @return void
     */
    protected function calculateFilterOptionsCount($items, &$amountArray)
    {
        $ids = array();
        foreach ($items as $item) {
            $ids[] = $item->get('id');
        }

        $counts = $this
            ->getDatabase()
            ->prepare(
                sprintf(
                    'SELECT COUNT(value_id) AS amount FROM %1$s WHERE att_id="%2$s" AND value_id IN (%3$s)',
                    $this->getReferenceTable(),
                    $this->get('id'),
                    implode(',', array_fill(0, count($ids), '?'))
                )
            )
            ->execute($ids);

        while ($counts->next()) {
            /** @noinspection PhpUndefinedFieldInspection */
            $amountArray[$counts->value_id] = $counts->amount;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Fetch filter options from foreign table.
     */
    public function getFilterOptions($idList, $usedOnly, &$arrCount = null)
    {
        $strDisplayValue = $this->getValueColumn();
        $strSortingValue = $this->getSortingColumn();

        if (!($this->getTagMetaModel() && $strDisplayValue)) {
            return array();
        }

        $filter = $this->getTagMetaModel()->getEmptyFilter();

        $this->buildFilterRulesForFilterSetting($filter);

        // Add some more filter rules.
        if ($usedOnly) {
            $this->buildFilterRulesForUsedOnly($filter, $idList);
        } elseif ($idList && is_array($idList)) {
            $filter->addFilterRule(new StaticIdList($idList));
        }

        $objItems = $this->getTagMetaModel()->findByFilter($filter, $strSortingValue);

        if ($arrCount !== null) {
            $this->calculateFilterOptionsCount($objItems, $arrCount);
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
        $filterSettings = $this
            ->getMetaModel()
            ->getServiceContainer()
            ->getFilterFactory()
            ->createCollection($this->get('tag_filter'));

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
                'SELECT value_id AS value
                     FROM tl_metamodel_tag_relation
                     WHERE att_id = ?
                     GROUP BY value'
            // @codingStandardsIgnoreEnd
            );

            $arrUsedValues = $this->getDatabase()
                ->prepare($query)
                ->execute($params)
                ->fetchEach('value');

        } else {
            $query = sprintf(
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                'SELECT value_id AS value
                    FROM tl_metamodel_tag_relation
                    WHERE att_id = ?
                      AND item_id IN (%s)
                    GROUP BY value',
                implode(',', array_fill(0, count($idList), '?')) // 1
            // @codingStandardsIgnoreEnd
            );

            $arrUsedValues = $this->getDatabase()
                ->prepare($query)
                ->execute(array_merge($params, $idList))
                ->fetchEach('value');

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

            $textValue  = isset($parsed['text'][$displayValue])
                ? $parsed['text'][$displayValue]
                : $item->get($displayValue);
            $aliasValue = isset($parsed['text'][$aliasColumn])
                ? $parsed['text'][$aliasColumn]
                : $item->get($aliasColumn);

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
            $rows = $this
                ->getDatabase()
                ->prepare(
                    sprintf(
                        'SELECT item_id AS id, value_id AS value
                        FROM tl_metamodel_tag_relation
                        WHERE tl_metamodel_tag_relation.item_id IN (%1$s)
                        AND att_id = ?
                        ORDER BY tl_metamodel_tag_relation.value_sorting',
                        // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                        implode(',', array_fill(0, count($arrIds), '?')) // 1
                        // @codingStandardsIgnoreEnd
                    )
                )
                ->execute(array_merge($arrIds, array($this->get('id'))));

            $valueIds     = array();
            $referenceIds = array();

            while ($rows->next()) {
                /** @noinspection PhpUndefinedFieldInspection */
                $value = $rows->value;
                /** @noinspection PhpUndefinedFieldInspection */
                $valueIds[$rows->id][] = $value;
                $referenceIds[]        = $value;
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
