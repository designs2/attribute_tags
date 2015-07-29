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

use MetaModels\Attribute\ITranslated;
use MetaModels\Filter\Rules\StaticIdList;
use MetaModels\Filter\IFilter;
use MetaModels\IItem;
use MetaModels\IItems;
use MetaModels\IMetaModel;

/**
 * This is the MetaModelAttribute class for handling tag attributes.
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
     * {@inheritDoc}
     */
    protected function checkConfiguration()
    {
        return parent::checkConfiguration()
            && (null !== $this->getTagMetaModel());
    }

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
     * @param string[] $valueIds The ids of the values to retrieve.
     *
     * @return array
     */
    protected function getValuesById($valueIds)
    {
        $recursionKey = $this->getMetaModel()->getTableName();
        $metaModel    = $this->getTagMetaModel();
        $filter       = $metaModel->getEmptyFilter();
        $filter->addFilterRule(new StaticIdList($valueIds));

        // Prevent recursion.
        static $tables = array();
        if (isset($tables[$recursionKey])) {
            return array();
        }
        $tables[$recursionKey] = $recursionKey;

        $items = $metaModel->findByFilter($filter, $this->getSortingColumn());
        unset($tables[$recursionKey]);

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
     * Sort an id list by the option column.
     *
     * @param array $idList The id list to sort.
     *
     * @return array
     */
    private function sortIdsBySortingColumn($idList)
    {
        // Now sort the values according to our sorting column.
        $filter = $this->getTagMetaModel()->getEmptyFilter();
        // Add some more filter rules.
        $filter->addFilterRule(new StaticIdList(array_keys($idList)));

        $items = $this->getTagMetaModel()->findByFilter($filter, $this->getSortingColumn());

        return array_keys(
            $this->convertItemsToFilterOptions($items, $this->getValueColumn(), $this->getAliasColumn())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function valueToWidget($varValue)
    {
        // If we have a tree picker, the value must be a comma separated string.
        if (empty($varValue)) {
            return array();
        }

        $aliasColumn = $this->getAliasColumn();
        $arrResult   = array();

        if ($varValue) {
            foreach ($varValue as $arrValue) {
                $aliasValue = isset($arrValue[$aliasColumn]) && !empty($arrValue[$aliasColumn])
                    ? $arrValue[$aliasColumn]
                    : $arrValue[self::TAGS_RAW][$aliasColumn];

                if (!empty($aliasValue)) {
                    $arrResult[$arrValue[self::TAGS_RAW]['id']] = $aliasValue;
                }
            }
        }

        // Sorting of values must be done.
        $arrResult = $this->sortIdsBySortingColumn($arrResult);

        if ($this->isTreePicker()) {
            return implode(',', $arrResult);
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
        $model     = $this->getTagMetaModel();
        $alias     = $this->getAliasColumn();
        $attribute = $model->getAttribute($alias);
        $valueIds  = array();

        if ($attribute) {
            // It is an attribute, we may search for it.
            foreach ($varValue as $value) {
                if ($attribute instanceof ITranslated) {
                    $ids = $attribute->searchForInLanguages(
                        $value,
                        array($model->getActiveLanguage(), $model->getFallbackLanguage())
                    );
                } else {
                    $ids = $attribute->searchFor($value);
                }
                // If all match, return all.
                if (null === $ids) {
                    $valueIds = $model->getIdsFromFilter($model->getEmptyFilter(), $alias);
                    break;
                }
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
                            'SELECT v.id FROM %1$s AS v WHERE v.%2$s IN (%3$s) LIMIT 1',
                            $model->getTableName(),
                            $alias,
                            $this->parameterMask($varValue)
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
        $filter = '';
        $ids    = array();
        foreach ($items as $item) {
            $ids[] = $item->get('id');
        }

        if ($ids) {
            $filter = sprintf(' AND value_id IN (%1$s)', $this->parameterMask($ids));
        }

        $counts = $this
            ->getDatabase()
            ->prepare(
                sprintf(
                    'SELECT value_id, COUNT(item_id) AS amount FROM %1$s WHERE att_id="%2$s" %3$s GROUP BY value_id',
                    $this->getReferenceTable(),
                    $this->get('id'),
                    $filter
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
        if (!$this->isProperlyConfigured()) {
            return array();
        }
        $strDisplayValue = $this->getValueColumn();
        $strSortingValue = $this->getSortingColumn();

        $filter = $this->getTagMetaModel()->getEmptyFilter();

        $this->buildFilterRulesForFilterSetting($filter);

        // Add some more filter rules.
        if ($usedOnly) {
            $this->buildFilterRulesForUsedOnly($filter, $idList ? $idList : array());
        } elseif ($idList && is_array($idList)) {
            $filter->addFilterRule(new StaticIdList($idList));
        }

        $objItems = $this->getTagMetaModel()->findByFilter($filter, $strSortingValue);

        if ($arrCount !== null) {
            $this->calculateFilterOptionsCount($objItems, $arrCount);
        }

        return $this->convertItemsToFilterOptions($objItems, $strDisplayValue, $this->getAliasColumn(), $arrCount);
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
        if (!$this->get('tag_filter')) {
            return;
        }

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
            $arrUsedValues = $this
                ->getDatabase()
                ->prepare(
                    'SELECT value_id AS value
                     FROM tl_metamodel_tag_relation
                     WHERE att_id = ?
                     GROUP BY value'
                )
                ->execute($params)
                ->fetchEach('value');

        } else {
            $query = sprintf(
                'SELECT value_id AS value
                    FROM tl_metamodel_tag_relation
                    WHERE att_id = ?
                      AND item_id IN (%s)
                    GROUP BY value',
                $this->parameterMask($idList)
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
     *
     * @param string         $displayValue The name of the attribute to use as value.
     *
     * @param string         $aliasColumn  The name of the attribute to use as alias.
     *
     * @param null|string[]  $count        The counter array.
     *
     * @return array
     */
    protected function convertItemsToFilterOptions($items, $displayValue, $aliasColumn, &$count = null)
    {
        $result = array();
        foreach ($items as $item) {
            $parsedDisplay = $item->parseAttribute($displayValue);
            $parsedAlias   = $item->parseAttribute($aliasColumn);

            $textValue  = isset($parsedDisplay['text'])
                ? $parsedDisplay['text']
                : $item->get($displayValue);
            $aliasValue = isset($parsedAlias['text'])
                ? $parsedAlias['text']
                : $item->get($aliasColumn);

            $result[$aliasValue] = $textValue;

            if (null !== $count) {
                if (isset($count[$item->get('id')])) {
                    $count[$aliasValue] = $count[$item->get('id')];
                    unset($count[$item->get('id')]);
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataFor($arrIds)
    {
        if (!$this->isProperlyConfigured()) {
            return array();
        }

        $rows = $this
            ->getDatabase()
            ->prepare(
                sprintf(
                    'SELECT item_id AS id, value_id AS value
                    FROM tl_metamodel_tag_relation
                    WHERE tl_metamodel_tag_relation.item_id IN (%1$s)
                    AND att_id = ?
                    ORDER BY tl_metamodel_tag_relation.value_sorting',
                    $this->parameterMask($arrIds)
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

        $values = $this->getValuesById($referenceIds);
        $result = array();
        foreach ($valueIds as $itemId => $tagIds) {
            foreach ($tagIds as $tagId) {
                $result[$itemId][$tagId] = $values[$tagId];
            }
        }

        return $result;
    }
}
