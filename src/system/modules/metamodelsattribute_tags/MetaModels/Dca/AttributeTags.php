<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package    MetaModels
 * @subpackage AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\Dca;

use DcGeneral\DataContainerInterface;
use MetaModels\Helper\ContaoController;

/**
 * Supplementary class for handling DCA information for select attributes.
 *
 * @package    MetaModels
 * @subpackage AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class AttributeTags extends Attribute
{
	/**
	 * @var AttributeTags
	 */
	protected static $objInstance = null;

	/**
	 * Get the static instance.
	 *
	 * @static
	 * @return AttributeTags
	 */
	public static function getInstance()
	{
		if (self::$objInstance == null) {
			self::$objInstance = new AttributeTags();
		}
		return self::$objInstance;
	}

	public function getTableNames()
	{
		$objDB = \Database::getInstance();
		return $objDB->listTables();
	}

	public function getColumnNames(DataContainerInterface $objDC)
	{
		$arrFields = array();

		if (($objDC->getEnvironment()->getCurrentModel())
			&& \Database::getInstance()->tableExists($objDC->getEnvironment()->getCurrentModel()->getProperty('tag_table')))
		{
			foreach (\Database::getInstance()->listFields($objDC->getEnvironment()->getCurrentModel()->getProperty('tag_table')) as $arrInfo)
			{
				if ($arrInfo['type'] != 'index')
				{
					$arrFields[$arrInfo['name']] = $arrInfo['name'];
				}
			}
		}

		return $arrFields;
	}

	public function getIntColumnNames(DataContainerInterface $objDC)
	{
		$arrFields = array();

		if (($objDC->getEnvironment()->getCurrentModel())
			&& \Database::getInstance()->tableExists($objDC->getEnvironment()->getCurrentModel()->getProperty('tag_table')))
		{
			foreach (\Database::getInstance()->listFields($objDC->getEnvironment()->getCurrentModel()->getProperty('tag_table')) as $arrInfo)
			{
				if ($arrInfo['type'] != 'index' && $arrInfo['type'] == 'int')
				{
					$arrFields[$arrInfo['name']] = $arrInfo['name'];
				}
			}
		}

		return $arrFields;
	}

	public function checkQuery($varValue, DataContainerInterface $objDC)
	{
		if ($objDC->getEnvironment()->getCurrentModel() && $varValue)
		{
			$objDB = \Database::getInstance();
			$objModel = $objDC->getEnvironment()->getCurrentModel();

			$strTableName = $objModel->getProperty('tag_table');
			$strColNameId = $objModel->getProperty('tag_id');
			$strColNameValue = $objModel->getProperty('tag_column');
			$strColNameAlias = $objModel->getProperty('tag_alias') ? $objModel->getProperty('tag_alias') : $strColNameId;
			$strSortColumn = $objModel->getProperty('tag_sorting') ? $objModel->getProperty('tag_sorting') : $strColNameId;

			$strColNameWhere = $varValue;

			$strQuery = sprintf('SELECT %1$s.*
			FROM %1$s%2$s ORDER BY %1$s.%3$s',
				$strTableName, //1
				($strColNameWhere ? ' WHERE ('.$strColNameWhere.')' : false), //2
				$strSortColumn // 3
			);

			// replace inserttags but do not cache
			$strQuery = ContaoController::getInstance()->replaceInsertTags($strQuery, false);

			try
			{
				$objValue = $objDB->prepare($strQuery)
					->execute();
			}
			catch(\Exception $e)
			{
				// add error
				$objDC->addError($GLOBALS['TL_LANG']['tl_metamodel_attribute']['sql_error']);

				// log error
				$this->log($e->getMessage(), 'TableMetaModelsAttributeTags checkQuery()', TL_ERROR);

				// keep the current value
				return $objModel->getProperty('tag_where');
			}
		}

		return $varValue;
	}
}
