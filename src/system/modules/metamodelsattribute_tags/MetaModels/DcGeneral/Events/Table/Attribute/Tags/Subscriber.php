<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package    AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\DcGeneral\Events\Table\Attribute\Tags;

use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;
use ContaoCommunityAlliance\Contao\Bindings\Events\Controller\ReplaceInsertTagsEvent;
use ContaoCommunityAlliance\Contao\EventDispatcher\Event\CreateEventDispatcherEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\EncodePropertyValueFromWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetPropertyOptionsEvent;
use ContaoCommunityAlliance\DcGeneral\Factory\Event\BuildDataDefinitionEvent;
use MetaModels\DcGeneral\Events\BaseSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handle events for tl_metamodel_attribute for tag attributes.
 *
 * @package AttributeTags
 * @author  Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class Subscriber
	extends BaseSubscriber
{
	/**
	 * Register all listeners to handle creation of a data container.
	 *
	 * @param CreateEventDispatcherEvent $event The event.
	 *
	 * @return void
	 */
	public static function registerEvents(CreateEventDispatcherEvent $event)
	{
		$dispatcher = $event->getEventDispatcher();
		self::registerBuildDataDefinitionFor(
			'tl_metamodel_attribute',
			$dispatcher,
			__CLASS__ . '::registerTableMetaModelAttributeEvents'
		);
	}

	/**
	 * Register the events for table tl_metamodel_attribute.
	 *
	 * @param BuildDataDefinitionEvent $event The event being processed.
	 *
	 * @return void
	 */
	public static function registerTableMetaModelAttributeEvents(BuildDataDefinitionEvent $event)
	{
		static $registered;
		if ($registered)
		{
			return;
		}
		$registered = true;
		$dispatcher = $event->getDispatcher();

		self::registerListeners(
			array(
				GetPropertyOptionsEvent::NAME => __CLASS__ . '::getTableNames',
			),
			$dispatcher,
			array('tl_metamodel_attribute', 'tag_table')
		);

		self::registerListeners(
			array(
				GetPropertyOptionsEvent::NAME => __CLASS__ . '::getColumnNames',
			),
			$dispatcher,
			array('tl_metamodel_attribute', 'tag_column')
		);

		self::registerListeners(
			array(
				GetPropertyOptionsEvent::NAME => __CLASS__ . '::getIntColumnNames',
			),
			$dispatcher,
			array('tl_metamodel_attribute', 'tag_id')
		);

		self::registerListeners(
			array(
				GetPropertyOptionsEvent::NAME => __CLASS__ . '::getColumnNames',
			),
			$dispatcher,
			array('tl_metamodel_attribute', 'tag_alias')
		);

		self::registerListeners(
			array(
				GetPropertyOptionsEvent::NAME => __CLASS__ . '::getColumnNames',
			),
			$dispatcher,
			array('tl_metamodel_attribute', 'tag_sorting')
		);

		self::registerListeners(
			array(
				EncodePropertyValueFromWidgetEvent::NAME => __CLASS__ . '::ensureCustomQueryIsValid',
			),
			$dispatcher,
			array('tl_metamodel_attribute', 'tag_where')
		);
	}

	/**
	 * Retrieve all database table names.
	 *
	 * @param GetPropertyOptionsEvent $event The event.
	 *
	 * @return void
	 */
	public static function getTableNames(GetPropertyOptionsEvent $event)
	{
		$objDB = \Database::getInstance();
		$event->setOptions($objDB->listTables());
	}

	/**
	 * Retrieve all column names for the current selected table.
	 *
	 * @param GetPropertyOptionsEvent $event The event.
	 *
	 * @return void
	 */
	public static function getColumnNames(GetPropertyOptionsEvent $event)
	{
		$model   = $event->getModel();
		$table   = $model->getProperty('tag_table');
		$databse = \Database::getInstance();

		if (!$table || !$databse->tableExists($table))
		{
			return;
		}

		$result = array();

		foreach ($databse->listFields($table) as $arrInfo)
		{
			if ($arrInfo['type'] != 'index')
			{
				$result[$arrInfo['name']] = $arrInfo['name'];
			}
		}

		$event->setOptions($result);
	}

	/**
	 * Retrieve all column names of type int for the current selected table.
	 *
	 * @param GetPropertyOptionsEvent $event The event.
	 *
	 * @return void
	 */
	public static function getIntColumnNames(GetPropertyOptionsEvent $event)
	{
		$model   = $event->getModel();
		$table   = $model->getProperty('tag_table');
		$databse = \Database::getInstance();

		if (!$table || !$databse->tableExists($table))
		{
			return;
		}

		$result = array();

		foreach ($databse->listFields($table) as $arrInfo)
		{
			if ($arrInfo['type'] != 'index' && $arrInfo['type'] == 'int')
			{
				$result[$arrInfo['name']] = $arrInfo['name'];
			}
		}

		$event->setOptions($result);
	}

	/**
	 * Called by tl_metamodel_attribute.tag_where onsave_callback.
	 *
	 * Check if the select_where value is valid by firing a test query.
	 *
	 * @param EncodePropertyValueFromWidgetEvent $event The event.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When no table name has been given.
	 */
	public static function ensureCustomQueryIsValid(EncodePropertyValueFromWidgetEvent $event)
	{
		$values = $event->getPropertyValueBag();
		$value  = $event->getValue();

		if ($value)
		{
			$db = \Database::getInstance();

			$tableName    = $values->getPropertyValue('tag_table');
			$colNameId    = $values->getPropertyValue('tag_id');
			$sortColumn   = $values->getPropertyValue('tag_sorting') ?: $colNameId;
			$colNameWhere = $value;

			$query = sprintf('
				SELECT %1$s.*
				FROM %1$s%2$s
				ORDER BY %1$s.%3$s',
				// @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
				$tableName,                                                // 1
				($colNameWhere ? ' WHERE ('.$colNameWhere.')' : false),    // 2
				$sortColumn                                                // 3
			// @codingStandardsIgnoreEnd
			);

			// Replace insert tags but do not cache.
			/** @var EventDispatcherInterface $dispatcher */
			$dispatcher = $GLOBALS['container']['event-dispatcher'];
			$event      = new ReplaceInsertTagsEvent($query, false);
			$dispatcher->dispatch(ContaoEvents::CONTROLLER_REPLACE_INSERT_TAGS, $event);
			$query = $event->getBuffer();

			try
			{
				$db
					->prepare($query)
					->execute();
			}
			catch(\Exception $e)
			{
				throw new \RuntimeException($GLOBALS['TL_LANG']['tl_metamodel_attribute']['sql_error']);
			}
		}
	}
}
