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
 * @author    Stefan Heimes <stefan_heimes@hotmail.com>
 * @author    Christopher Boelter <christopher@boelter.eu>
 * @copyright The MetaModels team.
 * @license   LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Tags;

use Contao\Database\Result;
use MetaModels\Attribute\BaseComplex;
use MetaModels\Render\Template;
use MetaModels\Filter\Rules\FilterRuleTags;

/**
 * This is the MetaModelAttribute class for handling tag attributes.
 *
 * @package    AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 */
abstract class AbstractTags extends BaseComplex
{
    /**
     * The widget mode to use.
     *
     * @var int
     */
    protected $widgetMode;

    /**
     * Retrieve the database instance.
     *
     * @return \Database
     */
    protected function getDatabase()
    {
        return $this->getMetaModel()->getServiceContainer()->getDatabase();
    }

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
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getTagSource()
    {
        return $this->get('tag_table');
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getIdColumn()
    {
        return $this->get('tag_id') ?: 'id';
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getSortingColumn()
    {
        return $this->get('tag_sorting') ?: $this->getIdColumn();
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getValueColumn()
    {
        return $this->get('tag_column');
    }

    /**
     * Determine the correct alias column to use.
     *
     * @return string
     */
    protected function getAliasColumn()
    {
        $strColNameAlias = $this->get('tag_alias');
        if ($this->isTreePicker() || !$strColNameAlias) {
            $strColNameAlias = $this->get('tag_id');
        }
        return $strColNameAlias;
    }

    /**
     * Determine the correct alias column to use.
     *
     * @return string
     *
     * @deprecated Use the getAliasColumn function instead.
     */
    protected function getAliasCol()
    {
        return $this->getAliasColumn();
    }

    /**
     * Determine the correct where column to use.
     *
     * @return string
     */
    protected function getWhereColumn()
    {
        return $this->get('tag_where') ? html_entity_decode($this->get('tag_where')) : null;
    }

    /**
     * Return the name of the table with the references. (m:n).
     *
     * @return string
     */
    protected function getReferenceTable()
    {
        return 'tl_metamodel_tag_relation';
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareTemplate(Template $objTemplate, $arrRowData, $objSettings)
    {
        parent::prepareTemplate($objTemplate, $arrRowData, $objSettings);
        $objTemplate->alias = $this->getAliasColumn();
        $objTemplate->value = $this->getValueColumn();
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
                'tag_filter',
                'tag_filterparams',
                'tag_sorting',
                'tag_as_wizard',
                'mandatory',
                'submitOnChange',
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
            $arrFieldDef['eval']['sourceName'] = $this->getTagSource();
            $arrFieldDef['eval']['fieldType']  = 'checkbox';
            $arrFieldDef['eval']['idProperty'] = $this->getAliasColumn();
        } elseif ($this->widgetMode == 1) {
            // If tag as wizard is true, change the input type.
            $arrFieldDef['inputType'] = 'checkboxWizard';
        } elseif ($this->widgetMode == 3) {
            $arrFieldDef['inputType']      = 'select';
            $arrFieldDef['eval']['chosen'] = true;
        } else {
            $arrFieldDef['inputType'] = 'checkbox';
        }

        try {
            $arrFieldDef['options'] = $this->getFilterOptions(null, false);
        } catch (\Exception $exception) {
            $arrFieldDef['options'] = 'Error: ' . $exception->getMessage();
        }

        $arrFieldDef['eval']['includeBlankOption'] = true;
        $arrFieldDef['eval']['multiple']           = true;

        return $arrFieldDef;
    }

    /**
     * Translate the values from the widget.
     *
     * @param array $varValue The values.
     *
     * @return array
     */
    abstract protected function getValuesFromWidget($varValue);

    /**
     * {@inheritdoc}
     */
    public function widgetToValue($varValue, $itemId)
    {
        // If we are in tree mode, we got a comma separate list.
        if ($this->isTreePicker() && !empty($varValue) && !is_array($varValue)) {
            $varValue = explode(',', $varValue);
        }

        if ((!is_array($varValue)) || empty($varValue)) {
            return array();
        }

        return $this->getValuesFromWidget($varValue);
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
     * Loop over the Result until the item id is not matching anymore the requested item id.
     *
     * @param string $itemId  The item id for which the ids shall be retrieved.
     *
     * @param Result $allTags The database result from which the ids shall be extracted.
     *
     * @return array
     */
    protected function getExistingTags($itemId, $allTags)
    {
        $thisExisting = array();

        // Determine existing tags for this item.
        /** @noinspection PhpUndefinedFieldInspection */
        if (($allTags->item_id == $itemId)) {
            /** @noinspection PhpUndefinedFieldInspection */
            $thisExisting[] = $allTags->value_id;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        while ($allTags->next() && ($allTags->item_id == $itemId)) {
            /** @noinspection PhpUndefinedFieldInspection */
            $thisExisting[] = $allTags->value_id;
        }

        return $thisExisting;
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
    private function setDataForItem($itemId, $tags, $existingTagIds)
    {
        $database = $this->getDatabase();

        if ($tags === null) {
            $tagIds = array();
        } else {
            $tagIds = array_keys($tags);
        }
        $thisExisting = $this->getExistingTags($itemId, $existingTagIds);

        // First pass, delete all not mentioned anymore.
        $valuesToRemove = array_diff($thisExisting, $tagIds);
        if ($valuesToRemove) {
            $database
                ->prepare(
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
                    (int) $tags[$valueId]['tag_value_sorting'],
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

                $database
                    ->prepare(
                        'UPDATE tl_metamodel_tag_relation
                        SET value_sorting = ' . (int) $tags[$valueId]['tag_value_sorting'] . '
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
        $itemIds  = array_keys($arrValues);
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
                    implode(',', array_fill(0, count($itemIds), '?'))
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
                    'DELETE FROM %s
                    WHERE
                    att_id=?
                    AND item_id IN (%s)',
                    $this->getReferenceTable(),
                    implode(',', $arrIds)
                )
            )->execute($this->get('id'));
        }
    }
}
