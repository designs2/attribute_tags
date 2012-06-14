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

	public function getOptions($blnUsedOnly=false)
	{
		return array();
	}

	/////////////////////////////////////////////////////////////////
	// interface IMetaModelAttribute
	/////////////////////////////////////////////////////////////////

	public function getAttributeSettingNames()
	{
		return array_merge(parent::getAttributeSettingNames(), array(
			'tag_table',
			'tag_column',
			'tag_id',
			'tag_alias',
		));
	}

	public function getFieldDefinition()
	{
		// TODO: add tree support here.
		$arrFieldDef=parent::getFieldDefinition();
		$arrFieldDef['inputType'] = 'select';
		$arrFieldDef['options'] = $this->getOptions();
		return $arrFieldDef;
	}

	public function parseValue($arrRowData, $strOutputFormat = 'html')
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

		$arrResult['html'] = implode(', ', $arrValue);
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

	/////////////////////////////////////////////////////////////////
	// interface IMetaModelAttributeComplex
	/////////////////////////////////////////////////////////////////

	public function getDataFor($arrIds)
	{
		$objDB = Database::getInstance();
		$strTableName = $this->get('tag_table');
		$strColNameId = $this->get('tag_id');
		$arrReturn = array();

		if ($strTableName && $strColNameId)
		{
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
				$arrReturn[$objValue->$strMetaModelTableNameId][] = $objValue->row();
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