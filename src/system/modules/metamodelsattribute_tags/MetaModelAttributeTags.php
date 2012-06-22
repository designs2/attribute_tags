<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package	   MetaModels
 * @subpackage AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  CyberSpectrum
 * @license    private
 * @filesource
 */
if (!defined('TL_ROOT'))
{
	die('You cannot access this file directly!');
}

/**
 * This is the MetaModelAttribute class for handling tag attributes.
 * 
 * @package	   MetaModels
 * @subpackage AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class MetaModelAttributeTags extends MetaModelAttributeComplex
{

	/**
	 * Determine the column to be used for alias.
	 * This is either the configured alias column or the id, if
	 * an alias column is absent.
	 * 
	 * @return string the name of the column.
	 */
	public function getAliasCol()
	{
		$strColNameAlias = $this->get('tag_alias');
		if (!$strColNameAlias)
		{
			$strColNameAlias = $this->get('tag_id');
		}
		return $strColNameAlias;
	}


	/////////////////////////////////////////////////////////////////
	// interface IMetaModelAttribute
	/////////////////////////////////////////////////////////////////

	/**
	 * {@inheritdoc}
	 */
	public function getAttributeSettingNames()
	{
		return array_merge(parent::getAttributeSettingNames(), array(
			'tag_table',
			'tag_column',
			'tag_id',
			'tag_alias',
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFieldDefinition()
	{
		// TODO: add tree support here.
		$arrFieldDef=parent::getFieldDefinition();
		$arrFieldDef['inputType'] = 'checkboxWizard';
		$arrFieldDef['options'] = $this->getFilterOptions();
		$arrFieldDef['eval']['includeBlankOption'] = true;
		$arrFieldDef['eval']['multiple'] = true;
		return $arrFieldDef;
	}

	public function parseValue($arrRowData, $strOutputFormat = 'text')
	{
		$arrResult = parent::parseValue($arrRowData, $strOutputFormat);
		$arrValue = array();

		if ($arrRowData[$this->getColName()])
		{
			foreach ($arrRowData[$this->getColName()] as $arrTag)
			{
				$arrValue[] = $arrTag[$this->get('tag_column')];
			}
		}

		$arrResult['text'] = implode(', ', $arrValue);
		return $arrResult;
	}

	/**
	 * {@inheritdoc}
	 */
	public function parseFilterUrl($arrUrlParams)
	{
		$objFilterRule = NULL;
		if (key_exists($this->getColName(), $arrUrlParams))
		{
			$objFilterRule = new MetaModelFilterRuleTags($this, $arrUrlParams[$this->getColName()]);
		}
		return $objFilterRule;
	}

	/**
	 * {@inheritdoc}
	 * 
	 * Fetch filter options from foreign table.
	 * 
	 */
	public function getFilterOptions($arrIds = array())
	{
		$strTableName = $this->get('tag_table');
		$strColNameId = $this->get('tag_id');

		$arrReturn = array();

		if ($strTableName && $strColNameId)
		{
			$strColNameValue = $this->get('tag_column');
			$strColNameAlias = $this->getAliasCol();
			$objDB = Database::getInstance();
			if ($arrIds)
			{
				$objValue = $objDB->prepare(sprintf('
					SELECT %1$s.*
					FROM %1$s
					LEFT JOIN tl_metamodel_tag_relation ON (
						(tl_metamodel_tag_relation.att_id=?)
						AND (tl_metamodel_tag_relation.value_id=%1$s.%2$s)
					)
					WHERE tl_metamodel_tag_relation.item_id IN (%3$s) GROUP BY %1$s.%2$s',
					$strTableName, // 1
					$strColNameId, // 2
					implode(',', $arrIds) // 3
				))
				->execute($this->get('id'));
			} else {
				$objValue = $objDB->prepare(sprintf('SELECT %1$s.* FROM %1$s', $strTableName))
				->execute();
			}

			while ($objValue->next())
			{
				$arrReturn[$objValue->$strColNameAlias] = $objValue->$strColNameValue;
			}
		}
		return $arrReturn;
	}

	/////////////////////////////////////////////////////////////////
	// interface IMetaModelAttributeComplex
	/////////////////////////////////////////////////////////////////

	public function getDataFor($arrIds)
	{
		$strTableName = $this->get('tag_table');
		$strColNameId = $this->get('tag_id');
		$arrReturn = array();

		if ($strTableName && $strColNameId)
		{
			$objDB = Database::getInstance();
			$strMetaModelTableName = $this->getMetaModel()->getTableName();
			$strMetaModelTableNameId = $strMetaModelTableName.'_id';

			$objValue = $objDB->prepare(sprintf('
				SELECT %1$s.*, tl_metamodel_tag_relation.item_id AS %2$s
				FROM %1$s
				LEFT JOIN tl_metamodel_tag_relation ON (
					(tl_metamodel_tag_relation.att_id=?)
					AND (tl_metamodel_tag_relation.value_id=%1$s.%3$s)
				)
				WHERE tl_metamodel_tag_relation.item_id IN (%4$s)',
				$strTableName, // 1
				$strMetaModelTableNameId, // 2
				$strColNameId, // 3
				implode(',', $arrIds) // 4
			))
			->execute($this->get('id'));

			while ($objValue->next())
			{
				if(!$arrReturn[$objValue->$strMetaModelTableNameId])
				{
					$arrReturn[$objValue->$strMetaModelTableNameId] = array();
				}
				$arrData = $objValue->row();
				unset($arrData[$strMetaModelTableNameId]);
				$arrReturn[$objValue->$strMetaModelTableNameId][$objValue->$strColNameId] = $arrData;
			}
		}
		return $arrReturn;
	}

	public function setDataFor($arrValues)
	{
		// TODO: store to database.
	}
}

?>