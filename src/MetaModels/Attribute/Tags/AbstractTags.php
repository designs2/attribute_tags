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
 * @copyright The MetaModels team.
 * @license   LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Tags;

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
        return \Database::getInstance();
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
     * Determine the correct where column to use.
     *
     * @return string
     */
    protected function getWhereColumn()
    {
        return $this->get('tag_where');
    }

    /**
     * Return the name of the table with the references. (m:n)
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
    protected function prepareTemplate(Template $objTemplate, $arrRowData, $objSettings = null)
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
            $arrFieldDef['eval']['sourceName'] = $this->getTagSource();
            $arrFieldDef['eval']['fieldType']  = 'checkbox';
            $arrFieldDef['eval']['idProperty'] = $this->getAliasColumn();
        } elseif ($this->widgetMode == 1) {
            // If tag as wizard is true, change the input type.
            $arrFieldDef['inputType'] = 'checkboxWizard';
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
     * {@inheritdoc}
     */
    public function searchFor($strPattern)
    {
        $objFilterRule = new FilterRuleTags($this, $strPattern);
        return $objFilterRule->getMatchingIds();
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

    /**
     * Convert the passed values to a list of value ids.
     *
     * @param string[] $values The values to convert.
     *
     * @return int[]
     */
    abstract public function convertValuesToValueIds($values);
}
